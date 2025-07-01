<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


class UpdateException extends \Exception {

	/** @param list<string> $data */
	public function __construct(
		protected array $data,
	) {
	}

	/** @return list<string> */
	public function getData(): array {
		return $this->data;
	}
}


class LogException extends \Exception {
}


use CurlHandle;

class Updater {
	private string $nextcloudDir;
	private array $configValues = [];
	private string $currentVersion = 'unknown';
	private string $buildTime;
	private bool $updateAvailable = false;
	private ?string $requestID = null;
	private bool $disabled = false;
	private int $previousProgress = 0;

	/**
	 * Updater constructor
	 * @param string $baseDir the absolute path to the /updater/ directory in the Nextcloud root
	 * @throws \Exception
	 */
	public function __construct(
		string $baseDir,
	) {
		$this->nextcloudDir = realpath(dirname($baseDir));

		[$this->configValues] = $this->readConfigFile();

		if (php_sapi_name() !== 'cli' && ($this->configValues['upgrade.disable-web'] ?? false)) {
			// updater disabled
			$this->disabled = true;
			return;
		}

		$dataDir = $this->getUpdateDirectoryLocation();
		if (empty($dataDir)) {
			throw new \Exception('Could not read data directory from config.php.');
		}

		$versionFileName = $this->nextcloudDir . '/version.php';
		if (!file_exists($versionFileName)) {
			// fallback to version in config.php
			$version = $this->getConfigOptionString('version');
			$buildTime = '';
		} else {
			/** @var ?string $OC_Build */
			require_once $versionFileName;
			/** @psalm-suppress UndefinedVariable
			 * @var ?string $version
			 */
			$version = $OC_VersionString;
			$buildTime = $OC_Build;
		}

		if (!is_string($version) || !is_string($buildTime)) {
			return;
		}

		// normalize version to 3 digits
		$splittedVersion = explode('.', $version);
		if (sizeof($splittedVersion) >= 3) {
			$splittedVersion = array_slice($splittedVersion, 0, 3);
		}

		$this->currentVersion = implode('.', $splittedVersion);
		$this->buildTime = $buildTime;
	}

	/**
	 * @return array{array, string}
	 */
	private function readConfigFile(): array {
		if ($dir = getenv('NEXTCLOUD_CONFIG_DIR')) {
			$configFileName = realpath($dir . '/config.php');
		} else {
			$configFileName = $this->nextcloudDir . '/config/config.php';
		}
		if (!file_exists($configFileName)) {
			throw new \Exception('Could not find config.php (' . $configFileName . '). Is this file in the "updater" subfolder of Nextcloud?');
		}
		$filePointer = @fopen($configFileName, 'r');
		if ($filePointer === false) {
			throw new \Exception('Could not open config.php (' . $configFileName . ').');
		}
		if (!flock($filePointer, LOCK_SH)) {
			throw new \Exception('Could not acquire a shared lock on the config file (' . $configFileName . ')');
		}

		try {
			require $configFileName;
		} finally {
			// Close the file pointer and release the lock
			flock($filePointer, LOCK_UN);
			fclose($filePointer);
		}

		/** @var array $CONFIG */
		return [$CONFIG,$configFileName];
	}

	/**
	 * Returns whether the web updater is disabled
	 */
	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * Returns current version or "unknown" if this could not be determined.
	 */
	public function getCurrentVersion(): string {
		return $this->currentVersion;
	}

