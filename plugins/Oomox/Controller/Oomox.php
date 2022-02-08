<?php

declare(strict_types = 1);

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

namespace Plugin\Oomox\Controller;

use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Form;
use function App\Core\I18n\_m;
use App\Util\Common;
use App\Util\Exception\ClientException;
use App\Util\Exception\NoLoggedInUser;
use App\Util\Exception\RedirectException;
use App\Util\Exception\ServerException;
use App\Util\Formatting;
use Plugin\Oomox\Entity\Oomox as EntityOomox;
use Plugin\Oomox\Oomox as PluginOomox;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Oomox controller
 *
 * @package  GNUsocial
 * @category Oomox
 *
 * @author    Eliseu Amaro <mail@eliseuama.ro>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Oomox
{
    /**
     * Generates a FormInterface depending on current theme settings and system-wide colour preference.
     * Receives the user's Oomox entity, and wether or not its intended for dark of light theme to change its behaviour accordingly.
     *
     * @throws ServerException
     */
    public static function getOomoxForm(?EntityOomox $current_oomox_settings, bool $is_light): FormInterface
    {
        $theme           = $is_light ? 'light' : 'dark';
        $foreground      = 'colour_foreground_' . $theme;
        $background_hard = 'colour_background_hard_' . $theme;
        $background_card = 'colour_background_card_' . $theme;
        $border          = 'colour_border_' . $theme;
        $accent          = 'colour_accent_' . $theme;
        $reset           = 'colour_reset_' . $theme;
        $save            = 'save_oomox_colours_' . $theme;

        if (isset($current_oomox_settings)) {
            if ($is_light) {
                $current_foreground      = $current_oomox_settings->getColourForegroundLight() ?: '#09090d';
                $current_background_hard = $current_oomox_settings->getColourBackgroundHardLight() ?: '#ebebeb';
                $current_background_card = $current_oomox_settings->getColourBackgroundCardLight() ?: '#f0f0f0';
                $current_border          = $current_oomox_settings->getColourBorderLight() ?: '#C2C2C2';
                $current_accent          = $current_oomox_settings->getColourAccentLight() ?: '#a22430';
            } else {
                $current_foreground      = $current_oomox_settings->getColourForegroundDark() ?: '#eff0f1';
                $current_background_hard = $current_oomox_settings->getColourBackgroundHardDark() ?: '#0E0E0F';
                $current_background_card = $current_oomox_settings->getColourBackgroundCardDark() ?: '#0E0E0F';
                $current_border          = $current_oomox_settings->getColourBorderDark() ?: '#26262C';
                $current_accent          = $current_oomox_settings->getColourAccentDark() ?: '#5ddbcf';
            }
        } else {
            $current_foreground      = $is_light ? '#09090d' : '#eff0f1';
            $current_background_hard = $is_light ? '#ebebeb' : '#0E0E0F';
            $current_background_card = $is_light ? '#f0f0f0' : '#0E0E0F';
            $current_border          = $is_light ? '#C2C2C2' : '#26262C';
            $current_accent          = $is_light ? '#a22430' : '#5ddbcf';
        }

        return Form::create([
            [$foreground, ColorType::class, [
                'html5' => true,
                'data'  => $current_foreground,
                'label' => _m('Foreground colour'),
                'help'  => _m('Choose the foreground colour'), ],
            ],
            [$background_hard, ColorType::class, [
                'html5' => true,
                'data'  => $current_background_hard,
                'label' => _m('Background colour'),
                'help'  => _m('Choose the background colour'), ],
            ],
            [$background_card, ColorType::class, [
                'html5' => true,
                'data'  => $current_background_card,
                'label' => _m('Card background colour'),
                'help'  => _m('Choose the card background colour'), ],
            ],
            [$border, ColorType::class, [
                'html5' => true,
                'data'  => $current_border,
                'label' => _m('Border colour'),
                'help'  => _m('Choose the borders accents'), ],
            ],
            [$accent, ColorType::class, [
                'html5' => true,
                'data'  => $current_accent,
                'label' => _m('Accent colour'),
                'help'  => _m('Choose the accent colour'), ],
            ],
            ['hidden', HiddenType::class, []],
            [$reset, SubmitType::class, ['label' => _m('Reset colours to default')]],
            [$save, SubmitType::class, ['label' => _m('Submit')]],
        ]);
    }

    /**
     * Handles Light theme settings tab
     *
     * @throws NoLoggedInUser
     * @throws RedirectException
     * @throws ServerException
     */
    public static function oomoxSettingsLight(Request $request): array
    {
        $user     = Common::ensureLoggedIn();
        $actor_id = $user->getId();

        $current_oomox_settings = PluginOomox::getEntity($user);
        $form_light             = self::getOomoxForm($current_oomox_settings, true);

        $form_light->handleRequest($request);
        if ($form_light->isSubmitted() && $form_light->isValid()) {
            /** @var SubmitButton $reset_button */
            $reset_button = $form_light->get('colour_reset_light');
            if ($reset_button->isClicked()) {
                $current_oomox_settings?->resetTheme(true);
            } else {
                $data                   = $form_light->getData();
                $current_oomox_settings = EntityOomox::create(
                    [
                        'actor_id'                     => $actor_id,
                        'colour_foreground_light'      => $data['colour_foreground_light'],
                        'colour_background_hard_light' => $data['colour_background_hard_light'],
                        'colour_background_card_light' => $data['colour_background_card_light'],
                        'colour_border_light'          => $data['colour_border_light'],
                        'colour_accent_light'          => $data['colour_accent_light'],
                    ],
                );
            }

            if ($current_oomox_settings) {
                if ($reset_button->isClicked()) {
                    DB::remove(EntityOomox::getByPK($actor_id));
                } else {
                    DB::merge($current_oomox_settings);
                }
                DB::flush();
            }
            throw new RedirectException();
        }

        return ['_template' => 'oomox/oomoxSettingsLight.html.twig', 'oomoxLight' => $form_light->createView()];
    }

    /**
     * Handles the Dark theme settings tab
     *
     * @throws NoLoggedInUser
     * @throws RedirectException
     * @throws ServerException
     */
    public static function oomoxSettingsDark(Request $request): array
    {
        $user     = Common::ensureLoggedIn();
        $actor_id = $user->getId();

        $current_oomox_settings = PluginOomox::getEntity($user);
        $form_dark              = self::getOomoxForm($current_oomox_settings, false);

        $form_dark->handleRequest($request);
        if ($form_dark->isSubmitted() && $form_dark->isValid()) {
            $reset_button = $form_dark->get('colour_reset_dark');
            /** @var SubmitButton $reset_button */
            if ($reset_button->isClicked()) {
                $current_oomox_settings?->resetTheme(false);
            } else {
                $data                   = $form_dark->getData();
                $current_oomox_settings = EntityOomox::create(
                    [
                        'actor_id'                    => $actor_id,
                        'colour_foreground_dark'      => $data['colour_foreground_dark'],
                        'colour_background_hard_dark' => $data['colour_background_hard_dark'],
                        'colour_background_card_dark' => $data['colour_background_card_dark'],
                        'colour_border_dark'          => $data['colour_border_dark'],
                        'colour_accent_dark'          => $data['colour_accent_dark'],
                    ],
                );
            }

            if ($current_oomox_settings) {
                if ($reset_button->isClicked()) {
                    DB::remove(EntityOomox::getByPK($actor_id));
                } else {
                    DB::merge($current_oomox_settings);
                }
                DB::flush();
            }

            Cache::delete(PluginOomox::cacheKey($user));
            throw new RedirectException();
        }

        return ['_template' => 'oomox/oomoxSettingsDark.html.twig', 'oomoxDark' => $form_dark->createView()];
    }

    /**
     * Renders the resulting CSS file from user options, serves that file as a response
     *
     * @throws ClientException
     * @throws NoLoggedInUser
     * @throws ServerException
     */
    public function oomoxCSS(): Response
    {
        $user = Common::ensureLoggedIn();

        $oomox_table = PluginOomox::getEntity($user);
        if (\is_null($oomox_table)) {
            throw new ClientException(_m('No custom colours defined', 404));
        }

        $content = Formatting::twigRenderFile('/oomox/root_override.css.twig', ['oomox' => $oomox_table]);
        return new Response($content, status: 200, headers: ['content-type' => 'text/css', 'rel' => 'stylesheet']);
    }
}
