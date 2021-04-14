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
 * @category Controller
 *
 * @author    Hugo Sales <hugo@fc.up.pt>
 * @author    Eliseu Amaro <eliseu@fc.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Controller;

// use App\Core\Event;
// use App\Util\Common;
use App\Core\DB\DB;
use App\Core\Form;
use function App\Core\I18n\_m;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

class UserPanel extends AbstractController
{
    public function __invoke(Request $request)
    {
        $prof = Form::create([
            [_m('Nickname'),   TextType::class,   ['help' => '1-64 lowercase letters or numbers, no punctuation or spaces.']],
            [_m('FullName'),   TextType::class,    ['help' => 'A full name is required, if empty it will be set to your nickname.']],
            [_m('Homepage'),   TextType::class,    ['help' => 'URL of your homepage, blog, or profile on another site.']],
            [_m('Bio'),   TextareaType::class,    ['help' => 'Describe yourself and your interests.']],
            [_m('Location'),   TextType::class,    ['help' => 'Where you are, like "City, State (or Region), Country".']],
            [_m('Tags'),   TextType::class,    ['help' => 'Tags for yourself (letters, numbers, -, ., and _), comma- or space- separated.']],
            ['save',        SubmitType::class, ['label' => _m('Save')]], ]);

        $prof->handleRequest($request);
        if ($prof->isSubmitted()) {
            $data = $prof->getData();
            if ($prof->isValid()) {
                $profile = DB::find('\App\Entity\Profile', ['id' => 2]);
                foreach (['Nickname', 'FullName', 'Homepage', 'Bio', 'Location', 'Tags'] as $key) {
                    $method = "set{$key}";
                    $profile->{$method}($data[_m($key)]);
                }
                DB::flush();
            } else {
                // Display error
            }
        }

        return $this->render('settings/profile.html.twig', [
            'prof' => $prof->createView(),
        ]);
    }
}
