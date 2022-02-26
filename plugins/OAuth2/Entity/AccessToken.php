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

use DateTimeInterface;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use Plugin\OAuth2\Util\Token;

class AccessToken extends Token implements AccessTokenEntityInterface
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private string $id;
    private DateTimeInterface $expiry;
    private ?int $user_id = null;
    private string $client_id;
    private string $token_scopes;
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

    public function setUserId(?int $user_id): self
    {
        $this->user_id = $user_id;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setClientId(string $client_id): self
    {
        $this->client_id = mb_substr($client_id, 0, 64);
        return $this;
    }

    public function getClientId(): string
    {
        return $this->client_id;
    }

    public function setTokenScopes(string $token_scopes): self
    {
        $this->token_scopes = $token_scopes;
        return $this;
    }

    public function getTokenScopes(): string
    {
        return $this->token_scopes;
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

    public CryptKey $private_key;
    public function setPrivateKey(CryptKey $privateKey)
    {
        $this->private_key = $privateKey;
    }

    public function __toString()
    {
        return $this->getId();
    }

    public static function schemaDef(): array
    {
        return parent::tokenSchema('oauth2_access_token');
    }
}
