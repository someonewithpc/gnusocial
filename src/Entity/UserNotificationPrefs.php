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

namespace App\Entity;

use DateTimeInterface;

/**
 * Entity for user IM preferences
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 StatusNet Inc.
 * @author    Hugo Sales <hugo@fc.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class UserNotificationPrefs
{
    // {{{ Autocode

    private int $user_id;
    private string $service_name;
    private string $transport;
    private ?int $profile_id;
    private bool $posts_by_followed;
    private bool $mention;
    private bool $follow;
    private bool $favorite;
    private bool $nudge;
    private bool $dm;
    private bool $post_on_status_change;
    private ?bool $enable_posting;
    private \DateTimeInterface $created;
    private \DateTimeInterface $modified;

    public function setUserId(int $user_id): self
    {
        $this->user_id = $user_id;
        return $this;
    }
    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setServiceName(string $service_name): self
    {
        $this->service_name = $service_name;
        return $this;
    }
    public function getServiceName(): string
    {
        return $this->service_name;
    }

    public function setTransport(string $transport): self
    {
        $this->transport = $transport;
        return $this;
    }
    public function getTransport(): string
    {
        return $this->transport;
    }

    public function setProfileId(?int $profile_id): self
    {
        $this->profile_id = $profile_id;
        return $this;
    }
    public function getProfileId(): ?int
    {
        return $this->profile_id;
    }

    public function setPostsByFollowed(bool $posts_by_followed): self
    {
        $this->posts_by_followed = $posts_by_followed;
        return $this;
    }
    public function getPostsByFollowed(): bool
    {
        return $this->posts_by_followed;
    }

    public function setMention(bool $mention): self
    {
        $this->mention = $mention;
        return $this;
    }
    public function getMention(): bool
    {
        return $this->mention;
    }

    public function setFollow(bool $follow): self
    {
        $this->follow = $follow;
        return $this;
    }
    public function getFollow(): bool
    {
        return $this->follow;
    }

    public function setFavorite(bool $favorite): self
    {
        $this->favorite = $favorite;
        return $this;
    }
    public function getFavorite(): bool
    {
        return $this->favorite;
    }

    public function setNudge(bool $nudge): self
    {
        $this->nudge = $nudge;
        return $this;
    }
    public function getNudge(): bool
    {
        return $this->nudge;
    }

    public function setDm(bool $dm): self
    {
        $this->dm = $dm;
        return $this;
    }
    public function getDm(): bool
    {
        return $this->dm;
    }

    public function setPostOnStatusChange(bool $post_on_status_change): self
    {
        $this->post_on_status_change = $post_on_status_change;
        return $this;
    }
    public function getPostOnStatusChange(): bool
    {
        return $this->post_on_status_change;
    }

    public function setEnablePosting(?bool $enable_posting): self
    {
        $this->enable_posting = $enable_posting;
        return $this;
    }
    public function getEnablePosting(): ?bool
    {
        return $this->enable_posting;
    }

    public function setCreated(DateTimeInterface $created): self
    {
        $this->created = $created;
        return $this;
    }
    public function getCreated(): DateTimeInterface
    {
        return $this->created;
    }

    public function setModified(DateTimeInterface $modified): self
    {
        $this->modified = $modified;
        return $this;
    }
    public function getModified(): DateTimeInterface
    {
        return $this->modified;
    }

    // }}} Autocode

    public static function schemaDef(): array
    {
        return [
            'name'   => 'user_notification_prefs',
            'fields' => [
                'user_id'               => ['type' => 'int', 'not null' => true],
                'service_name'          => ['type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'name on this service'],
                'transport'             => ['type' => 'varchar', 'length' => 191, 'not null' => true, 'description' => 'transport (ex xmpp, aim)'],
                'profile_id'            => ['type' => 'int',  'default' => null, 'description' => 'If not null, settings are specific only to a given profiles'],
                'posts_by_followed'     => ['type' => 'bool', 'not null' => true, 'default' => false, 'description' => 'Notify when a new notice by someone we follow is made'],
                'mention'               => ['type' => 'bool', 'not null' => true, 'default' => false, 'description' => 'Notify when mentioned by someone we do not follow'],
                'follow'                => ['type' => 'bool', 'not null' => true, 'default' => false, 'description' => 'Notify someone follows us'],
                'favorite'              => ['type' => 'bool', 'not null' => true, 'default' => false, 'description' => 'Notify someone favorites a notice by us'],
                'nudge'                 => ['type' => 'bool', 'not null' => true, 'default' => false, 'description' => 'Notify someone nudges us'],
                'dm'                    => ['type' => 'bool', 'not null' => true, 'default' => false, 'description' => 'Notify someone sends us a direct message'],
                'post_on_status_change' => ['type' => 'bool', 'not null' => true, 'default' => false, 'description' => 'Post a notice when our status in service changes'],
                'enable_posting'        => ['type' => 'bool', 'default' => true,  'description' => 'Enable posting from this service'],
                'created'               => ['type' => 'datetime',  'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
                'modified'              => ['type' => 'timestamp', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key' => ['user_id', 'transport'],
            'unique keys' => [
                'transport_service_key' => ['transport', 'service_name'],
            ],
            'foreign keys' => [
                'user_notification_prefs_user_id_fkey' => ['user', ['user_id' => 'id']],
                'user_notification_prefs_profile'      => ['profile', ['profile_id' => 'id']],
            ],
            'indexes' => [
                'user_notification_prefs_user_profile_idx' => ['user_id', 'profile_id'],
            ],
        ];
    }
}
