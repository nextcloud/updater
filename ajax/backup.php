<?php

/**
 * ownCloud - Updater plugin
 *
 * @author Victor Dubiniuk
 * @copyright 2014 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Updater;

\OCP\JSON::checkAdminUser();
\OCP\JSON::callCheck();

set_time_limit(0);
clearstatcache();

try {
	$backupPath = Backup::create();
	\OCP\JSON::success(array(
		'message' => $backupPath
	));
	
} catch (PermissionException $e){
	//Path is not writable
	\OCP\JSON::error(array(
		'message' => $e->getExtendedMessage()
	));
} catch (FsException $e){
	//Backup failed
} catch (\Exception $e){
	//Something went wrong. We don't know what
	App::log($e->getMessage());
	//$watcher->failure((string) App::$l10n->t('Failed to create backup'));
}