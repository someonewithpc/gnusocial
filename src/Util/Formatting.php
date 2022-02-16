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
 * String formatting utilities
 *
 * @package   GNUsocial
 * @category  Util
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Util;

use App\Core\DB\DB;
use App\Core\Event;
use App\Core\Log;
use App\Entity\Actor;
use App\Util\Exception\NicknameException;
use App\Util\Exception\ServerException;
use Component\Circle\Circle;
use Component\Circle\Entity\ActorCircle;
use Component\Group\Entity\LocalGroup;
use Component\Tag\Tag;
use Exception;
use Functional as F;
use InvalidArgumentException;

abstract class Formatting
{
    private static ?\Twig\Environment $twig;
    public static function setTwig(\Twig\Environment $twig)
    {
        self::$twig = $twig;
    }

    public static function twigRenderString(string $template, array $context): string
    {
        return self::$twig->createTemplate($template, null)->render($context);
    }

    public static function twigRenderFile(string $template_path, array $context): string
    {
        return self::$twig->render($template_path, $context);
    }

    /**
     * Normalize path by converting \ to /
     */
    public static function normalizePath(string $path): string
    {
        return preg_replace(',(/|\\\\)+,', '/', $path);
    }

    /**
     * Get plugin name from it's path, or null if not a plugin
     */
    public static function moduleFromPath(string $path): ?string
    {
        foreach (['/plugins/', '/components/'] as $mod_p) {
            $module = mb_strpos($path, $mod_p);
            if ($module === false) {
                continue;
            }
            $cut  = $module + mb_strlen($mod_p);
            $cut2 = mb_strpos($path, '/', $cut);
            if ($cut2) {
                $final = mb_substr($path, $cut, $cut2 - $cut);
            } else {
                // We might be running directly from the plugins dir?
                // If so, there's no place to store locale info.
                $m = 'The GNU social install dir seems to contain a piece named \'plugin\' or \'component\'';
                Log::critical($m);
                throw new ServerException($m);
            }
            return $final;
        }
        return null;
    }

    /**
     * Check whether $haystack starts with $needle
     *
     * @param array|string $haystack if array, check that all strings start with $needle (see below)
     * @param array|string $needle   if array, check that one of the $needles is found
     */
    public static function startsWith(array|string $haystack, array|string $needle): bool
    {
        if (\is_string($haystack)) {
            return F\some(\is_array($needle) ? $needle : [$needle], fn ($n) => str_starts_with($haystack, $n));
        } else {
            return F\every($haystack, fn ($haystack) => self::startsWith($haystack, $needle));
        }
    }
    /**
     * Check whether $haystack ends with $needle
     *
     * @param array|string $haystack if array, check that all strings end with $needle (see below)
     * @param array|string $needle   if array, check that one of the $needles is found
     */
    public static function endsWith(array|string $haystack, array|string $needle): bool
    {
        if (\is_string($haystack)) {
            return F\some(\is_array($needle) ? $needle : [$needle], fn ($n) => str_ends_with($haystack, $n));
        } else {
            return F\every($haystack, fn ($haystack) => self::endsWith($haystack, $needle));
        }
    }

    /**
     * If $haystack starts with $needle, remove it from the beginning
     */
    public static function removePrefix(string $haystack, string $needle)
    {
        return self::startsWith($haystack, $needle) ? mb_substr($haystack, mb_strlen($needle)) : $haystack;
    }

    /**
     * If $haystack ends with $needle, remove it from the end
     */
    public static function removeSuffix(string $haystack, string $needle): string
    {
        return self::endsWith($haystack, $needle) && !empty($needle) ? mb_substr($haystack, 0, -mb_strlen($needle)) : $haystack;
    }

    public static function camelCaseToSnakeCase(string $str): string
    {
        return mb_strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
    }

    public static function snakeCaseToCamelCase(string $str): string
    {
        return implode('', F\map(preg_split('/[\b_]/', $str), F\ary('ucfirst', 1)));
    }

