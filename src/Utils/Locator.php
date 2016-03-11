<?php
/**
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Owncloud\Updater\Utils;

use \Owncloud\Updater\Console\Application;

class Locator {

	/**
	 * absolute path to ownCloud root
	 * @var string 
	 */
	protected $owncloudRootPath;

	/**
	 * absolute path to updater root
	 * @var string
	 */
	protected $updaterRootPath;

	/**
	 *
	 * @param string $baseDir
	 */
	public function __construct($baseDir){
		$this->updaterRootPath = $baseDir;
		$this->owncloudRootPath = dirname($baseDir);
	}

	public function getOwncloudRootPath(){
		return $this->owncloudRootPath;
	}

	/**
	 * expected items in the core
	 * @return array
	 */
	public function getRootDirContent(){
		return [
			"config/config.sample.php",
			"core",
			"l10n",
			"lib",
			"ocs",
			"ocs-provider",
			"resources",
			"settings",
			".htaccess",
			".mailmap",
			".tag",
			".user.ini",
			"AUTHORS",
			"console.php",
			"COPYING-AGPL",
			"cron.php",
			"db_structure.xml",
			"index.html",
			"index.php",
			"indie.json",
			"occ",
			"public.php",
			"remote.php",
			"robots.txt",
			"status.php",
			"version.php"
		];
	}

	public function getUpdaterContent(){
		return [
			'app',
			'application.php',
			'box.json',
			'composer.json',
			'composer.lock',
			'CONTRIBUTING.md',
			'COPYING-AGPL',
			'index.php',
			'pub',
			'src',
			'vendor',
			'README.md',
			'.travis.yml',
			'.scrutinizer.yml',
		];
	}

	/**
	 * Absolute path to core root dir content
	 * @return array
	 */
	public function getRootDirItems(){
		$items = $this->getRootDirContent();
		$items = array_map(
			function($item){ return $this->owncloudRootPath . "/" . $item;	},
			$items
		);
		return $items;
	}

	/**
	 * Absolute path
	 * @return string
	 * @throws \Exception
	 */
	public function getDataDir(){
		$container = Application::$container;
		if (isset($container['utils.configReader']) && $container['utils.configReader']->getIsLoaded()){
			return $container['utils.configReader']->getByPath('system.datadirectory');
		}

		// Fallback case
		include $this->getPathToConfigFile();
		if (isset($CONFIG['datadirectory'])){
			return $CONFIG['datadirectory'];
		}

		// Something went wrong
		throw new \Exception('Unable to detect datadirectory');
	}

	/**
	 * Absolute path to updater root dir
	 * @return string
	 */
	public function getUpdaterBaseDir(){
		return $this->getDataDir() . '/updater-data';
	}

	/**
	 * Absolute path to create a core and apps backups
	 * @return string
	 */
	public function getCheckpointDir(){
		return $this->getUpdaterBaseDir() . '/checkpoint';
	}

	/**
	 * Absolute path to store downloaded packages
	 * @return string
	 */
	public function getDownloadBaseDir(){
		return $this->getUpdaterBaseDir() . '/download';
	}

	public function getExtractionBaseDir(){
		 return $this->owncloudRootPath . "/_oc_upgrade";
	}

	/**
	 *
	 * @return string
	 */
	public function getPathToOccFile(){
		return $this->owncloudRootPath . '/occ';
	}

	/**
	 *
	 * @return string
	 */
	public function getInstalledVersion(){
		include $this->getPathToVersionFile();

		/** @var $OC_Version string */
		return $OC_Version;
	}

	/**
	 *
	 * @return string
	 */
	public function getChannelFromVersionsFile(){
		include $this->getPathToVersionFile();

		/** @var $OC_Version string */
		return $OC_Channel;
	}

	/**
	 *
	 * @return string
	 */
	public function getBuild(){
		include $this->getPathToVersionFile();

		/** @var $OC_Build string */
		return $OC_Build;
	}

	public function getPathtoConfigFiles($filePostfix = 'config.php'){
		// Only config.php for now
		return [
			$this->owncloudRootPath . '/config/' . $filePostfix
		];
	}

	public function getPathToConfigFile(){
		return $this->owncloudRootPath . '/config/config.php';
	}

	/**
	 *
	 * @return string
	 */
	public function getPathToVersionFile(){
		return $this->owncloudRootPath . '/version.php';
	}

}
