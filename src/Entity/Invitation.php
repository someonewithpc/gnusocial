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

use App\Core\Entity;
use DateTimeInterface;

/**
 * Entity for user invitations
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
class Invitation extends Entity
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private string $code;
    private int $user_id;
    private string $address;
    private string $address_type;
    private ?int $registered_user_id = null;
    private \DateTimeInterface $created;

    public function setCode(string $code): self
    {
        $this->code = \mb_substr($code, 0, 32);
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setUserId(int $user_id): self
    {
        $this->user_id = $user_id;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setAddress(string $address): self
    {
        $this->address = \mb_substr($address, 0, 191);
        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddressType(string $address_type): self
    {
        $this->address_type = \mb_substr($address_type, 0, 8);
        return $this;
    }

    public function getAddressType(): string
    {
        return $this->address_type;
    }

    public function setRegisteredUserId(?int $registered_user_id): self
    {
        $this->registered_user_id = $registered_user_id;
        return $this;
    }

    public function getRegisteredUserId(): ?int
    {
        return $this->registered_user_id;
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

    // @codeCoverageIgnoreEnd
    // }}} Autocode

    public static function schemaDef(): array
    {
        return [
            'name'   => 'invitation',
            'fields' => [
                'code'               => ['type' => 'varchar', 'length' => 32,        'not null' => true, 'description' => 'random code for an invitation'],
                'user_id'            => ['type' => 'int',     'foreign key' => true, 'target' => 'LocalUser.id', 'multiplicity' => 'many to one', 'name' => 'invitation_user_id_fkey', 'not null' => true, 'description' => 'who sent the invitation'],
                'address'            => ['type' => 'varchar', 'length' => 191,       'not null' => true, 'description' => 'invitation sent to'],
                'address_type'       => ['type' => 'varchar', 'length' => 8,         'not null' => true, 'description' => 'address type ("email", "xmpp", "sms")'],
                'registered_user_id' => ['type' => 'int',     'foreign key' => true, 'target' => 'LocalUser.id', 'multiplicity' => 'one to one', 'name' => 'invitation_registered_user_id_fkey', 'not null' => false, 'description' => 'if the invitation is converted, who the new user is'],
                'created'            => ['type' => 'datetime', 'not null' => true,   'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
            ],
            'primary key' => ['code'],
            'indexes'     => [
                'invitation_address_idx'            => ['address', 'address_type'],
                'invitation_user_id_idx'            => ['user_id'],
                'invitation_registered_user_id_idx' => ['registered_user_id'],
            ],
        ];
    }
}