	/**
	 * Returns currently used release channel
	 */
	private function getCurrentReleaseChannel(): string {
		return $this->getConfigOptionString('updater.release.channel') ?? 'stable';
	}

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function checkForUpdate() {
		$response = $this->getUpdateServerResponse();

		$this->silentLog('[info] checkForUpdate() ' . print_r($response, true));

		$version = isset($response['version']) ? (string)$response['version'] : '';
		$versionString = isset($response['versionstring']) ? (string)$response['versionstring'] : '';

		if ($version !== '' && $version !== $this->currentVersion) {
			$this->updateAvailable = true;
			$releaseChannel = $this->getCurrentReleaseChannel();
			$updateText = 'Update to ' . htmlentities($versionString) . ' available. (channel: "' . htmlentities($releaseChannel) . '")<br /><span class="light">Following file will be downloaded automatically:</span> <code class="light">' . (string)$response['url'] . '</code>';

			// only show changelog link for stable releases (non-RC & non-beta)
			if (!preg_match('!(rc|beta)!i', $versionString)) {
				$changelogURL = $this->getChangelogURL(substr($version, 0, strrpos($version, '.') ?: 0));
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
	 */
	public function updateAvailable(): bool {
		return $this->updateAvailable;
	}

	/**
	 * Returns the specified config option
	 */
	public function getConfigOption(string $key): mixed {
		return $this->configValues[$key] ?? null;
	}

	/**
	 * Returns the specified string config option
	 */
	public function getConfigOptionString(string $key): ?string {
		if (isset($this->configValues[$key])) {
			if (!is_string($this->configValues[$key])) {
				$this->silentLog('[error] Config key ' . $key . ' should be a string, found ' . gettype($this->configValues[$key]));
			}
			return (string)$this->configValues[$key];
		} else {
			return null;
		}
	}

	/**
	 * Returns the specified mandatory string config option
	 */
	public function getConfigOptionMandatoryString(string $key): string {
		if (isset($this->configValues[$key])) {
			if (!is_string($this->configValues[$key])) {
				$this->silentLog('[error] Config key ' . $key . ' should be a string, found ' . gettype($this->configValues[$key]));
			}
			return (string)$this->configValues[$key];
		} else {
			throw new \Exception('Config key ' . $key . ' is missing');
		}
	}

	/**
	 * Gets the data directory location on the local filesystem
	 */
	private function getUpdateDirectoryLocation(): string {
		return $this->getConfigOptionString('updatedirectory') ?? $this->getConfigOptionString('datadirectory') ?? '';
	}

	/**
	 * Returns the expected files and folders as array
	 */
	private function getExpectedElementsList(): array {
		$expected = [
			// Generic
			'.',
			'..',
			// Folders
			'.reuse',
			'.well-known',
			'3rdparty',
			'apps',
			'config',
			'core',
			'data',
			'dist',
			'l10n',
			'lib',
			'LICENSES',
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
			'composer.json',
			'composer.lock',
			'console.php',
			'cron.php',
			'index.php',
			'package.json',
			'package-lock.json',
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
			'REUSE.toml',
		];
		return array_merge($expected, $this->getAppDirectories());
	}

	/**
	 * Returns app directories specified in config.php
	 *
	 * @return list<string> Paths relative to nextcloud root directory
	 */
	private function getAppDirectories(): array {
		$expected = [];
		if ($appsPaths = $this->getConfigOption('apps_paths')) {
			if (!is_array($appsPaths)) {
				throw new \Exception('Configuration key apps_paths should be an array');
			}

			foreach ($appsPaths as $appsPath) {
				if (!is_array($appsPath) || !isset($appsPath['path']) || !is_string($appsPath['path'])) {
					throw new \Exception('Invalid configuration in apps_paths configuration key');
				}
				$appDir = basename($appsPath['path']);
				if (strpos($appsPath['path'], $this->nextcloudDir . '/') === 0) {
					$relativePath = substr($appsPath['path'], strlen($this->nextcloudDir . '/'));
					if ($relativePath !== 'apps') {
						$expected[] = $relativePath;
					}
				}
			}
		}
		return $expected;
	}

	/**
	 * Gets the recursive directory iterator over the Nextcloud folder
	 *
	 * @param list<string> $excludedPaths Name of root directories to skip
	 * @return \Generator<string, \SplFileInfo>
	 */
	private function getRecursiveDirectoryIterator(string $folder, array $excludedPaths): \Generator {
		foreach ($excludedPaths as $element) {
			if (strpos($element, '/') !== false) {
				throw new \Exception('Excluding subpaths is not supported yet');
			}
		}
		$exclusions = array_flip($excludedPaths);

		$handle = opendir($folder);

		if ($handle === false) {
			throw new \Exception('Could not open ' . $folder);
		}

		/* Store first level children in an array to avoid trouble if changes happen while iterating */
		$children = [];
		while ($name = readdir($handle)) {
			if (in_array($name, ['.', '..'])) {
				continue;
			}
			if (isset($exclusions[$name])) {
				continue;
			}
			$children[] = $name;
		}

		closedir($handle);

		foreach ($children as $name) {
			$path = $folder . '/' . $name;
			if (is_dir($path)) {
				yield from $this->getRecursiveDirectoryIterator($path, []);
			}
			yield $path => new \SplFileInfo($path);
		}
	}

	/**
	 * Checks for files that are unexpected.
	 */
	public function checkForExpectedFilesAndFolders(): void {
		$this->silentLog('[info] checkForExpectedFilesAndFolders()');

		$expectedElements = $this->getExpectedElementsList();
		$unexpectedElements = [];
		foreach (new \DirectoryIterator($this->nextcloudDir) as $fileInfo) {
			if (array_search($fileInfo->getFilename(), $expectedElements) === false) {
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
	public function checkWritePermissions(): void {
		$this->silentLog('[info] checkWritePermissions()');

		$excludedElements = [
			'.rnd',
			'.well-known',
			'data',
		];

		$notWritablePaths = [];
		foreach ($this->getRecursiveDirectoryIterator($this->nextcloudDir, $excludedElements) as $path => $fileInfo) {
			if (!$fileInfo->isWritable()) {
				$notWritablePaths[] = $fileInfo->getFilename();
			}
		}
		if (count($notWritablePaths) > 0) {
			throw new UpdateException($notWritablePaths);
		}

		$this->silentLog('[info] end of checkWritePermissions()');
	}

	/**
	 * Sets the maintenance mode to the defined value
	 *
	 * @throws \Exception when config.php can't be written
	 */
	public function setMaintenanceMode(bool $state): void {
		$this->silentLog('[info] setMaintenanceMode("' . ($state ? 'true' : 'false') . '")');

		[$CONFIG, $configFileName] = $this->readConfigFile();
		$this->silentLog('[info] configFileName ' . $configFileName);

		$CONFIG['maintenance'] = $state;
		$content = "<?php\n";
		$content .= '$CONFIG = ';
		$content .= var_export($CONFIG, true);
		$content .= ";\n";
		$writeSuccess = file_put_contents($configFileName, $content, LOCK_EX);
		if ($writeSuccess === false) {
			throw new \Exception('Could not write to config.php (' . $configFileName . ')');
		}
		$this->silentLog('[info] end of setMaintenanceMode()');
	}

	/**
	 * Creates a backup of all files and moves it into data/updater-$instanceid/backups/nextcloud-X-Y-Z/
	 *
	 * @throws \Exception
	 */
	public function createBackup(): void {
		$this->silentLog('[info] createBackup()');

		$excludedElements = [
			'.rnd',
			'.well-known',
			'data',
		];

		// Create new folder for the backup
		$backupFolderLocation = $this->getUpdateDirectoryLocation() . '/updater-' . $this->getConfigOptionMandatoryString('instanceid') . '/backups/nextcloud-' . $this->getConfigOptionMandatoryString('version') . '-' . time() . '/';
		$this->silentLog('[info] backup folder location: ' . $backupFolderLocation);

		$state = mkdir($backupFolderLocation, 0750, true);
		if ($state === false) {
			throw new \Exception('Could not create backup folder location');
		}

		foreach ($this->getRecursiveDirectoryIterator($this->nextcloudDir, $excludedElements) as $absolutePath => $fileInfo) {
			$relativePath = explode($this->nextcloudDir, $absolutePath)[1];
			$relativeDirectory = dirname($relativePath);

			// Create folder if it doesn't exist
			if (!file_exists($backupFolderLocation . '/' . $relativeDirectory)) {
				$state = mkdir($backupFolderLocation . '/' . $relativeDirectory, 0750, true);
				if ($state === false) {
					throw new \Exception('Could not create folder: ' . $backupFolderLocation . '/' . $relativeDirectory);
				}
			}

			// If it is a file copy it
			if ($fileInfo->isFile()) {
				$state = copy($fileInfo->getRealPath(), $backupFolderLocation . $relativePath);
				if ($state === false) {
					$message = sprintf(
						'Could not copy "%s" to "%s"',
						$fileInfo->getRealPath(),
						$backupFolderLocation . $relativePath
					);

					if (is_readable($fileInfo->getRealPath()) === false) {
						$message = sprintf(
							'%s. Source %s is not readable',
							$message,
							$fileInfo->getRealPath()
						);
					}

					if (is_writable($backupFolderLocation . $relativePath) === false) {
						$message = sprintf(
							'%s. Destination %s is not writable',
							$message,
							$backupFolderLocation . $relativePath
						);
					}

					throw new \Exception($message);
				}
			}
		}
		$this->silentLog('[info] end of createBackup()');
	}

	private function getChangelogURL(string $versionString): string {
		$this->silentLog('[info] getChangelogURL()');
		$changelogWebsite = 'https://nextcloud.com/changelog/';
		$changelogURL = $changelogWebsite . '#' . str_replace('.', '-', $versionString);
		return $changelogURL;
	}

	/**
	 * @throws \Exception
	 */
	private function getUpdateServerResponse(): array {
		$this->silentLog('[info] getUpdateServerResponse()');

		$updaterServer = $this->getConfigOptionString('updater.server.url');
		if ($updaterServer === null) {
			// FIXME: used deployed URL
			$updaterServer = 'https://updates.nextcloud.com/updater_server/';
		}
		$this->silentLog('[info] updaterServer: ' . $updaterServer);

		$releaseChannel = $this->getCurrentReleaseChannel();
		$this->silentLog('[info] releaseChannel: ' . $releaseChannel);
		$this->silentLog('[info] internal version: ' . $this->getConfigOptionMandatoryString('version'));

		$updateURL = $updaterServer . '?version=' . str_replace('.', 'x', $this->getConfigOptionMandatoryString('version')) . 'xxx' . $releaseChannel . 'xx' . urlencode($this->buildTime) . 'x' . PHP_MAJOR_VERSION . 'x' . PHP_MINOR_VERSION . 'x' . PHP_RELEASE_VERSION;
		$this->silentLog('[info] updateURL: ' . $updateURL);

		// Download update response
		$curl = $this->getCurl($updateURL);

		/** @var false|string $response */
		$response = curl_exec($curl);
		if ($response === false) {
			throw new \Exception('Could not do request to updater server: ' . curl_error($curl));
		}
		curl_close($curl);

		// Response can be empty when no update is available
		if ($response === '') {
			return [];
		}

		$xml = simplexml_load_string($response);
		if ($xml === false) {
			throw new \Exception('Could not parse updater server XML response');
		}
		$response = get_object_vars($xml);
		$this->silentLog('[info] getUpdateServerResponse response: ' . print_r($response, true));
		return $response;
	}

	/**
	 * Downloads the nextcloud folder to $DATADIR/updater-$instanceid/downloads/$filename
	 *
	 * Logs download progress
	 * Resumes incomplete downloads if possible
	 * Supports outbound proxy usage
	 * Logs download statistics upon completion
	 *
	 * TODO: Provide download progress in real-time (in both CLI and Web modes)
	 *
	 * @throws \Exception
	 */
	public function downloadUpdate(?string $url = null): void {
		$this->silentLog('[info] downloadUpdate()');

		if ($url) {
			// If a URL is provided, use it directly
			$downloadURLs = [$url];
		} else {
			// Otherwise, get the download URLs from the update server
			$downloadURLs = $this->getDownloadURLs();
		}

		$this->silentLog('[info] will try to download archive from: ' . implode(', ', $downloadURLs));

		$storageLocation = $this->getUpdateDirectoryLocation() . '/updater-' . $this->getConfigOptionMandatoryString('instanceid') . '/downloads/';

		if (!file_exists($storageLocation)) {
			$state = mkdir($storageLocation, 0750, true);
			if ($state === false) {
				throw new \Exception('Could not mkdir storage location');
			}
			$this->silentLog('[info] storage location created');
		} else {
			$this->silentLog('[info] storage location already exists');
			// clean-up leftover extracted content from any prior runs, but leave any downloaded Archives alone
			if (file_exists($storageLocation . 'nextcloud/')) {
				$this->silentLog('[info] extracted Archive location exists');
				$this->recursiveDelete($storageLocation . 'nextcloud/');
			}
		}

		foreach ($downloadURLs as $url) {
			$this->previousProgress = 0;
			$saveLocation = $storageLocation . basename($url);
			if ($this->downloadArchive($url, $saveLocation)) {
				return;
			}
		}

		throw new \Exception('All downloads failed. See updater logs for more information.');
	}

	private function getDownloadURLs(): array {
		$response = $this->getUpdateServerResponse();
		$downloadURLs = [];
		if (!isset($response['downloads']) || !is_array($response['downloads'])) {
			if (isset($response['url']) && is_string($response['url'])) {
				// Compatibility with previous verison of updater_server
				$ext = pathinfo($response['url'], PATHINFO_EXTENSION);
				$response['downloads'] = [
					$ext => [$response['url']]
				];
			} else {
				throw new \Exception('Response from update server is missing download URLs');
			}
		}
		foreach ($response['downloads'] as $format => $urls) {
			if (!$this->isAbleToDecompress($format)) {
				continue;
			}
			foreach ($urls as $url) {
				if (!is_string($url)) {
					continue;
				}
				$downloadURLs[] = $url;
			}
		}

		if (empty($downloadURLs)) {
			throw new \Exception('Your PHP install is not able to decompress any archive. Try to install modules like zip or bzip.');
		}

		return array_unique($downloadURLs);

	}

	private function getCurl(string $url): CurlHandle {
		$ch = curl_init($url);
		if ($ch === false) {
			throw new \Exception('Fail to open cUrl handler');
		}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => 'Nextcloud Updater',
			CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_MAXREDIRS => 2,
		]);

		if ($this->getConfigOption('proxy') !== null) {
			curl_setopt_array($ch, [
				CURLOPT_PROXY => $this->getConfigOptionString('proxy'),
				CURLOPT_PROXYUSERPWD => $this->getConfigOptionString('proxyuserpwd'),
				CURLOPT_HTTPPROXYTUNNEL => $this->getConfigOption('proxy') ? 1 : 0,
			]);
		}

		return $ch;
	}

	private function downloadArchive(string $fromUrl, string $toLocation): bool {
		$ch = $this->getCurl($fromUrl);

		// see if there's an existing incomplete download to resume
		if (is_file($toLocation)) {
			$size = (int)filesize($toLocation);
			$range = $size . '-';
			curl_setopt($ch, CURLOPT_RANGE, $range);
			$this->silentLog('[info] previous download found; resuming from ' . $this->formatBytes($size));
		}

		$fp = fopen($toLocation, 'ab');
		if ($fp === false) {
			throw new \Exception('Fail to open file in ' . $toLocation);
		}

		curl_setopt_array($ch, [
			CURLOPT_NOPROGRESS => false,
			CURLOPT_PROGRESSFUNCTION => [$this, 'downloadProgressCallback'],
			CURLOPT_FILE => $fp,
		]);

		if (curl_exec($ch) === false) {
			throw new \Exception('Curl error: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($httpCode !== 200 && $httpCode !== 206) {
			fclose($fp);
			unlink($toLocation);
			$this->silentLog('[warn] fail to download archive from ' . $fromUrl . '. Error: ' . $httpCode . ' ' . curl_error($ch));
			curl_close($ch);

			return false;
		}
		// download succeeded
		$info = curl_getinfo($ch);
		$this->silentLog('[info] download stats: size=' . $this->formatBytes((int)$info['size_download']) . ' bytes; total_time=' . round($info['total_time'], 2) . ' secs; avg speed=' . $this->formatBytes((int)$info['speed_download']) . '/sec');

		curl_close($ch);
		fclose($fp);

		$this->silentLog('[info] end of downloadUpdate()');
		return true;
	}

	/**
	 * Check if PHP is able to decompress archive format
	 */
	private function isAbleToDecompress(string $ext): bool {
		// Only zip is supported for now
		return $ext === 'zip' && extension_loaded($ext);
	}

	private function downloadProgressCallback(\CurlHandle $resource, int $download_size, int $downloaded, int $upload_size, int $uploaded): void {
		if ($download_size !== 0) {
			$progress = (int)round($downloaded * 100 / $download_size);
			if ($progress > $this->previousProgress) {
				$this->previousProgress = $progress;
				// log every 2% increment for the first 10% then only log every 10% increment after that
				if ($progress % 10 === 0 || ($progress < 10 && $progress % 2 === 0)) {
					$this->silentLog("[info] download progress: $progress% (" . $this->formatBytes($downloaded) . ' of ' . $this->formatBytes($download_size) . ')');
				}
			}
		}
	}

	private function formatBytes(int $bytes, int $precision = 2): string {
		$units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);

		// Uncomment one of the following alternatives
		$bytes /= pow(1024, $pow);
		// $bytes /= (1 << (10 * $pow));

		return round($bytes, $precision) . $units[(int)$pow];
	}

	/**
	 * @throws \Exception
	 */
	private function getDownloadedFilePath(): string {
		$storageLocation = $this->getUpdateDirectoryLocation() . '/updater-' . $this->getConfigOptionMandatoryString('instanceid') . '/downloads/';
		$this->silentLog('[info] storage location: ' . $storageLocation);

		$filesInStorageLocation = scandir($storageLocation);
		$files = array_values(array_filter($filesInStorageLocation, function (string $path) {
			// Match files with - in the name and extension (*-*.*)
			return preg_match('/^.*-.*\..*$/i', $path);
		}));
		// only the downloaded archive
		if (count($files) !== 1) {
			throw new \Exception('There are more files than the downloaded archive in the downloads/ folder.');
		}
		return $storageLocation . $files[0];
	}

	/**
	 * Verifies the integrity of the downloaded file
	 *
	 * @throws \Exception
	 */
	public function verifyIntegrity(?string $urlOverride = null): void {
		$this->silentLog('[info] verifyIntegrity()');

		if ($this->getCurrentReleaseChannel() === 'daily') {
			$this->silentLog('[info] current channel is "daily" which is not signed. Skipping verification.');
			return;
		}

		if ($urlOverride) {
			$this->silentLog('[info] custom download url provided, cannot verify signature');
			return;
		}

		$response = $this->getUpdateServerResponse();
		if (empty($response['signature'])) {
			throw new \Exception('No signature specified for defined update');
		}
		if (!is_string($response['signature'])) {
			throw new \Exception('Signature specified for defined update should be a string');
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

		$validSignature = openssl_verify(
			file_get_contents($this->getDownloadedFilePath()),
			base64_decode($response['signature']),
			$certificate,
			OPENSSL_ALGO_SHA512
		) === 1;

		if ($validSignature === false) {
			throw new \Exception('Signature of update is not valid');
		}

		$this->silentLog('[info] end of verifyIntegrity()');
	}

	/**
	 * Gets the version as declared in $versionFile
	 *
	 * @throws \Exception If $OC_Version is not defined in $versionFile
	 */
	private function getVersionByVersionFile(string $versionFile): string {
		/** @psalm-suppress UnresolvableInclude */
		require $versionFile;

		/** @psalm-suppress UndefinedVariable */
		if (isset($OC_Version)) {
			/** @var string[] $OC_Version */
			return implode('.', $OC_Version);
		}

		throw new \Exception("OC_Version not found in $versionFile");
	}

	/**
	 * Extracts the download
	 *
	 * @throws \Exception
	 */
	public function extractDownload(): void {
		$this->silentLog('[info] extractDownload()');
		$downloadedFilePath = $this->getDownloadedFilePath();

		if (!extension_loaded('zip')) {
			throw new \Exception('Required PHP extension missing: zip');
		}

		$libzip_version = defined('ZipArchive::LIBZIP_VERSION') ? \ZipArchive::LIBZIP_VERSION : 'Unknown (but old)';
		$this->silentLog('[info] Libzip version detected: ' . $libzip_version);

		$zip = new \ZipArchive;
		$zipState = $zip->open($downloadedFilePath);
		if ($zipState === true) {
			$extraction = $zip->extractTo(dirname($downloadedFilePath));
			if ($extraction === false) {
				throw new \Exception('Error during unpacking zipfile: ' . ($zip->getStatusString()));
			}
			$zip->close();
			$state = unlink($downloadedFilePath);
			if ($state === false) {
				throw new \Exception("Can't unlink " . $downloadedFilePath);
			}
		} else {
			throw new \Exception("Can't handle ZIP file. Error code is: " . print_r($zipState, true));
		}

		// Ensure that the downloaded version is not lower
		$downloadedVersion = $this->getVersionByVersionFile(dirname($downloadedFilePath) . '/nextcloud/version.php');
		$currentVersion = $this->getVersionByVersionFile($this->nextcloudDir . '/version.php');
		if (version_compare($downloadedVersion, $currentVersion, '<')) {
			throw new \Exception('Downloaded version is lower than installed version');
		}

		$this->silentLog('[info] end of extractDownload()');
	}

	/**
	 * Replaces the entry point files with files that only return a 503
	 *
	 * @throws \Exception
	 */
	public function replaceEntryPoints(): void {
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
		foreach ($filesToReplace as $file) {
			$this->silentLog('[info] replace ' . $file);
			$parentDir = dirname($this->nextcloudDir . '/' . $file);
			if (!file_exists($parentDir)) {
				$r = mkdir($parentDir);
				if ($r !== true) {
					throw new \Exception('Can\'t create parent directory for entry point: ' . $file);
				}
			}
			$state = file_put_contents($this->nextcloudDir . '/' . $file, $content);
			if ($state === false) {
				throw new \Exception('Can\'t replace entry point: ' . $file);
			}
		}

		$this->silentLog('[info] end of replaceEntryPoints()');
	}

	/**
	 * Recursively deletes the specified folder from the system
	 *
	 * @throws \Exception
	 */
	private function recursiveDelete(string $folder): void {
		if (!file_exists($folder)) {
			return;
		}

		$directories = [];
		$files = [];
		foreach ($this->getRecursiveDirectoryIterator($folder, []) as $fileInfo) {
			if ($fileInfo->isDir()) {
				rmdir($fileInfo->getRealPath());
			} else {
				if ($fileInfo->isLink()) {
					unlink($fileInfo->getPathName());
				} else {
					unlink($fileInfo->getRealPath());
				}
			}
		}

		$state = rmdir($folder);
		if ($state === false) {
			throw new \Exception('Could not rmdir ' . $folder);
		}
	}

	/**
	 * Delete old files from the system as much as possible
	 *
	 * @throws \Exception
	 */
	public function deleteOldFiles(): void {
		$this->silentLog('[info] deleteOldFiles()');

		$shippedAppsFile = $this->nextcloudDir . '/core/shipped.json';
		$shippedAppsFileContent = file_get_contents($shippedAppsFile);
		if ($shippedAppsFileContent === false) {
			throw new \Exception('core/shipped.json is not available');
		}
		$shippedAppsFileContentDecoded = json_decode($shippedAppsFileContent, true);
		if (!is_array($shippedAppsFileContentDecoded)
			|| !is_array($shippedApps = $shippedAppsFileContentDecoded['shippedApps'] ?? [])) {
			throw new \Exception('core/shipped.json content is invalid');
		}

		$newShippedAppsFile = $this->getUpdateDirectoryLocation() . '/updater-' . $this->getConfigOptionMandatoryString('instanceid') . '/downloads/nextcloud/core/shipped.json';
		$newShippedAppsFileContent = file_get_contents($newShippedAppsFile);
		if ($newShippedAppsFileContent === false) {
			throw new \Exception('core/shipped.json is not available in the new release');
		}
		$newShippedAppsFileContentDecoded = json_decode($newShippedAppsFileContent, true);
		if (!is_array($newShippedAppsFileContentDecoded)
			|| !is_array($newShippedApps = $newShippedAppsFileContentDecoded['shippedApps'] ?? [])) {
			throw new \Exception('core/shipped.json content is invalid in the new release');
		}

		// Delete shipped apps
		$shippedApps = array_merge($shippedApps, $newShippedApps);
		/** @var string $app */
		foreach ($shippedApps as $app) {
			$this->recursiveDelete($this->nextcloudDir . '/apps/' . $app);
		}

		$configSampleFile = $this->nextcloudDir . '/config/config.sample.php';
		if (file_exists($configSampleFile)) {
			$this->silentLog('[info] config sample exists');

			// Delete example config
			$state = unlink($configSampleFile);
			if ($state === false) {
				throw new \Exception('Could not unlink sample config');
			}
		}

		$themesReadme = $this->nextcloudDir . '/themes/README';
		if (file_exists($themesReadme)) {
			$this->silentLog('[info] themes README exists');

			// Delete themes
			$state = unlink($themesReadme);
			if ($state === false) {
				throw new \Exception('Could not delete themes README');
			}
		}
		$this->recursiveDelete($this->nextcloudDir . '/themes/example/');

		// Delete the rest
		$excludedElements = [
			'.well-known',
			'data',
			'index.php',
			'status.php',
			'remote.php',
			'public.php',
			'ocs',
			'config',
			'themes',
			'apps',
			'updater',
		];
		$excludedElements = array_merge($excludedElements, $this->getAppDirectories());
		foreach ($this->getRecursiveDirectoryIterator($this->nextcloudDir, $excludedElements) as $path => $fileInfo) {
			if ($fileInfo->isFile() || $fileInfo->isLink()) {
				$state = unlink($path);
				if ($state === false) {
					throw new \Exception('Could not unlink: ' . $path);
				}
			} elseif ($fileInfo->isDir()) {
				$state = rmdir($path);
				if ($state === false) {
					throw new \Exception('Could not rmdir: ' . $path);
				}
			}
		}

		$this->silentLog('[info] end of deleteOldFiles()');
	}

	/**
	 * Moves the specified files except the excluded elements to the correct position
	 *
	 * @param list<string> $excludedElements Name of root directories to skip
	 * @throws \Exception
	 */
	private function moveWithExclusions(string $dataLocation, array $excludedElements): void {
		foreach ($this->getRecursiveDirectoryIterator($dataLocation, $excludedElements) as $path => $fileInfo) {
			$fileName = explode($dataLocation, $path)[1];

			if ($fileInfo->isFile()) {
				if (!file_exists($this->nextcloudDir . '/' . dirname($fileName))) {
					$state = mkdir($this->nextcloudDir . '/' . dirname($fileName), 0755, true);
					if ($state === false) {
						throw new \Exception('Could not mkdir ' . $this->nextcloudDir . '/' . dirname($fileName));
					}
				}
				$state = @rename($path, $this->nextcloudDir . '/' . $fileName);
				if ($state === false) {
					throw new \Exception(
						sprintf(
							'Could not rename %s to %s',
							$path,
							$this->nextcloudDir . '/' . $fileName
						)
					);
				}
			}
			if ($fileInfo->isDir()) {
				$state = rmdir($path);
				if ($state === false) {
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
	public function moveNewVersionInPlace(): void {
		$this->silentLog('[info] moveNewVersionInPlace()');

		// Rename everything else except the entry and updater files
		$excludedElements = [
			'updater',
			'index.php',
			'status.php',
			'remote.php',
			'public.php',
			'ocs',
		];
		$storageLocation = $this->getUpdateDirectoryLocation() . '/updater-' . $this->getConfigOptionMandatoryString('instanceid') . '/downloads/nextcloud/';
		$this->silentLog('[info] storage location: ' . $storageLocation);

		// Rename apps and other stuff
		$this->moveWithExclusions($storageLocation, $excludedElements);

		// Rename everything except the updater (It will not move what was already moved as it’s not in $storageLocation anymore)
		$this->moveWithExclusions($storageLocation, ['updater']);

		// The updater folder is moved last in finalize()

		$this->silentLog('[info] end of moveNewVersionInPlace()');
	}

	/**
	 * Finalize and cleanup the updater by finally replacing the updater script
	 */
	public function finalize(): void {
		$this->silentLog('[info] finalize()');

		$storageLocation = $this->getUpdateDirectoryLocation() . '/updater-' . $this->getConfigOptionMandatoryString('instanceid') . '/downloads/nextcloud/';
		$this->silentLog('[info] storage location: ' . $storageLocation);
		$this->moveWithExclusions($storageLocation, []);
		$state = rmdir($storageLocation);
		if ($state === false) {
			throw new \Exception('Could not rmdir $storagelocation');
		}

		$state = unlink($this->getUpdateDirectoryLocation() . '/updater-' . $this->getConfigOptionMandatoryString('instanceid') . '/.step');
		if ($state === false) {
			throw new \Exception('Could not rmdir .step');
		}

		/* Check if there is the need to extend .user.ini */
		$user_ini_additional_lines = $this->getConfigOption('user_ini_additional_lines');
		if ($user_ini_additional_lines) {
			$this->silentLog('[info] Extend .user.ini');
			if (is_array($user_ini_additional_lines)) {
				$user_ini_additional_lines = implode(PHP_EOL, $user_ini_additional_lines);
			}

			$result = file_put_contents($this->nextcloudDir . '/.user.ini', PHP_EOL . '; Additional settings from config.php:' . PHP_EOL . $user_ini_additional_lines . PHP_EOL, FILE_APPEND);
			if ($result === false) {
				throw new \Exception('Could not append to .user.ini');
			}
		}

		if (function_exists('opcache_reset')) {
			$this->silentLog('[info] call opcache_reset()');
			opcache_reset();
		}

		$this->silentLog('[info] end of finalize()');
	}

	/**
	 * @throws \Exception
	 */
	private function writeStep(string $state, int $step): void {
		$updaterDir = $this->getUpdateDirectoryLocation() . '/updater-' . $this->getConfigOptionMandatoryString('instanceid');
		if (!file_exists($updaterDir . '/.step')) {
			if (!file_exists($updaterDir)) {
				$result = mkdir($updaterDir);
				if ($result === false) {
					throw new \Exception('Could not create $updaterDir');
				}
			}
			$result = touch($updaterDir . '/.step');
			if ($result === false) {
				throw new \Exception('Could not create .step');
			}
		}

		$result = file_put_contents($updaterDir . '/.step', json_encode(['state' => $state, 'step' => $step]));
		if ($result === false) {
			throw new \Exception('Could not write to .step');
		}
	}

	/**
	 * @throws \Exception
	 */
	public function startStep(int $step): void {
		$this->silentLog('[info] startStep("' . $step . '")');
		$this->writeStep('start', $step);
	}

	/**
	 * @throws \Exception
	 */
	public function endStep(int $step): void {
		$this->silentLog('[info] endStep("' . $step . '")');
		$this->writeStep('end', $step);
	}

	/**
	 * @throws \Exception
	 */
	public function currentStep(): array {
		$this->silentLog('[info] currentStep()');

		$updaterDir = $this->getUpdateDirectoryLocation() . '/updater-' . $this->getConfigOptionMandatoryString('instanceid');
		if (!file_exists($updaterDir . '/.step')) {
			return [];
		}

		$state = file_get_contents($updaterDir . '/.step');
		if ($state === false) {
			throw new \Exception('Could not read from .step');
		}

		$jsonData = json_decode($state, true);
		if (!is_array($jsonData)) {
			throw new \Exception('Can\'t decode .step JSON data');
		}

		return $jsonData;
	}

	public function getUpdateStepFileLocation(): string {
		$updaterDir = $this->getUpdateDirectoryLocation() . '/updater-' . $this->getConfigOption('instanceid');
		return $updaterDir . '/.step';
	}

	/**
	 * Rollback the changes if $step has failed
	 *
	 * @throws \Exception
	 */
	public function rollbackChanges(int $step): void {
		$this->silentLog('[info] rollbackChanges("' . $step . '")');

		$updaterDir = $this->getUpdateDirectoryLocation() . '/updater-' . $this->getConfigOptionMandatoryString('instanceid');
		if (file_exists($updaterDir . '/.step')) {
			$this->silentLog('[info] unlink .step');
			$state = unlink($updaterDir . '/.step');
			if ($state === false) {
				throw new \Exception('Could not delete .step');
			}
		}

		if ($step >= 7) {
			$this->silentLog('[info] rollbackChanges - step >= 7');
			// TODO: If it fails after step 7: Rollback
		}
		$this->silentLog('[info] end of  rollbackChanges()');
	}

	/**
	 * Logs an exception with current datetime prepended to updater.log
	 *
	 * @throws LogException
	 */
	public function logException(\Exception $e): void {
		$message = '[error] ';

		$message .= 'Exception: ' . get_class($e) . PHP_EOL;
		$message .= 'Message: ' . $e->getMessage() . PHP_EOL;
		$message .= 'Code:' . $e->getCode() . PHP_EOL;
		$message .= 'Trace:' . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
		$message .= 'File:' . $e->getFile() . PHP_EOL;
		$message .= 'Line:' . $e->getLine() . PHP_EOL;
		if ($e instanceof UpdateException) {
			$message .= 'Data:' . PHP_EOL . print_r($e->getData(), true) . PHP_EOL;
		}
		$this->log($message);
	}

	/**
	 * Logs a message with current datetime prepended to updater.log
	 *
	 * @throws LogException
	 */
	public function log(string $message): void {
		$updaterLogPath = $this->getUpdateDirectoryLocation() . '/updater.log';

		$fh = fopen($updaterLogPath, 'a');
		if ($fh === false) {
			throw new LogException('Could not open updater.log');
		}

		if ($this->requestID === null) {
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
		if ($result === false) {
			throw new LogException('Could not write to updater.log');
		}

		fclose($fh);
	}


	/**
	 * Logs a message with current datetime prepended to updater.log but drops possible LogException
	 */
	public function silentLog(string $message): void {
		try {
			$this->log($message);
		} catch (LogException $logE) {
			/* ignore log exception here (already detected later anyways) */
		}
	}


	/**
	 * Logs current version
	 */
	public function logVersion(): void {
		$this->silentLog('[info] current version: ' . $this->currentVersion . ' build time: ' . $this->buildTime);
	}
}

class Auth {
	public function __construct(
		private Updater $updater,
		private string $password,
	) {
		$this->updater = $updater;
		$this->password = $password;
	}

	/**
	 * Whether the current user is authenticated
	 */
	public function isAuthenticated(): bool {
		$storedHash = $this->updater->getConfigOptionString('updater.secret');

		// As a sanity check the stored hash can never be empty
		if ($storedHash === '' || $storedHash === null) {
			return false;
		}

		return password_verify($this->password, $storedHash);
	}
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Check if the config.php is at the expected place
try {
	$updater = new Updater(__DIR__);
	if ($updater->isDisabled()) {
		http_response_code(403);
		die('Updater is disabled, please use the command line');
	}
} catch (\Exception $e) {
	// logging here is not possible because we don't know the data directory
	http_response_code(500);
	die($e->getMessage());
}

// Check if the updater.log can be written to
try {
	$updater->log('[info] request to updater');
} catch (\Exception $e) {
	if (isset($_POST['step'])) {
		// mark step as failed
		http_response_code(500);
		header('Content-Type: application/json');
		echo(json_encode(['proceed' => false, 'response' => $e->getMessage()]));
		die();
	}
	// show logging error to user
	die($e->getMessage());
}

// Check for authentication
$password = ($_SERVER['HTTP_X_UPDATER_AUTH'] ?? $_POST['updater-secret-input'] ?? '');
if (!is_string($password)) {
	die('Invalid type ' . gettype($password) . ' for password');
}
$auth = new Auth($updater, $password);

// Check if already a step is in process
$currentStep = $updater->currentStep();
$stepNumber = 0;
if ($currentStep !== []) {
	$stepState = (string)$currentStep['state'];
	$stepNumber = (int)$currentStep['step'];
	$updater->log('[info] Step ' . $stepNumber . ' is in state "' . $stepState . '".');

	if ($stepState === 'start') {
		die(
			sprintf(
				'Step %d is currently in process. Please reload this page later or remove the following file to start from scratch: %s',
				$stepNumber,
				$updater->getUpdateStepFileLocation()
			)
		);
	}
}

if (isset($_POST['step']) && !is_array($_POST['step'])) {
	$updater->log('[info] POST request for step "' . $_POST['step'] . '"');
	set_time_limit(0);
	try {
		if (!$auth->isAuthenticated()) {
			throw new \Exception('Not authenticated');
		}

		$step = (int)$_POST['step'];
		if ($step > 12 || $step < 1) {
			throw new \Exception('Invalid step');
		}

		$updater->startStep($step);
		switch ($step) {
			case 1:
				$updater->checkForExpectedFilesAndFolders();
				break;
			case 2:
				$updater->checkWritePermissions();
				break;
			case 3:
				$updater->createBackup();
				break;
			case 4:
				$updater->downloadUpdate();
				break;
			case 5:
				$updater->verifyIntegrity();
				break;
			case 6:
				$updater->extractDownload();
				break;
			case 7:
				$updater->setMaintenanceMode(true);
				break;
			case 8:
				$updater->replaceEntryPoints();
				break;
			case 9:
				$updater->deleteOldFiles();
				break;
			case 10:
				$updater->moveNewVersionInPlace();
				break;
			case 11:
				$updater->setMaintenanceMode(false);
				break;
			case 12:
				$updater->finalize();
				break;
		}
		$updater->endStep($step);
		header('Content-Type: application/json');
		echo(json_encode(['proceed' => true]));
	} catch (UpdateException $e) {
		$data = $e->getData();

		try {
			$updater->log('[error] POST request failed with UpdateException');
			$updater->logException($e);
		} catch (LogException $logE) {
			$data[] = ' (and writing to log failed also with: ' . $logE->getMessage() . ')';
		}

		if (isset($step)) {
			$updater->rollbackChanges($step);
		}
		http_response_code(500);
		header('Content-Type: application/json');
		echo(json_encode(['proceed' => false, 'response' => $data]));
	} catch (\Exception $e) {
		$message = $e->getMessage();

		try {
			$updater->log('[error] POST request failed with other exception');
			$updater->logException($e);
		} catch (LogException $logE) {
			$message .= ' (and writing to log failed also with: ' . $logE->getMessage() . ')';
		}

		if (isset($step)) {
			$updater->rollbackChanges($step);
		}
		http_response_code(500);
		header('Content-Type: application/json');
		echo(json_encode(['proceed' => false, 'response' => $message]));
	}

	die();
}

$updater->log('[info] show HTML page');
$updater->logVersion();
?>

<html>
<head>
	<style>
		html, body, div, span, object, iframe, h1, h2, h3, h4, h5, h6, p, blockquote, pre, a, abbr, acronym, address, code, del, dfn, em, img, q, dl, dt, dd, ol, ul, li, fieldset, form, label, legend, table, caption, tbody, tfoot, thead, tr, th, td, article, aside, dialog, figure, footer, header, nav, section {
			margin: 0;
			padding: 0;
			border: 0;
			outline: 0;
			font-weight: inherit;
			font-size: 100%;
			font-family: inherit;
			vertical-align: baseline;
			cursor: default;
		}
		body {
			font-family: 'Open Sans', Frutiger, Calibri, 'Myriad Pro', Myriad, sans-serif;
			background-color: #ffffff;
			font-weight: 400;
			font-size: .8em;
			line-height: 1.6em;
			color: #000;
			height: auto;
		}
		a {
			border: 0;
			color: #000;
			text-decoration: none;
			cursor: pointer;
		}
		.external_link {
			text-decoration: underline;
		}
		ul {
			list-style: none;
		}
		.output ul {
			list-style: initial;
			padding: 0 30px;
		}
		#header {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			height: 45px;
			line-height: 2.5em;
			background-color: #0082c9;
			box-sizing: border-box;
		}
		.header-appname {
			color: #fff;
			font-size: 20px;
			font-weight: 300;
			line-height: 45px;
			padding: 0;
			margin: 0;
			display: inline-block;
			position: absolute;
			margin-left: 5px;
		}
		#header svg {
			margin: 5px;
		}

		#content-wrapper {
			position: absolute;
			height: 100%;
			width: 100%;
			overflow-x: hidden;
			padding-top: 45px;
			box-sizing: border-box;
		}

		#content {
			position: relative;
			height: 100%;
			margin: 0 auto;
		}
		#app-navigation {
			width: 250px;
			height: 100%;
			float: left;
			box-sizing: border-box;
			background-color: #fff;
			padding-bottom: 44px;
			-webkit-user-select: none;
			-moz-user-select: none;
			-ms-user-select: none;
			user-select: none;
			border-right: 1px solid #eee;
		}
		#app-navigation > ul {
			position: relative;
			height: 100%;
			width: inherit;
			overflow: auto;
			box-sizing: border-box;
		}
		#app-navigation li {
			position: relative;
			width: 100%;
			box-sizing: border-box;
		}
		#app-navigation li > a {
			display: block;
			width: 100%;
			line-height: 44px;
			min-height: 44px;
			padding: 0 12px;
			overflow: hidden;
			box-sizing: border-box;
			white-space: nowrap;
			text-overflow: ellipsis;
			color: #000;
			opacity: .57;
		}
		#app-navigation li:hover > a, #app-navigation li:focus > a {
			opacity: 1;
		}

		#app-content {
			position: relative;
			height: 100%;
			overflow-y: auto;
		}
		#progress {
			width: 600px;
		}
		.section {
			padding: 25px 30px;
		}
		.hidden {
			display: none;
		}

		li.step, .light {
			-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=57)";
			opacity: .57;
		}

		li.step h2 {
			padding: 5px 2px 5px 30px;
			margin-top: 12px;
			margin-bottom: 0;
			-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=57)";
			opacity: .57;
			background-position:8px 50%;
			background-repeat: no-repeat;
		}

		li.current-step, li.passed-step, li.failed-step, li.waiting-step {
			-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=100)";
			opacity: 1;
		}

		.current-step {
			background-repeat: no-repeat;
			background-position: center;
			min-width: 16px;
			min-height: 16px;
			position: relative;
		}
		.current-step:after {
			z-index: 2;
			content: '';
			height: 12px;
			width: 12px;
			margin: -8px 0 0 -8px;
			position: absolute;
			top: 14px;
			left: 16px;
			border-radius: 100%;
			-webkit-animation: rotate .8s infinite linear;
			animation: rotate .8s infinite linear;
			-webkit-transform-origin: center;
			-ms-transform-origin: center;
			transform-origin: center;
			border: 2px solid rgba(150, 150, 150, 0.5);
			border-top-color: #969696;
		}

		@keyframes rotate {
			from {
				transform: rotate(0deg);
			}
			to {
				transform: rotate(360deg);
			}
		}

		li.current-step h2, li.passed-step h2, li.failed-step h2, li.waiting-step h2 {
			-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=100)";
			opacity: 1;
		}

		li.passed-step h2 {
			background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGhlaWdodD0iMTYiIHdpZHRoPSIxNiIgdmVyc2lvbj0iMS4xIiB2aWV3Qm94PSIwIDAgMTYgMTYiPjxwYXRoIGQ9Im0yLjM1IDcuMyA0IDRsNy4zLTcuMyIgc3Ryb2tlPSIjNDZiYTYxIiBzdHJva2Utd2lkdGg9IjIiIGZpbGw9Im5vbmUiLz48L3N2Zz4NCg==);
		}

		li.failed-step {
			background-color: #ffd4d4;
			border-radius: 3px;
		}
		li.failed-step h2 {
			color: #000;
			background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGhlaWdodD0iMTYiIHdpZHRoPSIxNiIgdmVyc2lvbj0iMS4xIiB2aWV3Ym94PSIwIDAgMTYgMTYiPjxwYXRoIGQ9Im0xNCAxMi4zLTEuNyAxLjctNC4zLTQuMy00LjMgNC4zLTEuNy0xLjcgNC4zLTQuMy00LjMtNC4zIDEuNy0xLjcgNC4zIDQuMyA0LjMtNC4zIDEuNyAxLjctNC4zIDQuM3oiIGZpbGw9IiNkNDAwMDAiLz48L3N2Zz4NCg==);
		}

		li.step .output {
			position: relative;
			padding: 5px 5px 5px 32px;
		}

		h2 {
			font-size: 20px;
			font-weight: 300;
			margin-bottom: 12px;
			color: #555;
		}

		button, a.button {
			font-family: 'Open Sans', Frutiger, Calibri, 'Myriad Pro', Myriad, sans-serif;
			font-size: 13px;
			font-weight: 600;
			color: #545454;
			margin: 3px 3px 3px 0;
			padding: 6px 12px;
			background-color: #f7f7f7;
			border-radius: 3px;
			border: 1px solid #dbdbdb;
			cursor: pointer;
			outline: none;
			min-height: 34px;
			box-sizing: border-box;
		}

		button:hover, button:focus, a.button:hover, a.button:focus {
			border-color: #0082c9;
		}

		code {
			font-family: monospace;
			font-size: 1.2em;
			background-color: #eee;
			border-radius: 2px;
			padding: 2px 6px 2px 4px;
		}

		#login code {
			display: block;
			border-radius: 3px;
		}

		#login form {
			margin-top: 5px;
		}

		#login input {
			border-radius: 3px;
			border: 1px solid rgba(240,240,240,.9);
			margin: 3px 3px 3px 0;
			padding: 9px 6px;
			font-size: 13px;
			outline: none;
			cursor: text;
		}

		.section {
			max-width: 600px;
			margin: 0 auto;
		}

		pre {
			word-wrap: break-word;
		}

	</style>
