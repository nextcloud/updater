<?php
/**
 * @copyright Copyright (c) 2016-2017 Lukas Reschke <lukas@statuscode.ch>
 * @copyright Copyright (c) 2016 Morris Jobke <hey@morrisjobke.de>
 * @copyright Copyright (c) 2018 Jonas Sulzer <jonas@violoncello.ch>
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

namespace NC\Updater;

class Updater {
	/** @var string */
	private $baseDir;
	/** @var array */
	private $configValues = [];
	/** @var string */
	private $currentVersion = 'unknown';
	/** @var string */
	private $buildTime;
	/** @var bool */
	private $updateAvailable = false;
	/** @var string */
	private $requestID = null;
	/** @var bool */
	private $disabled = false;

	/**
	 * Updater constructor
	 * @param $baseDir string the absolute path to the /updater/ directory in the Nextcloud root
	 * @throws \Exception
	 */
	public function __construct($baseDir) {
		$this->baseDir = $baseDir;

		if($dir = getenv('NEXTCLOUD_CONFIG_DIR')) {
			$configFileName = rtrim($dir, '/') . '/config.php';
		} else {
			$configFileName = $this->baseDir . '/../config/config.php';
		}
		if (!file_exists($configFileName)) {
			throw new \Exception('Could not find config.php. Is this file in the "updater" subfolder of Nextcloud?');
		}

		/** @var array $CONFIG */
		require_once $configFileName;
		$this->configValues = $CONFIG;

		if (php_sapi_name() !== 'cli' && ($this->configValues['upgrade.disable-web'] ?? false)) {
			// updater disabled
			$this->disabled = true;
			return;
		}

		$dataDir = $this->getDataDirectoryLocation();
		if(empty($dataDir) || !is_string($dataDir)) {
			throw new \Exception('Could not read data directory from config.php.');
		}

		$versionFileName = $this->baseDir . '/../version.php';
		if (!file_exists($versionFileName)) {
			// fallback to version in config.php
			$version = $this->getConfigOption('version');
			$buildTime = '';
		} else {
			/** @var string $OC_VersionString */
			/** @var string $OC_Build */
			require_once $versionFileName;
			$version = $OC_VersionString;
			$buildTime = $OC_Build;
		}

		if($version === null) {
			return;
		}
		if($buildTime === null) {
			return;
		}

		// normalize version to 3 digits
		$splittedVersion = explode('.', $version);
		if(sizeof($splittedVersion) >= 3) {
			$splittedVersion = array_slice($splittedVersion, 0, 3);
		}

		$this->currentVersion = implode('.', $splittedVersion);
		$this->buildTime = $buildTime;
	}

	/**
	 * Returns whether the web updater is disabled
	 *
	 * @return bool
	 */
	public function isDisabled() {
		return $this->disabled;
	}

	/**
	 * Returns current version or "unknown" if this could not be determined.
	 *
	 * @return string
	 */
	public function getCurrentVersion() {
		return $this->currentVersion;
	}

	/**
	 * Returns currently used release channel
	 *
	 * @return string
	 */
	private function getCurrentReleaseChannel() {
		return !is_null($this->getConfigOption('updater.release.channel')) ? $this->getConfigOption('updater.release.channel') : 'stable';
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function checkForUpdate() {
		$response = $this->getUpdateServerResponse();

		$this->silentLog('[info] checkForUpdate() ' . print_r($response, true));

		$version = isset($response['version']) ? $response['version'] : '';
		$versionString = isset($response['versionstring']) ? $response['versionstring'] : '';

		if ($version !== '' && $version !== $this->currentVersion) {
			$this->updateAvailable = true;
			$releaseChannel = $this->getCurrentReleaseChannel();
			$updateText = 'Update to ' . htmlentities($versionString) . ' available. (channel: "' . htmlentities($releaseChannel) . '")<br /><span class="light">Following file will be downloaded automatically:</span> <code class="light">' . $response['url'] . '</code>';

			// only show changelog link for stable releases (non-RC & non-beta)
			if (!preg_match('!(rc|beta)!i', $versionString)) {
				$changelogURL = $this->getChangelogURL(substr($version, 0, strrpos($version, '.')));
				$updateText .= '<br /><a class="external_link" href="' . $changelogURL . '" target="_blank" rel="noreferrer noopener">Open changelog ↗</a>';
			}
		} else {
			$updateText = 'No update available.';
		}

		if ($this->updateAvailable && isset($response['autoupdater']) && !($response['autoupdater'] === 1 || $response['autoupdater'] === '1')) {
			$this->updateAvailable = false;

			$updateText .= '<br />The updater is disabled for this update - please update manually.';
		}

		$this->silentLog('[info] end of checkForUpdate() ' . $updateText);
		return $updateText;
	}

	/**
	 * Returns bool whether update is available or not
	 *
	 * @return bool
	 */
	public function updateAvailable() {
		return $this->updateAvailable;
	}

	/**
	 * Returns the specified config options
	 *
	 * @param string $key
	 * @return mixed|null Null if the entry is not found
	 */
	public function getConfigOption($key) {
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
		$expected = [
			// Generic
			'.',
			'..',
			// Folders
			'.well-known',
			'3rdparty',
			'apps',
			'config',
			'core',
			'data',
			'l10n',
			'lib',
			'ocs',
			'ocs-provider',
			'ocm-provider',
			'resources',
			'settings',
			'themes',
			'updater',
			// Files
			'.rnd',
			'index.html',
			'indie.json',
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
			'CHANGELOG.md',
			'COPYING',
			'COPYING-AGPL',
			'occ',
			'db_structure.xml',
		];
		return array_merge($expected, $this->getAppDirectories());
	}

	/**
	 * Returns app directories specified in config.php
	 *
	 * @return array
	 */
	private function getAppDirectories() {
		$expected = [];
		if($appsPaths = $this->getConfigOption('apps_paths')) {
			foreach ($appsPaths as $appsPath) {
				$parentDir = realpath($this->baseDir . '/../');
				$appDir = basename($appsPath['path']);
				if(strpos($appsPath['path'], $parentDir) === 0 && $appDir !== 'apps') {
					$expected[] = $appDir;
				}
			}
		}
		return $expected;
	}

	/**
	 * Gets the recursive directory iterator over the Nextcloud folder
	 *
	 * @param string $folder
	 * @return \RecursiveIteratorIterator
	 */
	private function getRecursiveDirectoryIterator($folder = null) {
		if ($folder === null) {
			$folder = $this->baseDir . '/../';
		}
		return new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
	}

	/**
	 * Checks for files that are unexpected.
	 */
	public function checkForExpectedFilesAndFolders() {
		$this->silentLog('[info] checkForExpectedFilesAndFolders()');

		$expectedElements = $this->getExpectedElementsList();
		$unexpectedElements = [];
		foreach (new \DirectoryIterator($this->baseDir . '/../') as $fileInfo) {
			if(array_search($fileInfo->getFilename(), $expectedElements) === false) {
				$unexpectedElements[] = $fileInfo->getFilename();
			}
		}

		if (count($unexpectedElements) !== 0) {
			throw new UpdateException($unexpectedElements);
		}
		$this->silentLog('[info] end of checkForExpectedFilesAndFolders()');
	}

	/**
	 * Checks for files that are not writable
	 */
	public function checkWritePermissions() {
		$this->silentLog('[info] checkWritePermissions()');

		$notWritablePaths = array();
		$dir = new \RecursiveDirectoryIterator($this->baseDir . '/../');
		$filter = new RecursiveDirectoryIteratorWithoutData($dir);
		$it = new \RecursiveIteratorIterator($filter);

		foreach ($it as $path => $dir) {
			if(!is_writable($path)) {
				$notWritablePaths[] = $path;
			}
		}
		if(count($notWritablePaths) > 0) {
			throw new UpdateException($notWritablePaths);
		}

		$this->silentLog('[info] end of checkWritePermissions()');
	}

	/**
	 * Sets the maintenance mode to the defined value
	 *
	 * @param bool $state
	 * @throws \Exception when config.php can't be written
	 */
	public function setMaintenanceMode($state) {
		$this->silentLog('[info] setMaintenanceMode("' . ($state ? 'true' : 'false') .  '")');

		if($dir = getenv('NEXTCLOUD_CONFIG_DIR')) {
			$configFileName = rtrim($dir, '/') . '/config.php';
		} else {
			$configFileName = $this->baseDir . '/../config/config.php';
		}
		$this->silentLog('[info] configFileName ' . $configFileName);

		// usually is already tested in the constructor but just to be on the safe side
		if (!file_exists($configFileName)) {
			throw new \Exception('Could not find config.php.');
		}
		/** @var array $CONFIG */
		require $configFileName;
		$CONFIG['maintenance'] = $state;
		$content = "<?php\n";
		$content .= '$CONFIG = ';
		$content .= var_export($CONFIG, true);
		$content .= ";\n";
		$state = file_put_contents($configFileName, $content);
		if ($state === false) {
			throw new \Exception('Could not write to config.php');
		}
		$this->silentLog('[info] end of setMaintenanceMode()');
	}

	/**
	 * Creates a backup of all files and moves it into data/updater-$instanceid/backups/nextcloud-X-Y-Z/
	 *
	 * @throws \Exception
	 */
	public function createBackup() {
		$this->silentLog('[info] createBackup()');

		$excludedElements = [
			'.rnd',
			'.well-known',
			'data',
		];

		// Create new folder for the backup
		$backupFolderLocation = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid').'/backups/nextcloud-'.$this->getConfigOption('version') . '-' . time() . '/';
		$this->silentLog('[info] backup folder location: ' . $backupFolderLocation);

		$state = mkdir($backupFolderLocation, 0750, true);
		if($state === false) {
			throw new \Exception('Could not create backup folder location');
		}

		// Copy the backup files
		$currentDir = $this->baseDir . '/../';

		/**
		 * @var string $path
		 * @var \SplFileInfo $fileInfo
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
					$message = sprintf(
						'Could not copy "%s" to "%s"',
						$fileInfo->getRealPath(),
						$backupFolderLocation . $fileName
					);

					if(is_readable($fileInfo->getRealPath()) === false) {
						$message = sprintf(
							'%s. Source %s is not readable',
							$message,
							$fileInfo->getRealPath()
						);
					}

					if(is_writable($backupFolderLocation . $fileName) === false) {
						$message = sprintf(
							'%s. Destination %s is not writable',
							$message,
							$backupFolderLocation . $fileName
						);
					}

					throw new \Exception($message);
				}
			}
		}
		$this->silentLog('[info] end of createBackup()');
	}

	private function getChangelogURL($versionString) {
		$this->silentLog('[info] getChangelogURL()');
		$changelogWebsite = 'https://nextcloud.com/changelog/';
		$changelogURL = $changelogWebsite . '#' . str_replace('.', '-', $versionString);
		return $changelogURL;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	private function getUpdateServerResponse() {
		$this->silentLog('[info] getUpdateServerResponse()');

		$updaterServer = $this->getConfigOption('updater.server.url');
		if($updaterServer === null) {
			// FIXME: used deployed URL
			$updaterServer = 'https://updates.nextcloud.com/updater_server/';
		}
		$this->silentLog('[info] updaterServer: ' . $updaterServer);

		$releaseChannel = $this->getCurrentReleaseChannel();
		$this->silentLog('[info] releaseChannel: ' . $releaseChannel);
		$this->silentLog('[info] internal version: ' . $this->getConfigOption('version'));

		$updateURL = $updaterServer . '?version='. str_replace('.', 'x', $this->getConfigOption('version')) .'xxx'.$releaseChannel.'xx'.urlencode($this->buildTime).'x'.PHP_MAJOR_VERSION.'x'.PHP_MINOR_VERSION.'x'.PHP_RELEASE_VERSION;
		$this->silentLog('[info] updateURL: ' . $updateURL);

		// Download update response
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $updateURL,
			CURLOPT_USERAGENT => 'Nextcloud Updater',
		]);

		if ($this->getConfigOption('proxy') !== null) {
			curl_setopt_array($curl, [
				CURLOPT_PROXY => $this->getConfigOption('proxy'),
				CURLOPT_PROXYUSERPWD => $this->getConfigOption('proxyuserpwd'),
				CURLOPT_HTTPPROXYTUNNEL => $this->getConfigOption('proxy') ? 1 : 0,
			]);
		}

		$response = curl_exec($curl);
		if($response === false) {
			throw new \Exception('Could not do request to updater server: '.curl_error($curl));
		}
		curl_close($curl);

		// Response can be empty when no update is available
		if($response === '') {
			return [];
		}

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
		$this->silentLog('[info] getUpdateServerResponse response: ' . print_r($response, true));
		return $response;
	}

	/**
	 * Downloads the nextcloud folder to $DATADIR/updater-$instanceid/downloads/$filename
	 *
	 * @throws \Exception
	 */
	public function downloadUpdate() {
		$this->silentLog('[info] downloadUpdate()');

		$response = $this->getUpdateServerResponse();
		$storageLocation = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid') . '/downloads/';
		if(file_exists($storageLocation)) {
			$this->silentLog('[info] storage location exists');
			$this->recursiveDelete($storageLocation);
		}
		$state = mkdir($storageLocation, 0750, true);
		if($state === false) {
			throw new \Exception('Could not mkdir storage location');
		}

		$fp = fopen($storageLocation . basename($response['url']), 'w+');
		$ch = curl_init($response['url']);
		curl_setopt_array($ch, [
			CURLOPT_FILE => $fp,
			CURLOPT_USERAGENT => 'Nextcloud Updater',
		]);

		if ($this->getConfigOption('proxy') !== null) {
			curl_setopt_array($ch, [
				CURLOPT_PROXY => $this->getConfigOption('proxy'),
				CURLOPT_PROXYUSERPWD => $this->getConfigOption('proxyuserpwd'),
				CURLOPT_HTTPPROXYTUNNEL => $this->getConfigOption('proxy') ? 1 : 0,
			]);
		}

		if(curl_exec($ch) === false) {
			throw new \Exception('Curl error: ' . curl_error($ch));
		}
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($httpCode !== 200) {
			$statusCodes = [
				400 => 'Bad request',
				401 => 'Unauthorized',
				403 => 'Forbidden',
				404 => 'Not Found',
				500 => 'Internal Server Error',
				502 => 'Bad Gateway',
				503 => 'Service Unavailable',
				504 => 'Gateway Timeout',
			];

			$message = 'Download failed';
			if(isset($statusCodes[$httpCode])) {
				$message .= ' - ' . $statusCodes[$httpCode] . ' (HTTP ' . $httpCode . ')';
			} else {
				$message .= ' - HTTP status code: ' . $httpCode;
			}

			$curlErrorMessage = curl_error($ch);
			if(!empty($curlErrorMessage)) {
				$message .= ' - curl error message: ' . $curlErrorMessage;
			}

			$message .= ' - URL: ' . htmlentities($response['url']);

			throw new \Exception($message);
		}
		curl_close($ch);
		fclose($fp);

		$this->silentLog('[info] end of downloadUpdate()');
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	private function getDownloadedFilePath() {
		$storageLocation = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid') . '/downloads/';
		$this->silentLog('[info] storage location: ' . $storageLocation);

		$filesInStorageLocation = scandir($storageLocation);
		$files = array_values(array_filter($filesInStorageLocation, function($path){
			return $path !== '.' && $path !== '..';
		}));
		// only the downloaded archive
		if(count($files) !== 1) {
			throw new \Exception('There are more files than the downloaded archive in the downloads/ folder.');
		}
		return $storageLocation . '/' . $files[0];
	}

	/**
	 * Verifies the integrity of the downloaded file
	 *
	 * @throws \Exception
	 */
	public function verifyIntegrity() {
		$this->silentLog('[info] verifyIntegrity()');

		if($this->getCurrentReleaseChannel() === 'daily') {
			$this->silentLog('[info] current channel is "daily" which is not signed. Skipping verification.');
			return;
		}

		$response = $this->getUpdateServerResponse();
		if(!isset($response['signature'])) {
			throw new \Exception('No signature specified for defined update');
		}

		$certificate = <<<EOF
-----BEGIN CERTIFICATE-----
MIIEojCCA4qgAwIBAgICEAAwDQYJKoZIhvcNAQELBQAwezELMAkGA1UEBhMCREUx
GzAZBgNVBAgMEkJhZGVuLVd1ZXJ0dGVtYmVyZzEXMBUGA1UECgwOTmV4dGNsb3Vk
IEdtYkgxNjA0BgNVBAMMLU5leHRjbG91ZCBDb2RlIFNpZ25pbmcgSW50ZXJtZWRp
YXRlIEF1dGhvcml0eTAeFw0xNjA2MTIyMTA1MDZaFw00MTA2MDYyMTA1MDZaMGYx
CzAJBgNVBAYTAkRFMRswGQYDVQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxEjAQBgNV
BAcMCVN0dXR0Z2FydDEXMBUGA1UECgwOTmV4dGNsb3VkIEdtYkgxDTALBgNVBAMM
BGNvcmUwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDUxcrn2DC892IX
8+dJjZVh9YeHF65n2ha886oeAizOuHBdWBfzqt+GoUYTOjqZF93HZMcwy0P+xyCf
Qqak5Ke9dybN06RXUuGP45k9UYBp03qzlUzCDalrkj+Jd30LqcSC1sjRTsfuhc+u
vH1IBuBnf7SMUJUcoEffbmmpAPlEcLHxlUGlGnz0q1e8UFzjbEFj3JucMO4ys35F
qZS4dhvCngQhRW3DaMlQLXEUL9k3kFV+BzlkPzVZEtSmk4HJujFCnZj1vMcjQBg/
Bqq1HCmUB6tulnGcxUzt/Z/oSIgnuGyENeke077W3EyryINL7EIyD4Xp7sxLizTM
FCFCjjH1AgMBAAGjggFDMIIBPzAJBgNVHRMEAjAAMBEGCWCGSAGG+EIBAQQEAwIG
QDAzBglghkgBhvhCAQ0EJhYkT3BlblNTTCBHZW5lcmF0ZWQgU2VydmVyIENlcnRp
ZmljYXRlMB0GA1UdDgQWBBQwc1H9AL8pRlW2e5SLCfPPqtqc0DCBpQYDVR0jBIGd
MIGagBRt6m6qqTcsPIktFz79Ru7DnnjtdKF+pHwwejELMAkGA1UEBhMCREUxGzAZ
BgNVBAgMEkJhZGVuLVd1ZXJ0dGVtYmVyZzESMBAGA1UEBwwJU3R1dHRnYXJ0MRcw
FQYDVQQKDA5OZXh0Y2xvdWQgR21iSDEhMB8GA1UEAwwYTmV4dGNsb3VkIFJvb3Qg
QXV0aG9yaXR5ggIQADAOBgNVHQ8BAf8EBAMCBaAwEwYDVR0lBAwwCgYIKwYBBQUH
AwEwDQYJKoZIhvcNAQELBQADggEBADZ6+HV/+0NEH3nahTBFxO6nKyR/VWigACH0
naV0ecTcoQwDjKDNNFr+4S1WlHdwITlnNabC7v9rZ/6QvbkrOTuO9fOR6azp1EwW
2pixWqj0Sb9/dSIVRpSq+jpBE6JAiX44dSR7zoBxRB8DgVO2Afy0s80xEpr5JAzb
NYuPS7M5UHdAv2dr16fDcDIvn+vk92KpNh1NTeZFjBbRVQ9DXrgkRGW34TK8uSLI
YG6jnfJ6eJgTaO431ywWPXNg1mUMaT/+QBOgB299QVCKQU+lcZWptQt+RdsJUm46
NY/nARy4Oi4uOe88SuWITj9KhrFmEvrUlgM8FvoXA1ldrR7KiEg=
-----END CERTIFICATE-----
EOF;

		$validSignature = (bool)openssl_verify(
			file_get_contents($this->getDownloadedFilePath()),
			base64_decode($response['signature']),
			$certificate,
			OPENSSL_ALGO_SHA512
		);

		if($validSignature === false) {
			throw new \Exception('Signature of update is not valid');
		}

		$this->silentLog('[info] end of verifyIntegrity()');
	}

	/**
	 * Gets the version as declared in $versionFile
	 *
	 * @param string $versionFile
	 * @return string
	 * @throws \Exception If $OC_Version is not defined in $versionFile
	 */
	private function getVersionByVersionFile($versionFile) {
		require $versionFile;

		if(isset($OC_Version)) {
			/** @var array $OC_Version */
			return implode('.', $OC_Version);
		}

		throw new \Exception("OC_Version not found in $versionFile");
	}

	/**
	 * Extracts the download
	 *
	 * @throws \Exception
	 */
	public function extractDownload() {
		$this->silentLog('[info] extractDownload()');
		$downloadedFilePath = $this->getDownloadedFilePath();

		$zip = new \ZipArchive;
		$zipState = $zip->open($downloadedFilePath);
		if ($zipState === true) {
			$extraction = $zip->extractTo(dirname($downloadedFilePath));
			if($extraction === false) {
				throw new \Exception('Error during unpacking zipfile: '.($zip->getStatusString()));
			}
			$zip->close();
			$state = unlink($downloadedFilePath);
			if($state === false) {
				throw new \Exception("Can't unlink ". $downloadedFilePath);
			}
		} else {
			throw new \Exception("Can't handle ZIP file. Error code is: ".$zipState);
		}

		// Ensure that the downloaded version is not lower
		$downloadedVersion = $this->getVersionByVersionFile(dirname($downloadedFilePath) . '/nextcloud/version.php');
		$currentVersion = $this->getVersionByVersionFile($this->baseDir . '/../version.php');
		if(version_compare($downloadedVersion, $currentVersion, '<')) {
			throw new \Exception('Downloaded version is lower than installed version');
		}

		$this->silentLog('[info] end of extractDownload()');
	}

	/**
	 * Replaces the entry point files with files that only return a 503
	 *
	 * @throws \Exception
	 */
	public function replaceEntryPoints() {
		$this->silentLog('[info] replaceEntryPoints()');

		$filesToReplace = [
			'index.php',
			'status.php',
			'remote.php',
			'public.php',
			'ocs/v1.php',
			'ocs/v2.php',
		];

		$content = "<?php\nhttp_response_code(503);\ndie('Update in process.');";
		foreach($filesToReplace as $file) {
			$this->silentLog('[info] replace ' . $file);
			$parentDir = dirname($this->baseDir . '/../' . $file);
			if(!file_exists($parentDir)) {
				$r = mkdir($parentDir);
				if($r !== true) {
					throw new \Exception('Can\'t create parent directory for entry point: ' . $file);
				}
			}
			$state = file_put_contents($this->baseDir  . '/../' . $file, $content);
			if($state === false) {
				throw new \Exception('Can\'t replace entry point: '.$file);
			}
		}

		$this->silentLog('[info] end of replaceEntryPoints()');
	}

	/**
	 * Recursively deletes the specified folder from the system
	 *
	 * @param string $folder
	 * @throws \Exception
	 */
	private function recursiveDelete($folder) {
		if(!file_exists($folder)) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		$directories = array();
		$files = array();
		foreach ($iterator as $fileInfo) {
			if ($fileInfo->isDir()) {
				$directories[] = $fileInfo->getRealPath();
			} else {
				if ($fileInfo->isLink()) {
					$files[] = $fileInfo->getPathName();
				} else {
					$files[] = $fileInfo->getRealPath();
				}
			}
		}

		foreach ($files as $file) {
			unlink($file);
		}
		foreach ($directories as $dir) {
			rmdir($dir);
		}

		$state = rmdir($folder);
		if($state === false) {
			throw new \Exception('Could not rmdir ' . $folder);
		}
	}

	/**
	 * Delete old files from the system as much as possible
	 *
	 * @throws \Exception
	 */
	public function deleteOldFiles() {
		$this->silentLog('[info] deleteOldFiles()');

		$shippedAppsFile = $this->baseDir . '/../core/shipped.json';
		if(!file_exists($shippedAppsFile)) {
			throw new \Exception('core/shipped.json is not available');
		}

		$newShippedAppsFile = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid') . '/downloads/nextcloud/core/shipped.json';
		if(!file_exists($newShippedAppsFile)) {
			throw new \Exception('core/shipped.json is not available in the new release');
		}

		// Delete shipped apps
		$shippedApps = array_merge(
			json_decode(file_get_contents($shippedAppsFile), true)['shippedApps'],
			json_decode(file_get_contents($newShippedAppsFile), true)['shippedApps']
		);
		foreach($shippedApps as $app) {
			$this->recursiveDelete($this->baseDir . '/../apps/' . $app);
		}

		$configSampleFile = $this->baseDir . '/../config/config.sample.php';
		if(file_exists($configSampleFile)) {
			$this->silentLog('[info] config sample exists');

			// Delete example config
			$state = unlink($configSampleFile);
			if ($state === false) {
				throw new \Exception('Could not unlink sample config');
			}
		}

		$themesReadme = $this->baseDir . '/../themes/README';
		if(file_exists($themesReadme)) {
			$this->silentLog('[info] themes README exists');

			// Delete themes
			$state = unlink($themesReadme);
			if ($state === false) {
				throw new \Exception('Could not delete themes README');
			}
		}
		$this->recursiveDelete($this->baseDir . '/../themes/example/');

		// Delete the rest
		$excludedElements = [
			'.well-known',
			'data',
			'index.php',
			'status.php',
			'remote.php',
			'public.php',
			'ocs/v1.php',
			'ocs/v2.php',
			'config',
			'themes',
			'apps',
			'updater',
		];
		$excludedElements = array_merge($excludedElements, $this->getAppDirectories());
		/**
		 * @var string $path
		 * @var \SplFileInfo $fileInfo
		 */
		foreach ($this->getRecursiveDirectoryIterator() as $path => $fileInfo) {
			$currentDir = $this->baseDir . '/../';
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
			if($fileInfo->isFile() || $fileInfo->isLink()) {
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

		$this->silentLog('[info] end of deleteOldFiles()');
	}

	/**
	 * Moves the specified filed except the excluded elements to the correct position
	 *
	 * @param string $dataLocation
	 * @param array $excludedElements
	 * @throws \Exception
	 */
	private function moveWithExclusions($dataLocation, array $excludedElements) {
		/**
		 * @var \SplFileInfo $fileInfo
		 */
		foreach ($this->getRecursiveDirectoryIterator($dataLocation) as $path => $fileInfo) {
			$fileName = explode($dataLocation, $path)[1];
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
				if(!file_exists($this->baseDir . '/../' . dirname($fileName))) {
					$state = mkdir($this->baseDir . '/../' . dirname($fileName), 0755, true);
					if($state === false) {
						throw new \Exception('Could not mkdir ' . $this->baseDir  . '/../' . dirname($fileName));
					}
				}
				$state = rename($path, $this->baseDir  . '/../' . $fileName);
				if($state === false) {
					throw new \Exception(
						sprintf(
							'Could not rename %s to %s',
							$path,
							$this->baseDir . '/../' . $fileName
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
	}

	/**
	 * Moves the newly downloaded files into place
	 *
	 * @throws \Exception
	 */
	public function moveNewVersionInPlace() {
		$this->silentLog('[info] moveNewVersionInPlace()');

		// Rename everything else except the entry and updater files
		$excludedElements = [
			'updater',
			'index.php',
			'status.php',
			'remote.php',
			'public.php',
			'ocs/v1.php',
			'ocs/v2.php',
		];
		$storageLocation = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid') . '/downloads/nextcloud/';
		$this->silentLog('[info] storage location: ' . $storageLocation);
		$this->moveWithExclusions($storageLocation, $excludedElements);

		// Rename everything except the updater files
		$this->moveWithExclusions($storageLocation, ['updater']);

		$this->silentLog('[info] end of moveNewVersionInPlace()');
	}

	/**
	 * Finalize and cleanup the updater by finally replacing the updater script
	 */
	public function finalize() {
		$this->silentLog('[info] finalize()');

		$storageLocation = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid') . '/downloads/nextcloud/';
		$this->silentLog('[info] storage location: ' . $storageLocation);
		$this->moveWithExclusions($storageLocation, []);
		$state = rmdir($storageLocation);
		if($state === false) {
			throw new \Exception('Could not rmdir $storagelocation');
		}
		$state = unlink($this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid') . '/.step');
		if($state === false) {
			throw new \Exception('Could not rmdir .step');
		}

		if (function_exists('opcache_reset')) {
			$this->silentLog('[info] call opcache_reset()');
			opcache_reset();
		}

		$this->silentLog('[info] end of finalize()');
	}

	/**
	 * @param string $state
	 * @param int $step
	 * @throws \Exception
	 */
	private function writeStep($state, $step) {
		$updaterDir = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid');
		if(!file_exists($updaterDir . '/.step')) {
			if(!file_exists($updaterDir)) {
				$result = mkdir($updaterDir);
				if ($result === false) {
					throw new \Exception('Could not create $updaterDir');
				}
			}
			$result = touch($updaterDir . '/.step');
			if($result === false) {
				throw new \Exception('Could not create .step');
			}
		}

		$result = file_put_contents($updaterDir . '/.step', json_encode(['state' => $state, 'step' => $step]));
		if($result === false) {
			throw new \Exception('Could not write to .step');
		}
	}

	/**
	 * @param int $step
	 * @throws \Exception
	 */
	public function startStep($step) {
		$this->silentLog('[info] startStep("' . $step . '")');
		$this->writeStep('start', $step);
	}

	/**
	 * @param int $step
	 * @throws \Exception
	 */
	public function endStep($step) {
		$this->silentLog('[info] endStep("' . $step . '")');
		$this->writeStep('end', $step);
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function currentStep() {
		$this->silentLog('[info] currentStep()');

		$updaterDir = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid');
		$jsonData = [];
		if(file_exists($updaterDir. '/.step')) {
			$state = file_get_contents($updaterDir . '/.step');
			if ($state === false) {
				throw new \Exception('Could not read from .step');
			}

			$jsonData = json_decode($state, true);
			if (!is_array($jsonData)) {
				throw new \Exception('Can\'t decode .step JSON data');
			}
		}
		return $jsonData;
	}

	/**
	 * Rollback the changes if $step has failed
	 *
	 * @param int $step
	 * @throws \Exception
	 */
	public function rollbackChanges($step) {
		$this->silentLog('[info] rollbackChanges("' . $step . '")');

		$updaterDir = $this->getDataDirectoryLocation() . '/updater-'.$this->getConfigOption('instanceid');
		if(file_exists($updaterDir . '/.step')) {
			$this->silentLog('[info] unlink .step');
			$state = unlink($updaterDir . '/.step');
			if ($state === false) {
				throw new \Exception('Could not delete .step');
			}
		}

		if($step >= 7) {
			$this->silentLog('[info] rollbackChanges - step >= 7');
			// TODO: If it fails after step 7: Rollback
		}
		$this->silentLog('[info] end of  rollbackChanges()');
	}

	/**
	 * Logs an exception with current datetime prepended to updater.log
	 *
	 * @param \Exception $e
	 * @throws LogException
	 */
	public function logException(\Exception $e) {
		$message = '[error] ';

		$message .= 'Exception: ' . get_class($e) . PHP_EOL;
		$message .= 'Message: ' . $e->getMessage() . PHP_EOL;
		$message .= 'Code:' . $e->getCode() . PHP_EOL;
		$message .= 'Trace:' . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
		$message .= 'File:' . $e->getFile() . PHP_EOL;
		$message .= 'Line:' . $e->getLine() . PHP_EOL;
		if($e instanceof UpdateException) {
			$message .= 'Data:' . PHP_EOL . print_r($e->getData(), true) . PHP_EOL;
		}
		$this->log($message);
	}

	/**
	 * Logs a message with current datetime prepended to updater.log
	 *
	 * @param string $message
	 * @throws LogException
	 */
	public function log($message) {
		$updaterLogPath = $this->getDataDirectoryLocation() . '/updater.log';

		$fh = fopen($updaterLogPath, 'a');
		if($fh === false) {
			throw new LogException('Could not open updater.log');
		}

		if($this->requestID === null) {
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$charactersLength = strlen($characters);
			$randomString = '';
			for ($i = 0; $i < 10; $i++) {
				$randomString .= $characters[rand(0, $charactersLength - 1)];
			}
			$this->requestID = $randomString;
		}

		$logLine = date(\DateTime::ISO8601) . ' ' . $this->requestID . ' ' . $message . PHP_EOL;

		$result = fwrite($fh, $logLine);
		if($result === false) {
			throw new LogException('Could not write to updater.log');
		}

		fclose($fh);
	}


	/**
	 * Logs a message with current datetime prepended to updater.log but drops possible LogException
	 *
	 * @param string $message
	 */
	public function silentLog($message) {
		try {
			$this->log($message);
		} catch (LogException $logE) {
			/* ignore log exception here (already detected later anyways) */
		}
	}


	/**
	 * Logs current version
	 *
	 */
	public function logVersion() {
		$this->silentLog('[info] current version: ' . $this->currentVersion . ' build time: ' . $this->buildTime);
	}
}
