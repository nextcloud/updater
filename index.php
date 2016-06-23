<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

class Updater {
	/** @var array */
	private $configValues = [];

	public function __construct() {
		$configFileName = __DIR__ . '/../config/config.php';
		if (!file_exists($configFileName)) {
			throw new \Exception('Could not find '.__DIR__.'/../config.php. Is this file in the "updater" subfolder of Nextcloud?');
		}

		/** @var array $CONFIG */
		require_once $configFileName;
		$this->configValues = $CONFIG;
	}

	/**
	 * Returns the specified config options
	 *
	 * @param string $key
	 * @return mixed|null Null if the entry is not found
	 */
	private function getConfigOption($key) {
		return isset($this->configValues[$key]) ? $this->configValues[$key] : null;
	}

	/**
	 * Gets the data directory location on the local filesystem
	 *
	 * @return string
	 */
	private function getDataDirectoryLocation() {
		return $this->configValues['datadirectory'];
	}

	/**
	 * Returns the expected files and folders as array
	 *
	 * @return array
	 */
	private function getExpectedElementsList() {
		return $expectedElements = [
			// Generic
			'.',
			'..',
			// Folders
			'3rdparty',
			'apps',
			'config',
			'core',
			'data',
			'l10n',
			'lib',
			'ocs',
			'ocs-provider',
			'resources',
			'settings',
			'themes',
			'updater',
			// Files
			'index.html',
			'.user.ini',
			'console.php',
			'cron.php',
			'index.php',
			'public.php',
			'remote.php',
			'status.php',
			'version.php',
			'robots.txt',
			'.htaccess',
			'AUTHORS',
			'COPYING-AGPL',
			'occ',
			'db_structure.xml',
		];
	}

	/**
	 * Gets the recursive directory iterator over the Nextcloud folder
	 *
	 * @param string $folder
	 * @return RecursiveIteratorIterator
	 */
	private function getRecursiveDirectoryIterator($folder = __DIR__ . '/../') {
		return new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
	}

	/**
	 * Returns the files are are unexpected. If none unexpected files are found
	 * an empty array is returned.
	 *
	 * @return array
	 */
	public function checkForExpectedFilesAndFolders() {
		$expectedElements = $this->getExpectedElementsList();
		$unexpectedElements = [];
		foreach (new DirectoryIterator(__DIR__ . '/../') as $fileInfo) {
			if(array_search($fileInfo->getFilename(), $expectedElements) === false) {
				$unexpectedElements[] = $fileInfo->getFilename();
			}
		}

		return $unexpectedElements;
	}

	/**
	 * Returns the files that are not writable
	 *
	 * @return array
	 */
	public function checkWritePermissions() {
		// TODO: Exclude data folder
		$notWriteablePaths = array();
		foreach ($this->getRecursiveDirectoryIterator() as $path => $dir) {
			if(!is_writable($path)) {
				$notWriteablePaths[] = $path;
			}
		}
		return $notWriteablePaths;
	}

	/**
	 * Sets the maintenance mode to the defined value
	 *
	 * @param bool $state
	 * @return bool Whether it was possible to write to the config file or not
	 */
	public function setMaintenanceMode($state) {
		/** @var array $CONFIG */
		$configFileName = __DIR__ . '/../config/config.php';
		require $configFileName;
		$CONFIG['maintenance'] = $state;
		$content = "<?php\n";
		$content .= '$CONFIG = ';
		$content .= var_export($CONFIG, true);
		$content .= ";\n";
		$state = file_put_contents($configFileName, $content);
		return $state !== false;
	}

