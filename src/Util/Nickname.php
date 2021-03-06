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

namespace App\Util;

use App\Core\DB\DB;
use App\Entity\GSActor;
use App\Util\Exception\NicknameBlacklistedException;
use App\Util\Exception\NicknameEmptyException;
use App\Util\Exception\NicknameException;
use App\Util\Exception\NicknameInvalidException;
use App\Util\Exception\NicknamePathCollisionException;
use App\Util\Exception\NicknameReservedException;
use App\Util\Exception\NicknameTakenException;
use App\Util\Exception\NicknameTooLongException;
use App\Util\Exception\NicknameTooShortException;
use Functional as F;
use Normalizer;

/**
 * Nickname validation
 *
 * @category  Validation
 * @package   GNUsocial
 *
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet Inc.
 * @author    Brion Vibber <brion@pobox.com>
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @author    Nym Coy <nymcoy@gmail.com>
 * @copyright 2009-2014 Free Software Foundation, Inc http://www.fsf.org
 * @auuthor   Daniel Supernault <danielsupernault@gmail.com>
 * @auuthor   Diogo Cordeiro <diogo@fc.up.pt>
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2018-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Nickname
{
    /**
     * Regex fragment for pulling a formated nickname *OR* ID number.
     * Suitable for router def of 'id' parameters on API actions.
     *
     * Not guaranteed to be valid after normalization; run the string through
     * Nickname::normalize() to get the canonical form, or Nickname::isValid()
     * if you just need to check if it's properly formatted.
     *
     * This, DISPLAY_FMT, and CANONICAL_FMT should not be enclosed in []s.
     *
     * @fixme would prefer to define in reference to the other constants
     */
    const INPUT_FMT = '(?:[0-9]+|[0-9a-zA-Z_]{1,64})';

    /**
     * Regex fragment for acceptable user-formatted variant of a nickname.
     *
     * This includes some chars such as underscore which will be removed
     * from the normalized canonical form, but still must fit within
     * field length limits.
     *
     * Not guaranteed to be valid after normalization; run the string through
     * Nickname::normalize() to get the canonical form, or Nickname::isValid()
     * if you just need to check if it's properly formatted.
     *
     * This, INPUT_FMT and CANONICAL_FMT should not be enclosed in []s.
     */
    const DISPLAY_FMT = '[0-9a-zA-Z_]{1,64}';

    /**
     * Simplified regex fragment for acceptable full WebFinger ID of a user
     *
     * We could probably use an email regex here, but mainly we are interested
     * in matching it in our URLs, like https://social.example/user@example.com
     */
    const WEBFINGER_FMT = '(?:\w+[\w\-\_\.]*)?\w+\@' . URL_REGEX_DOMAIN_NAME;

    /**
     * Regex fragment for checking a canonical nickname.
     *
     * Any non-matching string is not a valid canonical/normalized nickname.
     * Matching strings are valid and canonical form, but may still be
     * unavailable for registration due to blacklisting et.
     *
     * Only the canonical forms should be stored as keys in the database;
     * there are multiple possible denormalized forms for each valid
     * canonical-form name.
     *
     * This, INPUT_FMT and DISPLAY_FMT should not be enclosed in []s.
     */
    const CANONICAL_FMT = '[0-9a-z]{1,64}';

    /**
     * Maximum number of characters in a canonical-form nickname. Changes must validate regexs
     */
    const MAX_LEN = 64;

    /**
     * Regex with non-capturing group that matches whitespace and some
     * characters which are allowed right before an @ or ! when mentioning
     * other users. Like: 'This goes out to:@mmn (@chimo too) (!awwyiss).'
     *
     * FIXME: Make this so you can have multiple whitespace but not multiple
     * parenthesis or something. '(((@n_n@)))' might as well be a smiley.
     */
    const BEFORE_MENTIONS = '(?:^|[\s\.\,\:\;\[\(]+)';

    /**
     * Normalize an input $nickname, and normalize it to its canonical form.
     * The canonical form will be returned, or an exception thrown if invalid.
     *
     * @throws NicknameException              (base class)
     * @throws NicknameBlacklistedException
     * @throws NicknameEmptyException
     * @throws NicknameInvalidException
     * @throws NicknamePathCollisionException
     * @throws NicknameTakenException
     * @throws NicknameTooLongException
     * @throws NicknameTooShortException
     */
    public static function normalize(string $nickname, bool $check_already_used = true, bool $checking_reserved = false): string
    {
        if (mb_strlen($nickname) > self::MAX_LEN) {
            // Display forms must also fit!
            throw new NicknameTooLongException();
        }

        $nickname = trim($nickname);
        $nickname = str_replace('_', '', $nickname);
        $nickname = mb_strtolower($nickname);
        $nickname = Normalizer::normalize($nickname, Normalizer::FORM_C);

        if (!$checking_reserved) {
            if (mb_strlen($nickname) < 1) {
                throw new NicknameEmptyException();
            } elseif (mb_strlen($nickname) < Common::config('nickname', 'min_length')) {
                throw new NicknameTooShortException();
            } elseif (!self::isCanonical($nickname) && !filter_var($nickname, FILTER_VALIDATE_EMAIL)) {
                throw new NicknameInvalidException();
            } elseif (self::isReserved($nickname) || Common::isSystemPath($nickname)) {
                throw new NicknameReservedException();
            } elseif ($check_already_used) {
                $actor = self::checkTaken($nickname);
                if ($actor instanceof GSActor) {
                    throw new NicknameTakenException($actor);
                }
            }
        }

        return $nickname;
    }

    /**
     * Nice simple check of whether the given string is a valid input nickname,
     * which can be normalized into an internally canonical form.
     *
     * Note that valid nicknames may be in use or reserved.
     *
     * @return bool True if nickname is valid. False if invalid (or taken if $check_already_used == true).
     */
    public static function isValid(string $nickname, bool $check_already_used = true): bool
    {
        try {
            self::normalize($nickname, $check_already_used);
        } catch (NicknameException $e) {
            return false;
        }

        return true;
    }

    /**
     * Is the given string a valid canonical nickname form?
     */
    public static function isCanonical(string $nickname): bool
    {
        return preg_match('/^(?:' . self::CANONICAL_FMT . ')$/', $nickname);
    }

    /**
     * Is the given string in our nickname blacklist?
     */
    public static function isReserved(string $nickname): bool
    {
        $reserved = Common::config('nickname', 'reserved');
        if (empty($reserved)) {
            return false;
        }
        return in_array($nickname, array_merge($reserved, F\map($reserved, function ($n) {
            return self::normalize($n, check_already_used: false, checking_reserved: true);
        })));
    }

    /**
     * Is the nickname already in use locally? Checks the User table.
     *
     * @return null|GSActor Returns GSActor if nickname found
     */
    public static function checkTaken(string $nickname): ?GSActor
    {
        foreach (['local_user' => 'id', 'local_group' => 'group_id'] as $table => $id_field) {
            $ret = DB::dql("select a from gsactor a join {$table} t with a.id = t.{$id_field} " .
                           'where a.normalized_nickname = :nick', ['nick' => self::normalize($nickname, check_already_used: false)]);

            if (!empty($ret)) {
                return $ret[0];
            }
        }
        return null;
    }
}
