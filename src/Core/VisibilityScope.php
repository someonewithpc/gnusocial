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

namespace App\Core;

use App\Util\Bitmap;

class VisibilityScope extends Bitmap
{
    public const PUBLIC    = 1;
    public const SITE      = 2;
    public const ADDRESSEE = 4;
    public const GROUP     = 8;
    public const FOLLOWER  = 16;
    public const MESSAGE   = 32;

    public static int $instance_scope = self::PUBLIC | self::SITE;
}
