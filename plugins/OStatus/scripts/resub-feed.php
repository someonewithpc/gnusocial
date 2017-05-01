#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));

$longoptions = array('unsub');
$shortoptions = 'u';

$helptext = <<<END_OF_HELP
resub-feed.php [options] http://example.com/atom-feed-url
Reinitialize the WebSub subscription for the given feed. This may help get
things restarted if we and the hub have gotten our states out of sync.

Options:

   -u --unsub  Unsubscribe instead of subscribing.

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$validate = new Validate();

if (empty($args[0]) || !$validate->uri($args[0])) {
    print "$helptext";
    exit(1);
}

$feedurl = $args[0];


$sub = FeedSub::getKV('uri', $feedurl);
if (!$sub) {
    print "Feed $feedurl is not subscribed.\n";
    exit(1);
}

print "Old state:\n";
showSub($sub);

try {
    echo "\n";
    if (have_option('u') || have_option('--unsub')) {
        echo "Pinging hub {$sub->huburi} with unsubscription for {$sub->uri}\n";
        $sub->unsubscribe();
    } else {
        echo "Pinging hub {$sub->huburi} with new subscription for {$sub->uri}\n";
        $sub->subscribe();
    }
    echo "ok\n";
} catch (Exception $e) {
    echo 'Could not confirm. '.get_class($e).': '.$e->getMessage()."\n";
}

$sub2 = FeedSub::getKV('uri', $feedurl);

print "\n";
print "New state:\n";
showSub($sub2);

function showSub($sub)
{
    print "  Subscription state: $sub->sub_state\n";
    print "  Verify token: $sub->verify_token\n";
    print "  Signature secret: $sub->secret\n";
    print "  Sub start date: $sub->sub_start\n";
    print "  Sub end date: $sub->sub_end\n";
    print "  Sub lease remaining: {$sub->getLeaseRemaining()}\n";
    print "  Record created: $sub->created\n";
    print "  Record modified: $sub->modified\n";
}
