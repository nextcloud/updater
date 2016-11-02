#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use NC\Updater\UpdateCommand;

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$application = new Application();
$application->add(new UpdateCommand());
$application->setDefaultCommand('update');
$application->run();