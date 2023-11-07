<?php

declare(strict_types=1);

require_once __DIR__.'/vendor-bin/coding-standard/vendor/autoload.php';

use Nextcloud\CodingStandard\Config;

$config = new Config();
$config
	->getFinder()
	->ignoreVCSIgnored(true)
	->notPath('vendor')
	->notPath('vendor-bin')
	->in(__DIR__);

return $config;
