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

namespace App\Tests\Util;

use Jchook\AssertThrows\AssertThrows;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use TypeError;

class HTMLTest extends WebTestCase
{
    use AssertThrows;

    public function testHTML()
    {
        static::assertSame('', HTML\HTML::html(''));
        static::assertSame('<a></a>', HTML\HTML::html(['a' => '']));
        static::assertSame("<div>\n  <p></p>\n</div>", HTML\HTML::html(['div' => ['p' => '']]));
        static::assertSame("<div>\n  <div>\n    <p></p>\n  </div>\n</div>", HTML\HTML::html(['div' => ['div' => ['p' => '']]]));
        static::assertSame("<div>\n  <div>\n    <div>\n      <p></p>\n    </div>\n  </div>\n</div>", HTML\HTML::html(['div' => ['div' => ['div' => ['p' => '']]]]));
        static::assertSame('<a href="test"><p></p></a>', HTML\HTML::html(['a' => ['attrs' => ['href' => 'test'], 'p' => '']]));
        static::assertSame('<a><p>foo</p><br></a>', HTML\HTML::html(['a' => ['p' => 'foo', 'br' => 'empty']]));
        static::assertSame("<div>\n  <a><p>foo</p><br></a>\n</div>", HTML\HTML::html(['div' => ['a' => ['p' => 'foo', 'br' => 'empty']]]));
        static::assertSame('<div><a><p>foo</p><br></a></div>', HTML\HTML::html(['div' => ['a' => ['p' => 'foo', 'br' => 'empty']]], options: ['indent' => false]));
        static::assertThrows(TypeError::class, fn () => HTML\HTML::html(1));
        static::assertSame('<a href="test">foo</a>', HTML\HTML::tag('a', ['href' => 'test'], content: 'foo', options: ['empty' => false]));
        static::assertSame('<br>', HTML\HTML::tag('br', attrs: null, content: null, options: ['empty' => true]));
    }
}
