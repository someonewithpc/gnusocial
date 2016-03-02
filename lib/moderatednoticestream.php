<?php

if (!defined('GNUSOCIAL')) { exit(1); }

/**
 * Moderated notice stream, will take into account the scoping of
 * notices as well as whether the profile is moderated somehow,
 * such as by sandboxing or silencing.
 *
 * Inherits $this->scoped from ScopingNoticeStream as the Profile
 * this stream is meant for. Can be null in case we're not logged in.
 *
 * @category  Stream
 * @package   GNUsocial
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2016 Free Software Foundation, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      https://gnu.io/social
 */

class ModeratedNoticeStream extends ScopingNoticeStream
{
    protected function filter(Notice $notice)
    {
        if (!parent::filter($notice)) {
            return false;
        }

        // If the notice author is sandboxed
        if ($notice->getProfile()->isSandboxed()) {
            // and we're either not logged in OR we aren't some kind of privileged user that can see spam etc.
            if (!$this->scoped instanceof Profile || !$this->scoped->hasRight(Right::REVIEWSPAM)) {
                return false;
            }
        }

        return true;
    }
}
