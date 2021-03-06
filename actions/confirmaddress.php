<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Confirm an address
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
 * @category  Confirm
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Confirm an address
 *
 * When users change their SMS, email, Jabber, or other addresses, we send out
 * a confirmation code to make sure the owner of that address approves. This class
 * accepts those codes.
 *
 * @category Confirm
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class ConfirmaddressAction extends ManagedAction
{
    /** type of confirmation. */

    protected $address;

    protected function doPreparation()
    {
        if (!common_logged_in()) {
            common_set_returnto($this->selfUrl());
            common_redirect(common_local_url('login'));
        }
        $code = $this->trimmed('code');
        if (!$code) {
            // TRANS: Client error displayed when not providing a confirmation code in the contact address confirmation action.
            throw new ClientException(_('No confirmation code.'));
        }
        $confirm = Confirm_address::getKV('code', $code);
        if (!$confirm instanceof Confirm_address) {
            // TRANS: Client error displayed when providing a non-existing confirmation code in the contact address confirmation action.
            throw new ClientException(_('Confirmation code not found.'), 404);
        }

        try {
            $profile = Profile::getByID($confirm->user_id);
        } catch (NoResultException $e) {
            common_log(LOG_INFO, 'Tried to confirm the email for a deleted profile: '._ve(['id'=>$confirm->user_id, 'email'=>$confirm->address]));
            $confirm->delete();
            throw $e;
        }
        if (!$profile->sameAs($this->scoped)) {
            // TRANS: Client error displayed when not providing a confirmation code for another user in the contact address confirmation action.
            throw new AuthorizationException(_('That confirmation code is not for you!'));
        }

        $type = $confirm->address_type;
        $transports = array();
        Event::handle('GetImTransports', array(&$transports));
        if (!in_array($type, array('email', 'sms')) && !in_array($type, array_keys($transports))) {
            // TRANS: Server error for an unknown address type, which can be 'email', 'sms', or the name of an IM network (such as 'xmpp' or 'aim')
            throw new ServerException(sprintf(_('Unrecognized address type %s'), $type));
        }
        $this->address = $confirm->address;

        $cur = $this->scoped->getUser();

        $cur->query('BEGIN');
        if (in_array($type, array('email', 'sms'))) {
            common_debug("Confirming {$type} address for user {$this->scoped->getID()}");
            if ($cur->$type == $confirm->address) {
                // Already verified, so delete the confirm_address entry
                $confirm->delete();
                // TRANS: Client error for an already confirmed email/jabber/sms address.
                throw new AlreadyFulfilledException(_('That address has already been confirmed.'));
            }

            $orig_user = clone($cur);

            $cur->$type = $confirm->address;

            if ($type == 'sms') {
                $cur->carrier  = ($confirm->address_extra)+0;
                $carrier       = Sms_carrier::getKV($cur->carrier);
                $cur->smsemail = $carrier->toEmailAddress($cur->sms);
            }

            // Throws exception on failure.
            $cur->updateWithKeys($orig_user);

            if ($type == 'email') {
                $cur->emailChanged();
            }

        } else {

            $user_im_prefs = new User_im_prefs();
            $user_im_prefs->transport = $confirm->address_type;
            $user_im_prefs->user_id = $cur->id;
            if ($user_im_prefs->find() && $user_im_prefs->fetch()) {
                if($user_im_prefs->screenname == $confirm->address){
                    // Already verified, so delete the confirm_address entry
                    $confirm->delete();
                    // TRANS: Client error for an already confirmed IM address.
                    throw new AlreadyFulfilledException(_('That address has already been confirmed.'));
                }
                $user_im_prefs->screenname = $confirm->address;
                $result = $user_im_prefs->update();

                if ($result === false) {
                    common_log_db_error($user_im_prefs, 'UPDATE', __FILE__);
                    // TRANS: Server error displayed when updating IM preferences fails.
                    throw new ServerException(_('Could not update user IM preferences.'));
                }
            }else{
                $user_im_prefs = new User_im_prefs();
                $user_im_prefs->screenname = $confirm->address;
                $user_im_prefs->transport = $confirm->address_type;
                $user_im_prefs->user_id = $cur->id;
                $user_im_prefs->created = common_sql_now();
                $result = $user_im_prefs->insert();

                if ($result === false) {
                    common_log_db_error($user_im_prefs, 'INSERT', __FILE__);
                    // TRANS: Server error displayed when adding IM preferences fails.
                    throw new ServerException(_('Could not insert user IM preferences.'));
                }
            }

        }

        $confirm->delete();

        $cur->query('COMMIT');
    }

    /**
     * Title of the page
     *
     * @return string title
     */
    function title()
    {
        // TRANS: Title for the contact address confirmation action.
        return _('Confirm address');
    }

    /**
     * Show a confirmation message.
     *
     * @return void
     */
    function showContent()
    {
        $this->element('p', null,
                       // TRANS: Success message for the contact address confirmation action.
                       // TRANS: %s can be 'email', 'jabber', or 'sms'.
                       sprintf(_('The address "%s" has been '.
                                 'confirmed for your account.'),
                               $this->address));
    }
}