</head>
<body>
<div id="header">
	<svg xmlns="http://www.w3.org/2000/svg" version="1.1" xml:space="preserve" height="34" width="62" enable-background="new 0 0 196.6 72" y="0px" x="0px" viewBox="0 0 62.000002 34"><path style="color-rendering:auto;text-decoration-color:#000000;color:#000000;isolation:auto;mix-blend-mode:normal;shape-rendering:auto;solid-color:#000000;block-progression:tb;text-decoration-line:none;image-rendering:auto;white-space:normal;text-indent:0;enable-background:accumulate;text-transform:none;text-decoration-style:solid" fill="#fff" d="m31.6 4.0001c-5.95 0.0006-10.947 4.0745-12.473 9.5549-1.333-2.931-4.266-5.0088-7.674-5.0092-4.6384 0.0005-8.4524 3.8142-8.453 8.4532-0.0008321 4.6397 3.8137 8.4544 8.4534 8.455 3.4081-0.000409 6.3392-2.0792 7.6716-5.011 1.5261 5.4817 6.5242 9.5569 12.475 9.5569 5.918 0.000457 10.89-4.0302 12.448-9.4649 1.3541 2.8776 4.242 4.9184 7.6106 4.9188 4.6406 0.000828 8.4558-3.8144 8.4551-8.455-0.000457-4.6397-3.8154-8.454-8.4551-8.4533-3.3687 0.0008566-6.2587 2.0412-7.6123 4.9188-1.559-5.4338-6.528-9.4644-12.446-9.464zm0 4.9623c4.4687-0.000297 8.0384 3.5683 8.0389 8.0371 0.000228 4.4693-3.5696 8.0391-8.0389 8.0388-4.4687-0.000438-8.0375-3.5701-8.0372-8.0388 0.000457-4.4682 3.5689-8.0366 8.0372-8.0371zm-20.147 4.5456c1.9576 0.000226 3.4908 1.5334 3.4911 3.491 0.000343 1.958-1.533 3.4925-3.4911 3.4927-1.958-0.000228-3.4913-1.5347-3.4911-3.4927 0.0002284-1.9575 1.5334-3.4907 3.4911-3.491zm40.205 0c1.9579-0.000343 3.4925 1.533 3.4927 3.491 0.000457 1.9584-1.5343 3.493-3.4927 3.4927-1.958-0.000228-3.4914-1.5347-3.4911-3.4927 0.000221-1.9575 1.5335-3.4907 3.4911-3.491z"/></svg>
	<h1 class="header-appname">Updater</h1>
