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
 * ActivityPub implementation for GNU social
 *
 * @package   GNUsocial
 *
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @copyright 2018-2019 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 * @see      http://www.gnu.org/software/social/
 */
defined('GNUSOCIAL') || die();

/**
 * ActivityPub error representation
 *
 * @category  Plugin
 * @package   GNUsocial
 *
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Activitypub_undo
{
    /**
     * Generates an ActivityPub representation of a Undo
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     *
     * @param array $object
     *
     * @return array pretty array to be used in a response
     */
    public static function undo_to_array(array $object): array
    {
        $res = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id'       => $object['id'] . '/undo',
            'type'     => 'Undo',
            'actor'    => $object['actor'],
            'object'   => $object,
        ];
        return $res;
    }

    /**
     * Verifies if a given object is acceptable for a Undo Activity.
     *
     * @param array $object
     *
     * @throws Exception
     *
     * @return bool
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     */
    public static function validate_object(array $object): bool
    {
        if (!is_array($object)) {
            throw new Exception('Invalid Object Format for Undo Activity.');
        }
        if (!isset($object['type'])) {
            throw new Exception('Object type was not specified for Undo Activity.');
        }
        switch ($object['type']) {
            case 'Follow':
            case 'Like':
                // Validate data
                if (!filter_var($object['object'], FILTER_VALIDATE_URL)) {
                    throw new Exception('Object is not a valid Object URI for Activity.');
                }
                break;
            default:
                throw new Exception('This is not a supported Object Type for Undo Activity.');
        }
        return true;
    }
}
