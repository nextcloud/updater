<?php

/**
 * ownCloud - Updater plugin
 *
 * @author Victor Dubiniuk
 * @copyright 2013 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Updater;

\OCP\JSON::checkAdminUser();
\OCP\JSON::callCheck();

try {
	$list = Helper::scandir(App::getBackupBase());
} catch (\Exception $e) {
	$list = array();
}
clearstatcache();
$result = array();
foreach ($list as $item){
	if ($item=='.' || $item=='..'){
		continue;
	}
	$result[] = array(
		'title' => $item,
		'date' => date ("F d Y H:i:s", filectime(App::getBackupBase() . '/' . $item)),
		'size' => human_filesize(filesize(App::getBackupBase() . '/' . $item))
	);
}

\OCP\JSON::success(array('data' => $result));

/* adapted from http://php.net/manual/de/function.filesize.php */
function human_filesize($bytes, $decimals = 2) {
  $sz = 'BKMGTP';
  $factor = floor((strlen($bytes) - 1) / 3);
  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}
