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
 * Action for showing profiles self-tagged with a given tag
 *
 * @category  Action
 * @package   GNUsocial
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

defined('GNUSOCIAL') || die();

/**
 * This class outputs a paginated list of profiles self-tagged with a given tag
 *
 * @category  Output
 * @copyright 2009 StatusNet, Inc.
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 * @see       Action
 */
class SelftagAction extends Action
{
    public $tag  = null;
    public $page = null;

    /**
     * For initializing members of the class.
     *
     * @param array $args misc. arguments
     *
     * @return boolean true
     * @throws ClientException
     */
    public function prepare(array $args = [])
    {
        parent::prepare($args);

        $this->tag = $this->trimmed('tag');

        if (!common_valid_profile_tag($this->tag)) {
            // TRANS: Client error displayed when trying to list a profile with an invalid list.
            // TRANS: %s is the invalid list name.
            $this->clientError(sprintf(
                _('Not a valid list: %s.'),
                $this->tag
            ));
            return null;
        }

        $this->page = ($this->arg('page')) ? $this->arg('page') : 1;

        common_set_returnto($this->selfUrl());

        return true;
    }

    /**
     * Handler method
     *
     * @return void is read only action?
     */
    public function handle()
    {
        parent::handle();
        $this->showPage();
    }

    /**
     * Whips up a query to get a list of profiles based on the provided
     * people tag and page, initalizes a ProfileList widget, and displays
     * it to the user.
     */
    public function showContent()
    {
        $profile = new Profile();

        $offset = ($this->page - 1) * PROFILES_PER_PAGE;
        $limit  = PROFILES_PER_PAGE + 1;

        // XXX: memcached this

        $qry =  'SELECT profile.* ' .
                'FROM profile JOIN ( profile_tag, profile_list ) ' .
                'ON profile.id = profile_tag.tagger ' .
                'AND profile_tag.tagger = profile_list.tagger ' .
                'AND profile_list.tag = profile_tag.tag ' .
                'WHERE profile_tag.tagger = profile_tag.tagged ' .
                "AND profile_tag.tag = '%s' ";

        $user = common_current_user();
        if (empty($user)) {
            $qry .= 'AND profile_list.private = false ';
        } else {
            $qry .= 'AND (profile_list.tagger = ' . $user->id .
                    ' OR profile_list.private = false) ';
        }

        $qry .= 'ORDER BY profile_tag.modified DESC ' .
                'LIMIT ' . $limit . ' OFFSET ' . $offset;

        $profile->query(sprintf($qry, $this->tag));

        $ptl = new SelfTagProfileList($profile, $this); // pass the ammunition
        $cnt = $ptl->show();

        $this->pagination(
            $this->page > 1,
            $cnt > PROFILES_PER_PAGE,
            $this->page,
            'selftag',
            ['tag' => $this->tag]
        );
    }

    /**
     * Returns the page title
     *
     * @return string page title
     */
    public function title()
    {
        // TRANS: Page title for page showing self tags.
        // TRANS: %1$s is a tag, %2$d is a page number.
        return sprintf(
            _('Users self-tagged with %1$s, page %2$d'),
            $this->tag,
            $this->page
        );
    }
}

class SelfTagProfileList extends ProfileList
{
    public function newListItem(Profile $target)
    {
        return new SelfTagProfileListItem($target, $this->action);
    }
}

class SelfTagProfileListItem extends ProfileListItem
{
    public function linkAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'selftag')) {
            $aAttrs['rel'] .= ' nofollow';
        }

        return $aAttrs;
    }

    public function homepageAttributes()
    {
        $aAttrs = parent::linkAttributes();

        if (common_config('nofollow', 'selftag')) {
            $aAttrs['rel'] = 'nofollow';
        }

        return $aAttrs;
    }

    public function showTags()
    {
        $selftags = new SelfTagsWidget($this->out, $this->profile, $this->profile);
        $selftags->show();

        $user = common_current_user();

        if (!empty($user) && $user->id != $this->profile->getID() &&
                $user->getProfile()->canTag($this->profile)) {
            $yourtags = new PeopleTagsWidget($this->out, $user, $this->profile);
            $yourtags->show();
        }
    }
}
