<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Groups with the most posts section
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Widget
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Groups with the most posts section
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class GroupsByPostsSection extends GroupSection
{
    function getGroups()
    {
        $limit = GROUPS_PER_SECTION;

        $qry = 'SELECT user_group.*, count(*) as value ' .
          'FROM user_group JOIN group_inbox '.
          'ON user_group.id = group_inbox.group_id ' .
          'GROUP BY user_group.id,user_group.nickname,user_group.fullname,user_group.homepage,user_group.description,user_group.location,user_group.original_logo,user_group.homepage_logo,user_group.stream_logo,user_group.mini_logo,user_group.created,user_group.modified ' .
          'ORDER BY value DESC LIMIT ' . $limit;

        $group = Memcached_DataObject::cachedQuery('User_group',
                                                   $qry,
                                                   3600);
        return $group;
    }

    function title()
    {
        // TRANS: Title for groups with the most posts section.
        return _('Active groups');
    }

    function divId()
    {
        return 'top_groups_by_post';
    }
}
