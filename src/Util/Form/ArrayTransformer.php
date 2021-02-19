<?php

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
 * Handle network public feed
 *
 * @package  GNUsocial
 * @category Form
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Util\Form;

use App\Util\Formatting;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class ArrayTransformer implements DataTransformerInterface
{
    // Can't use type annotations, to conform to interface

    /**
     * @param array $a
     *
     * @return string
     */
    public function transform($a)
    {
        if (!is_array($a)) {
            throw new TransformationFailedException();
        }
        return Formatting::toString($a, Formatting::SPLIT_BY_SPACE);
    }

    /**
     * @param string $s
     *
     * @return array
     */
    public function reverseTransform($s)
    {
        if (empty($s)) {
            return [];
        }

        if (is_string($s) && Formatting::toArray($s, $arr, Formatting::SPLIT_BY_BOTH)) {
            return $arr;
        } else {
            throw new TransformationFailedException();
        }
    }
}