	/**
	 * Creates a backup of all files and moves it into data/updater-$instanceid/backups/nextcloud-X-Y-Z/
	 *
	 * @throws Exception
	 */
	public function createBackup() {
		$excludedElements = [
			'data',
		];

		// Create new folder for the backup
		$backupFolderLocation = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid').'/backups/nextcloud-'.$this->getConfigOption('version') . '/';
		if(file_exists($backupFolderLocation)) {
			$this->recursiveDelete($backupFolderLocation);
		}
		$state = mkdir($backupFolderLocation, 0750, true);
		if($state === false) {
			throw new \Exception('Could not create backup folder location');
		}

		// Copy the backup files
		$currentDir = __DIR__ . '/../';

		/**
		 * @var string $path
		 * @var SplFileInfo $fileInfo
		 */
		foreach ($this->getRecursiveDirectoryIterator($currentDir) as $path => $fileInfo) {
			$fileName = explode($currentDir, $path)[1];
			$folderStructure = explode('/', $fileName, -1);

			// Exclude the exclusions
			if(isset($folderStructure[0])) {
				if(array_search($folderStructure[0], $excludedElements) !== false) {
					continue;
				}
			} else {
				if(array_search($fileName, $excludedElements) !== false) {
					continue;
				}
			}

			// Create folder if it doesn't exist
			if(!file_exists($backupFolderLocation . '/' . dirname($fileName))) {
				$state = mkdir($backupFolderLocation . '/' . dirname($fileName), 0750, true);
				if($state === false) {
					throw new \Exception('Could not create folder: '.$backupFolderLocation.'/'.dirname($fileName));
				}
			}

			// If it is a file copy it
			if($fileInfo->isFile()) {
				$state = copy($fileInfo->getRealPath(), $backupFolderLocation . $fileName);
				if($state === false) {
					throw new \Exception(
						sprintf(
							'Could not copy "%s" to "%s"',
							$fileInfo->getRealPath(),
							$backupFolderLocation . $fileName
						)
					);
				}
			}
		}
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	private function getUpdateServerResponse() {
		$updaterServer = $this->getConfigOption('updater.server.url');
		if($updaterServer === null) {
			// FIXME: used deployed URL
			$updaterServer = 'https://updates.nextcloud.org/updater_server/';
		}

		// Download update response
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => 1,
			// TODO: Detect release channel, probably we want to write the channel to config.php for that
			CURLOPT_URL => $updaterServer . '?version='. str_replace('.', 'x', $this->getConfigOption('version')) .'xxxstablexx',
			CURLOPT_USERAGENT => 'Nextcloud Updater',
		]);
		$response = curl_exec($curl);
		if($response === false) {
			throw new \Exception('Could not do request to updater server: '.curl_error($curl));
		}
		curl_close($curl);

