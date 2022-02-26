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

namespace Plugin\OAuth2\Util;

use App\Core\Entity;
use DateTimeImmutable;
use DateTimeInterface;
use Functional as F;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\TokenInterface;
use Plugin\OAuth2\Repository;

/**
 * A type of token, needs to be extended.
 *
 * Since there's no way to specify an abstract method that returns a
 * child of self, need to use method annotations
 *
 * @template T of self
 *
 * @method T setId(string $id)
 * @method T setExpiry(\DateTimeInterface $expiry)
 * @method T setUserId(?int $id)
 * @method T setClientId(string $id)
 * @method T setTokenScopes(string $scopes)
 *
 * From Entity:
 * @method bool hasTokenScopes()
 */
abstract class Token extends Entity implements TokenInterface
{
    abstract public function getId(): string;
    // abstract public function setId(string $id): child;
    abstract public function getExpiry(): DateTimeInterface;
    // abstract public function setExpiry(\DateTimeInterface $expiry): child;
    abstract public function getUserId(): ?int;
    // abstract public function setUserId(?int $id): child;
    abstract public function getClientId(): string;
    // abstract public function setClientId(string $id): child;
    abstract public function getTokenScopes(): string;
    // abstract public function setTokenScopes(string $scopes): child;

    public function getIdentifier(): string
    {
        return $this->getId();
    }

    public function setIdentifier($identifier)
    {
        $this->setId($identifier);
    }

    /**
     * Get the token's expiry date time.
     */
    public function getExpiryDateTime(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($this->getExpiry());
    }

    /**
     * Set the date time when the token expires.
     */
    public function setExpiryDateTime(DateTimeImmutable $dateTime)
    {
        $this->setExpiry($dateTime);
    }

    /**
     * Set the identifier of the user associated with the token.
     *
     * @param null|int|string $identifier The identifier of the user
     */
    public function setUserIdentifier($identifier)
    {
        $this->setUserId($identifier);
    }

    /**
     * Get the token user's identifier.
     */
    public function getUserIdentifier(): int|string|null
    {
        return $this->getUserId();
    }

    /**
     * Get the client that the token was issued to.
     */
    public function getClient(): ClientEntityInterface
    {
        return (new Repository\Client)->getClientEntity($this->getClientId());
    }

    /**
     * Set the client that the token was issued to.
     */
    public function setClient(ClientEntityInterface $client)
    {
        $this->setClientId($client->getIdentifier());
    }

    /**
     * Associate a scope with the token.
     */
    public function addScope(ScopeEntityInterface $scope)
    {
        $scope = $this->hasTokenScopes() ? $this->getTokenScopes() . ' ' . $scope->getIdentifier() : $scope->getIdentifier();
        $this->setTokenScopes($scope);
    }

    /**
     * Return an array of scopes associated with the token.
     *
     * @return ScopeEntityInterface[]
     */
    public function getScopes(): array
    {
        return F\map(
            explode(' ', $this->getTokenScopes()),
            fn (string $scope) => (new Repository\Scope)->getScopeEntityByIdentifier($scope),
        );
    }

    public static function tokenSchema(string $table_name): array
    {
        return [
            'name'   => $table_name,
            'fields' => [
                'id'           => ['type' => 'char', 'length' => 64, 'not null' => true, 'description' => 'identifier for this token'],
                'expiry'       => ['type' => 'datetime', 'not null' => true, 'description' => 'when this token expires'],
                'user_id'      => ['type' => 'int', 'foreign key' => true, 'description' => 'Actor foreign key'],
                'client_id'    => ['type' => 'char', 'length' => 64, 'not null' => true, 'foreign key' => true, 'description' => 'OAuth client foreign key'],
                'token_scopes' => ['type' => 'text', 'not null' => true, 'description' => 'Space separated scopes'],
                'revoked'      => ['type' => 'bool', 'not null' => true, 'foreign key' => true, 'description' => 'Whether this token is revoked'],
                'created'      => ['type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
            ],
            'primary key' => ['id'],
        ];
    }
}