    /**
     * Indent $in, a string or array, $level levels
     *
     * @param array|string $in
     * @param int          $level How many levels of indentation
     * @param int          $count How many spaces per indentation
     */
    public static function indent($in, int $level = 1, int $count = 2): string
    {
        if (\is_string($in)) {
            return self::indent(explode("\n", $in), $level, $count);
        } elseif (\is_array($in)) {
            $indent = str_repeat(' ', $count * $level);
            return implode("\n", F\map(
                F\select(
                    $in,
                    F\ary(fn ($s) => $s != '', 1),
                ),
                fn ($val) => F\concat($indent . $val),
            ));
        }
        throw new InvalidArgumentException('Formatting::indent\'s first parameter must be either an array or a string. Input was: ' . $in);
    }

    public const SPLIT_BY_SPACE = ' ';
    public const JOIN_BY_SPACE  = ' ';
    public const SPLIT_BY_COMMA = ', ';
    public const JOIN_BY_COMMA  = ', ';
    public const SPLIT_BY_BOTH  = '/[, ]/';

    /**
     * Convert scalars, objects implementing __toString or arrays to strings
     */
    public static function toString($value, string $join_type = self::JOIN_BY_COMMA): string
    {
        if (!\in_array($join_type, [static::JOIN_BY_SPACE, static::JOIN_BY_COMMA])) {
            throw new Exception('Formatting::toString received invalid join option');
        } else {
            if (!\is_array($value)) {
                return (string) $value;
            } else {
                return implode($join_type, $value);
            }
        }
    }

    /**
     * Convert a user supplied string to array and return whether the conversion was successful
     */
    public static function toArray(string $input, &$output, string $split_type = self::SPLIT_BY_COMMA): bool
    {
        if (!\in_array($split_type, [static::SPLIT_BY_SPACE, static::SPLIT_BY_COMMA, static::SPLIT_BY_BOTH])) {
            throw new Exception('Formatting::toArray received invalid split option');
        }
        if ($input == '') {
            $output = [];
            return true;
        }
        $matches = [];
        if (preg_match('/^ *\[?([^,]+(, ?[^,]+)*)\]? *$/', $input, $matches)) {
            switch ($split_type) {
                case self::SPLIT_BY_BOTH:
                    $arr = preg_split($split_type, $matches[1], 0, \PREG_SPLIT_NO_EMPTY);
                    break;
                case self::SPLIT_BY_COMMA:
                    $arr = preg_split('/, ?/', $matches[1]);
                    break;
                default:
                    $arr = explode($split_type[0], $matches[1]);
            }
            $output = str_replace([' \'', '\'', ' "', '"'], '', $arr);
            $output = F\map($output, F\ary('trim', 1));
            return true;
        }
        return false;
    }

    /**
     * Render a plain text note content into HTML, extracting links and tags
     */
    public static function renderPlainText(string $text, ?string $language = null): string
    {
        $text = self::quoteAndRemoveControlCodes($text);

        // Split \n\n into paragraphs, process each paragraph and merge
        return implode("\n", F\map(explode("\n\n", $text), function (string $paragraph) use ($language) {
            $paragraph = nl2br($paragraph, use_xhtml: false);
            Event::handle('RenderPlainTextNoteContent', [&$paragraph, $language]);

            return HTML::html(['p' => [$paragraph]], options: ['raw' => true, 'indent' => false]);
        }));
    }

    /**
     * Quote HTML special chars and strip Unicode text
     * formatting/direction codes. This is can be pretty dangerous for
     * visualisation of text or be used for mischief
     */
    public static function quoteAndRemoveControlCodes(string $text): string
    {
        // Quote special chars
        $text = htmlspecialchars($text, flags: \ENT_QUOTES | \ENT_SUBSTITUTE, double_encode: false);
        // Normalize newlines to strictly \n and remove ASCII control codes
        return preg_replace(['/[\x{0}-\x{8}\x{b}-\x{c}\x{e}-\x{19}\x{200b}-\x{200f}\x{202a}-\x{202e}]/u', '/\R/u'], ['', "\n"], $text);
    }

