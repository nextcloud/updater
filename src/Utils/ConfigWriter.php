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

use Owncloud\Updater\Utils\Locator;

class ConfigWriter {

	/** @var array Associative array ($key => $value) */
	protected $cache = array();

	/**
	 * @var Locator $locator
	 */
	protected $locator;

	/**
	 *
	 * @param Locator $locator
	 */
	public function __construct(Locator $locator){
		$this->locator = $locator;
	}
	
	public function setMaintenanceOn(){
		return $this->toggleMaintenance(true);
	}

	public function setMaintenanceOff(){
		return $this->toggleMaintenance(false);
	}

	protected function toggleMaintenance($flag){
		$this->readData();
		$this->cache['maintenance'] = $flag;
		$this->writeData();
	}

	protected function readData(){
		foreach ($this->locator->getPathtoConfigFiles() as $configFile){
			$filePointer = @fopen($configFile, 'r');
			if ($filePointer === false &&
				@!file_exists($configFile)) {
				// Opening the main config might not be possible, e.g. if the wrong
				// permissions are set (likely on a new installation)
				throw new \Exception(sprintf('Could not read %s', $configFile));
			}

			// Try to acquire a file lock
			if(!flock($filePointer, LOCK_SH)) {
				throw new \Exception(sprintf('Could not acquire a shared lock on the config file %s', $configFile));
			}

			unset($CONFIG);
			include $configFile;
			if(isset($CONFIG) && is_array($CONFIG)) {
				$this->cache = array_merge($this->cache, $CONFIG);
			}

			// Close the file pointer and release the lock
			flock($filePointer, LOCK_UN);
			fclose($filePointer);
		}
	}

	protected function writeData(){
		$configFilePath = current($this->locator->getPathtoConfigFiles());
		// Create a php file ...
		$content = "<?php\n";
		$content .= '$CONFIG = ';
		$content .= var_export($this->cache, true);
		$content .= ";\n";

		touch ($configFilePath);
		$filePointer = fopen($configFilePath, 'r+');

		// Prevent others not to read the config
		chmod($configFilePath, 0640);

		// File does not exist, this can happen when doing a fresh install
		if(!is_resource ($filePointer)) {
			$url = \OC_Helper::linkToDocs('admin-dir_permissions');
			throw new HintException(
				"Can't write into config directory!",
				'This can usually be fixed by '
				.'<a href="' . $url . '" target="_blank">giving the webserver write access to the config directory</a>.');
		}

		// Try to acquire a file lock
		if(!flock($filePointer, LOCK_EX)) {
			throw new \Exception(sprintf('Could not acquire an exclusive lock on the config file %s', $configFilePath));
		}

		// Write the config and release the lock
		ftruncate ($filePointer, 0);
		fwrite($filePointer, $content);
		fflush($filePointer);
		flock($filePointer, LOCK_UN);
		fclose($filePointer);
	}
}
