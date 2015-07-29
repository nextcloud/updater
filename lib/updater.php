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

class Updater {

	protected static $processed = [];

	public static function update($version, $backupBase){
		if (!is_dir($backupBase)){
			throw new \Exception("Backup directory $backupBase is not found");
		}

		// Switch include paths to backup
		$pathsArray = explode(PATH_SEPARATOR, get_include_path());
		$pathsTranslated = [];
		foreach ($pathsArray as $path){
			//Update all 3rdparty paths
			if (preg_match('|^' . preg_quote(\OC::$THIRDPARTYROOT . '/3rdparty') . '|', $path)) {
				$pathsTranslated[] = preg_replace(
					'|^' . preg_quote(\OC::$THIRDPARTYROOT . '/3rdparty') .'|', 
					$backupBase . '/3rdparty', 
					$path
				);
				continue;
			}
			// Update all OC webroot paths
			$pathsTranslated[] = preg_replace(
				'|^' . preg_quote(\OC::$SERVERROOT) .'|', 
				$backupBase,
				$path
			);
		}
		
		set_include_path(
			implode(PATH_SEPARATOR, $pathsTranslated) 
		);

		$tempDir = self::getTempDir();
		Helper::mkdir($tempDir, true);

		$installed = Helper::getDirectories();
		$sources = Helper::getSources($version);
		
		try{
				$thirdPartyUpdater = new \OCA\Updater\Location\Thirdparty(
						$installed[Helper::THIRDPARTY_DIRNAME],
						$sources[Helper::THIRDPARTY_DIRNAME]
				);
				$thirdPartyUpdater->update($tempDir . '/' . Helper::THIRDPARTY_DIRNAME);
				self::$processed[] = $thirdPartyUpdater;
				
				$coreUpdater = new \OCA\Updater\Location\Core(
						$installed[Helper::CORE_DIRNAME],
						$sources[Helper::CORE_DIRNAME]
				);
				$coreUpdater->update($tempDir . '/' . Helper::CORE_DIRNAME);
				self::$processed[] = $coreUpdater;
				
				$appsUpdater = new \OCA\Updater\Location\Apps(
						'', //TODO: put smth really helpful here ;)
						$sources[Helper::APP_DIRNAME]
				);
				$appsUpdater->update($tempDir . '/' . Helper::APP_DIRNAME);
				self::$processed[] = $appsUpdater;
		} catch (\Exception $e){
			self::rollBack();
			self::cleanUp();
			throw $e;
		}

		// zip backup 
		$zip = new \ZipArchive();
		if ($zip->open($backupBase . ".zip", \ZIPARCHIVE::CREATE) === true){
			Helper::addDirectoryToZip($zip, $backupBase);
			$zip->close();
			\OCP\Files::rmdirr($backupBase);
		}

		return true;
	}

	public static function rollBack(){
		foreach (self::$processed as $item){
			$item->rollback();
		}
	}

	public static function cleanUp(){
		Helper::removeIfExists(self::getTempDir());
		Helper::removeIfExists(self::getTempBase());
	}
	
	public static function isClean(){
		return !@file_exists(self::getTempDir());
	}

	public static function getTempDir(){
		return self::getTempBase() . 'tmp';
	}
	
	protected static function getTempBase(){
		$app = new \OCA\Updater\AppInfo\Application();
		return $app->getContainer()->query('Config')->getTempBase();
	}
}