    /**
     * Convert $str to it's closest ASCII representation
     */
    public static function slugify(string $str, int $length = 64): string
    {
        // php-intl is highly recommended...
        if (!\function_exists('transliterator_transliterate')) {
            $str = preg_replace('/[^\pL\pN]/u', '', $str);
            $str = mb_convert_case($str, \MB_CASE_LOWER, 'UTF-8');
            return mb_substr($str, 0, $length);
        }
        $str = transliterator_transliterate('Any-Latin;'          // any charset to latin compatible
            . 'NFD;'                        // decompose
            . '[:Nonspacing Mark:] Remove;' // remove nonspacing marks (accents etc.)
            . 'NFC;'                        // composite again
            . '[:Punctuation:] Remove;'     // remove punctuation (.,¿? etc.)
            . 'Lower();'                    // turn into lowercase
            . 'Latin-ASCII;',               // get ASCII equivalents (ð to d for example)
            $str, );
        return mb_substr(preg_replace('/[^\pL\pN]/u', '', $str), 0, $length);
    }

    /**
     * Find @-mentions in the given text, using the given notice object as context.
     * References will be resolved with common_relative_profile() against the user
     * who posted the notice.
     *
     * Note the return data format is internal, to be used for building links and
     * such. Should not be used directly; rather, call common_linkify_mentions().
     *
     * @param Actor $actor the Actor that is sending the current text
     */
    public static function findMentions(string $text, Actor $actor): array
    {
        $mentions = [];
        // XXX: We remove <span> because when content is in html the tag comes as #<span>hashtag</span>
        $text = str_replace('<span>', '', $text);
        if (Event::handle('StartFindMentions', [$actor, $text, &$mentions])) {

            // @person mentions
            $person_matches = self::findMentionsRaw($text, '@');
            foreach ($person_matches as $match) {
                try {
                    $nickname = Nickname::normalize($match[0], check_already_used: false, check_is_allowed: false);
                } catch (NicknameException) {
                    // Bogus match? Drop it.
                    continue;
                }

                $mentioned = $actor->findRelativeActor($nickname);

                if ($mentioned instanceof Actor) {
                    $url = $mentioned->getUri();    // prefer the URI as URL, if it is one.
                    if (!Common::isValidHttpUrl($url)) {
                        $url = $mentioned->getUrl();
                    }

                    $mention = [
                        'mentioned' => [$mentioned],
                        'type'      => 'mention',
                        'text'      => $match[0],
                        'position'  => $match[1],
                        'length'    => mb_strlen($match[0]),
                        'title'     => $mentioned->getFullname() ?? $mentioned->getNickname(),
                        'url'       => $url,
                    ];

                    $mentions[] = $mention;
                }
            }

            // @#circle/self-tag => mention of all subscribed circles tagged 'tag'
            $tag_matches = [];
            preg_match_all(
                Circle::TAG_CIRCLE_REGEX,
                $text,
                $tag_matches,
                \PREG_OFFSET_CAPTURE,
            );
            foreach ($tag_matches[1] as $tag_match) {
                $tag = Tag::extract($tag_match[0]);
                if (!Tag::validate($tag)) {
                    continue; // Ignore invalid tags
                }
                $ac = DB::findOneBy(ActorCircle::class, [
                    'tag'    => $tag, // Notify circle of name tag WHERE
                    'tagger' => $actor->getID(), // Circle was created by Actor
                ], return_null: true);

                if (\is_null($ac) || $ac->getPrivate()) {
                    continue;
                }

                $mentions[] = [
                    'mentioned' => $ac->getSubscribedActors(),
                    'type'      => 'list',
                    'text'      => $tag_match[0],
                    'position'  => $tag_match[1],
                    'length'    => mb_strlen($tag_match[0]),
                    'url'       => $ac->getUrl(),
                ];
            }

            // !group/!org mentions
            $group_matches = self::findMentionsRaw($text, '!');
            foreach ($group_matches as $match) {
                try {
                    $nickname = Nickname::normalize($match[0], check_already_used: false, check_is_allowed: false);
                } catch (NicknameException) {
                    // Bogus match? Drop it.
                    continue;
                }

                $mentioned = LocalGroup::getActorByNickname($nickname);

                if ($mentioned instanceof Actor) {
                    $url = $mentioned->getUri();    // prefer the URI as URL, if it is one.
                    if (!Common::isValidHttpUrl($url)) {
                        $url = $mentioned->getUrl();
                    }

                    $mentions[] = [
                        'mentioned' => [$mentioned],
                        'type'      => 'group',
                        'text'      => $match[0],
                        'position'  => $match[1],
                        'length'    => mb_strlen($match[0]),
                        'title'     => $mentioned->getFullname() ?? $mentioned->getNickname(),
                        'url'       => $url,
                    ];
                }
            }

            Event::handle('EndFindMentions', [$actor, $text, &$mentions]);
        }
        return $mentions;
    }

