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

namespace Component\Notification;

use App\Core\DB\DB;
use App\Core\Event;
use App\Util\Exception\ServerException;
use function App\Core\I18n\_m;
use App\Core\Log;
use App\Core\Modules\Component;
use App\Core\Router\RouteLoader;
use App\Core\Router\Router;
use App\Entity\Activity;
use App\Entity\Actor;
use App\Entity\LocalUser;
use Component\FreeNetwork\FreeNetwork;
use Component\Notification\Controller\Feed;

class Notification extends Component
{
    /**
     * @param RouteLoader $m
     * @return bool
     */
    public function onAddRoute(RouteLoader $m): bool
    {
        $m->connect('feed_notifications', '/feed/notifications', [Feed::class, 'notifications']);
        return Event::next;
    }

    /**
     * @param int $actor_id
     * @param LocalUser $user
     * @param int $ordering
     * @return bool
     * @throws ServerException
     */
    public function onCreateDefaultFeeds(int $actor_id, LocalUser $user, int &$ordering): bool
    {
        DB::persist(\App\Entity\Feed::create([
            'actor_id' => $actor_id,
            'url'      => Router::url($route = 'feed_notifications'),
            'route'    => $route,
            'title'    => _m('Notifications'),
            'ordering' => $ordering++,
        ]));
        return Event::next;
    }

    /**
     * Enqueues a notification for an Actor (such as person or group) which means
     * it shows up in their home feed and such.
     * @param Actor $sender
     * @param Activity $activity
     * @param array $ids_already_known
     * @param string|null $reason
     * @return bool
     */
    public function onNewNotification(Actor $sender, Activity $activity, array $ids_already_known = [], ?string $reason = null): bool
    {
        $targets = $activity->getNotificationTargets(ids_already_known: $ids_already_known, sender_id: $sender->getId());
        if (Event::handle('NewNotificationWithTargets', [$sender, $activity, $targets, $reason]) === Event::next) {
            self::notify($sender, $activity, $targets, $reason);
        }

        return Event::next;
    }

    /**
     * Bring given Activity to Targets's attention
     * @param Actor $sender
     * @param Activity $activity
     * @param array $targets
     * @param string|null $reason
     * @return bool
     */
    public static function notify(Actor $sender, Activity $activity, array $targets, ?string $reason = null): bool
    {
        $remote_targets = [];
        foreach ($targets as $target) {
            if ($target->getIsLocal()) {
                if ($target->hasBlocked($activity->getActor())) {
                    Log::info("Not saving reply to actor {$target->getId()} from sender {$sender->getId()} because of a block.");
                    continue;
                }
                if (Event::handle('NewNotificationShould', [$activity, $target]) === Event::next) {
                    if ($sender->getId() === $target->getId()
                        || $activity->getActorId() === $target->getId()) {
                        // The target already knows about this, no need to bother with a notification
                        continue;
                    }
                    // TODO: use https://symfony.com/doc/current/notifier.html
                }
            } else {
                // We have no authority nor responsibility of notifying remote actors of a remote actor's doing
                if ($sender->getIsLocal()) {
                    $remote_targets[] = $target;
                }
            }
            // XXX: Unideal as in failures the rollback will leave behind a false notification,
            // but most notifications (all) require flushing the objects first
            // Should be okay as long as implementors bear this in mind
            DB::wrapInTransaction(fn () => DB::persist(Entity\Notification::create([
                'activity_id' => $activity->getId(),
                'target_id'   => $target->getId(),
                'reason'      => $reason,
            ])));
        }

        FreeNetwork::notify($sender, $activity, $remote_targets, $reason);

        return Event::next;
    }
}
