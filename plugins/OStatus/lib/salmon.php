<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A sample plugin to show best practices for StatusNet plugins
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
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class Salmon
{
    const REL_SALMON = 'salmon';
    const REL_MENTIONED = 'mentioned';

    // XXX: these are deprecated
    const NS_REPLIES = "http://salmon-protocol.org/ns/salmon-replies";
    const NS_MENTIONS = "http://salmon-protocol.org/ns/salmon-mention";

    /**
     * Sign and post the given Atom entry as a Salmon message.
     *
     * Side effects: may generate a keypair on-demand for the given user,
     * which can be very slow on some systems (like those without php5-gmp).
     *
     * @param string $endpoint_uri
     * @param string $xml string representation of payload
     * @param Profile $user profile whose keys we sign with (must be a local user)
     * @return boolean success
     */
    public static function post($endpoint_uri, $xml, Profile $actor, Profile $target=null)
    {
        if (empty($endpoint_uri)) {
            common_debug('No endpoint URI for Salmon post to '.$actor->getUri());
            return false;
        }

        try {
            $magic_env = MagicEnvelope::signAsUser($xml, $actor->getUser());
        } catch (Exception $e) {
            common_log(LOG_ERR, "Salmon unable to sign: " . $e->getMessage());
            return false;
        }

        // $target is so far only used in Diaspora, so it can be null
        if (Event::handle('SalmonSlap', array($endpoint_uri, $magic_env, $target))) {
            return false;
            //throw new ServerException('Could not distribute salmon slap as no plugin completed the event.');
        }

        return true;
    }
}
