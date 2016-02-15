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

use Owncloud\Updater\Console\Application;
use Owncloud\Updater\Utils\FilesystemHelper;
use Owncloud\Updater\Utils\Locator;

class Checkpoint {

	const CORE_DIR = 'core';
	const THIRDPARTY_DIR = '3rdparty';
	const APP_DIR = 'apps';

	/**
	 * @var Locator $locator
	 */
	protected $locator;

	/**
	 * @var Filesystemhelper $fsHelper
	 */
	protected $fsHelper;

	/**
	 *
	 * @param Locator $locator
	 */
	public function __construct(Locator $locator, FilesystemHelper $fsHelper){
		$this->locator = $locator;
		$this->fsHelper = $fsHelper;
	}

	public function create(){
		$checkpointName = $this->getCheckpointName();
		$checkpointPath = $this->locator->getCheckpointDir() . '/' . $checkpointName;
		try{
			if (!$this->fsHelper->isWritable($this->locator->getCheckpointDir())){
				throw new \Exception($this->locator->getCheckpointDir() . ' is not writable.');
			}
			$this->fsHelper->mkdir($checkpointPath);

			$checkpointCorePath = $checkpointPath . '/' . self::CORE_DIR;
			$this->fsHelper->mkdir($checkpointCorePath);
			$core = $this->locator->getRootDirItems();
			foreach ($core as $coreItem){
				$cpItemPath = $checkpointCorePath . '/' . basename($coreItem);
				$this->fsHelper->copyr($coreItem, $cpItemPath, true);
			}
			//copy config.php
			$configDirSrc = $this->locator->getOwncloudRootPath() . '/config';
			$configDirDst = $checkpointCorePath . '/config';
			$this->fsHelper->copyr($configDirSrc, $configDirDst, true);

			//copy 3rdparty
			$this->fsHelper->copyr($this->locator->getOwncloudRootPath() . '/' . self::THIRDPARTY_DIR, $checkpointCorePath . '/' . self::THIRDPARTY_DIR, true);

			$checkpointAppPath = $checkpointPath . '/' . self::APP_DIR;
			$this->fsHelper->mkdir($checkpointAppPath);
			$appManager = Application::$container['utils.appmanager'];
			$apps = $appManager->getAllApps();
			foreach ($apps as $appId){
				$appPath = $appManager->getAppPath($appId);
				if ($appPath){
					$this->fsHelper->copyr($appPath, $checkpointAppPath . '/' . $appId, true);
				}
			}

		} catch (\Exception $e){
			$application = Application::$container['application'];
			$application->getLogger()->error($e->getMessage());
			$this->fsHelper->removeIfExists($checkpointPath);
			throw $e;
		}
		return $checkpointName;
	}

	public function restore($checkpointId){
		$checkpointDir = $this->locator->getCheckpointDir() . '/' . $checkpointId;
		if (!$this->fsHelper->fileExists($checkpointDir)){
			$message = sprintf('Checkpoint %s does not exist.', $checkpointId);
			throw new \UnexpectedValueException($message);
		}
		$ocRoot = $this->locator->getOwncloudRootPath();
		$this->fsHelper->copyr($checkpointDir . '/' . self::CORE_DIR, $ocRoot, false);
		$this->fsHelper->copyr($checkpointDir . '/' . self::APP_DIR, $ocRoot . '/' . self::APP_DIR, false);
	}

	public function getAll(){
		$checkpointDir = $this->locator->getCheckpointDir();
		$content = $this->fsHelper->isDir($checkpointDir) ? $this->fsHelper->scandir($checkpointDir) : [];
		$checkpoints = array_filter(
				$content,
				function($dir){
					return !in_array($dir, ['.', '..']);
				}
		);
		return $checkpoints;
	}

	protected function getCheckpointName(){
		$versionString = implode('.', $this->locator->getInstalledVersion());
		return uniqid($versionString . '-');
	}

}
