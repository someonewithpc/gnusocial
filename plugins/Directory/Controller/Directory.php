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

namespace Plugin\Directory\Controller;

use App\Core\DB\DB;
use Symfony\Component\HttpFoundation\Request;

class Directory
{
    /**
     * actors stream
     *
     * @param Request $request
     *
     * @return array template
     */
    public function actors(Request $request)
    {
        return ['_template' => 'directory/actors.html.twig', 'actors' => DB::dql('select g from App\Entity\GSActor g order by g.nickname ASC')];
    }

    /**
     * groups stream
     *
     * @param Request $request
     *
     * @return array template
     */
    public function groups(Request $request)
    {
        return ['_template' => 'directory/groups.html.twig', 'groups' => DB::dql('select g from App\Entity\Group g order by g.nickname ASC')];
    }
}
