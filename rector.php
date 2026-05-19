<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Nextcloud\Rector\Set\NextcloudSets;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/lib',
		__DIR__ . '/tests',
	])
	->withSkipPath(__DIR__ . '/tests/data')
	->withImportNames(importShortClasses: false)
	->withPreparedSets(
		codeQuality: true,
		codingStyle: true,
		deadCode: true,
		earlyReturn: true,
		instanceOf: true,
		privatization: true,
	)
	->withPhpSets(php82: true)
	->withSets([
		NextcloudSets::NEXTCLOUD_34,
	]);
;
