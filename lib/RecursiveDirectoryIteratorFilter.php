<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
