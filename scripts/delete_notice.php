#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

define('INSTALLDIR', dirname(__DIR__));
define('PUBLICDIR', INSTALLDIR . DIRECTORY_SEPARATOR . 'public');

$shortoptions = 'i::u::y';
$longoptions = array('id=', 'uri=', 'yes');

$helptext = <<<END_OF_HELP
delete_notice.php [options]
deletes a notice (but not related File objects) from the database

  -i --id       Local ID of the notice
  -u --uri      Notice URI
  -y --yes      do not wait for confirmation

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

try {
    if (have_option('i', 'id')) {
        $id = get_option_value('i', 'id');
        $notice = Notice::getByID($id);
    } else if (have_option('u', 'uri')) {
        $uri = get_option_value('u', 'uri');
        $notice = Notice::getByUri($uri);
    } else {
        print $helptext;
        throw new ClientException('You must provide either an ID or a URI.');
    }
} catch (Exception $e) {
    print "ERROR: {$e->getMessage()}\n";
    exit(1);
}

if (!have_option('y', 'yes')) {
    print "About to PERMANENTLY delete notice ".$notice->getID()." by '".$notice->getProfile()->getNickname()."'. Are you sure? [y/N] ";
    $response = fgets(STDIN);
    if (strtolower(trim($response)) != 'y') {
        print "Aborting.\n";
        exit(0);
    }
}

print "Deleting...";
$notice->delete();
print "DONE.\n";
