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

set_time_limit(0);

$updateEventSource = new \OC_EventSource();
$watcher = new UpdateWatcher($updateEventSource);
\OCP\Util::connectHook('update', 'success', $watcher, 'success');
\OCP\Util::connectHook('update', 'failure', $watcher, 'failure');

// Download package
// Url to download package e.g. http://download.owncloud.org/releases/owncloud-4.0.5.tar.bz2
$packageUrl = '';

//Package version e.g. 4.0.4
$packageVersion = '';
$updateData = \OC_Updater::check();

if (isset($updateData['version'])){
	$packageVersion = $updateData['version'];
}
if (isset($updateData['url'])){
	$packageUrl = $updateData['url'];
}
if (!strlen($packageVersion) || !strlen($packageUrl)) {
	App::log('Invalid response from update feed.');
	$watcher->failure((string) App::$l10n->t('Version not found'));
}


//Some cleanup first
Downloader::cleanUp($packageVersion);
if (!Downloader::isClean($packageVersion)){
	$message = App::$l10n->t('Upgrade is not possible. Your webserver has not enough permissions to remove the following directory:');
	$message .= '<br />' . Downloader::getPackageDir($packageVersion);
	$message .= '<br />' . App::$l10n->t('Update permissions on this directory and its content or remove it manually first.');
	$watcher->failure($message);
}

Updater::cleanUp();
if (!Updater::isClean()){
	$message = App::$l10n->t('Upgrade is not possible. Your webserver has not enough permissions to remove the following directory:');
	$message .= '<br />' . Updater::getTempDir();
	$message .= '<br />' . App::$l10n->t('Update permissions on this directory and its content or remove it manually first.');
	$watcher->failure($message);
}

// Downloading new version
try {
	$watcher->success((string)  App::$l10n->t('Downloading package...'));
	Downloader::getPackage($packageUrl, $packageVersion);
} catch (\Exception $e) {
	App::log($e->getMessage());
	$watcher->failure((string) App::$l10n->t('Unable to fetch package'));
}

try {
	$watcher->success((string) App::$l10n->t('Moving files...'));
	Updater::update($packageVersion, $backupPath);
	
	// We are done. Some cleanup
	Downloader::cleanUp($packageVersion);
	Updater::cleanUp();
	$watcher->success((string) App::$l10n->t('All done. Click to the link below to start database upgrade.'));
	$watcher->done();
} catch (\Exception $e){
	App::log($e->getMessage());
	$watcher->failure((string) App::$l10n->t('Update failed') . '<br /><br />' . $e->getMessage());
}

class UpdateWatcher {

	/**
	 * @var \OC_EventSource $eventSource;
	 */
	private $eventSource;

	public function __construct($eventSource) {
		$this->eventSource = $eventSource;
	}
	
	public function error($message) {
		\OC_Util::obEnd();
		$this->eventSource->send('error', $message);
		ob_start();
	}

	public function success($message) {
		\OC_Util::obEnd();
		$this->eventSource->send('success', $message);
		ob_start();
	}

	public function failure($message) {
		\OC_Util::obEnd();
		$this->eventSource->send('failure', $message);
		$this->eventSource->close();
		die();
	}

	public function done() {
		\OC_Util::obEnd();
		$this->eventSource->send('done', '');
		$this->eventSource->close();
	}
}
