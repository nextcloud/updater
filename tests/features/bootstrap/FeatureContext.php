<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Behat\Context\SnippetAcceptingContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements SnippetAcceptingContext {
	protected $buildDir;

	protected $serverDir;

	protected $updateServerDir;

	protected $tmpDownloadDir;

	protected $downloadURL = 'https://download.nextcloud.com/server/releases/';

	protected $dailyDownloadURL = 'https://download.nextcloud.com/server/daily/latest-';

	protected $prereleasesDownloadURL = 'https://download.nextcloud.com/server/prereleases/';

	/** @var resource */
	protected $updaterServerProcess;

	/** @var string[] */
	protected $CLIOutput;

	/** @var integer */
	protected $CLIReturnCode;

	/** @var string */
	protected $autoupdater = '1';

	/** @var bool */
	protected $skipIt = false;

	public function __construct() {
		$baseDir = __DIR__ . '/../../data/';
		$this->serverDir = $baseDir . 'server/';
		$this->tmpDownloadDir = $baseDir . 'downloads/';
		$this->updateServerDir = $baseDir . 'update-server/';
		$this->buildDir = $baseDir . '../../';
		if (!file_exists($baseDir) && !mkdir($baseDir)) {
			throw new RuntimeException('Creating tmp download dir failed');
		}

		if (!file_exists($this->serverDir) && !mkdir($this->serverDir)) {
			throw new RuntimeException('Creating server dir failed');
		}

		if (!file_exists($this->tmpDownloadDir) && !mkdir($this->tmpDownloadDir)) {
			throw new RuntimeException('Creating tmp download dir failed');
		}

		if (!file_exists($this->updateServerDir) && !mkdir($this->updateServerDir)) {
			throw new RuntimeException('Creating update server dir failed');
		}
	}

	/**
	 * @AfterScenario
	 */
	public function stopUpdateServer() {
		if (is_resource($this->updaterServerProcess)) {
			proc_terminate($this->updaterServerProcess);
			proc_close($this->updaterServerProcess);
		}
	}

	/**
	 * @Given /the current (installed )?version is ([0-9.]+((beta|RC|rc)[0-9]?)?|stable[0-9]+|master)/
	 */
	public function theCurrentInstalledVersionIs($installed, $version) {
		if ($this->skipIt) {
			return;
		}

		// recursive deletion of server folder
		if (file_exists($this->serverDir)) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($this->serverDir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ($iterator as $fileInfo) {
				$action = $fileInfo->isDir() ? 'rmdir' : 'unlink';
				$action($fileInfo->getRealPath());
			}

			$state = rmdir($this->serverDir);
			if ($state === false) {
				throw new \Exception('Could not rmdir ' . $this->serverDir);
			}
		}

		$filename = 'nextcloud-' . $version . '.zip';

		if (!file_exists($this->tmpDownloadDir . $filename)) {
			$fp = fopen($this->tmpDownloadDir . $filename, 'w+');
			$url = $this->downloadURL . $filename;
			if (str_contains((string)$version, 'RC') || str_contains((string)$version, 'rc') || str_contains((string)$version, 'beta')) {
				$url = $this->prereleasesDownloadURL . 'nextcloud-' . $version . '.zip';
			} elseif (str_contains((string)$version, 'stable') || str_contains((string)$version, 'master')) {
				$url = $this->dailyDownloadURL . $version . '.zip';
			}

			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Nextcloud Updater');
			if (curl_exec($ch) === false) {
				throw new \Exception('Curl error: ' . curl_error($ch));
			}

			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($httpCode !== 200) {
				throw new \Exception('Download failed for ' . $url . ' - HTTP code: ' . $httpCode);
			}

			curl_close($ch);
			fclose($fp);
		}

		$zip = new ZipArchive;
		$zipState = $zip->open($this->tmpDownloadDir . $filename);
		if ($zipState === true) {
			$zip->extractTo($this->serverDir);
			$zip->close();
		} else {
			throw new \Exception('Cant handle ZIP file. Error code is: ' . $zipState);
		}

		if ($installed === '') {
			// the instance should not be installed
			return;
		}

		chdir($this->serverDir . 'nextcloud');
		shell_exec('chmod +x occ');
		exec('./occ maintenance:install --admin-user=admin --admin-pass=admin 2>&1', $output, $returnCode);

		if ($returnCode !== 0) {
			throw new Exception('Install failed' . PHP_EOL . implode(PHP_EOL, $output));
		}
	}

	/**
	 * @Given there is no update available
	 */
	public function thereIsNoUpdateAvailable() {
		if ($this->skipIt) {
			return;
		}

		$this->runUpdateServer();

		$content = '';
		file_put_contents($this->updateServerDir . 'index.php', $content);
	}

	/**
	 * @Given  the autoupdater is disabled
	 */
	public function theAutoupdaterIsDisabled() {
		if ($this->skipIt) {
			return;
		}

		$this->autoupdater = '0';
	}

	/**
	 * @When the CLI updater is run successfully
	 */
	public function theCliUpdaterIsRunSuccessfully() {
		if ($this->skipIt) {
			return;
		}

		$this->theCliUpdaterIsRun();

		if ($this->CLIReturnCode !== 0) {
			throw new Exception('updater failed' . PHP_EOL . implode(PHP_EOL, $this->CLIOutput));
		}
	}

	/**
	 * @When the CLI updater is run
	 */
	public function theCliUpdaterIsRun() {
		if ($this->skipIt) {
			return;
		}

		if (!file_exists($this->buildDir . 'updater.phar')) {
			throw new Exception('updater.phar not available - please build it in advance via "box build -c box.json"');
		}

		copy($this->buildDir . 'updater.phar', $this->serverDir . 'nextcloud/updater/updater');
		chdir($this->serverDir . 'nextcloud/updater');
		chmod($this->serverDir . 'nextcloud/updater/updater', 0755);
		exec('./updater -n', $output, $returnCode);

		// sleep to let the opcache do it's work and invalidate the status.php
		sleep(5);
		$this->CLIOutput = $output;
		$this->CLIReturnCode = $returnCode;
	}

	/**
	 * @param $version
	 */
	public function getSignatureForVersion(string $version): string {
		$signatures = [
			'25.0.1' => 'gPMcRBmMW875VGEEtP/qfpjbS8kYwVsW0DwK1DdDBrW+DduIUiwiOM4PVi99pVq2
zJ01A6pWPwx9adIiqnm7tBTgBYtrmS6S4KY4DWp+kx7ndflvO/xIH1B8PyvLuFXM
2FKj5i45YhUv8rTJ3jgmNj6UrfFB0CJTo+slHAPwTWooF4IsZUjTw97MjpnYkLIQ
vyuADZWp4CFxQq5FTmgaFmdRuSbZuHgt9bbUvXE+CzPKS/OxfdxbHbwrBLuyFsbE
wdEGUgjkWJRHIyK8UX/5XccUETH2C82l0cwTVILWMvieBPGtRH8matFpxeJW0jzX
4wgr7yW+fOGMm4OFpbQR5A==',
			'26.0.13' => 'bvGxFDuB+F5C9DqiARiF9MifdcZEQ2R5+AvgCEs/hnrUugRjTXMvJPRkaDLL01Yf
QoiNwNG3da/2JQEAfZ23YkQedNQ6T3fs7HGbhUZA3xFZb06kxQpLJFI/Ncei8i16
+QyxhlQtOlhBG0ExG0M0LD3Ow9ZFsCkRk1Ja2YIRBW3mRUdnqew8mYYKltZJL444
D5BO/0AisCh9hVI7JzExVmwYL/HOmbG5GBpy7BLJnSOUU0Di5PSfwoLIOqLsg/9+
qVqpedb3ivvwVR1pZqTUyrUPDYojLnyw3XCSKb588U6kSNhaMj/Kl5/5KT34OG+2
m04vBdfnV+VUhCBz0tYn9A==',
			'26.0.3' => 'THIS IS AN INVALID SIGNATURE',
			'27.0.1rc1' => 'YYGUtFUbej0iQbQr3F17NKiO7oulHm+YykMaAVsHMinMPyXKJ1ZL4sBrnhNpx80O
MX2F4ZMRDkgyh0oTsdb7klMM2yXWHLfRljg2IDEDVmUiqSr4wXYX1TblqF1HXgw/
aiSBqWBB8USujt4JAjDH36PjxZJyv1MhhEdVLNH6/HRyFpQ6meEBHjO7eviALdC/
QfCpyNUXcKvO9bsVwjV7rWXASnbTZV8xjkO0BUUa1e3ofdiltWYpHr0HX1cLXTIk
gsXjLF6nzxFh5IV8q9zV7nprfC+nQ6WaQyuMb6RyIvle0url7cYj6HRnp0QpVUY2
08lECPhB+LAJq08b+lzLMA==',
			'27.1.11' => 'brs2KkUu60QmFZD46rSTyg3qBSlYnv584xeFNWLl2ZM4cwItzJ5wXeajfrPvoUQJ
GWw7Ln4pQPHja4GrYaNfrKewbBzdJ295glFA5Biwk8OaacsAh6lZm4QH87OUjvgS
560LH0hW99Jf9aFv/qqj56T0GSGlGS3qv/HNimmeC0sZfLNDdxjaDWBpVJkt+45Z
vJJ8XVSDOlNNKjcgvcUMrsDItXioSwBst6vTdR5IKLAFivlb7HYLUN48R9h57QM2
v8X/N49mF+Wk3PQa19wBVsUFYkaQuG9FTjUVgvp8bgv3s9rhrOLJa5KUOpdcodgZ
faeql723PcZEzPJ3dzisSw==',
			'28.0.0beta4' => 'tguTYQ9w6cpQITNbVphOYsHGTvYPDi8aznjUM8Xyxi0HTIuK3WPBTdgrn7jPTC5+
JlwoyTQTRI2ut0SvEzVK5OrKTotPtNaNRSwpo0VAtuavEAWK6ZtH0g5oujHDyn/7
7S149qpPkbir6Lf7qMSSje92CF1LFOQDEqXW9HibfRVzMvTk2iTz//cTVcnyTxgi
QbK5O5wLmo7Gp8UNZsHL6CXTHo7p8zd8I2T86poJAttgwGIGJ0rQe1AYh/kJEOEz
CAzl6Rd033pBht1t9Y9mFfnWd70a4v9stSdhCwVo08fqxOcoJrCZQ4wwEWN3ReYj
/xB2sIdvkLkDyESNNzmhmg==',
			'28.0.14' => 'e3wnEZE0ooyNX8CpsSEgXafLoOU/U+zORUyeqKczWuuf2Srq4edl2SCaQgvdSLsG
DZo8h9LLEsh544/NyS8VOY7aJVqR2JOC4bUyztfNTnlppRLVTCIXx053Eht9+neN
pYlPy8hBK+KBLoN7q3WYcWL1QOIrUAzgxhjwshMrTxNrHi8Nq7g37iZUzhPU5HWw
MUID9gsQnT+aFurooLVvWMM8Ad0RkU72i5Y7I80c+v/2MYE9rxUmNC54noVePvrj
R8zf/PC+Yj1vxFZ0hYAtweLgBxfwU5cNBYfH7M1I9FLlb88p/XDWx6XaBz4Ql6LK
lbpDxNE9UiM09JG1dU7Ebg==',
		];

		return $signatures[$version] ?? '';
	}

	private function writeUpdaterIndex(
		string $version,
		string $name,
		string $url,
		int $autoupdater,
	) {
		$signature = $this->getSignatureForVersion($version);
		$content = <<<XML
			<?php header("Content-Type: application/xml"); ?>
			<nextcloud>
				<version>{$version}</version>
				<versionstring>{$name}</versionstring>
				<url>{$url}</url>
				<web>https://docs.nextcloud.org/server/10/admin_manual/maintenance/manual_upgrade.html</web>
				<autoupdater>{$autoupdater}</autoupdater>
				<signature>{$signature}</signature>
			</nextcloud>
			XML;

		file_put_contents($this->updateServerDir . 'index.php', $content);

	}

	/**
	 * @Given /there is an update to version ([0-9.]+) available/
	 */
	public function thereIsAnUpdateToVersionAvailable($version) {
		if ($this->skipIt) {
			return;
		}

		$this->runUpdateServer();

		$this->writeUpdaterIndex(
			str_replace(['9.1', '9.2'], ['10.0', '11.0'], $version),
			'Nextcloud ' . $version,
			'https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.zip',
			$this->autoupdater,
		);
	}

	/**
	 * @Given there is an update to prerelease version :version available
	 */
	public function thereIsAnUpdateToPrereleaseVersionAvailable($version) {
		if ($this->skipIt) {
			return;
		}

		$this->runUpdateServer();
		$this->writeUpdaterIndex(
			str_replace(['9.1', '9.2'], ['10.0', '11.0'], $version),
			'Nextcloud ' . $version,
			'https://download.nextcloud.com/server/prereleases/nextcloud-' . $version . '.zip',
			$this->autoupdater,
		);
	}

	/**
	 * @Given /there is an update to daily version of (.*) available/
	 */
	public function thereIsAnUpdateToDailyVersionAvailable($version) {
		if ($this->skipIt) {
			return;
		}

		$this->runUpdateServer();

		$this->writeUpdaterIndex(
			'100.0.0',
			'Nextcloud ' . $version,
			'https://download.nextcloud.com/server/daily/latest-' . $version . '.zip',
			1,
		);
	}

	/**
	 * runs the updater server
	 * @throws Exception
	 */
	protected function runUpdateServer() {
		$configFile = $this->serverDir . 'nextcloud/config/config.php';
		$content = file_get_contents($configFile);
		$content = preg_replace('!\$CONFIG\s*=\s*array\s*\(!', "\$CONFIG = array(\n 'updater.server.url' => 'http://localhost:8870/',", $content);
		file_put_contents($configFile, $content);

		if (!is_null($this->updaterServerProcess)) {
			throw new Exception('Update server already started');
		}

		$cmd = 'php -S localhost:8870 -t ' . $this->updateServerDir . ' 2>/dev/null 1>/dev/null';
		$this->updaterServerProcess = proc_open($cmd, [], $pipes, $this->updateServerDir);

		if (!is_resource($this->updaterServerProcess)) {
			throw new Exception('Update server could not be started');
		}

		// to let the server start
		sleep(1);
	}

	/**
	 * @Then /the installed version should be ([0-9.]+)/
	 */
	public function theInstalledVersionShouldBe2($version) {
		if ($this->skipIt) {
			return;
		}

		/** @var $OC_Version */
		require $this->serverDir . 'nextcloud/version.php';

		$installedVersion = implode('.', $OC_Version);

		if (!str_starts_with($installedVersion, (string)$version)) {
			throw new Exception('Version mismatch - Installed: ' . $installedVersion . ' Wanted: ' . $version);
		}
	}

	/**
	 * @Then /maintenance mode should be (on|off)/
	 */
	public function maintenanceModeShouldBe($state) {
		if ($this->skipIt) {
			return;
		}

		chdir($this->serverDir . 'nextcloud');
		shell_exec('chmod +x occ');
		exec('./occ maintenance:mode', $output, $returnCode);

		$expectedOutput = 'Maintenance mode is currently '
			. ($state === 'on' ? 'enabled' : 'disabled');

		if ($returnCode !== 0 || in_array(str_contains(implode(PHP_EOL, $output), $expectedOutput), [0, false], true)) {
			throw new Exception('Maintenance mode does not match ' . PHP_EOL . implode(PHP_EOL, $output));
		}
	}

	/**
	 * @Given the current channel is :channel
	 * @param string $channel
	 */
	public function theCurrentChannelIs($channel) {
		if ($this->skipIt) {
			return;
		}

		chdir($this->serverDir . 'nextcloud');
		shell_exec('chmod +x occ');
		exec('./occ config:system:set --value ' . $channel . ' updater.release.channel');
	}

	/**
	 * @Given the config key :key is set to :value of type :type
	 * @param string $type ('string', 'boolean', 'integer', 'double')
	 */
	public function theConfigKeyIsSetTo(string $key, mixed $value, string $type = 'string') {
		if ($this->skipIt) {
			return;
		}

		if (!in_array($type, ['string', 'boolean', 'integer', 'double'])) {
			throw new Exception('Invalid type given: ' . $type);
		}

		chdir($this->serverDir . 'nextcloud');
		shell_exec('chmod +x occ');
		exec(sprintf("./occ config:system:set %s --value '%s' --type '%s'", $key, $value, $type));
	}

	/**
	 * @Then the user ini file contains :content
	 */
	public function theUserIniFileContains(string $content) {
		if ($this->skipIt) {
			return;
		}

		$userIniFile = $this->serverDir . 'nextcloud/.user.ini';
		if (!file_exists($userIniFile)) {
			throw new Exception('User ini file does not exist: ' . $userIniFile);
		}

		$contents = file_get_contents($userIniFile);
		if (!str_contains($contents, $content)) {
			throw new Exception('Content not found in user ini file: ' . $content);
		}
	}

	/**
	 * @Then /upgrade is (not required|required)/
	 */
	public function upgradeIs($state) {
		if ($this->skipIt) {
			return;
		}

		chdir($this->serverDir . 'nextcloud');
		shell_exec('chmod +x occ');
		exec('./occ status', $output, $returnCode);

		$upgradeOutput = 'Nextcloud or one of the apps require upgrade';

		$outputString = implode(PHP_EOL, $output);
		if ($returnCode !== 0) {
			throw new Exception('Return code of status output does not match ' . PHP_EOL . $outputString);
		}

		if ($state === 'not required') {
			if (str_contains($outputString, $upgradeOutput)) {
				throw new Exception('Upgrade is required ' . PHP_EOL . implode(PHP_EOL, $output));
			}
		} elseif (in_array(str_contains($outputString, $upgradeOutput), [0, false], true)) {
			throw new Exception('Upgrade is not required ' . PHP_EOL . implode(PHP_EOL, $output));
		}
	}

	/**
	 * @Then /the return code should not be (\S*)/
	 */
	public function theReturnCodeShouldNotBe($expectedReturnCode) {
		if ($this->skipIt) {
			return;
		}

		if ($this->CLIReturnCode === (int)$expectedReturnCode) {
			throw new Exception('Return code does match but should not match: ' . $this->CLIReturnCode . PHP_EOL . implode(PHP_EOL, $this->CLIOutput));
		}
	}

	/**
	 * @Then /the output should contain "(.*)"/
	 */
	public function theOutputShouldBe($expectedOutput) {
		if ($this->skipIt) {
			return;
		}

		if (in_array(str_contains(implode(PHP_EOL, $this->CLIOutput), (string)$expectedOutput), [0, false], true)) {
			throw new Exception('Output does not match: ' . PHP_EOL . implode(PHP_EOL, $this->CLIOutput));
		}
	}

	/**
	 * @Given /the version number is decreased in the config.php to enforce upgrade/
	 */
	public function theVersionNumberIsDecreasedInTheConfigPHPToEnforceUpgrade() {
		if ($this->skipIt) {
			return;
		}

		$configFile = $this->serverDir . 'nextcloud/config/config.php';
		$content = file_get_contents($configFile);
		$content = preg_replace("!'version'\s*=>\s*'(\d+\.\d+\.\d+)\.\d+!", "'version' => '$1", $content);
		file_put_contents($configFile, $content);
	}

	/**
	 * @Given there is a folder called :name
	 */
	public function thereIsAFolderCalled($name) {
		if ($this->skipIt) {
			return;
		}

		mkdir($this->serverDir . 'nextcloud/' . $name);
	}

	/**
	 * @Given there is a config for a secondary apps directory called :name
	 */
	public function thereIsAConfigForASecondaryAppsDirectoryCalled($name) {
		if ($this->skipIt) {
			return;
		}

		$configFile = $this->serverDir . 'nextcloud/config/config.php';
		$content = file_get_contents($configFile);
		$appsPaths = <<<EOF
	'apps_paths' => [
		[
			'path'=> dirname(__DIR__) . '/apps',
			'url' => '/apps',
			'writable' => true,
		],
		[
			'path'=> dirname(__DIR__) . '/%s',
			'url' => '/%s',
			'writable' => true,
		],
	],
EOF;
		$appsPaths = sprintf($appsPaths, $name, $name);

		$content = preg_replace("!\);!", $appsPaths . ');', $content);
		file_put_contents($configFile, $content);
	}

	/**
	 * @Given /PHP is at least in version ([0-9.]+)/
	 */
	public function phpIsAtLeastInVersion($version) {
		$this->skipIt = in_array(version_compare($version, PHP_VERSION, '<'), [0, false], true);
	}
}