</div>
<input type="hidden" id="updater-access-key" value="<?php echo htmlentities($password) ?>"/>
<input type="hidden" id="updater-step-start" value="<?php echo $stepNumber ?>" />
<div id="content-wrapper">
	<div id="content">

		<div id="app-content">
		<?php if ($auth->isAuthenticated()): ?>
			<ul id="progress" class="section">
				<li id="step-init" class="step icon-loading passed-step">
					<h2>Initializing</h2>
					<div class="output">Current version is <?php echo($updater->getCurrentVersion()); ?>.<br>
						<?php echo($updater->checkForUpdate()); ?><br>

						<?php
						if ($updater->updateAvailable() || $stepNumber > 0) {
							$buttonText = 'Start update';
							if ($stepNumber > 0) {
								$buttonText = 'Continue update';
							} ?>
							<button id="startUpdateButton"><?php echo $buttonText ?></button>
							<?php
						}
			?>
						<button id="retryUpdateButton" class="hidden">Retry update</button>
						</div>
				</li>
				<li id="step-check-files" class="step <?php if ($stepNumber >= 1) {
					echo 'passed-step';
				}?>">
					<h2>Check for expected files</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-check-permissions" class="step <?php if ($stepNumber >= 2) {
					echo 'passed-step';
				}?>">
					<h2>Check for write permissions</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-backup" class="step <?php if ($stepNumber >= 3) {
					echo 'passed-step';
				}?>">
					<h2>Create backup</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-download" class="step <?php if ($stepNumber >= 4) {
					echo 'passed-step';
				}?>">
					<h2>Downloading</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-verify-integrity" class="step <?php if ($stepNumber >= 5) {
					echo 'passed-step';
				}?>">
					<h2>Verifying integrity</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-extract" class="step <?php if ($stepNumber >= 6) {
					echo 'passed-step';
				}?>">
					<h2>Extracting</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-enable-maintenance" class="step <?php if ($stepNumber >= 7) {
					echo 'passed-step';
				}?>">
					<h2>Enable maintenance mode</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-entrypoints" class="step <?php if ($stepNumber >= 8) {
					echo 'passed-step';
				}?>">
					<h2>Replace entry points</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-delete" class="step <?php if ($stepNumber >= 9) {
					echo 'passed-step';
				}?>">
					<h2>Delete old files</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-move" class="step <?php if ($stepNumber >= 10) {
					echo 'passed-step';
				}?>">
					<h2>Move new files in place</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-maintenance-mode" class="step <?php if ($stepNumber >= 11) {
					echo 'passed-step';
				}?>">
					<h2>Continue with web based updater</h2>
					<div class="output hidden">
						<button id="maintenance-disable">Disable maintenance mode and continue in the web based updater</button>
					</div>
				</li>
				<li id="step-done" class="step <?php if ($stepNumber >= 12) {
					echo 'passed-step';
				}?>">
					<h2>Done</h2>
					<div class="output hidden">
						<a id="back-to-nextcloud" class="button">Go back to your Nextcloud instance to finish the update</a>
					</div>
				</li>
			</ul>
		<?php else: ?>
			<div id="login" class="section">
				<h2>Authentication</h2>
				<p>To login you need to provide the unhashed value of "updater.secret" in your config file.</p>
				<p>If you don't know that value, you can access this updater directly via the Nextcloud admin screen or generate
				your own secret:</p>
				<code>php -r '$password = trim(shell_exec("openssl rand -base64 48"));if(strlen($password) === 64) {$hash = password_hash($password, PASSWORD_DEFAULT) . "\n"; echo "Insert as \"updater.secret\": ".$hash; echo "The plaintext value is: ".$password."\n";}else{echo "Could not execute OpenSSL.\n";};'</code>
				<form method="post" name="login">
					<fieldset>
						<input type="password" name="updater-secret-input" value=""
							   placeholder="Secret"
							   autocomplete="on" required>
						<button id="updater-secret-submit">Login</button>
					</fieldset>
				</form>
				<?php if (isset($_POST['updater-secret-input']) && !$auth->isAuthenticated()): ?>
				<p>Invalid password</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		</div>
	</div>
