<?php

declare(strict_types=1);

// {{{ License
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
// }}}

/**
 * ActivityPub implementation for GNU social
 *
 * @package   GNUsocial
 * @category  ActivityPub
 *
 * @author    Diogo Peralta Cordeiro <@diogo.site>
 * @copyright 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace Plugin\ActivityPub\Util\Model;

use ActivityPhp\Type\AbstractObject;
use Plugin\ActivityPub\Entity\ActivitypubActivity;

/**
 * This class handles translation between JSON and ActivityPub Announces
 *
 * @copyright 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class ActivityAnnounce extends Activity
{
    protected static function handle_core_activity(\App\Entity\Actor $actor, AbstractObject $type_activity, mixed $type_object, ?ActivitypubActivity &$ap_act): ActivitypubActivity
    {
        // The only core Announce we recognise is for (transitive) activities coming from Group actors
        if ($actor->isGroup()) {
            if ($type_object instanceof AbstractObject) {
                $actual_to = array_flip(is_string($type_object->get('to')) ? [$type_object->get('to')] : $type_object->get('to'));
                $actual_cc = array_flip(is_string($type_object->get('cc')) ? [$type_object->get('cc')] : $type_object->get('cc'));
                $actual_cc[$type_activity->get('actor')] = true; // Add group to targets
                foreach (is_string($type_activity->get('to')) ? [$type_activity->get('to')] : $type_activity->get('to') as $to) {
                    if ($to !== 'https://www.w3.org/ns/activitystreams#Public') {
                        $actual_to[$to] = true;
                    }
                }
                foreach (is_string($type_activity->get('cc')) ? [$type_activity->get('cc')] : $type_activity->get('cc') as $cc) {
                    if ($cc !== 'https://www.w3.org/ns/activitystreams#Public') {
                        $actual_cc[$cc] = true;
                    }
                }
                $type_object->set('to', array_keys($actual_to));
                $type_object->set('cc', array_keys($actual_cc));
                $ap_act = self::fromJson($type_object);
            }
        }
        return $ap_act ?? ($ap_act = $type_object);
    }
}
