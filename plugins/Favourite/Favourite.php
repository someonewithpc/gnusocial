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
 * @author    Eliseu Amaro <mail@eliseuama.ro>
 * @author    Diogo Peralta Cordeiro <@diogo.site>
 * @copyright 2021-2022 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace Plugin\Favourite;

use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Event;
use function App\Core\I18n\_m;
use App\Core\Modules\NoteHandlerPlugin;
use App\Core\Router\RouteLoader;
use App\Core\Router\Router;
use App\Entity\Activity;
use App\Entity\Actor;
use App\Entity\Feed;
use App\Entity\LocalUser;
use App\Entity\Note;
use App\Util\Common;
use App\Util\Nickname;
use DateTime;
use Plugin\Favourite\Entity\NoteFavourite as FavouriteEntity;
use Symfony\Component\HttpFoundation\Request;

class Favourite extends NoteHandlerPlugin
{
    /**
     * Creates a new Favourite Entity, upon the given Actor performs a Favourite
     * action on the given Note object
     *
     * A new notification is then handled, informing all interested Actors of this action
     *
     * @param int $note_id  Note id being favoured
     * @param int $actor_id Actor performing favourite Activity
     *
     * @throws \App\Util\Exception\ServerException
     */
    public static function favourNote(int $note_id, int $actor_id, string $source = 'web'): ?Activity
    {
        $opts                  = ['note_id' => $note_id, 'actor_id' => $actor_id];
        $note_already_favoured = Cache::get(
            FavouriteEntity::cacheKeys($note_id, $actor_id)['favourite'],
            fn () => DB::findOneBy('note_favourite', $opts, return_null: true),
        );
        $activity = null;
        if (\is_null($note_already_favoured)) {
            DB::persist(FavouriteEntity::create($opts));
            Cache::delete(FavouriteEntity::cacheKeys($note_id, $actor_id)['favourite']);
            $activity = Activity::create([
                'actor_id'    => $actor_id,
                'verb'        => 'favourite',
                'object_type' => 'note',
                'object_id'   => $note_id,
                'source'      => $source,
            ]);
            DB::persist($activity);

        }
        return $activity;
    }

    /**
     * Removes the Favourite Entity created beforehand, by the same Actor, and on the same Note
     *
     * Informs all interested Actors of this action, handling out the NewNotification event
     *
     * @param int $note_id  Note id being unfavoured
     * @param int $actor_id Actor undoing favourite Activity
     *
     * @throws \App\Util\Exception\ServerException
     */
    public static function unfavourNote(int $note_id, int $actor_id, string $source = 'web'): ?Activity
    {
        $note_already_favoured = Cache::get(
            FavouriteEntity::cacheKeys($note_id, $actor_id)['favourite'],
            static fn () => DB::findOneBy('note_favourite', ['note_id' => $note_id, 'actor_id' => $actor_id], return_null: true),
        );
        $activity = null;
        if (!\is_null($note_already_favoured)) {
            DB::removeBy(FavouriteEntity::class, ['note_id' => $note_id, 'actor_id' => $actor_id]);

            Cache::delete(FavouriteEntity::cacheKeys($note_id, $actor_id)['favourite']);
            $favourite_activity = DB::findBy('activity', ['verb' => 'favourite', 'object_type' => 'note', 'actor_id' => $actor_id, 'object_id' => $note_id], order_by: ['created' => 'DESC'])[0];
            $activity           = Activity::create([
                'actor_id'    => $actor_id,
                'verb'        => 'undo', // 'undo_favourite',
                'object_type' => 'activity', // 'note',
                'object_id'   => $favourite_activity->getId(), // $note_id,
                'source'      => $source,
            ]);
            DB::persist($activity);
        }
        return $activity;
    }

