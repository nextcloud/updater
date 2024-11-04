#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');

$application = new NC\Updater\CommandApplication();
$application->run();
