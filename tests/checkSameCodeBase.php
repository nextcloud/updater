<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$excludedFiles = [
	'CommandApplication.php',
	'UpdateCommand.php',
];
$failedFiles = [];

$libDir = __DIR__ . '/../lib/';

$indexPhpContent = file_get_contents(__DIR__ . '/../index.php');

function findDiffPos($original, $copy) {
	$lowerLimit = 0;
	$upperLimit = strlen($copy) - 1;

	do {
		$index = $lowerLimit + round(($upperLimit - $lowerLimit) / 2);

		$partOfCopy = substr($copy, 0, $index);
		if (strpos($original, $partOfCopy) === false) {
			$upperLimit = $index;
		} else {
			$lowerLimit = $index;
		}
	} while ($upperLimit - $lowerLimit > 5);

	$matchingSubstring = substr($copy, 0, $lowerLimit);
	if (strlen($matchingSubstring) <= 20) {
		$originalStart = 0;
		$copyStart = 0;
	} else {
		$originalStart = strpos($original, $matchingSubstring) + strlen($matchingSubstring) - 20;
		$copyStart = strlen($matchingSubstring) - 20;
	}
	$stringOriginal = substr($original, $originalStart, 40);
	$stringCopy = substr($copy, $copyStart, 40);

	echo "diff is in here: (between character $lowerLimit and $upperLimit):" . PHP_EOL;
	echo '...' . $stringOriginal . '...' . PHP_EOL;
	echo '...' . $stringCopy . '...' . PHP_EOL;
}

$iterator = new \RecursiveDirectoryIterator(
	$libDir,
	\RecursiveDirectoryIterator::SKIP_DOTS
);
/**
 * @var string $path
 * @var SplFileInfo $fileInfo
 */
foreach ($iterator as $path => $fileInfo) {
	$fileName = explode($libDir, $path)[1];

	if (array_search($fileName, $excludedFiles) !== false) {
		continue;
	}

	$fileContent = file_get_contents($path);

	$fileContent = explode("namespace NC\\Updater;\n", $fileContent, 2)[1];

	$fileContent = trim($fileContent);

	if (strpos($indexPhpContent, $fileContent) === false) {
		$failedFiles[] = $fileName;
		echo "$fileName" . PHP_EOL . PHP_EOL;
		findDiffPos($indexPhpContent, $fileContent);
		echo PHP_EOL;
	}
}

if ($failedFiles !== []) {
	echo "Code is not the same" . PHP_EOL;
	exit(1);
}

echo "Code is the same" . PHP_EOL;
