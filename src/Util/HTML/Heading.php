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

namespace App\Util\HTML;

use function App\Core\I18n\_m;
use App\Util\HTML;
use Twig\Markup;

class Heading extends HTML
{
    private string $heading_type = 'h1';
    private string $heading_text;
    private array $classes = [];

    public function __construct(int $level, array $classes, string $text)
    {
        $this->setHeadingText($text);
        foreach ($classes as $class) {
            $this->addClass($class);
        }

        if ($level >= 1 && $level <= 6) {
            $this->heading_type = 'h' . $level;
        }
    }

    public function addClass(string $c): self
    {
        if (!\in_array($c, $this->classes, true)) {
            $this->classes[] = $c;
        }
        return $this;
    }

    public function getHtml(): Markup
    {
        return new Markup($this->__toString(), 'UTF-8');
    }

    public function __toString()
    {
        return $this::html([$this->getHeadingType() => ['attrs' => ['class' => !empty($this->getClasses()) ? implode(' ', $this->getClasses()) : ''], _m($this->getHeadingText())]]);
    }

    public function getHeadingType(): string
    {
        return $this->heading_type;
    }

    public function setHeadingType(string $value): static
    {
        $this->heading_type = $value;
        return $this;
    }

    public function getClasses(): array
    {
        return $this->classes;
    }

    public function getHeadingText(): string
    {
        return $this->heading_text;
    }

    public function setHeadingText(string $value): static
    {
        $this->heading_text = $value;
        return $this;
    }
}
