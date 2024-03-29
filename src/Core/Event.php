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
 * GNU social's event handler wrapper around Symfony's,
 * keeping our old interface, which is more convenient and just as powerful
 *
 * @package GNUsocial
 * @category Event
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Core;

use ReflectionFunction;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

abstract class Event
{
    /**
     * Constants to be returned from event handlers, for increased clarity
     *
     * bool stop - Stop other handlers from processing the event
     * bool next - Allow the other handlers to process the event
     */
    public const stop = false;
    public const next = true;

    private static EventDispatcherInterface $dispatcher;

    public static function setDispatcher(EventDispatcherInterface $dis): void
    {
        self::$dispatcher = $dis;
    }

    /**
     * Add an event handler
     *
     * To run some code at a particular point in GNU social processing.
     * Named events include receiving an XMPP message, adding a new notice,
     * or showing part of an HTML page.
     *
     * The arguments to the handler vary by the event. Handlers can return
     * two possible values: false means that the event has been replaced by
     * the handler completely, and no default processing should be done.
     * Non-false means successful handling, and that the default processing
     * should succeed. (Note that this only makes sense for some events.)
     *
     * Handlers can also abort processing by throwing an exception; these will
     * be caught by the closest code and displayed as errors.
     *
     * @param string   $name     Name of the event
     * @param callable $handler  Code to run
     * @param int      $priority Higher runs first
     */
    public static function addHandler(
        string $name,
        callable $handler,
        int $priority = 0,
        string $ns = 'GNUsocial.',
    ): void {
        self::$dispatcher->addListener(
            $ns . $name,
            function ($event, $event_name, $dispatcher) use ($handler, $name) {
                // Old style of events (preferred)
                if ($event instanceof GenericEvent) {
                    if ($handler(...$event->getArguments()) === self::stop) {
                        $event->stopPropagation();
                    }
                    return $event;
                }
                // @codeCoverageIgnoreStart
                // Symfony style of events
                Log::warning("Event::addHandler for {$name} doesn't Conform to GNU social guidelines. Use of this style of event is discouraged.");
                $handler($event, $event_name, $dispatcher);

            // @codeCoverageIgnoreEnd
            },
            $priority,
        );
    }

    /**
     * Handle an event
     *
     * Events are any point in the code that we want to expose for admins
     * or third-party developers to use.
     *
     * We pass in an array of arguments (including references, for stuff
     * that can be changed), and each assigned handler gets run with those
     * arguments. Exceptions can be thrown to indicate an error.
     *
     * @param string $name Name of the event that's happening
     * @param array  $args Arguments for handlers
     * @param string $ns   Namspace for the event
     *
     * @return bool flag saying whether to continue processing, based
     *              on results of handlers
     */
    public static function handle(string $name, array $args = [], string $ns = 'GNUsocial.'): bool
    {
        return !(self::$dispatcher->dispatch(new GenericEvent($name, $args), $ns . $name)->isPropagationStopped());
    }

    /**
     * Check to see if an event handler exists
     *
     * Look to see if there's any handler for a given event, or narrow
     * by providing the name of a specific plugin class.
     *
     * @param string $name   Name of the event to look for
     * @param string $plugin Optional name of the plugin class to look for
     *
     * @return bool flag saying whether such a handler exists
     */
    public static function hasHandler(string $name, ?string $plugin = null, string $ns = 'GNUsocial.'): bool
    {
        $listeners = self::$dispatcher->getListeners($ns . $name);
        if (isset($plugin)) {
            foreach ($listeners as $event_handler) {
                $class = (new ReflectionFunction((new ReflectionFunction($event_handler))->getStaticVariables()['handler']))->getClosureScopeClass()->getName();
                if ($class === $plugin) {
                    return true;
                }
            }
        } else {
            return !empty($listeners);
        }
        return false;
    }

    /**
     * Get the array of handlers for $name
     *
     * @param string $name Name of event
     */
    public static function getHandlers(string $name, string $ns = 'GNUsocial.'): array
    {
        return self::$dispatcher->getListeners($ns . $name);
    }
}
