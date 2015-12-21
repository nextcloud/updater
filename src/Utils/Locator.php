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

	public function getRootDirItems(){
		/* 8.2. can not provide items so we list them here.
		 * waiting for https://github.com/owncloud/core/pull/20285
		 */
		$items = [
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
		$items = array_map(
			function($item){ return $this->owncloudRootPath . "/" . $item;	},
			$items
		);
		return $items;
	}

	public function getDataDir(){
		return $this->updaterRootPath . '/data';
	}

	public function getCheckpointDir(){
		return $this->getDataDir() . '/checkpoint';
	}

	public function getDownloadBaseDir(){
		return $this->getDataDir() . '/download';
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

	/**
	 *
	 * @return string
	 */
	public function getPathToVersionFile(){
		return $this->owncloudRootPath . '/version.php';
	}

}
