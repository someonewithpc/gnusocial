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
class Activitypub_create
{
    /**
     * Generates an ActivityPub representation of a Create
     *
     * @param string $actor
     * @param array  $object
     * @param bool   $directMessage whether it is a private Create activity or not
     *
     * @return array pretty array to be used in a response
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     */
    public static function create_to_array(string $actor, string $uri, $object, bool $directMessage = false): array
    {
        $res = [
            '@context'      => 'https://www.w3.org/ns/activitystreams',
            'id'            => $object['id'] . '/create',
            'type'          => 'Create',
            'directMessage' => $directMessage,
            'to'            => $object['to'],
            'cc'            => $object['cc'],
            'actor'         => $actor,
            'object'        => $object,
        ];
        return $res;
    }

    /**
     * Verifies if a given object is acceptable for a Create Activity.
     *
     * @param array $object
     *
     * @throws Exception if invalid
     *
     * @return bool True if acceptable, false if valid but unsupported
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     */
    public static function validate_object($object): bool
    {
        if (!is_array($object)) {
            common_debug('ActivityPub Create Validator: Rejected because of invalid Object format.');
            throw new Exception('Invalid Object Format for Create Activity.');
        }
        if (!isset($object['type'])) {
            common_debug('ActivityPub Create Validator: Rejected because of Type.');
            throw new Exception('Object type was not specified for Create Activity.');
        }
        if (isset($object['directMessage']) && !is_bool($object['directMessage'])) {
            common_debug('ActivityPub Create Validator: Rejected because Object directMessage is invalid.');
            throw new Exception('Invalid Object directMessage.');
        }
        switch ($object['type']) {
            case 'Note':
                // Validate data
                return Activitypub_notice::validate_note($object);
                break;
            default:
                throw new Exception('This is not a supported Object Type for Create Activity.');
        }
        return true;
    }

    /**
     * Verify if received note is private (direct).
     * Note that we're conformant with the (yet) non-standard directMessage attribute:
     * https://github.com/w3c/activitypub/issues/196#issuecomment-304958984
     *
     * @param array $activity received Create-Note activity
     *
     * @return bool true if note is private, false otherwise
     *
     * @author Bruno casteleiro <brunoccast@fc.up.pt>
     */
    public static function isPrivateNote(array $activity): bool
    {
        if (isset($activity['directMessage'])) {
            return $activity['directMessage'];
        }

        return empty($activity['cc']) && !in_array('https://www.w3.org/ns/activitystreams#Public', $activity['to']);
    }
}
