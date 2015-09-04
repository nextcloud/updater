<?php

/**
 * ownCloud - Updater plugin
 *
 * @author Victor Dubiniuk
 * @copyright 2012-2013 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Updater;

class Downloader {

	const PACKAGE_ROOT = 'owncloud';

	protected static $package;

	public static function getPackage($url, $version) {
		self::$package = self::getBackupBase() . $version;
		if (preg_match('/\.zip$/i', $url)) {
			$type = '.zip';
		} elseif (preg_match('/(\.tgz|\.tar\.gz)$/i', $url)) {
			$type = '.tgz';
		} elseif (preg_match('/\.tar\.bz2$/i', $url)) {
			$type = '.tar.bz2';
		} else {
			throw new \Exception('Unable to extract package ' . $url . ': unknown format');
		}
		
		self::$package = self::$package . $type;
		
		try {
			// Reuse already downloaded package
			if (!file_exists(self::$package)){
				$client = \OC::$server->getHTTPClientService()->newClient();
				$client->get($url, 
					[
						'save_to' => self::$package,
						'timeout' => 10*60,
					]
				);

				\OC::$server->getLogger()->debug(
					'Downloaded ' . filesize(self::$package) . ' bytes.',
					['app' => 'updater']
				);
			} else {
				\OC::$server->getLogger()->debug(
					'Use already downloaded package ' . self::$package . '. Size is ' . filesize(self::$package) . ' bytes.',
					['app' => 'updater']
				);
			}
			
			$extractDir = self::getPackageDir($version);
			Helper::mkdir($extractDir, true);

			$archive = \OC_Archive::open(self::$package);
			if (!$archive || !$archive->extract($extractDir)) {
				throw new \Exception(self::$package . " extraction error");
			}
			
		} catch (\Exception $e){
			\OC::$server->getLogger()->error('Retrieving ' . $url, ['app' => 'updater']);
			self::cleanUp($version);
			throw $e;
		}
		
		//  Prepare extracted data
		//  to have '3rdparty', 'apps' and 'core' subdirectories
		$baseDir = $extractDir. '/' . self::PACKAGE_ROOT;
		if (!file_exists($baseDir)){
			\OC::$server->getLogger()->error(
				'Expected fresh sources in ' . $baseDir . '. Nothing is found. Something is wrong with OC_Archive.',
				['app' => 'updater']
			);
			\OC::$server->getLogger()->error(
				$extractDir  . ' content: ' . implode(' ', scandir($extractDir)),
				['app' => 'updater']
				
			);
			if ($type === '.zip' && !extension_loaded('zip')){
				$l10n = \OC::$server->getL10N('updater');
				$hint = $l10n->t('Please ask your server administrator to enable PHP zip extension.');
			}
			throw new \Exception(self::$package . " extraction error. " . $hint);
		}

		include $baseDir . '/version.php';
		Helper::checkVersion($OC_Version, $OC_VersionString);

		$sources = Helper::getSources($version);
		rename($baseDir . '/' . Helper::THIRDPARTY_DIRNAME, $sources[Helper::THIRDPARTY_DIRNAME]);
		rename($baseDir . '/' . Helper::APP_DIRNAME, $sources[Helper::APP_DIRNAME]);
		rename($baseDir, $sources[Helper::CORE_DIRNAME]);
	}

	public static function cleanUp($version){
		Helper::removeIfExists(self::getPackageDir($version));
		Helper::removeIfExists(self::getTempBase());
	}
	
	public static function isClean($version){
		return !@file_exists(self::getPackageDir($version));
	}
	
	public static function getPackageDir($version) {
		return self::getTempBase() . $version;
	}
	
	protected static function getTempBase(){
		$app = new \OCA\Updater\AppInfo\Application();
		return $app->getContainer()->query('Config')->getTempBase();
	}
	
	protected static function getBackupBase(){
		$app = new \OCA\Updater\AppInfo\Application();
		return $app->getContainer()->query('Config')->getBackupBase();
	}
}
