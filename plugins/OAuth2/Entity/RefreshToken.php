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
 * OAuth2 Client
 *
 * @package   GNUsocial
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2022 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace Plugin\OAuth2\Entity;

use App\Core\Entity;
use DateTimeInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

class RefreshToken extends Entity implements RefreshTokenEntityInterface
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private string $id;
    private DateTimeInterface $expiry;
    private ?string $access_token_id = null;
    private bool $revoked;
    private DateTimeInterface $created;

    public function setId(string $id): self
    {
        $this->id = mb_substr($id, 0, 64);
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setExpiry(DateTimeInterface $expiry): self
    {
        $this->expiry = $expiry;
        return $this;
    }

    public function getExpiry(): DateTimeInterface
    {
        return $this->expiry;
    }

    public function setAccessTokenId(?string $access_token_id): self
    {
        $this->access_token_id = \is_null($access_token_id) ? null : mb_substr($access_token_id, 0, 64);
        return $this;
    }

    public function getAccessTokenId(): ?string
    {
        return $this->access_token_id;
    }

    public function setRevoked(bool $revoked): self
    {
        $this->revoked = $revoked;
        return $this;
    }

    public function getRevoked(): bool
    {
        return $this->revoked;
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

    // @codeCoverageIgnoreEnd
    // }}} Autocode

    /**
     * Get the token's identifier.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->getId();
    }

    /**
     * Set the token's identifier.
     */
    public function setIdentifier($identifier)
    {
        $this->setId($identifier);
    }

    /**
     * Get the token's expiry date time.
     *
     * @return DateTimeImmutable
     */
    public function getExpiryDateTime()
    {
        return $this->getExpiry();
    }

    /**
     * Set the date time when the token expires.
     *
     * @param DateTimeImmutable $dateTime
     */
    public function setExpiryDateTime(\DateTimeImmutable $dateTime)
    {
        $this->setExpiry($dateTime);
    }

    /**
     * Set the access token that the refresh token was associated with.
     */
    public function setAccessToken(AccessTokenEntityInterface $accessToken)
    {
        $this->setAccessTokenId($accessToken->getIdentifier());
    }

    /**
     * Get the access token that the refresh token was originally associated with.
     *
     * @return AccessTokenEntityInterface
     */
    public function getAccessToken()
    {
        return (new Repository\AccessToken)->getAccessTokenEntity($this->getAccessTokenId());
    }

    public static function schemaDef(): array
    {
        return [
            'name'   => 'oauth2_refresh_token',
            'fields' => [
                'id'              => ['type' => 'char',     'length' => 64,        'not null' => true, 'description' => 'identifier for this token'],
                'expiry'          => ['type' => 'datetime', 'not null' => true,    'description' => 'when this token expires'],
                'access_token_id' => ['type' => 'char',     'length' => 64,        'foreign key' => true, 'description' => 'Actor foreign key'],
                'revoked'         => ['type' => 'bool',     'not null' => true,    'foreign key' => true, 'description' => 'Whether this token is revoked'],
                'created'         => ['type' => 'datetime', 'not null' => true,    'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
            ],
            'primary key' => ['id'],
        ];
    }
}
