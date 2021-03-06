<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Entity\LogSystem;

use App\Exceptions\LogEntryObsoleteException;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class ExceptionLogEntry extends AbstractLogEntry
{
    protected $typeString = 'exception';

    public function __construct()
    {
        parent::__construct();

        throw new LogEntryObsoleteException();
    }

    /**
     * The class name of the exception that caused this log entry.
     *
     * @return string
     */
    public function getExceptionClass(): string
    {
        return $this->extra['t'] ?? 'Unknown Class';
    }

    /**
     * Returns the file where the exception happened.
     *
     * @return string
     */
    public function getFile(): string
    {
        return $this->extra['f'] ?? 'Unknown file';
    }

    /**
     * Returns the line where the exception happened.
     *
     * @return int
     */
    public function getLine(): int
    {
        return $this->extra['l'] ?? -1;
    }

    /**
     * Return the message of the exception.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->extra['m'] ?? 'Unknown message';
    }
}
