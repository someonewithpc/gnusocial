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

namespace Plugin\ActivityPub\Controller;

use App\Core\Controller;
use App\Core\DB\DB;
use App\Core\Event;
use function App\Core\I18n\_m;
use App\Core\Log;
use App\Core\Router\Router;
use App\Entity\Actor;
use App\Util\Common;
use App\Util\Exception\ClientException;
use Component\FreeNetwork\Entity\FreeNetworkActorProtocol;
use Component\FreeNetwork\Util\Discovery;
use Exception;
use const PHP_URL_HOST;
use Plugin\ActivityPub\Entity\ActivitypubActor;
use Plugin\ActivityPub\Entity\ActivitypubRsa;
use Plugin\ActivityPub\Util\Explorer;
use Plugin\ActivityPub\Util\HTTPSignature;
use Plugin\ActivityPub\Util\Model;
use Plugin\ActivityPub\Util\TypeResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * ActivityPub Inbox Handler
 *
 * @copyright 2018-2019, 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Inbox extends Controller
{
    public function onGet(Request $request, ?int $gsactor_id = null): TypeResponse
    {
        return new TypeResponse(json_encode(['error' => 'No AP C2S inbox yet.']), 400);
    }

    /**
     * Create an Inbox Handler to receive something from someone.
     */
    public function onPost(Request $request, ?int $gsactor_id = null): TypeResponse
    {
        $error = function (string $m, ?Exception $e = null): TypeResponse {
            Log::error('ActivityPub Error Answer: ' . ($json = json_encode(['error' => $m, 'exception' => var_export($e, true)])));
            if (\is_null($e)) {
                return new TypeResponse($json, 400);
            } else {
                throw $e;
            }
        };
        $path = Router::url('activitypub_inbox', type: Router::ABSOLUTE_PATH);

        if (!\is_null($gsactor_id)) {
            try {
                $user = DB::findOneBy('local_user', ['id' => $gsactor_id]);
                $path = Router::url('activitypub_actor_inbox', ['gsactor_id' => $user->getId()], type: Router::ABSOLUTE_PATH);
            } catch (Exception $e) {
                throw new ClientException(_m('No such actor.'), 404, previous: $e);
            }
        }

        Log::debug('ActivityPub Inbox: Received a POST request.');
        $body = (string) $this->request->getContent();
        Log::debug('ActivityPub Inbox: Request Body content: ' . $body);
        $type = Model::jsonToType($body);

        if ($type->has('actor') === false) {
            return $error('Actor not found in the request.');
        }

        try {
            $resource_parts = parse_url($type->get('actor'));
            if ($resource_parts['host'] !== Common::config('site', 'server')) {
                $actor    = DB::wrapInTransaction(fn () => Explorer::getOneFromUri($type->get('actor')));
                $ap_actor = DB::findOneBy(ActivitypubActor::class, ['actor_id' => $actor->getId()]);
            } else {
                throw new Exception('Only remote actors can use this endpoint.');
            }
            unset($resource_parts);
        } catch (Exception $e) {
            return $error('Invalid actor.', $e);
        }

        $activitypub_rsa  = ActivitypubRsa::getByActor($actor);
        $actor_public_key = $activitypub_rsa->getPublicKey();

        $headers = $this->request->headers->all();
        Log::debug('ActivityPub Inbox: Request Headers.', [$headers]);
        // Flattify headers
        foreach ($headers as $key => $val) {
            $headers[$key] = $val[0];
        }

        if (!isset($headers['signature'])) {
            Log::debug('ActivityPub Inbox: HTTP Signature: Missing Signature header.');
            return $error('Missing Signature header.');
            // TODO: support other methods beyond HTTP Signatures
        }

        // Extract the signature properties
        $signatureData = HTTPSignature::parseSignatureHeader($headers['signature']);
        Log::debug('ActivityPub Inbox: HTTP Signature Data.', [$signatureData]);
        if (isset($signatureData['error'])) {
            return $error(json_encode($signatureData, \JSON_PRETTY_PRINT));
        }

        [$verified, /*$headers*/] = HTTPSignature::verify($actor_public_key, $signatureData, $headers, $path, $body);

        // If the signature fails verification the first time, update profile as it might have changed public key
        if ($verified !== 1) {
            try {
                $res = Explorer::getRemoteActorActivity($ap_actor->getUri());
                if (\is_null($res)) {
                    return $error('Invalid remote actor (null response).');
                }
            } catch (Exception $e) {
                return $error('Invalid remote actor.', $e);
            }
            try {
                ActivitypubActor::update_profile($ap_actor, $actor, $activitypub_rsa, $res);
            } catch (Exception $e) {
                return $error('Failed to updated remote actor information.', $e);
            }

            [$verified, /*$headers*/] = HTTPSignature::verify($actor_public_key, $signatureData, $headers, $path, $body);
        }

        // If it still failed despite profile update
        if ($verified !== 1) {
            Log::debug('ActivityPub Inbox: HTTP Signature: Invalid signature.');
            return $error('Invalid signature.');
        }

        // HTTP signature checked out, make sure the "actor" of the activity matches that of the signature
        Log::debug('ActivityPub Inbox: HTTP Signature: Authorised request. Will now start the inbox handler.');

        // TODO: Check if Actor has authority over payload

        // Store Activity
        $ap_act = Model\Activity::fromJson($type, ['source' => 'ActivityPub']);
        FreeNetworkActorProtocol::protocolSucceeded(
            'activitypub',
            $ap_actor->getActorId(),
            Discovery::normalize($actor->getNickname() . '@' . parse_url($ap_actor->getInboxUri(), PHP_URL_HOST)),
        );
        $already_known_ids = [];
        if (!empty($ap_act->_object_mention_ids)) {
            $already_known_ids = $ap_act->_object_mention_ids;
        }

        DB::flush();
        if (Event::handle('ActivityPubNewNotification', [$actor, $ap_act->getActivity(), $already_known_ids, _m('{nickname} attentioned you.', ['{nickname}' => $actor->getNickname()])]) === Event::next) {
            Event::handle('NewNotification', [$actor, $ap_act->getActivity(), $already_known_ids, _m('{nickname} attentioned you.', ['{nickname}' => $actor->getNickname()])]);
        }

        dd($ap_act, $act = $ap_act->getActivity(), $act->getActor(), $act->getObject());

        return new TypeResponse($type, status: 202);
    }
}
