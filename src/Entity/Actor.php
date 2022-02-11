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

namespace App\Entity;

use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Entity;
use App\Core\Event;
use App\Core\Router\Router;
use App\Util\Exception\BugFoundException;
use App\Util\Exception\NicknameException;
use App\Util\Exception\NotFoundException;
use App\Util\Formatting;
use App\Util\Nickname;
use Component\Avatar\Avatar;
use Component\Language\Entity\ActorLanguage;
use Component\Language\Entity\Language;
use Component\Subscription\Entity\ActorSubscription;
use Functional as F;

/**
 * Entity for actors
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet Inc.
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009-2014 Free Software Foundation, Inc http://www.fsf.org
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Actor extends Entity
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $id;
    private string $nickname;
    private ?string $fullname = null;
    private int $roles;
    private int $type;
    private ?string $homepage = null;
    private ?string $bio = null;
    private ?string $location = null;
    private ?float $lat = null;
    private ?float $lon = null;
    private ?int $location_id = null;
    private ?int $location_service = null;
    private bool $is_local;
    private \DateTimeInterface $created;
    private \DateTimeInterface $modified;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setNickname(string $nickname): self
    {
        $this->nickname = \mb_substr($nickname, 0, 64);
        return $this;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function setFullname(?string $fullname): self
    {
        $this->fullname = $fullname;
        return $this;
    }

    public function getFullname(): ?string
    {
        return $this->fullname;
    }

    public function setRoles(int $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getRoles(): int
    {
        return $this->roles;
    }

    public function setType(int $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setHomepage(?string $homepage): self
    {
        $this->homepage = $homepage;
        return $this;
    }

    public function getHomepage(): ?string
    {
        return $this->homepage;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLat(?float $lat): self
    {
        $this->lat = $lat;
        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLon(?float $lon): self
    {
        $this->lon = $lon;
        return $this;
    }

    public function getLon(): ?float
    {
        return $this->lon;
    }

    public function setLocationId(?int $location_id): self
    {
        $this->location_id = $location_id;
        return $this;
    }

    public function getLocationId(): ?int
    {
        return $this->location_id;
    }

    public function setLocationService(?int $location_service): self
    {
        $this->location_service = $location_service;
        return $this;
    }

    public function getLocationService(): ?int
    {
        return $this->location_service;
    }

    public function setIsLocal(bool $is_local): self
    {
        $this->is_local = $is_local;
        return $this;
    }

    public function getIsLocal(): bool
    {
        return $this->is_local;
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): \DateTimeInterface
    {
        return $this->created;
    }

    public function setModified(\DateTimeInterface $modified): self
    {
        $this->modified = $modified;
        return $this;
    }

    public function getModified(): \DateTimeInterface
    {
        return $this->modified;
    }

    // @codeCoverageIgnoreEnd
    // }}} Autocode

    public const PERSON = 1;
    public const GROUP = 2;
    public const BOT = 3;

    public static function cacheKeys(int|self $actor_id, mixed $other = null): array
    {
        $actor_id = \is_int($actor_id) ? $actor_id : $actor_id->getId();

        return [
            'id' => "actor-id-{$actor_id}",
            'nickname' => "actor-nickname-id-{$actor_id}",
            'fullname' => "actor-fullname-id-{$actor_id}",
            'self-tags' =>  "actor-self-tags-{$actor_id}",
            'circles' => "actor-circles-{$actor_id}",
            'subscribers' => "subscribers-{$actor_id}",
            'subscribed' => "subscribed-{$actor_id}",
            'relative-nickname' => "actor-{$actor_id}-relative-nickname-{$other}", // $other is $nickname
            'can-admin' => "actor-{$actor_id}-can-admin-{$other}", // $other is an actor id
        ];
    }

    public function getLocalUser(): ?LocalUser
    {
        if ($this->getIsLocal()) {
            return DB::findOneBy('local_user', ['id' => $this->getId()]);
        } else {
            throw new NotFoundException('This is a remote actor.');
        }
    }

    public function getAvatarUrl(string $size = 'medium')
    {
        return Avatar::getUrl($this->getId(), $size);
    }

    public function getAvatarDimensions(string $size = 'medium')
    {
        return Avatar::getDimensions($this->getId(), $size);
    }

    public static function getById(int $id): ?self
    {
        return Cache::get(self::cacheKeys($id)['id'], fn() => DB::findOneBy(self::class, ['id' => $id]));
    }

    public static function getNicknameById(int $id): string
    {
        return Cache::get(self::cacheKeys($id)['nickname'], fn() => self::getById($id)->getNickname());
    }

    public static function getFullnameById(int $id): ?string
    {
        return Cache::get(self::cacheKeys($id)['fullname'], fn() => self::getById($id)->getFullname());
    }

    /**
     * For consistency with Note
     */
    public function getActorId(): int
    {
        return $this->getId();
    }

    /**
     * @return array ActorTag[] Self Tag Circles of which this actor is a member
     */
    public function getSelfTags(): array
    {
        return Cache::getList(
            self::cacheKeys($this->getId())['self-tags'],
            fn() => DB::findBy('actor_tag', ['tagger' => $this->getId(), 'tagged' => $this->getId()], order_by: ['modified' => 'DESC']),
        );
    }

    /**
     * @return array ActorCircle[]
     */
    public function getCircles(): array
    {
        return Cache::getList(
            self::cacheKeys($this->getId())['circles'],
            fn() => DB::findBy('actor_circle', ['tagger' => $this->getId()]),
        );
    }

    private function getSubCount(string $which, string $column): int
    {
        return Cache::get(
            self::cacheKeys($this->getId())[$which],
            fn() => DB::count(ActorSubscription::class, [$column => $this->getId()]) - ($this->getIsLocal() ? 1 : 0)
        );
    }

    public function getSubscribersCount(): int
    {
        return $this->getSubCount(which: 'subscribers', column: 'subscribed_id');
    }

    public function getSubscribedCount(): int
    {
        return $this->getSubCount(which: 'subscribed', column: 'subscriber_id');
    }

    public function getSubscriptions(): array
    {
        return DB::dql(<<<EOF
            SELECT a FROM actor AS a
            INNER JOIN actor_subscription AS s
            WITH a.id = s.subscribed_id
            WHERE s.subscriber_id = :self AND a.id != :self
        EOF, ['self' => $this->getId()]);
    }

    public function getSubscribers(): array
    {
        return DB::dql(<<<EOF
            SELECT a FROM actor AS a
            INNER JOIN actor_subscription AS s
            WITH a.id = s.subscriber_id
            WHERE s.subscribed_id = :self AND a.id != :self
        EOF, ['self' => $this->getId()]);
    }

    public function getSubscriptionsUrl(): string
    {
        return Router::url('actor_subscriptions_id', ['id' => $this->getId()]);
    }

    public function getSubscribersUrl(): string
    {
        return Router::url('actor_subscribers_id', ['id' => $this->getId()]);
    }

    /**
     * Resolve an ambiguous nickname reference, checking in following order:
     * - Actors that $sender subscribes to
     * - Actors that subscribe to $sender
     * - Any Actor
     *
     * @param string $nickname validated nickname of
     *
     * @throws NicknameException
     */
    public function findRelativeActor(string $nickname): ?self
    {
        // Will throw exception on invalid input.
        $nickname = Nickname::normalize($nickname, check_already_used: false);
        return Cache::get(
            self::cacheKeys($this->getId(), $nickname)['relative-nickname'],
            fn () => DB::dql(
                <<<'EOF'
                    SELECT a FROM actor AS a WHERE
                    a.id IN (SELECT sa.subscribed_id FROM actor_subscription sa JOIN actor aa WITH sa.subscribed_id = aa.id WHERE sa.subscriber_id = :actor_id AND aa.nickname = :nickname) OR
                    a.id IN (SELECT sb.subscriber_id FROM actor_subscription sb JOIN actor ab WITH sb.subscriber_id = ab.id WHERE sb.subscribed_id = :actor_id AND ab.nickname = :nickname) OR
                    a.nickname = :nickname
                    EOF,
                ['nickname' => $nickname, 'actor_id' => $this->getId()],
                ['limit'    => 1],
            )[0] ?? null,
        );
    }

    /**
     * Get a URI for this actor, i.e. a unique and stable identifier, using the ID
     */
    public function getUri(int $type = Router::ABSOLUTE_URL): string
    {
        $uri = null;
        if (Event::handle('StartGetActorUri', [$this, $type, &$uri]) === Event::next) {
            switch ($this->type) {
            case self::PERSON:
            case self::BOT:
            case self::GROUP:
                $uri = Router::url('actor_view_id', ['id' => $this->getId()], $type);
                break;
            default:
                throw new BugFoundException('Actor type added but `Actor::getUri` was not updated');
            }
            Event::handle('EndGetActorUri', [$this, $type, &$uri]);
        }
        return $uri;
    }

    /**
     * Get a URL for this actor, i.e. a user friendly URL, using the nickname
     *
     * @param int $type
     * @return string
     * @throws BugFoundException
     */
    public function getUrl(int $type = Router::ABSOLUTE_URL): string
    {
        if ($this->getIsLocal()) {
            $url = null;
            if (Event::handle('StartGetActorUrl', [$this, $type, &$url]) === Event::next) {
                $url = match ($this->type) {
                    self::PERSON, self::BOT => Router::url('person_actor_view_nickname', ['nickname' => mb_strtolower($this->getNickname())], $type),
                    self::GROUP => Router::url('group_actor_view_nickname', ['nickname' => $this->getNickname()], $type),
                    default => throw new BugFoundException('Actor type added but `Actor::getUrl` was not updated'),
                };
                Event::handle('EndGetActorUrl', [$this, $type, &$url]);
            }
        } else {
            return Router::url('actor_view_id', ['id' => $this->getId()], $type);
        }
        return $url;
    }

    public function getAliases(): array
    {
        return array_keys($this->getAliasesWithIDs());
    }

    public function getAliasesWithIDs(): array
    {
        $aliases = [];

        $aliases[$this->getUri(Router::ABSOLUTE_URL)] = $this->getId();
        $aliases[$this->getUrl(Router::ABSOLUTE_URL)] = $this->getId();

        return $aliases;
    }

    public function getTopLanguage(): Language
    {
        return ActorLanguage::getActorLanguages($this, context: null)[0];
    }

    /**
     * Get the most appropriate language for $this to use when
     * referring to $context (a reply or a group, for instance)
     *
     * @return Language[]
     */
    public function getPreferredLanguageChoices(?self $context = null): array
    {
        $langs = ActorLanguage::getActorLanguages($this, context: $context);
        return array_merge(...F\map($langs, fn ($l) => $l->toChoiceFormat()));
    }

    public function isVisibleTo(null|LocalUser|self $other): bool
    {
        return true; // TODO
    }

    /**
     * Check whether $this has permission for performing actions on behalf of $other
     */
    public function canAdmin(self $other): bool
    {
        if ($this->getIsLocal()) {
            switch ($other->getType()) {
                case self::PERSON:
                    return $this->getId() === $other->getId();
                case self::GROUP:
                    return Cache::get(
                        self::cacheKeys($this->getId(), $other->getId())['can-admin'],
                        function () use ($other) {
                            try {
                                return DB::findOneBy('group_member', ['group_id' => $other->getId(), 'actor_id' => $this->getId()])->getIsAdmin();
                            } catch (NotFoundException) {
                                return false;
                            }
                        },
                    );
                default:
                    return false;
            }
        } else {
            $canAdmin = false;
            Event::handle('FreeNetworkActorCanAdmin', [$this, $other, &$canAdmin]);
            return $canAdmin;
        }
    }

    /**
     * @method bool isPerson()
     * @method bool isGroup()
     * @method bool isBot()
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (Formatting::startsWith($name, 'is')) {
            $type  = Formatting::removePrefix($name, 'is');
            $const = self::class . '::' . mb_strtoupper($type);
            if (\defined($const)) {
                return $this->type === \constant($const);
            } else {
                throw new BugFoundException("Actor cannot be a '{$type}', check your spelling");
            }
        } else {
            return parent::__call($name, $arguments);
        }
    }

    public static function schemaDef(): array
    {
        return [
            'name'        => 'actor',
            'description' => 'local and remote users, groups and bots are actors, for instance',
            'fields'      => [
                'id'               => ['type' => 'serial', 'not null' => true, 'description' => 'unique identifier'],
                'nickname'         => ['type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'nickname or username'],
                'fullname'         => ['type' => 'text', 'description' => 'display name', 'default' => null],
                'roles'            => ['type' => 'int', 'not null' => true, 'description' => 'Bitmap of permissions this actor has'],
                'type'             => ['type' => 'int', 'not null' => true, 'description' => 'The type of actor (person, group, bot, etc)'],
                'homepage'         => ['type' => 'text', 'description' => 'identifying URL', 'default' => null],
                'bio'              => ['type' => 'text', 'description' => 'descriptive biography', 'default' => null],
                'location'         => ['type' => 'text', 'description' => 'physical location', 'default' => null],
                'lat'              => ['type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'latitude', 'default' => null],
                'lon'              => ['type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'longitude', 'default' => null],
                'location_id'      => ['type' => 'int', 'description' => 'location id if possible', 'default' => null],
                'location_service' => ['type' => 'int', 'description' => 'service used to obtain location id', 'default' => null],
                'is_local'         => ['type' => 'bool', 'not null' => true, 'description' => 'Does this actor have a LocalUser associated'],
                'created'          => ['type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
                'modified'         => ['type' => 'timestamp', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key' => ['id'],
            'indexes'     => [
                'actor_nickname_idx' => ['nickname'],
            ],
            'fulltext indexes' => [
                'actor_fulltext_idx' => ['nickname', 'fullname', 'location', 'bio', 'homepage'],
            ],
        ];
    }
}
