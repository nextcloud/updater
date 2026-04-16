<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace NC\Updater;

use Closure;
use CurlHandle;

class Updater {
	/** @var int */
	public const LAST_STEP = 12;

	/** @var non-empty-string */
	private readonly string $nextcloudDir;

	private array $configValues = [];

	private string $currentVersion = 'unknown';

	private readonly string $buildTime;

	private bool $updateAvailable = false;

	private ?string $requestID = null;

	private bool $disabled = false;

	private int $previousProgress = 0;

	private ?Closure $downloadProgress = null;

	/**
	 * Updater constructor
	 * @param string $baseDir the absolute path to the /updater/ directory in the Nextcloud root
	 * @throws \Exception
	 */
	public function __construct(
		string $baseDir,
	) {
		$nextcloudDir = realpath(dirname($baseDir));
		if ($nextcloudDir === false || $nextcloudDir === '') {
			throw new \Exception('Invalid baseDir provided');
		}

		$this->nextcloudDir = $nextcloudDir;

		[$this->configValues] = $this->readConfigFile();

		if (PHP_SAPI !== 'cli') {
			$this->disabled = (bool)($this->configValues['upgrade.disable-web'] ?? false);
			if ($this->disabled) {
				// Updater disabled
				return;
			}
		}

		$dataDir = $this->getUpdateDirectoryLocation();
		if ($dataDir === '' || $dataDir === '0') {
			throw new \Exception('Could not read data directory from config.php.');
		}

		$versionFileName = $this->nextcloudDir . '/version.php';
		if (!file_exists($versionFileName)) {
			// fallback to version in config.php
			$version = $this->getConfigOptionString('version');
			$buildTime = '';
		} else {
			/**
			 * @var ?string $OC_Build
			 * @var ?string $OC_VersionString
			 */
			require_once $versionFileName;

			$version = $OC_VersionString;
			$buildTime = $OC_Build;
		}

		if (!is_string($version) || !is_string($buildTime)) {
			return;
		}

		// normalize version to 3 digits
		$splittedVersion = explode('.', $version);
		if (count($splittedVersion) >= 3) {
			$splittedVersion = array_slice($splittedVersion, 0, 3);
		}

		$this->currentVersion = implode('.', $splittedVersion);
		$this->buildTime = $buildTime;
	}

