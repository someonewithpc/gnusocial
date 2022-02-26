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
 * ActivityPub implementation for GNU social
 *
 * @package   GNUsocial
 * @category  ActivityPub
 *
 * @author    Diogo Peralta Cordeiro <@diogo.site>
 * @copyright 2018-2019, 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace Plugin\ActivityPub\Util;

use App\Core\DB\DB;
use App\Core\HTTPClient;
use App\Core\Log;
use App\Entity\Actor;
use App\Entity\LocalUser;
use App\Util\Common;
use App\Util\Exception\NoSuchActorException;
use App\Util\Nickname;
use Exception;
use InvalidArgumentException;
use const JSON_UNESCAPED_SLASHES;
use Plugin\ActivityPub\ActivityPub;
use Plugin\ActivityPub\Entity\ActivitypubActor;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * ActivityPub's own Explorer
 *
 * Allows to discovery new remote actors
 *
 * @copyright 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Explorer
{
    private array $discovered_actors = [];

    /**
     * Shortcut function to get a single profile from its URL.
     *
     * @param bool $try_online whether to try online grabbing, defaults to true
     *
     * @throws ClientExceptionInterface
     * @throws NoSuchActorException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public static function getOneFromUri(string $uri, bool $try_online = true): Actor
    {
        $actors = (new self())->lookup($uri, $try_online);
        switch (\count($actors)) {
            case 1:
                return $actors[0];
            case 0:
                throw new NoSuchActorException('Invalid Actor.');
            default:
                throw new InvalidArgumentException('More than one actor found for this URI.');
        }
    }

    /**
     * Get every profile from the given URL
     * This function cleans the $this->discovered_actor_profiles array
     * so that there is no erroneous data
     *
     * @param string $uri        User's url
     * @param bool   $try_online whether to try online grabbing, defaults to true
     *
     * @throws ClientExceptionInterface
     * @throws NoSuchActorException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     *
     * @return array of Actor objects
     */
    public function lookup(string $uri, bool $try_online = true): array
    {
        if (\in_array($uri, ActivityPub::PUBLIC_TO)) {
            return [];
        }

        Log::debug('ActivityPub Explorer: Started now looking for ' . $uri);
        $this->discovered_actors = [];

        return $this->_lookup($uri, $try_online);
    }

    /**
     * Get every profile from the given URL
     * This is a recursive function that will accumulate the results on
     * $discovered_actor_profiles array
     *
     * @param string $uri        User's url
     * @param bool   $try_online whether to try online grabbing, defaults to true
     *
     * @throws ClientExceptionInterface
     * @throws NoSuchActorException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     *
     * @return array of Actor objects
     */
    private function _lookup(string $uri, bool $try_online = true): array
    {
        $grab_known = $this->grabKnownActor($uri);

        // First check if we already have it locally and, if so, return it.
        // If the known fetch fails and remote grab is required: store locally and return.
        if (!$grab_known && (!$try_online || !$this->grabRemoteActor($uri))) {
            throw new NoSuchActorException('Actor not found.');
        }

        return $this->discovered_actors;
    }

    /**
     * Get a known user profile from its URL and joins it on
     * $this->discovered_actor_profiles
     *
     * @param string $uri Actor's uri
     *
     * @throws Exception
     * @throws NoSuchActorException
     *
     * @return bool success state
     */
    private function grabKnownActor(string $uri): bool
    {
        Log::debug('ActivityPub Explorer: Searching locally for ' . $uri . ' offline.');

        // Try local
        if (Common::isValidHttpUrl($uri)) {
            // This means $uri is a valid url
            $resource_parts = parse_url($uri);
            // TODO: Use URLMatcher
            if ($resource_parts['host'] === Common::config('site', 'server')) {
                $str = $resource_parts['path'];
                // actor_view_nickname
                $renick = '/\/@(' . Nickname::DISPLAY_FMT . ')\/?/m';
                // actor_view_id
                $reuri = '/\/actor\/(\d+)\/?/m';
                if (preg_match_all($renick, $str, $matches, \PREG_SET_ORDER, 0) === 1) {
                    $this->discovered_actors[] = DB::findOneBy(
                        LocalUser::class,
                        ['nickname' => $matches[0][1]],
                    )->getActor();
                    return true;
                } elseif (preg_match_all($reuri, $str, $matches, \PREG_SET_ORDER, 0) === 1) {
                    $this->discovered_actors[] = Actor::getById((int) $matches[0][1]);
                    return true;
                }
            }
        }

        // Try standard ActivityPub route
        // Is this a known filthy little mudblood?
        $aprofile = DB::findOneBy(ActivitypubActor::class, ['uri' => $uri], return_null: true);
        if (!\is_null($aprofile)) {
            Log::debug('ActivityPub Explorer: Found a known ActivityPub Actor for ' . $uri);
            $this->discovered_actors[] = $aprofile->getActor();
            return true;
        } else {
            Log::debug('ActivityPub Explorer: Unable to find a known ActivityPub Actor for ' . $uri);
        }

        return false;
    }

    /**
     * Get a remote user(s) profile(s) from its URL and joins it on
     * $this->discovered_actor_profiles
     *
     * @param string $uri User's url
     *
     * @throws ClientExceptionInterface
     * @throws NoSuchActorException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     *
     * @return bool success state
     */
    private function grabRemoteActor(string $uri): bool
    {
        Log::debug('ActivityPub Explorer: Trying to grab a remote actor for ' . $uri);
        $response = HTTPClient::get($uri, ['headers' => ACTIVITYPUB::HTTP_CLIENT_HEADERS]);
        $res      = json_decode($response->getContent(), true);
        if ($response->getStatusCode() == 410) { // If it was deleted
            return true; // Nothing to add.
        } elseif (!HTTPClient::statusCodeIsOkay($response)) { // If it is unavailable
            return false; // Try to add at another time.
        }
        if (\is_null($res)) {
            Log::debug('ActivityPub Explorer: Invalid response returned from given Actor URL: ' . $res);
            return true; // Nothing to add.
        }

        if ($res['type'] === 'OrderedCollection') { // It's a potential collection of actors!!!
            Log::debug('ActivityPub Explorer: Found a collection of actors for ' . $uri);
            $this->travelCollection($res['first']);
            return true;
        } else {
            try {
                $this->discovered_actors[] = DB::wrapInTransaction(fn () => Model\Actor::fromJson(json_encode($res)))->getActor();
                return true;
            } catch (Exception $e) {
                Log::debug(
                    'ActivityPub Explorer: Invalid potential remote actor while grabbing remotely: ' . $uri
                    . '. He returned the following: ' . json_encode($res, JSON_UNESCAPED_SLASHES)
                    . ' and the following exception: ' . $e->getMessage(),
                );
                return false;
            }
        }

        return false;
    }

    /**
     * Allows the Explorer to transverse a collection of persons.
     *
     * @throws ClientExceptionInterface
     * @throws NoSuchActorException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function travelCollection(string $uri): bool
    {
        $response = HTTPClient::get($uri, ['headers' => ACTIVITYPUB::HTTP_CLIENT_HEADERS]);
        $res      = json_decode($response->getContent(), true);

        if (!isset($res['orderedItems'])) {
            return false;
        }

        // Accumulate findings
        foreach ($res['orderedItems'] as $actor_uri) {
            $this->_lookup($actor_uri);
        }

        // Go through entire collection
        if (!\is_null($res['next'])) {
            $this->travelCollection($res['next']);
        }

        return true;
    }

    /**
     * Get a remote user array from its URL (this function is only used for
     * profile updating and shall not be used for anything else)
     *
     * @param string $uri User's url
     *
     * @throws ClientExceptionInterface
     * @throws Exception
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     *
     * @return null|string If it is able to fetch, false if it's gone
     *                     // Exceptions when network issues or unsupported Activity format
     */
    public static function getRemoteActorActivity(string $uri): string|null
    {
        $response = HTTPClient::get($uri, ['headers' => ACTIVITYPUB::HTTP_CLIENT_HEADERS]);
        // If it was deleted
        if ($response->getStatusCode() == 410) {
            return null;
        } elseif (!HTTPClient::statusCodeIsOkay($response)) { // If it is unavailable
            throw new Exception('Non Ok Status Code for given Actor URL.');
        }
        return $response->getContent();
    }
}