		$xml = simplexml_load_string($response);
		if($xml === false) {
			throw new \Exception('Could not parse updater server XML response');
		}
		$json = json_encode($xml);
		if($json === false) {
			throw new \Exception('Could not JSON encode updater server response');
		}
		$response = json_decode($json, true);
		if($response === null) {
			throw new \Exception('Could not JSON decode updater server response.');
		}
		return $response;
	}

	/**
	 * Downloads the nextcloud folder to $DATADIR/updater-$instanceid/downloads/$filename
	 *
	 * @throws Exception
	 */
	public function downloadUpdate() {
		$response = $this->getUpdateServerResponse();
		$storageLocation = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid') . '/downloads/';
		$state = mkdir($storageLocation, 0750, true);
		if($state === false) {
			throw new \Exception('Could not mkdir storage location');
		}
		$fp = fopen($storageLocation . basename($response['url']), 'w+');
		$ch = curl_init($response['url']);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		if(curl_exec($ch) === false) {
			throw new \Exception('Curl error: ' . curl_error($ch));
		}
		curl_close($ch);
		fclose($fp);
	}

	/**
	 * Extracts the download
	 *
	 * @throws Exception
	 */
	public function extractDownload() {
		$storageLocation = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid') . '/downloads/';
		$files = scandir($storageLocation);
		if(count($files) !== 3) {
			throw new \Exception('Not exact 3 files existent in folder');
		}

		$zip = new ZipArchive;
		$zipState = $zip->open($storageLocation . '/' . $files[2]);
		if ($zipState === true) {
			$zip->extractTo($storageLocation);
			$zip->close();
			$state = unlink($storageLocation . '/' . $files[2]);
			if($state === false) {
				throw new \Exception('Cant unlink '. $storageLocation . '/' . $files[2]);
			}
		} else {
			throw new \Exception('Cant handle ZIP file. Error code is: '.$zipState);
		}
	}

	/**
	 * Replaces the entry point files with files that only return a 503
	 *
	 * @throws Exception
	 */
	public function replaceEntryPoints() {
		$filesToReplace = [
			'index.php',
			'status.php',
			'remote.php',
			'public.php',
			'ocs/v1.php',
		];

		$content = "<?php\nhttp_response_code(503);\ndie('Update in process.');";
		foreach($filesToReplace as $file) {
			$state = file_put_contents(__DIR__  . '/../' . $file, $content);
			if($state === false) {
				throw new \Exception('Cant replace entry point: '.$file);
			}
		}
	}

	/**
	 * Recursively deletes the specified folder from the system
	 *
	 * @param string $folder
	 * @throws Exception
	 */
	private function recursiveDelete($folder) {
		if(!file_exists($folder)) {
			return;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($iterator as $fileInfo) {
			$action = $fileInfo->isDir() ? 'rmdir' : 'unlink';
			$action($fileInfo->getRealPath());
		}
		$state = rmdir($folder);
		if($state === false) {
			throw new \Exception('Could not rmdir ' . $folder);
		}
	}

	/**
	 * Delete old files from the system as much as possible
	 *
	 * @throws Exception
	 */
	public function deleteOldFiles() {
		// Delete shipped apps
		$shippedApps = json_decode(file_get_contents(__DIR__ . '(/../core/shipped.json'), true);
		foreach($shippedApps['shippedApps'] as $app) {
			$this->recursiveDelete(__DIR__ . '/../apps/' . $app);
		}

		// Delete example config
		$state = unlink(__DIR__ . '/../config/config.sample.php');
		if($state === false) {
			throw new \Exception('Could not unlink sample config');
		}

		// Delete themes
		$state = unlink(__DIR__ . '/../themes/README');
		if($state === false) {
			throw new \Exception('Could not delete themes README');
		}
		$this->recursiveDelete(__DIR__ . '/../themes/example/');

		// Delete the rest
		$excludedElements = [
			'data',
			'index.php',
			'status.php',
			'remote.php',
			'public.php',
			'ocs/v1.php',
			'config',
			'themes',
			'apps',
			'updater',
		];
		/**
		 * @var string $path
		 * @var SplFileInfo $fileInfo
		 */
		foreach ($this->getRecursiveDirectoryIterator() as $path => $fileInfo) {
			$currentDir = __DIR__ . '/../';
			$fileName = explode($currentDir, $path)[1];
			$folderStructure = explode('/', $fileName, -1);
			// Exclude the exclusions
			if(isset($folderStructure[0])) {
				if(array_search($folderStructure[0], $excludedElements) !== false) {
					continue;
				}
			} else {
				if(array_search($fileName, $excludedElements) !== false) {
					continue;
				}
			}
			if($fileInfo->isFile()) {
				$state = unlink($path);
				if($state === false) {
					throw new \Exception('Could not unlink: '.$path);
				}
			} elseif($fileInfo->isDir()) {
				$state = rmdir($path);
				if($state === false) {
					throw new \Exception('Could not rmdir: '.$path);
				}
			}
		}
	}

	/**
	 * Moves the newly downloaded files into place
	 *
	 * @throws Exception
	 */
	public function moveNewVersionInPlace() {
		$excludedElements = [
			'updater',
			'index.php',
			'status.php',
			'remote.php',
			'public.php',
			'ocs/v1.php',
		];

		$storageLocation = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid') . '/downloads/nextcloud/';

		/**
		 * @var SplFileInfo $fileInfo
		 */
		foreach ($this->getRecursiveDirectoryIterator($storageLocation) as $path => $fileInfo) {
			$fileName = explode($storageLocation, $path)[1];
			$folderStructure = explode('/', $fileName, -1);

			// Exclude the exclusions
			if (isset($folderStructure[0])) {
				if (array_search($folderStructure[0], $excludedElements) !== false) {
					continue;
				}
			} else {
				if (array_search($fileName, $excludedElements) !== false) {
					continue;
				}
			}

			if($fileInfo->isFile()) {
				if(!file_exists(__DIR__ . '/../' . dirname($fileName))) {
					$state = mkdir(__DIR__ . '/../' . dirname($fileName), 0750, true);
					if($state === false) {
						throw new \Exception('Could not mkdir ' . __DIR__  . '/../' . dirname($fileName));
					}
				}
				$state = rename($path, __DIR__  . '/../' . $fileName);
				if($state === false) {
					throw new \Exception(
						sprintf(
							'Could not rename %s to %s',
							$path,
							__DIR__ . '/../' . $fileName
						)
					);
				}
			}
			if($fileInfo->isDir()) {
				$state = rmdir($path);
				if($state === false) {
					throw new \Exception('Could not rmdir ' . $path);
				}
			}
		}

		// Rename entry files of Nextcloud and updater file
		/**
		 * @var SplFileInfo $fileInfo
		 */
		foreach ($this->getRecursiveDirectoryIterator($storageLocation) as $path => $fileInfo) {
			$fileName = explode($storageLocation, $path)[1];
			if($fileInfo->isFile()) {
				if(!file_exists(__DIR__ . '/../' . dirname($fileName))) {
					$state = mkdir(__DIR__ . '/../' . dirname($fileName), 0750, true);
					if($state === false) {
						throw new \Exception('Could not mkdir ' . __DIR__  . '/../' . dirname($fileName));
					}
				}
				$state = rename($path, __DIR__  . '/../' . $fileName);
				if($state === false) {
					throw new \Exception(
						sprintf(
							'Could not rename %s to %s',
							$path,
							__DIR__ . '/../' . $fileName
						)
					);
				}
			}
			if($fileInfo->isDir()) {
				$state = rmdir($path);
				if($state === false) {
					throw new \Exception('Could not rmdir ' . $path);
				}
			}
		}

		$state = rmdir($storageLocation);
		if($state === false) {
			throw new \Exception('Could not rmdir $storagelocation');
		}
	}
}

