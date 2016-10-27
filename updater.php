#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use NC\Updater\UpdateCommand;

$application = new Application();
$application->add(new UpdateCommand());
$application->setDefaultCommand('update');
$application->run();