</div>

</body>
<?php if ($auth->isAuthenticated()): ?>
	<script>
        var nextcloudUrl = window.location.href.replace('updater/', '').replace('index.php', '');

        var backToButton = document.getElementById('back-to-nextcloud');
        if (backToButton) {
            backToButton.href = nextcloudUrl;
        }

		function escapeHTML(s) {
			return s.toString().split('&').join('&amp;').split('<').join('&lt;').split('>').join('&gt;').split('"').join('&quot;').split('\'').join('&#039;');
		}

		var done = false;
		var started = false;
		var updaterStepStart = parseInt(document.getElementById('updater-step-start').value);
		var elementId =false;
		function addStepText(id, text) {
			var el = document.getElementById(id);
			var output = el.getElementsByClassName('output')[0];
			if(typeof text === 'object') {
				text = JSON.stringify(text);
			}
			output.innerHTML = output.innerHTML + text;
			output.classList.remove('hidden');
		}
		function removeStepText(id) {
			var el = document.getElementById(id);
			var output = el.getElementsByClassName('output')[0];
			output.innerHTML = '';
			output.classList.add('hidden');
		}

		function currentStep(id) {
			var el = document.getElementById(id);
			el.classList.remove('failed-step');
			el.classList.remove('passed-step');
			el.classList.remove('waiting-step');
			el.classList.add('current-step');
		}

		function errorStep(id, numericId) {
			var el = document.getElementById(id);
			el.classList.remove('passed-step');
			el.classList.remove('current-step');
			el.classList.remove('waiting-step');
			el.classList.add('failed-step');

			// set start step to previous one
			updaterStepStart = numericId - 1;
			elementId = id;

			// show restart button
			var button = document.getElementById('retryUpdateButton');
			button.classList.remove('hidden');
		}

		function successStep(id) {
			var el = document.getElementById(id);
			el.classList.remove('failed-step');
			el.classList.remove('current-step');
			el.classList.remove('waiting-step');
			el.classList.add('passed-step');
		}

		function waitingStep(id) {
			var el = document.getElementById(id);
			el.classList.remove('failed-step');
			el.classList.remove('current-step');
			el.classList.remove('passed-step');
			el.classList.add('waiting-step');
		}

		function performStep(number, callback) {
			started = true;
			var httpRequest = new XMLHttpRequest();
			httpRequest.open('POST', window.location.href);
			httpRequest.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
			httpRequest.setRequestHeader('X-Updater-Auth', document.getElementById('updater-access-key').value);
			httpRequest.onreadystatechange = function () {
				if (httpRequest.readyState != 4) { // 4 - request done
					return;
				}

				if (httpRequest.status != 200) {
					// failure
				}

				if(httpRequest.responseText.substr(0,1) !== '{') {
					// it seems that this is not a JSON object
					var response = {
						processed: false,
						response: 'Parsing response failed.',
						detailedResponseText: httpRequest.responseText,
					};
					callback(response);
				} else {
					// parse JSON
					callback(JSON.parse(httpRequest.responseText));
				}

			};
			httpRequest.send("step="+number);
		}


		var performStepCallbacks = {
			0: function() { // placeholder that is called on start of the updater
				currentStep('step-check-files');
				performStep(1, performStepCallbacks[1]);
			},
			1: function(response) {
				if(response.proceed === true) {
					successStep('step-check-files');
					currentStep('step-check-permissions');
					performStep(2, performStepCallbacks[2]);
				} else {
					errorStep('step-check-files', 1);

					var text = '';
					if (typeof response['response'] === 'string') {
						text = escapeHTML(response['response']);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response['detailedResponseText']) + '</code></pre></details>';
					} else {
						text = 'Unknown files detected within the installation folder. This can be fixed by manually removing (or moving) these files. The following extra files have been found:<ul>';
						response['response'].forEach(function(file) {
							text += '<li>' + escapeHTML(file) + '</li>';
						});
						text += '</ul>';
					}
					addStepText('step-check-files', text);
				}
			},
			2: function(response) {
				if(response.proceed === true) {
					successStep('step-check-permissions');
					currentStep('step-backup');
					performStep(3, performStepCallbacks[3]);
				} else {
					errorStep('step-check-permissions', 2);

					var text = '';
					if (typeof response['response'] === 'string') {
						text = escapeHTML(response['response']);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response['detailedResponseText']) + '</code></pre></details>';
					} else {
						text = 'The following places can not be written to:<ul>';
						response['response'].forEach(function(file) {
							text += '<li>' + escapeHTML(file) + '</li>';
						});
						text += '</ul>';
					}
					addStepText('step-check-permissions', text);
				}
			},
			3: function (response) {
				if (response.proceed === true) {
					successStep('step-backup');
					currentStep('step-download');
					performStep(4, performStepCallbacks[4]);
				} else {
					errorStep('step-backup', 3);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-backup', text);
					}
				}
			},
			4: function (response) {
				if (response.proceed === true) {
					successStep('step-download');
					currentStep('step-verify-integrity');
					performStep(5, performStepCallbacks[5]);
				} else {
					errorStep('step-download', 4);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-download', text);
					}
				}
			},
			5: function (response) {
				if (response.proceed === true) {
					successStep('step-verify-integrity');
					currentStep('step-extract');
					performStep(6, performStepCallbacks[6]);
				} else {
					errorStep('step-verify-integrity', 5);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-verify-integrity', text);
					}
				}
			},
			6: function (response) {
				if (response.proceed === true) {
					successStep('step-extract');
					currentStep('step-enable-maintenance');
					performStep(7, performStepCallbacks[7]);
				} else {
					errorStep('step-extract', 6);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-extract', text);
					}
				}
			},
			7: function (response) {
				if (response.proceed === true) {
					successStep('step-enable-maintenance');
					currentStep('step-entrypoints');
					performStep(8, performStepCallbacks[8]);
				} else {
					errorStep('step-enable-maintenance', 7);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-enable-maintenance', text);
					}
				}
			},
			8: function (response) {
				if (response.proceed === true) {
					successStep('step-entrypoints');
					currentStep('step-delete');
					performStep(9, performStepCallbacks[9]);
				} else {
					errorStep('step-entrypoints', 8);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-entrypoints', text);
					}
				}
			},
			9: function (response) {
				if (response.proceed === true) {
					successStep('step-delete');
					currentStep('step-move');
					performStep(10, performStepCallbacks[10]);
				} else {
					errorStep('step-delete', 9);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-delete', text);
					}
				}
			},
			10: function (response) {
				if (response.proceed === true) {
					successStep('step-move');

					waitingStep('step-maintenance-mode');
					// show buttons to decide on maintenance mode
					var el = document.getElementById('step-maintenance-mode')
						.getElementsByClassName('output')[0];
					el.classList.remove('hidden');
				} else {
					errorStep('step-move', 10);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-move', text);
					}
				}
			},
			11: function (response) {
				if (response.proceed === true) {
					successStep('step-maintenance-mode');
					currentStep('step-done');
					performStep(12, performStepCallbacks[12]);
				} else {
					errorStep('step-maintenance-mode', 11);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-maintenance-mode', text);
					}
				}
			},
			12: function (response) {
				done = true;
				window.removeEventListener('beforeunload', confirmExit);
				if (response.proceed === true) {
					successStep('step-done');

					// show button to get to the web based migration steps
					var el = document.getElementById('step-done')
						.getElementsByClassName('output')[0];
					el.classList.remove('hidden');

					// above is the fallback if the Javascript redirect doesn't work
					window.location.href = nextcloudUrl;
				} else {
					errorStep('step-done', 12);
					var text = escapeHTML(response.response);
					text += '<br><details><summary>Show detailed response</summary><pre><code>' +
						escapeHTML(response.detailedResponseText) + '</code></pre></details>';
					addStepText('step-done', text);
				}
			},
		};

		function startUpdate() {
			performStepCallbacks[updaterStepStart]({
				proceed: true
			});
		}

		function retryUpdate() {
			//remove failed log
			if (elementId !== false) {
				var el = document.getElementById(elementId);
				el.classList.remove('passed-step');
				el.classList.remove('current-step');
				el.classList.remove('waiting-step');
				el.classList.remove('failed-step');

				removeStepText(elementId);

				elementId = false;
			}

			// hide restart button
			var button = document.getElementById('retryUpdateButton');
			button.classList.add('hidden');

			startUpdate();
		}

		function askForMaintenance() {
			var el = document.getElementById('step-maintenance-mode')
				.getElementsByClassName('output')[0];
			el.innerHTML = 'Maintenance mode will get disabled.<br>';
			currentStep('step-maintenance-mode');
			performStep(11, performStepCallbacks[11]);
		}

		if(document.getElementById('startUpdateButton')) {
			document.getElementById('startUpdateButton').onclick = function (e) {
				e.preventDefault();
				this.classList.add('hidden');
				startUpdate();
			};
		}
		if(document.getElementById('retryUpdateButton')) {
			document.getElementById('retryUpdateButton').onclick = function (e) {
				e.preventDefault();
				retryUpdate();
			};
		}
		if(document.getElementById('maintenance-disable')) {
			document.getElementById('maintenance-disable').onclick = function (e) {
				e.preventDefault();
				askForMaintenance();
			};
		}

		// Show a popup when user tries to close page
		function confirmExit() {
			if (done === false && started === true) {
				return 'Update is in progress. Are you sure, you want to close?';
			}
		}
		// this is unregistered in step 12
		window.addEventListener('beforeunload', confirmExit);
	</script>
<?php endif; ?>

</html>

