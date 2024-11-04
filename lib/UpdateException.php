<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace NC\Updater;

class UpdateException extends \Exception {

	/** @param list<string> $data */
	public function __construct(
		protected array $data,
	) {
	}

	/** @return list<string> */
	public function getData(): array {
		return $this->data;
	}
}
