<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 * @copyright Copyright (c) 2016 Morris Jobke <hey@morrisjobke.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace NC\Updater;

class RecursiveDirectoryIteratorFilter extends \RecursiveFilterIterator {
	private array $excludedPaths;

	public function __construct(
		\RecursiveDirectoryIterator $iterator,
		array $excludedPaths = ['data'],
	) {
		parent::__construct($iterator);
		$this->excludedPaths = array_flip($excludedPaths);
	}

	public function accept(): bool {
		return !isset($this->excludedPaths[$this->current()->getFilename()]);
	}
}