	/**
	 * @return array{array, string}
	 */
	private function readConfigFile(): array {
		if ($dir = (string)getenv('NEXTCLOUD_CONFIG_DIR')) {
			$configFileName = realpath($dir . '/config.php');
			if ($configFileName === false) {
				throw new \Exception('Configuration not found in ' . $dir);
			}
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
	 * @throws \Exception
	 */
	public function checkForUpdate(): string {
		$response = $this->getUpdateServerResponse();

		$this->silentLog('[info] checkForUpdate() ' . print_r($response, true));

		$version = isset($response['version']) ? (string)$response['version'] : '';
		$versionString = isset($response['versionstring']) ? (string)$response['versionstring'] : '';

		if ($version !== '' && $version !== $this->currentVersion) {
			$this->updateAvailable = true;
			$releaseChannel = $this->getCurrentReleaseChannel();
			$downloadUrl = current($this->getDownloadURLs());
			$updateText = 'Update to ' . htmlentities($versionString) . ' available. (channel: "' . htmlentities($releaseChannel) . '")<br /><span class="light">Following file will be downloaded automatically:</span> <code class="light">' . $downloadUrl . '</code>';

			// only show changelog link for stable releases (non-RC & non-beta)
			if (in_array(preg_match('!(rc|beta)!i', $versionString), [0, false], true)) {
				$changelogURL = $this->getChangelogURL(substr($version, 0, strrpos($version, '.') ?: 0));
				$updateText .= '<br /><a class="external_link" href="' . $changelogURL . '" target="_blank" rel="noreferrer noopener">Open changelog ↗</a>';
			}
		} else {
			$updateText = 'No update available.';
		}

		if ($this->updateAvailable && isset($response['autoupdater']) && ($response['autoupdater'] !== 1 && $response['autoupdater'] !== '1')) {
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
		}

		return null;
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
		}

		throw new \Exception('Config key ' . $key . ' is missing');
	}

	/**
	 * Gets the data directory location on the local filesystem
	 */
	private function getUpdateDirectoryLocation(): string {
		return $this->getConfigOptionString('updatedirectory') ?? $this->getConfigOptionString('datadirectory') ?? '';
	}

	/**
	 * Returns the expected files and folders as array
	 *
	 * @return list<string>
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
			'custom.d.ts',
			'cypress.d.ts',
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

				if (str_starts_with($appsPath['path'], $this->nextcloudDir . '/')) {
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
			if (str_contains($element, '/')) {
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
			if (!in_array($fileInfo->getFilename(), $expectedElements)) {
				$unexpectedElements[] = $fileInfo->getFilename();
			}
		}

		if ($unexpectedElements !== []) {
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
			'themes'
		];

		$notWritablePaths = [];
		foreach ($this->getRecursiveDirectoryIterator($this->nextcloudDir, $excludedElements) as $fileInfo) {
			if (!$fileInfo->isWritable()) {
				$notWritablePaths[] = $fileInfo->getFilename();
			}
		}

		// Special handling for included default theme
		foreach ($this->getRecursiveDirectoryIterator($this->nextcloudDir . '/themes/example', $excludedElements) as $fileInfo) {
			if (!$fileInfo->isWritable()) {
				$notWritablePaths[] = $fileInfo->getFilename();
			}
		}

		$themesReadmeFileInfo = new \SplFileInfo($this->nextcloudDir . '/themes/README');
		if (!$themesReadmeFileInfo->isWritable()) {
			$notWritablePaths[] = $themesReadmeFileInfo->getFilename();
		}

		if ($notWritablePaths !== []) {
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
		return $changelogWebsite . '#' . str_replace('.', '-', $versionString);
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

		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($response);
		if ($xml === false) {
			$content = strlen($response) > 200 ? substr($response, 0, 200) . '…' : $response;
			$errors = implode("\n", array_map(fn ($error) => $error->message, libxml_get_errors()));
			throw new \Exception('Could not parse updater server XML response: ' . $content . "\nErrors:\n" . $errors);
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
	public function downloadUpdate(string $url = '', ?Closure $downloadProgress = null): void {
		$this->silentLog('[info] downloadUpdate()');
		$this->downloadProgress = $downloadProgress;

		$downloadURLs = $url !== '' ? [$url] : $this->getDownloadURLs();

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
			$saveLocation = $storageLocation . basename((string)$url);
			if ($this->downloadArchive($url, $saveLocation)) {
				return;
			}
		}

		throw new \Exception('All downloads failed. See updater logs for more information.');
	}

	private function getDownloadURLs(): array {
		$response = $this->getUpdateServerResponse();
		$downloadURLs = [];

		if (isset($response['downloads'])) {
			$response['downloads'] = (array)$response['downloads'];
		} elseif (isset($response['url']) && is_string($response['url'])) {
			// Compatibility with previous verison of updater_server
			$ext = pathinfo($response['url'], PATHINFO_EXTENSION);
			$response['downloads'] = [
				$ext => [$response['url']]
			];
		} else {
			throw new \Exception('Response from update server is missing download URLs');
		}

		foreach ($response['downloads'] as $format => $urls) {
			if (!$this->isAbleToDecompress($format)) {
				continue;
			}

			// If only one download URL exists, $urls is a string
			$urls = (array)$urls;
			foreach ($urls as $url) {
				if (!is_string($url)) {
					continue;
				}

				$downloadURLs[] = $url;
			}
		}

		if ($downloadURLs === []) {
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

	private function downloadProgressCallback(CurlHandle $resource, int $download_size, int $downloaded): void {
		if ($download_size !== 0) {
			$progress = (int)round($downloaded * 100 / $download_size);
			if ($progress > $this->previousProgress) {
				$this->previousProgress = $progress;
				// log every 2% increment for the first 10% then only log every 10% increment after that
				if ($progress % 10 === 0 || ($progress < 10 && $progress % 2 === 0)) {
					$this->silentLog(sprintf('[info] download progress: %d%% (', $progress) . $this->formatBytes($downloaded) . ' of ' . $this->formatBytes($download_size) . ')');

					if ($this->downloadProgress instanceof \Closure) {
						($this->downloadProgress)($progress, $this->formatBytes($downloaded), $this->formatBytes($download_size));
					}
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
		$bytes /= 1024 ** $pow;
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
		$files = array_values(
			array_filter(
				$filesInStorageLocation,
				// Match files with - in the name and extension (*-*.*)
				fn (string $path) => preg_match('/^.*-.*\..*$/i', $path),
			)
		);
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
	public function verifyIntegrity(string $urlOverride = ''): void {
		$this->silentLog('[info] verifyIntegrity()');

		if ($this->getCurrentReleaseChannel() === 'daily') {
			$this->silentLog('[info] current channel is "daily" which is not signed. Skipping verification.');
			return;
		}

		if ($urlOverride !== '') {
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

		throw new \Exception('OC_Version not found in ' . $versionFile);
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
				if (!$r) {
					throw new \Exception("Can't create parent directory for entry point: " . $file);
				}
			}

			$state = file_put_contents($this->nextcloudDir . '/' . $file, $content);
			if ($state === false) {
				throw new \Exception("Can't replace entry point: " . $file);
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

		foreach ($this->getRecursiveDirectoryIterator($folder, []) as $fileInfo) {
			if ($fileInfo->isDir()) {
				rmdir($fileInfo->getRealPath());
			} elseif ($fileInfo->isLink()) {
				unlink($fileInfo->getPathName());
			} else {
				unlink($fileInfo->getRealPath());
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
			if ($dataLocation === '') {
				throw new \Exception('Invalid dataLocation procided');

			}

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
	 * @param 'start'|'end' $state
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
	 * @return array{step?:int,state?:string}
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
			throw new \Exception("Can't decode .step JSON data");
		}

		$result = [];

		if (isset($jsonData['step']) && $jsonData['step'] <= self::LAST_STEP && $jsonData['step'] > 0) {
			$result['step'] = (int)$jsonData['step'];
			if (isset($jsonData['state'])) {
				$result['state'] = (string)$jsonData['state'];
			} else {
				$result['state'] = 'start';
			}
			if ($result['step'] === self::LAST_STEP && $result['state'] !== 'start') {
				return [];
			}
		}

		return $result;
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

		$message .= 'Exception: ' . $e::class . PHP_EOL;
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
				$randomString .= $characters[random_int(0, $charactersLength - 1)];
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
		} catch (LogException) {
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
