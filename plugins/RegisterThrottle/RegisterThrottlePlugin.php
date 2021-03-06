<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Throttle registration by IP address
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Throttle registration by IP address
 *
 * We a) record IP address of registrants and b) throttle registrations.
 *
 * @category  Spam
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class RegisterThrottlePlugin extends Plugin
{
    const PLUGIN_VERSION = '2.0.0';

    /**
     * Array of time spans in seconds to limits.
     *
     * Default is 3 registrations per hour, 5 per day, 10 per week.
     */
    public $regLimits = array(604800 => 10, // per week
                              86400 => 5, // per day
                              3600 => 3); // per hour

    /**
     * Disallow registration if a silenced user has registered from
     * this IP address.
     */
    public $silenced = true;

    /**
     * Auto-silence all other users from the same registration_ip
     * as the one being silenced. Caution: Many users may come from
     * the same IP (even entire countries) without having any sort
     * of relevant connection for moderation.
     */
    public $auto_silence_by_ip = false;

    /**
     * Whether we're enabled; prevents recursion.
     */
    static private $enabled = true;

    /**
     * Database schema setup
     *
     * We store user registrations in a table registration_ip.
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    public function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing user-submitted flags on profiles
        $schema->ensureTable('registration_ip', Registration_ip::schemaDef());
        return true;
    }

    public function onRouterInitialized(URLMapper $m)
    {
        $m->connect('main/ipregistrations/:ipaddress',
                    ['action' => 'ipregistrations'],
                    ['ipaddress'   => '[0-9a-f\.\:]+']);
    }

    /**
     * Called when someone tries to register.
     *
     * We check the IP here to determine if it goes over any of our
     * configured limits.
     *
     * @param Action $action Action that is being executed
     *
     * @return boolean hook value
     */
    public function onStartRegistrationTry($action)
    {
        $ipaddress = $this->_getIpAddress();

        if (empty($ipaddress)) {
            // TRANS: Server exception thrown when no IP address can be found for a registation attempt.
            throw new ServerException(_m('Cannot find IP address.'));
        }

        foreach ($this->regLimits as $seconds => $limit) {

            $this->debug("Checking $seconds ($limit)");

            $reg = $this->_getNthReg($ipaddress, $limit);

            if (!empty($reg)) {
                $this->debug("Got a {$limit}th registration.");
                $regtime = strtotime($reg->created);
                $now     = time();
                $this->debug("Comparing {$regtime} to {$now}");
                if ($now - $regtime < $seconds) {
                    // TRANS: Exception thrown when too many user have registered from one IP address within a given time frame.
                    throw new Exception(_m('Too many registrations. Take a break and try again later.'));
                }
            }
        }

        // Check for silenced users

        if ($this->silenced) {
            $ids = Registration_ip::usersByIP($ipaddress);
            foreach ($ids as $id) {
                $profile = Profile::getKV('id', $id);
                if ($profile && $profile->isSilenced()) {
                    // TRANS: Exception thrown when attempting to register from an IP address from which silenced users have registered.
                    throw new Exception(_m('A banned user has registered from this address.'));
                }
            }
        }

        return true;
    }

    function onEndShowSections(Action $action)
    {
        if (!$action instanceof ShowstreamAction) {
            // early return for actions we're not interested in
            return true;
        }

        $target = $action->getTarget();
        if (!$target->isSilenced()) {
            // Only show the IP of users who are not silenced.
            return true;
        }

        $scoped = $action->getScoped();
        if (!$scoped->hasRight(Right::SILENCEUSER)) {
            // only show registration IP if we have the right to silence users
            return true;
        }

        $ri = Registration_ip::getKV('user_id', $target->getID());
        $ipaddress = null;
        if ($ri instanceof Registration_ip) {
            $ipaddress = $ri->ipaddress;
            unset($ri);
        }

        $action->elementStart('div', array('id' => 'entity_mod_log',
                                           'class' => 'section'));

        $action->element('h2', null, _('Registration IP'));

        // TRANS: Label for the information about which IP a users registered from.
        $action->element('strong', null, _('Registered from:'));
        $el = 'span';
        $attrs = ['class'=>'ipaddress'];
        if (!is_null($ipaddress)) {
            $el = 'a';
            $attrs['href'] = common_local_url('ipregistrations', array('ipaddress'=>$ipaddress));
        }
        $action->element($el, $attrs,
                            // TRANS: Unknown IP address.
                            $ipaddress ?: _('unknown'));

        $action->elementEnd('div');
    }

    /**
     * Called after someone registers, by any means.
     *
     * We record the successful registration and IP address.
     *
     * @param Profile $profile new user's profile
     *
     * @return boolean hook value
     */
    public function onEndUserRegister(Profile $profile)
    {
        $ipaddress = $this->_getIpAddress();

        if (empty($ipaddress)) {
            // User registration can happen from command-line scripts etc.
            return true;
        }

        $reg = new Registration_ip();

        $reg->user_id   = $profile->getID();
        $reg->ipaddress = mb_strtolower($ipaddress);
        $reg->created   = common_sql_now();

        $result = $reg->insert();

        if ($result === false) {
            common_log_db_error($reg, 'INSERT', __FILE__);
            // @todo throw an exception?
        }

        return true;
    }

    /**
     * Check the version of the plugin.
     *
     * @param array &$versions Version array.
     *
     * @return boolean hook value
     */
    public function onPluginVersion(array &$versions): bool
    {
        $versions[] = array('name' => 'RegisterThrottle',
                            'version' => self::PLUGIN_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => GNUSOCIAL_ENGINE_REPO_URL . 'tree/master/plugins/RegisterThrottle',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Throttles excessive registration from a single IP address.'));
        return true;
    }

    /**
     * Gets the current IP address.
     *
     * @return string IP address or null if not found.
     */
    private function _getIpAddress()
    {
        $keys = array('HTTP_X_FORWARDED_FOR',
                      'HTTP_X_CLIENT',
                      'CLIENT-IP',
                      'REMOTE_ADDR');

        foreach ($keys as $k) {
            if (!empty($_SERVER[$k])) {
                return $_SERVER[$k];
            }
        }

        return null;
    }

    /**
     * Gets the Nth registration with the given IP address.
     *
     * @param string  $ipaddress Address to key on
     * @param integer $n         Nth address
     *
     * @return Registration_ip nth registration or null if not found.
     */
    private function _getNthReg($ipaddress, $n)
    {
        $reg = new Registration_ip();

        $reg->ipaddress = $ipaddress;

        $reg->orderBy('created DESC');
        $reg->limit($n - 1, 1);

        if ($reg->find(true)) {
            return $reg;
        } else {
            return null;
        }
    }

    /**
     * When silencing a user, silence all other users registered from that IP
     * address.
     *
     * @param Profile $profile Person getting a new role
     * @param string  $role    Role being assigned like 'moderator' or 'silenced'
     *
     * @return boolean hook value
     */
    public function onEndGrantRole($profile, $role)
    {
        if (!self::$enabled) {
            return true;
        }

        if ($role !== Profile_role::SILENCED) {
            return true;
        }

        if (!$this->auto_silence_by_ip) {
            return true;
        }

        $ri = Registration_ip::getKV('user_id', $profile->getID());

        if (empty($ri)) {
            return true;
        }

        $ids = Registration_ip::usersByIP($ri->ipaddress);

        foreach ($ids as $id) {
            if ($id == $profile->getID()) {
                continue;
            }

            try {
                $other = Profile::getByID($id);
            } catch (NoResultException $e) {
                continue;
            }

            if ($other->isSilenced()) {
                continue;
            }

            // 'enabled' here is used to prevent recursion, since
            // we'll end up in this function again on ->silence()
            // though I actually think it doesn't matter since we
            // do this in onEndGrantRole and that means the above
            // $other->isSilenced() test should've 'continue'd...
            $old = self::$enabled;
            self::$enabled = false;
            $other->silence();
            self::$enabled = $old;
        }
    }
}