    /**
     * Does the actual regex pulls to find @-mentions in text.
     * Should generally not be called directly; for use in common_find_mentions.
     *
     * @param string $preMention Character(s) that signals a mention ('@', '!'...)
     *
     * @return array of PCRE match arrays
     */
    private static function findMentionsRaw(string $text, string $preMention = '@'): array
    {
        $atmatches = [];
        // the regexp's "(?!\@)" makes sure it doesn't matches the single "@remote" in "@remote@server.com"
        preg_match_all(
            '/' . Nickname::BEFORE_MENTIONS . preg_quote($preMention, '/') . '(' . Nickname::DISPLAY_FMT . ')\b(?!\@)/',
            $text,
            $atmatches,
            \PREG_OFFSET_CAPTURE,
        );

        return $atmatches[1];
    }

    /**
     * Finds @-mentions within the partially-rendered text section and
     * turns them into live links.
     *
     * Should generally not be called except from common_render_content().
     *
     * @param string $text   partially-rendered HTML
     * @param Actor  $author the Actor that is composing the current notice
     *
     * @return array [partially-rendered HTML, array of mentions]
     */
    public static function linkifyMentions(string $text, Actor $author, string $locale): array
    {
        $mentions = self::findMentions($text, $author);

        // We need to go through in reverse order by position,
        // so our positions stay valid despite our fudging with the
        // string!

        $points = [];

        foreach ($mentions as $mention) {
            $points[$mention['position']] = $mention;
        }

        krsort($points);

        foreach ($points as $position => $mention) {
            $linkText = self::linkifyMentionArray($mention);

            $text = substr_replace($text, $linkText, $position, $mention['length']);
        }

        return [$text, $mentions];
    }

    public static function linkifyMentionArray(array $mention)
    {
        $output = null;

        if (Event::handle('StartLinkifyMention', [$mention, &$output]) === Event::next) {
            $attrs = [
                'href'  => $mention['url'],
                'class' => 'h-card u-url p-nickname ' . $mention['type'], // https://microformats.org/wiki/h-card
            ];

            if (!empty($mention['title'])) {
                $attrs['title'] = $mention['title'];
            }

            $output = HTML::html(['span' => ['attrs' => ['class' => 'h-card'],
                HTML::html(['a' => ['attrs' => $attrs, $mention['title'] ?? $mention['text']]], options: ['indent' => false]),
            ]], options: ['indent' => false, 'raw' => true]);

            Event::handle('EndLinkifyMention', [$mention, &$output]);
        }

        return $output;
    }

    /**
     * Split words by `-`, `_` or lower to upper case transitions
     */
    public static function splitWords(string $words): array
    {
        return preg_split('/-|_|(?<=\p{Ll})(?=\p{Lu})/u', $words);
    }
}
