#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');

$application = new NC\Updater\CommandApplication();
$application->run();
