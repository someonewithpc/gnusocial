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

/**
 * Common utility functions
 *
 * @package   GNUsocial
 * @category  Util
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Twig;

use App\Core\Event;
use function App\Core\I18n\_m;
use App\Core\Router\Router;
use App\Entity\Actor;
use App\Entity\Feed;
use App\Entity\Note;
use App\Util\Common;
use App\Util\Formatting;
use Component\FreeNetwork\FreeNetwork;
use Functional as F;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

class Runtime implements RuntimeExtensionInterface, EventSubscriberInterface
{
    private Request $request;
    public function setRequest(Request $req)
    {
        $this->request = $req;
    }

    public function transchoice(array $message, int $count): string
    {
        return _m($message, ['count' => $count]);
    }

    public function isCurrentRouteActive(string ...$routes): string
    {
        return $this->isCurrentRoute('active', ...$routes);
    }

    public function isCurrentRoute(string $class, string ...$routes): string
    {
        $current_route = $this->request->attributes->get('_route');
        return F\some($routes, F\partial_left([Formatting::class, 'startsWith'], $current_route)) ? $class : '';
    }

    public function getProfileActions(Actor $actor)
    {
        $actions = [];
        Event::handle('AddProfileActions', [$this->request, $actor, &$actions]);
        return $actions;
    }

    public function getNoteActions(Note $note)
    {
        $actions = [];
        Event::handle('AddNoteActions', [$this->request, $note, &$actions]);
        return $actions;
    }

    public function getExtraNoteActions(Note $note)
    {
        $extra_actions = [];
        Event::handle('AddExtraNoteActions', [$this->request, $note, &$extra_actions]);
        return $extra_actions;
    }

    /**
     * Provides an easy way to add template blocks to the right panel, features more granular control over
     * appendage positioning, through the $location parameter.
     *
     * @param string $location where it should be added, available locations: 'prepend', 'main', 'append'
     * @param array  $vars     contains additional context information to be used by plugins (ex. WebMonetization)
     *
     * @return array contains all blocks to be added to the right panel
     */
    public function addRightPanelBlock(string $location, array $vars): array
    {
        $blocks = [];
        switch ($location) {
            case 'prepend':
                Event::handle('PrependRightPanelBlock', [$this->request, &$blocks]);
                break;
            case 'main':
                Event::handle('AddMainRightPanelBlock', [$this->request, &$blocks]);
                break;
            case 'append':
                Event::handle('AppendRightPanelBlock', [$this->request, $vars, &$blocks]);
                break;
        }
        return $blocks;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getConfig(...$args)
    {
        return Common::config(...$args);
    }

    /**
     * get stylesheets
     *
     * @return array|mixed
     * @codeCoverageIgnore
     */
    public function getShowStylesheets($route)
    {
        $styles = [];
        Event::handle('EndShowStyles', [&$styles, $route]);
        return $styles;
    }

    /**
     * @codeCoverageIgnore
     */
    public function handleEvent(string $event, ...$args)
    {
        $res    = [];
        $args[] = &$res;
        Event::handle($event, $args);
        return $res;
    }

    /**
     * Renders the Svg Icon template and returns it.
     *
     * @author Ângelo D. Moura <up201303828@fe.up.pt>
     */
    public function embedSvgIcon(Environment $twig, string $icon_name = '', string $icon_css_class = ''): string
    {
        return $twig->render('@public_path/assets/icons/' . $icon_name . '.svg.twig', ['iconClass' => $icon_css_class]);
    }

    public function isFirefox(): bool
    {
        $re_has_chrome = '/.*(?i)\bchrome\b.*/m';
        $re_has_gecko  = '/.*(?i)\bgecko\b.*/m';
        return (preg_match(pattern: $re_has_chrome, subject: $this->request->headers->get('User-Agent')) !== 1)
            && (preg_match(pattern: $re_has_gecko, subject: $this->request->headers->get('User-Agent')) === 1);
    }

    public function isInstanceOf($value, string $type): bool
    {
        return (\function_exists($func = 'is_' . $type) && $func($value)) || $value instanceof $type;
    }

    public function handleOverrideTemplateImport(string $template, string $default_import): string
    {
        $result = '';
        if (Event::handle('OverrideTemplateImport', [$template, $default_import, &$result]) !== Event::stop) {
            $result = $default_import;
        }
        return $result;
    }

    public function handleOverrideStylesheet(string $original_asset_path): string
    {
        $result = '';
        if (Event::handle('OverrideStylesheet', [$original_asset_path, &$result]) !== Event::stop) {
            $result = $original_asset_path;
        }
        return $result;
    }

    public function openDetails(?string $query, array $ids): string
    {
        return \in_array($query, $ids) ? 'open=""' : '';
    }

    public function getFeeds(Actor $actor): array
    {
        return Feed::getFeeds($actor);
    }

    public function mention(Actor $actor): string
    {
        if ($actor->isGroup()) {
            if ($actor->getIsLocal()) {
                return "!{$actor->getNickname()}";
            } else {
                return FreeNetwork::groupTagToName($actor->getNickname(), $actor->getUri(type: Router::ABSOLUTE_URL));
            }
        } else {
            if ($actor->getIsLocal()) {
                return "@{$actor->getNickname()}";
            } else {
                return FreeNetwork::mentionTagToName($actor->getNickname(), $actor->getUri(type: Router::ABSOLUTE_URL));
            }
        }
    }

    // ----------------------------------------------------------

    /**
     * @codeCoverageIgnore
     */
    public function onKernelRequest(RequestEvent $event)
    {
        // Request is not a service, can't find a better way to get it
        $this->request = $event->getRequest();
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }
}
