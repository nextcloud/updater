<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace NC\Updater;

class RecursiveDirectoryIteratorWithoutData extends \RecursiveFilterIterator {
	public function accept(): bool {
		$excludes = [
			'.rnd',
			'.well-known',
			'data',
			'..',
		];

		/** @var \SplFileInfo|false */
		$current = $this->current();
		if (!$current) {
			return false;
		}

		return !(in_array($current->getFilename(), $excludes, true) || $current->isDir());
	}
}
