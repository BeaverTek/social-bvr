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

defined('STATUSNET') || die();

/**
 * Form for disabling a plugin
 *
 * @category Form
 * @package  StatusNet
 * @author   Brion Vibber <brion@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      PluginEnableForm
 */
class PluginDisableForm extends PluginEnableForm
{
    /**
     * ID of the form
     *
     * @return string ID of the form
     */
    public function id()
    {
        return 'plugin-disable-' . $this->plugin;
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */
    public function formClass()
    {
        return 'form_plugin_disable';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */
    public function action()
    {
        return common_local_url(
            'plugindisable',
            ['plugin' => $this->plugin]
        );
    }

    /**
     * Action elements
     *
     * @return void
     * @throws Exception
     */
    public function formActions()
    {
        // TRANS: Plugin admin panel controls
        $this->out->submit('submit', _m('plugin', 'Disable'));
    }
}
