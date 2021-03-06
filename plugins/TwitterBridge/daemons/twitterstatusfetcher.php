#!/usr/bin/env php
<?php
// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @copyright 2008-2010 StatusNet, Inc
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));
define('PUBLICDIR', INSTALLDIR . DIRECTORY_SEPARATOR . 'public');

// Tune number of processes and how often to poll Twitter
// XXX: Should these things be in config.php?
define('MAXCHILDREN', 2);
define('POLL_INTERVAL', 70); // in seconds, Twitter API v1.1 says 15 calls every 15 mins

$shortoptions = 'di::';
$longoptions = array('id::', 'debug');

$helptext = <<<END_OF_TRIM_HELP
Batch script for retrieving Twitter messages from foreign service.

  -i --id              Identity (default 'generic')
  -d --debug           Debug (lots of log output)

END_OF_TRIM_HELP;

require_once INSTALLDIR . '/scripts/commandline.inc';
require_once INSTALLDIR . '/lib/util/common.php';
require_once INSTALLDIR . '/lib/util/daemon.php';
require_once dirname(__DIR__) . '/twitter.php';

/**
 * Fetch statuses from Twitter
 *
 * Fetches statuses from Twitter and inserts them as notices
 *
 * NOTE: an Avatar path MUST be set in config.php for this
 * script to work, e.g.:
 *     $config['avatar']['path'] = $config['site']['path'] . '/avatar/';
 *
 * @todo @fixme @gar Fix the above. For some reason $_path is always empty when
 * this script is run, so the default avatar path is always set wrong in
 * default.php. Therefore it must be set explicitly in config.php. --Z
 *
 * @category Twitter
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class TwitterStatusFetcher extends ParallelizingDaemon
{
    /**
     *  Constructor
     *
     * @param string  $id           the name/id of this daemon
     * @param int     $interval     sleep this long before doing everything again
     * @param int     $max_children maximum number of child processes at a time
     * @param boolean $debug        debug output flag
     *
     * @return void
     *
     **/
    public function __construct(
        $id = null,
        $interval = 60,
        $max_children = 2,
        $debug = null
    ) {
        parent::__construct($id, $interval, $max_children, $debug);
    }

    /**
     * Name of this daemon
     *
     * @return string Name of the daemon.
     */
    public function name()
    {
        return ('twitterstatusfetcher.'.$this->_id);
    }

    /**
     * Find all the Twitter foreign links for users who have requested
     * importing of their friends' timelines
     *
     * @return array flinks an array of Foreign_link objects
     */
    public function getObjects()
    {
        global $_DB_DATAOBJECT;
        $flink = new Foreign_link();
        $conn = &$flink->getDatabaseConnection();

        $flink->service = TWITTER_SERVICE;
        $flink->orderBy('last_noticesync');
        $flink->find();

        $flinks = array();

        while ($flink->fetch()) {
            if (($flink->noticesync & FOREIGN_NOTICE_RECV) ==
                FOREIGN_NOTICE_RECV) {
                $flinks[] = clone($flink);
                common_log(LOG_INFO, "sync: foreign id $flink->foreign_id");
            } else {
                common_log(LOG_INFO, "nothing to sync");
            }
        }

        $flink->free();
        unset($flink);

        $conn->disconnect();
        unset($_DB_DATAOBJECT['CONNECTIONS']);

        return $flinks;
    }

    // FIXME: make it so we can force a Foreign_link here without colliding with parent
    public function childTask($flink)
    {
        // Each child ps needs its own DB connection

        // Note: DataObject::getDatabaseConnection() creates
        // a new connection if there isn't one already
        $conn = &$flink->getDatabaseConnection();

        $this->getTimeline($flink, 'home_timeline');
        $this->getTimeline($flink, 'mentions_timeline');

        $flink->last_friendsync = common_sql_now();
        $flink->update();

        $conn->disconnect();

        // XXX: Couldn't find a less brutal way to blow
        // away a cached connection
        global $_DB_DATAOBJECT;
        unset($_DB_DATAOBJECT['CONNECTIONS']);
    }

    public function getTimeline(Foreign_link $flink, $timelineUri = 'home_timeline')
    {
        common_log(LOG_DEBUG, $this->name() . ' - Trying to get ' . $timelineUri .
                   ' timeline for Twitter user ' . $flink->foreign_id);

        $client = null;

        if (TwitterOAuthClient::isPackedToken($flink->credentials)) {
            $token = TwitterOAuthClient::unpackToken($flink->credentials);
            $client = new TwitterOAuthClient($token->key, $token->secret);
            common_log(LOG_DEBUG, $this->name() . ' - Grabbing ' . $timelineUri . ' timeline with OAuth.');
        } else {
            common_log(LOG_ERR, "Skipping " . $timelineUri . " timeline for " .
                       $flink->foreign_id . " since not OAuth.");
        }

        $timeline = null;

        $lastId = Twitter_synch_status::getLastId($flink->foreign_id, $timelineUri);

        common_log(LOG_DEBUG, "Got lastId value '" . $lastId . "' for foreign id '" .
                     $flink->foreign_id . "' and timeline '" . $timelineUri. "'");

        try {
            $timeline = $client->statusesTimeline($lastId, $timelineUri);
        } catch (Exception $e) {
            common_log(LOG_ERR, $this->name() .
                       ' - Unable to get ' . $timelineUri . ' timeline for user ' . $flink->user_id .
                       ' - code: ' . $e->getCode() . 'msg: ' . $e->getMessage());
        }

        if (empty($timeline)) {
            common_log(LOG_DEBUG, $this->name() .  " - Empty '" . $timelineUri . "' timeline.");
            return;
        }

        common_log(LOG_INFO, $this->name() .
                   ' - Retrieved ' . sizeof($timeline) . ' statuses from ' . $timelineUri . ' timeline' .
                   ' - for user ' . $flink->user_id);

        if (!empty($timeline)) {
            $qm = QueueManager::get();

            // Reverse to preserve order
            foreach (array_reverse($timeline) as $status) {
                $data = array(
                    'status' => $status,
                    'for_user' => $flink->foreign_id,
                );
                $qm->enqueue($data, 'tweetin');
            }

            $lastId = twitter_id($timeline[0]);
            Twitter_synch_status::setLastId($flink->foreign_id, $timelineUri, $lastId);
            common_debug("Set lastId value '$lastId' for foreign id '{$flink->foreign_id}' and timeline '" .
                         $timelineUri . "'");
        }

        // Okay, record the time we synced with Twitter for posterity
        $flink->last_noticesync = common_sql_now();
        $flink->update();
    }
}

$id    = null;
$debug = null;

if (have_option('i')) {
    $id = get_option_value('i');
} elseif (have_option('--id')) {
    $id = get_option_value('--id');
} elseif (count($args) > 0) {
    $id = $args[0];
} else {
    $id = null;
}

if (have_option('d') || have_option('debug')) {
    $debug = true;
}

$fetcher = new TwitterStatusFetcher($id, POLL_INTERVAL, MAXCHILDREN, $debug);
$fetcher->runOnce();
