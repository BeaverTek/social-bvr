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
 * DB interface for GNU social queues
 *
 * @package   GNUsocial
 * @author    Miguel Dantas <biodantasgs@gmail.com>
 * @copyright 2019 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

defined('GNUSOCIAL') || die();

class DBQueuePlugin extends Plugin
{
    const PLUGIN_VERSION = '0.1.0';

    public function onStartNewQueueManager(?QueueManager &$qm)
    {
        common_debug("Starting DB queue manager.");
        $qm = new DBQueueManager();
        return false;
    }

    public function onPluginVersion(array &$versions): bool
    {
        $versions[] = [
            'name' => 'DBQueue',
            'version' => self::PLUGIN_VERSION,
            'author' => 'Miguel Dantas',
            'homepage'    => GNUSOCIAL_ENGINE_URL,
            'description' =>
            // TRANS: Plugin description.
            _m('Plugin using the database as a backend for GNU social queues')
        ];
        return true;
    }
};
