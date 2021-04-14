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
 * @category Wrapper
 *
 * @author    Hugo Sales <hugo@fc.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Core;

use Symfony\Component\Form\Form as SymfForm;
use Symfony\Component\Form\FormFactoryInterface;

abstract class Form
{
    private static ?FormFactoryInterface $form_factory;

    public static function setFactory($ff): void
    {
        self::$form_factory = $ff;
    }

    public static function create(array $form,
                                  string $type = 'Symfony\Component\Form\Extension\Core\Type\FormType',
                                  array $options = null): SymfForm
    {
        $fb = self::$form_factory->createBuilder($type, array_merge($options ?? [], ['translation_domain' => false]));
        foreach ($form as $f) {
            $fb->add(...$f);
        }
        return $fb->getForm();
    }

    public static function isRequired(array $form, string $field): bool
    {
        return $form[$field][2]['required'] ?? true;
    }
}
