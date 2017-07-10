<?php
/*
 * StatusNet - the distributed open-source microblogging tool
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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Send a raw WebSub push atom update from our internal hub.
 * @package Hub
 * @author Brion Vibber <brion@status.net>
 */
class HubOutQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'hubout';
    }

    function handle($data)
    {
        assert(array_key_exists('atom', $data));
        assert(is_string($data['atom']));
        $atom = $data['atom'];

        assert(array_key_exists('retries', $data));
        $retries = intval($data['retries']);

        if (array_key_exists('topic', $data) && array_key_exists('callback', $data)) {
            assert(is_string($data['topic']));
            assert(is_string($data['callback']));

            $sub = HubSub::getByHashkey($data['topic'], $data['callback']);
        } elseif (array_key_exists('sub', $data)) {
            // queue behaviour changed 2017-07-09 to store topic/callback instead of sub object
            common_debug('Legacy behaviour of storing HubSub objects found, this should go away when all objects are handled...');
            $sub = $data['sub'];
        } else {
            throw new ServerException('No HubSub object available with queue item data.');
        }
        assert($sub instanceof HubSub);

        try {
            $sub->push($atom);
        } catch (AlreadyFulfilledException $e) {
            common_log(LOG_INFO, "Failed WebSub push to $sub->callback for $sub->topic (".get_class($e)."): " . $e->getMessage());
        } catch (Exception $e) {
            $retries--;
            $msg = "Failed WebSub push to $sub->callback for $sub->topic (".get_class($e)."): " . $e->getMessage();
            if ($retries > 0) {
                common_log(LOG_INFO, "$msg; scheduling for $retries more tries");

                // @fixme when we have infrastructure to schedule a retry
                // after a delay, use it.
                $sub->distribute($atom, $retries);
            } else {
                common_log(LOG_ERR, "$msg; discarding");
            }
        }

        return true;
    }
}