    /**
     * HTML rendering event that adds the favourite form as a note
     * action, if a user is logged in
     *
     * @param Note  $note    Current Note being rendered
     * @param array $actions Array containing all Note actions to be rendered
     *
     * @return bool Event hook, Event::next (true) is returned to allow Event to be handled by other handlers
     */
    public function onAddNoteActions(Request $request, Note $note, array &$actions): bool
    {
        if (\is_null($user = Common::user())) {
            return Event::next;
        }

        // If note is favourite, "is_favourite" is 1
        $opts         = ['note_id' => $note->getId(), 'actor_id' => $user->getId()];
        $is_favourite = !\is_null(
            Cache::get(
                FavouriteEntity::cacheKeys($note->getId(), $user->getId())['favourite'],
                static fn () => DB::findOneBy('note_favourite', $opts, return_null: true),
            ),
        );

        // Generating URL for favourite action route
        $args                 = ['id' => $note->getId()];
        $type                 = Router::ABSOLUTE_PATH;
        $favourite_action_url = $is_favourite
            ? Router::url('favourite_remove', $args, $type)
            : Router::url('favourite_add', $args, $type);

        $query_string = $request->getQueryString();
        // Concatenating get parameter to redirect the user to where he came from
        $favourite_action_url .= '?from=' . urlencode($request->getRequestUri());

        $extra_classes    = $is_favourite ? 'note-actions-set' : 'note-actions-unset';
        $favourite_action = [
            'url'     => $favourite_action_url,
            'title'   => $is_favourite ? 'Remove this note from favourites' : 'Favourite this note!',
            'classes' => "button-container favourite-button-container {$extra_classes}",
            'id'      => 'favourite-button-container-' . $note->getId(),
        ];

        $actions[] = $favourite_action;
        return Event::next;
    }

    /**
     * Appends on Note currently being rendered complementary information regarding whom (subject) performed which Activity (verb) on aforementioned Note (object)
     *
     * @param array $vars   Array containing necessary info to process event. In this case, contains the current Note being rendered
     * @param array $result Contains a hashmap for each Activity performed on Note (object)
     *
     * @return bool Event hook, Event::next (true) is returned to allow Event to be handled by other handlers
     */
    public function onAppendCardNote(array $vars, array &$result): bool
    {
        // If note is the original and user isn't the one who repeated, append on end "user repeated this"
        // If user is the one who repeated, append on end "you repeated this, remove repeat?"
        $check_user = !\is_null(Common::user());

        // The current Note being rendered
        $note = $vars['note'];

        // Will have actors array, and action string
        // Actors are the subjects, action is the verb (in the final phrase)
        $favourite_actors = FavouriteEntity::getNoteFavouriteActors($note);

        if (\count($favourite_actors) < 1) {
            return Event::next;
        }

        // Filter out multiple replies from the same actor
        $favourite_actors = array_unique($favourite_actors, \SORT_REGULAR);
        $result[]         = ['actors' => $favourite_actors, 'action' => 'favourited'];
        return Event::next;
    }

    /**
     * Deletes every favourite entity in table related to a deleted Note
     */
    public function onNoteDeleteRelated(Note &$note, Actor $actor): bool
    {
        $note_favourites_list = FavouriteEntity::getNoteFavourites($note);
        foreach ($note_favourites_list as $favourite_entity) {
            DB::remove($favourite_entity);
        }

        return Event::next;
    }

    /**
     * Maps Routes to their respective Controllers
     */
    public function onAddRoute(RouteLoader $r): bool
    {
        // Add/remove note to/from favourites
        $r->connect(id: 'favourite_add', uri_path: '/object/note/{id<\d+>}/favour', target: [Controller\Favourite::class, 'favouriteAddNote']);
        $r->connect(id: 'favourite_remove', uri_path: '/object/note/{id<\d+>}/unfavour', target: [Controller\Favourite::class, 'favouriteRemoveNote']);

        // View all favourites by actor id
        $r->connect(id: 'favourites_view_by_actor_id', uri_path: '/actor/{id<\d+>}/favourites', target: [Controller\Favourite::class, 'favouritesViewByActorId']);
        $r->connect(id: 'favourites_reverse_view_by_actor_id', uri_path: '/actor/{id<\d+>}/reverse_favourites', target: [Controller\Favourite::class, 'favouritesReverseViewByActorId']);

        // View all favourites by nickname
        $r->connect(id: 'favourites_view_by_nickname', uri_path: '/@{nickname<' . Nickname::DISPLAY_FMT . '>}/favourites', target: [Controller\Favourite::class, 'favouritesViewByActorNickname']);
        $r->connect(id: 'favourites_reverse_view_by_nickname', uri_path: '/@{nickname<' . Nickname::DISPLAY_FMT . '>}/reverse_favourites', target: [Controller\Favourite::class, 'reverseFavouritesViewByActorNickname']);
        return Event::next;
    }

