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
\define('INSTALLDIR', \dirname(__DIR__));
\define('PUBLICDIR', INSTALLDIR . \DIRECTORY_SEPARATOR . 'public');

$shortoptions = 'y';
$longoptions  = ['yes'];

$helptext = <<<'END_OF_HELP'
    clean_file_table.php [options]
    Deletes all local files where the filename cannot be found in the filesystem.

      -y --yes      do not wait for confirmation

    Will print '.' for each file, except for deleted ones which are marked as 'x'.

    END_OF_HELP;

require_once INSTALLDIR . '/scripts/commandline.inc';

if (!have_option('y', 'yes')) {
    echo 'About to delete local file entries where the file cannot be found. Are you sure? [y/N] ';
    $response = fgets(\STDIN);
    if (mb_strtolower(trim($response)) != 'y') {
        echo "Aborting.\n";
        exit(0);
    }
}

echo 'Deleting';
$file = new File();
// Select local files
$file->whereAdd('filename IS NOT NULL');
$file->whereAdd('url IS NULL', 'AND');
if ($file->find()) {
    while ($file->fetch()) {
        try {
            $file->getPath();
            echo '.';
        } catch (FileNotFoundException $e) {
            $file->delete();
            echo 'x';
        }
    }
}
echo "\nDONE.\n";
