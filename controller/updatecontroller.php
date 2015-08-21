<?php

/**
 * ownCloud - Updater plugin
 *
 * @author Victor Dubiniuk
 * @copyright 2015 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Updater\Controller;

use \OCP\AppFramework\Controller;
use \OCP\IRequest;
use \OCP\IL10N;

use \OCA\Updater\Channel;
use \OCA\Updater\Helper;
use \OCA\Updater\Downloader;
use \OCA\Updater\Updater;
use \OCA\Updater\Backup;
use \OCA\Updater\PermissionException;
use \OCA\Updater\FsException;

class UpdateController extends Controller{
	/** @var IL10N */
	private $l10n;
	
	/** @var Channel */
	private $channel;

	public function __construct($appName, IRequest $request, Channel $channel, IL10N $l10n){
		parent::__construct($appName, $request);
		$this->channel = $channel;
		$this->l10n = $l10n;
		set_time_limit(0);
	}
	
	public function download(){
		$request = file_get_contents('php://input');
		$decodedRequest = json_decode($request, true);

		// Downloading new version
		$packageUrl = isset($decodedRequest['url']) ? $decodedRequest['url'] : '';
		$packageVersion = isset($decodedRequest['version']) ? $decodedRequest['version'] : '';

		try {
			Downloader::getPackage($packageUrl, $packageVersion);
			$result = [
				'status' => 'success'
			];
			
		} catch (\Exception $e) {
			\OC::$server->getLogger()->error($e->getMessage(), ['app' => 'updater']);
			$result = [
				'status' => 'error',
				'message' => $e->getMessage()
			];
		}
		return $result;
	}
	
	public function backup(){
		clearstatcache();
		
		try {
			// Url to download package e.g. http://download.owncloud.org/releases/owncloud-8.0.2.tar.bz2
			$packageUrl = '';

			//Package version e.g. 8.0.2
			$packageVersion = '';
			$updateData = $this->channel->getFeed();

			if (!empty($updateData['version'])){
				$packageVersion = $updateData['version'];
			}
			if (!empty($updateData['url'])){
				$packageUrl = $updateData['url'];
			}
			if (!strlen($packageVersion) || !strlen($packageUrl)) {
				\OC::$server->getLogger()->error('Invalid response from update feed.', ['app' => 'updater']);
				throw new \Exception((string) $this->l10n->t('Version not found'));
			}

			$packageVersionArray = explode('.', $packageVersion);
			Helper::checkVersion($packageVersionArray, $packageVersion);
	
			//Some cleanup first
			Downloader::cleanUp($packageVersion);
			if (!Downloader::isClean($packageVersion)){
				$message = $this->l10n->t('Upgrade is not possible. Your web server does not have permission to remove the following directory:');
				$message .= '<br />' . Downloader::getPackageDir($packageVersion);
				$message .= '<br />' . $this->l10n->t('Update permissions on this directory and its content or remove it manually first.');
				throw new \Exception($message);
			}

			Updater::cleanUp();
			if (!Updater::isClean()){
				$message = $this->l10n->t('Upgrade is not possible. Your web server does not have permission to remove the following directory:');
				$message .= '<br />' . Updater::getTempDir();
				$message .= '<br />' . $this->l10n->t('Update permissions on this directory and its content or remove it manually first.');
				throw new \Exception($message);
			}
	
			$backupPath = Backup::create($packageVersion);
			$result = [
				'status' => 'success',
				'backup' => $backupPath,
				'version' => $packageVersion,
				'url' => $packageUrl
			];
	
		} catch (PermissionException $e){
			//Something is not writable|readable
			$result = [
				'status' => 'error',
				'message' => $e->getExtendedMessage()
			];
		} catch (FsException $e){
			//Backup failed
			\OC::$server->getLogger()->error($e->getMessage(), ['app' => 'updater']);
			$result = [
				'status' => 'error',
				'message' => $e->getMessage()
			];
		} catch (\Exception $e){
			//Something went wrong. We don't know what
			\OC::$server->getLogger()->error($e->getMessage(), ['app' => 'updater']);
			$result = [
				'status' => 'error',
				'message' => $e->getMessage()
			];
		}
		
		return $result;
	}
	
	public function update(){
		$request = file_get_contents('php://input');
		$decodedRequest = json_decode($request, true);
		$packageVersion = isset($decodedRequest['version']) ? $decodedRequest['version'] : '';
		$backupPath = isset($decodedRequest['backupPath']) ? $decodedRequest['backupPath'] : '';

		try {
			Updater::update($packageVersion, $backupPath);
	
			// We are done. Some cleanup
			Downloader::cleanUp($packageVersion);
			Updater::cleanUp();
			$result = [
				'status' => 'success'
			];
		} catch (\Exception $e){
			\OC::$server->getLogger()->error($e->getMessage(), ['app' => 'updater']);
			$result = [
				'status' => 'error',
				'message' => (string) $this->l10n->t('Update failed.') . $e->getMessage()
			];
		}
		return $result;
	}
}