// Check if the config.php is at the expected place
try {
	$updater = new Updater();
} catch (\Exception $e) {
	die($e->getMessage());
}

// TODO: Note when a step started and when one ended, also to prevent multiple people at the same time accessing the updater
if(isset($_POST['step'])) {
	set_time_limit(0);
	switch($_POST['step']) {
		case '1':
			$unexpectedElements = $updater->checkForExpectedFilesAndFolders();
			if(count($unexpectedElements) !== 0) {
				echo(json_encode(['proceed' => false, 'response' => $unexpectedElements]));
			} else {
				echo(json_encode(['proceed' => true]));
			}
			break;
		case '2':
			$notWriteableFiles = $updater->checkWritePermissions();
			if(count($notWriteableFiles) > 0) {
				echo(json_encode(['proceed' => false, 'response' => $notWriteableFiles]));
			} else {
				echo(json_encode(['proceed' => true]));
			}
			break;
		case '3':
			$success = $updater->setMaintenanceMode(true);
			if($success === true) {
				echo(json_encode(['proceed' => true]));
			} else {
				echo(json_encode(['proceed' => false]));
			}
			break;
		case '4':
			try {
				$updater->createBackup();
				echo(json_encode(['proceed' => true]));
			} catch (\Exception $e) {
				echo(json_encode(['proceed' => false, 'response' => $e->getMessage()]));
			}
			break;
		case '5':
			try {
				$updater->downloadUpdate();
				echo(json_encode(['proceed' => true]));
			} catch (\Exception $e) {
				echo(json_encode(['proceed' => false, 'response' => $e->getMessage()]));
			}
			break;
		case '6':
			try {
				$updater->extractDownload();
				echo(json_encode(['proceed' => true]));
			} catch (\Exception $e) {
				echo(json_encode(['proceed' => false, 'response' => $e->getMessage()]));
			}
			break;
		case '7':
			// TODO: If it fails after step 7: Rollback
			try {
				$updater->replaceEntryPoints();
				echo(json_encode(['proceed' => true]));
			} catch (\Exception $e) {
				echo(json_encode(['proceed' => false, 'response' => $e->getMessage()]));
			}
			break;
		case '8':
			try {
				$updater->deleteOldFiles();
				echo(json_encode(['proceed' => true]));
			} catch (\Exception $e) {
				echo(json_encode(['proceed' => false, 'response' => $e->getMessage()]));
			}
			break;
		case '9':
			try {
				$updater->moveNewVersionInPlace();
				$updater->setMaintenanceMode(false);
				echo(json_encode(['proceed' => true]));
			} catch (\Exception $e) {
				echo(json_encode(['proceed' => false, 'response' => $e->getMessage()]));
			}
			break;
	}

	die();
}
?>

