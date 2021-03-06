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

use App\Util\Exception\TemporaryFileException;

/**
 * Class oriented at providing automatic temporary file handling.
 *
 * @package   GNUsocial
 *
 * @author    Alexei Sorokin <sor.alexei@meowr.ru>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class TemporaryFile extends \SplFileInfo
{
    protected $resource;

    /**
     * @param array $options - ['prefix' => ?string, 'suffix' => ?string, 'mode' => ?string, 'directory' => ?string]
     */
    public function __construct(array $options = [])
    {
        $attempts = 16;
        for ($count = 0; $count < $attempts; ++$count) {
            $filename = uniqid(($options['directory'] ?? (sys_get_temp_dir() . '/')) . ($options['prefix'] ?? 'gs-php')) . ($options['suffix'] ?? '');

            $this->resource = @fopen($filename, $options['mode'] ?? 'w+b');
            if ($this->resource !== false) {
                break;
            }
        }
        if ($count == $attempts) {
            // @codeCoverageIgnoreStart
            $this->cleanup();
            throw new TemporaryFileException('Could not open file: ' . $filename);
            // @codeCoverageIgnoreEnd
        }

        parent::__construct($filename);
    }

    public function __destruct()
    {
        $this->close();
        $this->cleanup();
    }

    public function write($data): int
    {
        if (!is_null($this->resource)) {
            return fwrite($this->resource, $data);
        } else {
            // @codeCoverageIgnoreStart
            return null;
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Closes the file descriptor if opened.
     *
     * @return bool Whether successful
     */
    protected function close(): bool
    {
        $ret = true;
        if (!is_null($this->resource)) {
            $ret = fclose($this->resource);
        }
        if ($ret) {
            $this->resource = null;
        }
        return $ret;
    }

    /**
     * Closes the file descriptor and removes the temporary file.
     *
     * @return void
     */
    protected function cleanup(): void
    {
        $path = $this->getRealPath();
        $this->close();
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Get the file resource.
     *
     * @return resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Release the hold on the temporary file and move it to the desired
     * location, setting file permissions in the process.
     *
     * @param string File destination
     * @param int    New file permissions (in octal mode)
     *
     * @throws TemporaryFileException
     *
     * @return void
     */
    public function commit(string $destpath, int $umode = 0644): void
    {
        $temppath = $this->getRealPath();

        // Might be attempted, and won't end well
        if ($destpath === $temppath) {
            throw new TemporaryFileException('Cannot use self as destination');
        }

        // Memorise if the file was there and see if there is access
        $exists = file_exists($destpath);
        if (!@touch($destpath)) {
            throw new TemporaryFileException(
                'Insufficient permissions for destination: "' . $destpath . '"'
            );
        } elseif (!$exists) {
            // If the file wasn't there, clean it up in case of a later failure
            unlink($destpath);
        }
        if (!$this->close()) {
            // @codeCoverageIgnoreStart
            throw new TemporaryFileException('Could not close the resource');
            // @codeCoverageIgnoreEnd
        }

        rename($temppath, $destpath);
        chmod($destpath, $umode);
    }
}
