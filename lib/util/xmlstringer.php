<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Generator for in-memory XML
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
 * @category  Output
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
 * Create in-memory XML
 *
 * @category Output
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      Action
 * @see      HTMLOutputter
 */
class XMLStringer extends XMLOutputter
{
    public function __construct($indent = false)
    {
        $this->xw = new XMLWriter();
        $this->xw->openMemory();
        $this->xw->setIndent($indent);
    }

    public static function estring($tag, $attrs = null, $content = null)
    {
        $xs = new XMLStringer();
        $xs->element($tag, $attrs, $content);
        return $xs->getString();
    }

    // utility for quickly creating XML-strings

    public function getString()
    {
        return $this->xw->outputMemory();
    }
}