<html>
<body>
<h1>Nextcloud Updater</h1>

<?php
	// TODO: Proper auth also in the steps above…
if(!isset($_POST['password'])):
	?>
	<p>Please provide your defined password in config.php to proceed:</p>
	<form method="POST">
		<input type="password" name="password" />
		<input type="submit" />
	</form>
<?php endif; ?>

<?php if(isset($_POST['password']) && $_POST['password'] === '1'): ?>
	<pre id="progress">
Starting update process. Please be patient...
	</pre>
<?php endif; ?>
</body>
<?php if(isset($_POST['password']) && $_POST['password'] === '1'): ?>

	<script>
		function addStepText(text) {
			var previousValue = document.getElementById('progress').innerHTML;
			document.getElementById('progress').innerHTML = previousValue + "\r" + text;
		}

		function performStep(number) {
			var httpRequest = new XMLHttpRequest();
			httpRequest.open("POST", window.location.href, false);
			httpRequest.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			httpRequest.send("step="+number);
			var data = httpRequest.responseText;
			return JSON.parse(data);
		}

		// Step 1: Check for expected files
		addStepText('Start: Check for expected files');
		var response = performStep(1);
		if(response.proceed === true) {
			addStepText('Success: Check for expected files has succeeded');

			// Step 2: Check for write permissions
			addStepText('Start: Check for write permissions');
			response = performStep(2);
			if(response.proceed === true) {
				addStepText('Success: Check for write permissions');

				response = performStep(3);
				if(response.proceed === true) {
					// TODO: Put into loop and make non-rendering blocking
					addStepText('Enabled maintenance mode');

					addStepText('Start: Create backup');
					performStep(4);
					addStepText('Done: Create backup');

					addStepText('Start: Download update');
					performStep(5);
					addStepText('Done: Download update');

					addStepText('Start: Extract update');
					performStep(6);
					addStepText('Done: Extract update');

					addStepText('Start: Replace Entry Points');
					performStep(7);
					addStepText('Done: Replace Entry Points');

					addStepText('Start: Delete old files');
					performStep(8);
					addStepText('Done: Delete old files');

					addStepText('Start: Move new files in place');
					performStep(9);
					addStepText('Done: Move new files in place');
					addStepText('!!! Update done !!!');
				} else {
					addStepText('Error: Could not enable maintenance mode in config.php');
				}
			} else {
				addStepText('Error: Check for all write permissions failed. The following places can not be written to:');
				response['response'].forEach(function(file) {
					addStepText("\t"+file);
				});
			}
		} else {
			addStepText('Error: Check for all expected files failed. The following extra files have been found:');
			response['response'].forEach(function(file) {
				addStepText("\t"+file);
			});
		}
	</script>
<?php endif; ?>

</html>