    /**
     * Creates two feeds, including reverse favourites or favourites performed by given Actor
     *
     * @param int $actor_id Whom the favourites belong to
     *
     * @throws \App\Util\Exception\ServerException
     */
    public function onCreateDefaultFeeds(int $actor_id, LocalUser $user, int &$ordering): bool
    {
        DB::persist(Feed::create([
            'actor_id' => $actor_id,
            'url'      => Router::url($route = 'favourites_view_by_nickname', ['nickname' => $user->getNickname()]),
            'route'    => $route,
            'title'    => _m('Favourites'),
            'ordering' => $ordering++,
        ]));
        DB::persist(Feed::create([
            'actor_id' => $actor_id,
            'url'      => Router::url($route = 'favourites_reverse_view_by_nickname', ['nickname' => $user->getNickname()]),
            'route'    => $route,
            'title'    => _m('Reverse favourites'),
            'ordering' => $ordering++,
        ]));
        return Event::next;
    }

    // ActivityPub handling and processing for Favourites is below

    /**
     * ActivityPub Inbox handler for Like and Undo Like activities
     *
     * @param Actor                                               $actor         Actor who authored the activity
     * @param \ActivityPhp\Type\AbstractObject                    $type_activity Activity Streams 2.0 Activity
     * @param mixed                                               $type_object   Activity's Object
     * @param null|\Plugin\ActivityPub\Entity\ActivitypubActivity $ap_act        Resulting ActivitypubActivity
     *
     * @throws \App\Util\Exception\ClientException
     * @throws \App\Util\Exception\DuplicateFoundException
     * @throws \App\Util\Exception\NoSuchActorException
     * @throws \App\Util\Exception\ServerException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     *
     * @return bool Returns `Event::stop` if handled, `Event::next` otherwise
     */
    private function activitypub_handler(Actor $actor, \ActivityPhp\Type\AbstractObject $type_activity, mixed $type_object, ?\Plugin\ActivityPub\Entity\ActivitypubActivity &$ap_act): bool
    {
        if (!\in_array($type_activity->get('type'), ['Like', 'Undo'])) {
            return Event::next;
        }
        if ($type_activity->get('type') === 'Like') { // Favourite
            if ($type_object instanceof \ActivityPhp\Type\AbstractObject) {
                if ($type_object->get('type') === 'Note' || $type_object->get('type') === 'Page') {
                    $note    = \Plugin\ActivityPub\Util\Model\Note::fromJson($type_object);
                    $note_id = $note->getId();
                } else {
                    return Event::next;
                }
            } elseif ($type_object instanceof Note) {
                $note_id = $type_object->getId();
            } else {
                return Event::next;
            }
        } elseif ($type_object instanceof \ActivityPhp\Type\AbstractObject) {
            $ap_prev_favourite_act = \Plugin\ActivityPub\Util\Model\Activity::fromJson($type_object);
            $prev_favourite_act    = $ap_prev_favourite_act->getActivity();
            if ($prev_favourite_act->getVerb() === 'favourite' && $prev_favourite_act->getObjectType() === 'note') {
                $note_id = $prev_favourite_act->getObjectId();
            } else {
                return Event::next;
            }
        } elseif ($type_object instanceof Activity) {
            if ($type_object->getVerb() === 'favourite' && $type_object->getObjectType() === 'note') {
                $note_id = $type_object->getObjectId();
            } else {
                return Event::next;
            }
        } else {
            return Event::next;
        }

        if ($type_activity->get('type') === 'Like') {
            $activity = self::favourNote($note_id, $actor->getId(), source: 'ActivityPub');
        } else {
            $activity = self::unfavourNote($note_id, $actor->getId(), source: 'ActivityPub');
        }
        if (!\is_null($activity)) {
            // Store ActivityPub Activity
            $ap_act = \Plugin\ActivityPub\Entity\ActivitypubActivity::create([
                'activity_id'  => $activity->getId(),
                'activity_uri' => $type_activity->get('id'),
                'created'      => new DateTime($type_activity->get('published') ?? 'now'),
                'modified'     => new DateTime(),
            ]);
            DB::persist($ap_act);
        }
        return Event::stop;
    }

