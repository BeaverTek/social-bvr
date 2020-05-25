<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Login form
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
 * @category  Login
 * @package   GNUsocial
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2008-2009 StatusNet, Inc.
 * @copyright 2013 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class LoginAction extends FormAction
{
    protected $needLogin = false;

    /**
     * Handle input, produce output
     *
     * Switches on request method; either shows the form or handles its input.
     *
     * @return void
     */
    protected function handle()
    {
        if (!isset($_GET['code'])) {
            common_redirect('https://login.hsales.me/auth/realms/SSO/' .
                            'protocol/openid-connect/auth?scope=openid&client_id=social&response_type=code&redirect_uri='
                            . urlencode(common_local_url('login')));
        } else {
            if (!isset($_SESSION['oauth2state']) && !empty($_GET['session_state'])) {
                $_SESSION['oauth2state'] = $_GET['session_state'];
            }

            $http = new HTTPClient('https://login.hsales.me/auth/realms/SSO/protocol/openid-connect/token', 'POST');
            $http->addPostParameter(['code' => $_GET['code'],
                                     'grant_type' => 'authorization_code',
                                     'client_id' => 'social',
                                     'redirect_uri' => common_local_url('login')]);

            $response = json_decode($http->send()->getBody());

            $jwt_data = json_decode(base64_decode(explode('.', $response->access_token)[1]));

            $user = User::getKV($jwt_data->preferred_username);

            if (empty($user)) {
                $user = User::insert(
                    ['nickname' => $jwt_data->preferred_username,
                     // 'email' => $jwt_data->email,
                     'password' => common_random_hexstr(32),
                     'fullname' => $jwt_data->given_name . ' ' . $jwt_data->family_name,
                     // 'email_confirmed' => $jwt_data->email_verified,
                    ]);
            }

            var_dump($user);

            // common_set_user($user);
            // common_real_login(true);
            // $this->updateScopedProfile();
        }

        // if (common_is_real_login()) {
        //     common_redirect(common_local_url('all', array('nickname' => $this->scoped->nickname)), 307);
        // }

        // return parent::handle();
    }
}
