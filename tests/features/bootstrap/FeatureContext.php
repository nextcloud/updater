<?php

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
	protected $updaterServerProcess = null;
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
			if (str_contains($version, 'RC') || str_contains($version, 'rc') || str_contains($version, 'beta')) {
				$url = $this->prereleasesDownloadURL . 'nextcloud-' . $version . '.zip';
			} elseif (strpos($version, 'stable') !== false || strpos($version, 'master') !== false) {
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
			throw new \Exception('Cant handle ZIP file. Error code is: '.$zipState);
		}

		if ($installed === '') {
			// the instance should not be installed
			return;
		}

		chdir($this->serverDir . 'nextcloud');
		shell_exec('chmod +x occ');
		exec('./occ maintenance:install --admin-user=admin --admin-pass=admin 2>&1', $output, $returnCode);

		if ($returnCode !== 0) {
			throw new Exception('Install failed' . PHP_EOL . join(PHP_EOL, $output));
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
			throw new Exception('updater failed' . PHP_EOL . join(PHP_EOL, $this->CLIOutput));
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
	 * @return string
	 */
	public function getSignatureForVersion($version) {
		$signatures = [
			'24.0.1' => 'AGjMXl1X1hRSZv++TOhoS5GzQ5LovzG6uCESqEVgSR+Xd+l82lCUNvJ6saGYp8xk
￼wL3OnDOnNVcT11xV9Xybt9JLU9tmXf3hf4/HNeyufWKr6AgUENrG7p4dx+tzLk5v
￼fYtOdoqnhyNNLkrshWcEd7COiaK73O4IlivdOyEZkp/L16RlK5wcs4wAy+M/ot6G
￼vodwhgcFEbTxA4rRgPQgAk1jw/IKOBb5mMqR0DwZXZGDrnt32+++fTVhMBIVe1hA
￼XvRlbXYM64mwjbLN0jsrj4yXvaq081NRcHgDUnx7crmgZPVcPs94FFx/sJ9m0y0W
￼S43iWfUbYsoYueTboeC29A==',
			'24.0.2' => 'GskL4HWBPnCHgd0G/+Tk1gLtZLODgxrRbGa11BQxfpXHyn9OD88p2ul2rhQv3HDE
￼el2QsNc3lzIvuecR+q2tgsjbQR89eK+46SbhogrcUky0HV94gCa6B9Nvenh8/kX8
￼PRba/ewR1i6CvBVaXJ2xFc9haS1Dv6buBYm4w17F+cnLNvHnWcMVzNPuxRS8dWIw
￼Oy/tVM+rghS6KYpCXOkyw9jpf5nWR7PdaCITslmhDDDjPXr6fJz31tG1JDQuP4Qt
￼sEqM0y0G2jhBWLbptxP0ntVjJfITRAoCkpyJTgX4b9oem47YNotSD7yBGqbZWcuN
￼5+D+D9LWH+PgwZOANwlRZw==',
			'24.0.3' => 'THIS IS AN INVALID SIGNATURE',
			'25.0.0rc1' => 'AakoZm/DZWLUR33BmXolD7tAGCx5eGvGxYRMPKW5JfTqWo5z+oOz5v00+ERVZeg0
￼BHwWbiDyr3gZWf6orK6ZK2kNNmnM3v6T1r1ffBAnHlWpJ8+fIoczK+6mrFDTAx0z
￼7njXkXOcXxnr0yxG5L1cnRlnRxZju4OjmOu9cHfi7RAc9d7gwpLvWE3nkK5jovMG
￼yzIrcPoCRBuBve4ltzN3DSCa3+r4C+/9uLrvGc1hSzE5WMCdwear4Lt2Eryauaim
￼UOrN0ZRLcZDJjiV/N7abGYLBhuspeNSs4d7s/M/ofv1mQ0nRzU4QDYZ8URKey2i5
￼B2+18hf/XUUd4L+LVkrtUw==',
			'25.0.0' => 'Bcs9lgaMcvg8tRVikUE6/BMyrXWKdeG7dyKZ36ctznJ9jYaijAKcAlGwQnmgAyX3
￼UM/6EwponQgBJokt1zHOZjAWsvT56DTLlYpQxGTPcBZSoJacLwgoEMTIhJ7CWM+o
￼OHuT8cN4uDfv/t0+qA+ciUC7ju0dw53T0LUjUwGja8GZSkJGl9bR+/tuPHWpv0rD
￼C054a4ucH7pHBhZvuGKPZL4V+1StuXsP1QwcGMC48CEJOyAx1IfGLhh8J9GM4DgF
￼5Mp9w1w/nnxIACgkVidZh3/abym57rHuKgFzxGl7Yd3G4EBm8B3bYwuk3128UAB8
￼11/RHVStB7WEj855c6j9wA==',
			'25.0.1' => 'gPMcRBmMW875VGEEtP/qfpjbS8kYwVsW0DwK1DdDBrW+DduIUiwiOM4PVi99pVq2
￼zJ01A6pWPwx9adIiqnm7tBTgBYtrmS6S4KY4DWp+kx7ndflvO/xIH1B8PyvLuFXM
￼2FKj5i45YhUv8rTJ3jgmNj6UrfFB0CJTo+slHAPwTWooF4IsZUjTw97MjpnYkLIQ
￼vyuADZWp4CFxQq5FTmgaFmdRuSbZuHgt9bbUvXE+CzPKS/OxfdxbHbwrBLuyFsbE
￼wdEGUgjkWJRHIyK8UX/5XccUETH2C82l0cwTVILWMvieBPGtRH8matFpxeJW0jzX
￼4wgr7yW+fOGMm4OFpbQR5A==',
			'25.0.7' => 'dIMJO1TcrQ05IkpSHWsAgj0VOV9PNnvxFrwBzaxgi9nkTZjrlQxeswzrozNRlgOz
YO9QQT+jC4dG5SFu/wKAaF0cmYuAdJx4vz2DNgMKrOfODzXgshLk+vZtdyCtOtlq
hOlAeuPilB9K3Q+b4dVjrcv6op/dSEQBhaXI46QvCuvfB1EKLfUAWbLsxCMbr4Cd
Hsav3i+wHleTOL8F7Qc33gDCVtpgqWlyXJG1omEiD9D/Kj+SMTo+s9iwOwW2b0vw
81qbX21xwzPS1vA18qI0JZnd0sdMRGTYvPZJr/Wn2MuMajcMD+94A9W3ij/BygoE
3uimJONAqLSFY6KEnNuoIQ==',
			'26.0.0beta4' => 'lJVljfemugXaSV2Xt8MF9OGaj7VVa24S4GUFM0BABUzWAf0yRRGWcQAyk0Mg6ZBU
￼GvoprwbVhV3+UUGxdj9pQAFYcMqpfcQPdcPWLxk3lx3xuGj79ItyJXRwdahh+4wa
￼JnUAl/70n6Gp6c7F86aJxacUl7KeRbCgg4h5KGGw3aRZJb2R/Mm3TbFwsE1OIDMg
￼R0xZcevYS2lSJzjqY1M1QjfCZO4qq2XMDSEN1G58HMd2+QDWqa63QG7/wCF1ufLw
￼V9tAyTb/fjsj/oUM0wa2bePC3wk/ai8RjWXbZNX2g5YQAxgkCQjfdvb5RNVvmuCL
￼AJBwc9EthsEKMVkKj9R41g==',
			'26.0.0' => 'C7bAPCDo+ZrmIKkxXeJmInOINo2RI0zqxBmNk5bcMjXviPjPeE8SrHbhDcPLHMsp
wUM//AMxbYUKtKBHPYqvw28O5kQhDe9gyCGo5zyTeFDjpgNgQvxa6TxSyl5O03aD
CehZjUf8mnOyaSkJcmkCqJokl2uCXoB9r7pTZwGEURvjv7UOHe6rmKgJAWBeCb1D
ddE/CLFhszZSGGdZRnhYIR5aRkFxUWXiBqtCVbpPf0VjzYIlBzRXDh4s/8JjsPeW
gsVtwgwxZ3CfzIoVeFeq7LQep+bCstdTvCZjs7GmvS6SgRlDvAOm82EBGY1rVQKD
t87PcaZyrupEfAIfD9uQRA==',
		];

		if (isset($signatures[$version])) {
			return $signatures[$version];
		}

		return '';
	}

	/**
	 * @Given /there is an update to version ([0-9.]+) available/
	 */
	public function thereIsAnUpdateToVersionAvailable($version) {
		if ($this->skipIt) {
			return;
		}
		$this->runUpdateServer();

		$content = '<?php
        header("Content-Type: application/xml");
        ?>
<?xml version="1.0" encoding="UTF-8"?>
<nextcloud>
 <version>' . str_replace(['9.1', '9.2'], ['10.0', '11.0'], $version) . '</version>
 <versionstring>Nextcloud ' . $version . '</versionstring>
 <url>https://download.nextcloud.com/server/releases/nextcloud-' . $version . '.zip</url>
 <web>https://docs.nextcloud.org/server/10/admin_manual/maintenance/manual_upgrade.html</web>
 <autoupdater>' . $this->autoupdater . '</autoupdater>
 <signature>'.$this->getSignatureForVersion($version).'</signature>
</nextcloud>
';
		file_put_contents($this->updateServerDir . 'index.php', $content);
	}

	/**
	 * @Given there is an update to prerelease version :version available
	 */
	public function thereIsAnUpdateToPrereleaseVersionAvailable($version) {
		if ($this->skipIt) {
			return;
		}
		$this->runUpdateServer();

		$content = '<?php
        header("Content-Type: application/xml");
        ?>
<?xml version="1.0" encoding="UTF-8"?>
<nextcloud>
 <version>' . str_replace(['9.1', '9.2'], ['10.0', '11.0'], $version) . '</version>
 <versionstring>Nextcloud ' . $version . '</versionstring>
 <url>https://download.nextcloud.com/server/prereleases/nextcloud-' . $version . '.zip</url>
 <web>https://docs.nextcloud.org/server/10/admin_manual/maintenance/manual_upgrade.html</web>
 <autoupdater>1</autoupdater>
 <signature>'.$this->getSignatureForVersion($version).'</signature>
</nextcloud>
';
		file_put_contents($this->updateServerDir . 'index.php', $content);
	}

	/**
	 * @Given /there is an update to daily version of (.*) available/
	 */
	public function thereIsAnUpdateToDailyVersionAvailable($version) {
		if ($this->skipIt) {
			return;
		}
		$this->runUpdateServer();

		$content = '<?php
        header("Content-Type: application/xml");
        ?>
<?xml version="1.0" encoding="UTF-8"?>
<nextcloud>
 <version>100.0.0.0</version>
 <versionstring>Nextcloud ' . $version . '</versionstring>
 <url>https://download.nextcloud.com/server/daily/latest-' . $version . '.zip</url>
 <web>https://docs.nextcloud.org/server/10/admin_manual/maintenance/manual_upgrade.html</web>
 <autoupdater>1</autoupdater>
</nextcloud>
';
		file_put_contents($this->updateServerDir . 'index.php', $content);
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

		$cmd = "php -S localhost:8870 -t " . $this->updateServerDir . " 2>/dev/null 1>/dev/null";
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

		$installedVersion = join('.', $OC_Version);

		if (strpos($installedVersion, $version) !== 0) {
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

		$expectedOutput = 'Maintenance mode is currently ' .
			($state === 'on' ? 'enabled' : 'disabled');

		if ($returnCode !== 0 || strpos(join(PHP_EOL, $output), $expectedOutput) === false) {
			throw new Exception('Maintenance mode does not match ' . PHP_EOL . join(PHP_EOL, $output));
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
		exec('./occ config:system:set --value '.$channel.' updater.release.channel');
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

		$outputString = join(PHP_EOL, $output);
		if ($returnCode !== 0) {
			throw new Exception('Return code of status output does not match ' . PHP_EOL . $outputString);
		}

		if ($state === 'not required') {
			if (strpos($outputString, $upgradeOutput) !== false) {
				throw new Exception('Upgrade is required ' . PHP_EOL . join(PHP_EOL, $output));
			}
		} else {
			if (strpos($outputString, $upgradeOutput) === false) {
				throw new Exception('Upgrade is not required ' . PHP_EOL . join(PHP_EOL, $output));
			}
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
			throw new Exception('Return code does match but should not match: ' . $this->CLIReturnCode . PHP_EOL . join(PHP_EOL, $this->CLIOutput));
		}
	}

	/**
	 * @Then /the output should contain "(.*)"/
	 */
	public function theOutputShouldBe($expectedOutput) {
		if ($this->skipIt) {
			return;
		}
		if (strpos(join(PHP_EOL, $this->CLIOutput), $expectedOutput) === false) {
			throw new Exception('Output does not match: ' . PHP_EOL . join(PHP_EOL, $this->CLIOutput));
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
		$this->skipIt = !version_compare($version, PHP_VERSION, '<');
	}
}