    public function onActivityPubNewNotification(Actor $sender, Activity $activity, array $ids_already_known = [], ?string $reason = null): bool
    {
        switch ($activity->getVerb()) {
            case 'favourite':
                Event::handle('NewNotification', [$sender, $activity, [], _m('{nickname} favoured note {note_id}.', ['{nickname}' => $sender->getNickname(), '{note_id}' => $activity->getObjectId()])]);
                return Event::stop;
            case 'undo':
                if ($activity->getObjectType() === 'activity') {
                    $undone_favourite = $activity->getObject();
                    if ($undone_favourite->getVerb() === 'favourite') {
                        Event::handle('NewNotification', [$sender, $activity, [], _m('{nickname} unfavoured note {note_id}.', ['{nickname}' => $sender->getNickname(), '{note_id}' => $activity->getObjectId()])]);
                        return Event::stop;
                    }
                }
        }
        return Event::next;
    }

    /**
     * Convert an Activity Streams 2.0 Like or Undo Like into the appropriate Favourite and Undo Favourite entities
     *
     * @param Actor                                               $actor         Actor who authored the activity
     * @param \ActivityPhp\Type\AbstractObject                    $type_activity Activity Streams 2.0 Activity
     * @param \ActivityPhp\Type\AbstractObject                    $type_object   Activity Streams 2.0 Object
     * @param null|\Plugin\ActivityPub\Entity\ActivitypubActivity $ap_act        Resulting ActivitypubActivity
     *
     * @throws \App\Util\Exception\ClientException
     * @throws \App\Util\Exception\DuplicateFoundException
     * @throws \App\Util\Exception\NoSuchActorException
     * @throws \App\Util\Exception\ServerException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     *
     * @return bool Returns `Event::stop` if handled, `Event::next` otherwise
     */
    public function onNewActivityPubActivity(Actor $actor, \ActivityPhp\Type\AbstractObject $type_activity, \ActivityPhp\Type\AbstractObject $type_object, ?\Plugin\ActivityPub\Entity\ActivitypubActivity &$ap_act): bool
    {
        return $this->activitypub_handler($actor, $type_activity, $type_object, $ap_act);
    }

    /**
     * Convert an Activity Streams 2.0 formatted activity with a known object into Entities
     *
     * @param Actor                                               $actor         Actor who authored the activity
     * @param \ActivityPhp\Type\AbstractObject                    $type_activity Activity Streams 2.0 Activity
     * @param mixed                                               $type_object   Object
     * @param null|\Plugin\ActivityPub\Entity\ActivitypubActivity $ap_act        Resulting ActivitypubActivity
     *
     * @throws \App\Util\Exception\ClientException
     * @throws \App\Util\Exception\DuplicateFoundException
     * @throws \App\Util\Exception\NoSuchActorException
     * @throws \App\Util\Exception\ServerException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     *
     * @return bool Returns `Event::stop` if handled, `Event::next` otherwise
     */
    public function onNewActivityPubActivityWithObject(Actor $actor, \ActivityPhp\Type\AbstractObject $type_activity, mixed $type_object, ?\Plugin\ActivityPub\Entity\ActivitypubActivity &$ap_act): bool
    {
        return $this->activitypub_handler($actor, $type_activity, $type_object, $ap_act);
    }

    /**
     * Translate GNU social internal verb 'favourite' to Activity Streams 2.0 'Like'
     *
     * @param string      $verb                                GNU social's internal verb
     * @param null|string $gs_verb_to_activity_stream_two_verb Resulting Activity Streams 2.0 verb
     *
     * @return bool Returns `Event::stop` if handled, `Event::next` otherwise
     */
    public function onGSVerbToActivityStreamsTwoActivityType(string $verb, ?string &$gs_verb_to_activity_stream_two_verb): bool
    {
        if ($verb === 'favourite') {
            $gs_verb_to_activity_stream_two_verb = 'Like';
            return Event::stop;
        }
        return Event::next;
    }
}
