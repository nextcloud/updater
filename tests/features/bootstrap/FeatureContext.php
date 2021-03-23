<?php

use Behat\Behat\Context\SnippetAcceptingContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements SnippetAcceptingContext
{
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

    public function __construct()
    {
        $baseDir = __DIR__ . '/../../data/';
        $this->serverDir = $baseDir . 'server/';
        $this->tmpDownloadDir = $baseDir . 'downloads/';
        $this->updateServerDir = $baseDir . 'update-server/';
        $this->buildDir = $baseDir . '../../';
        if(!file_exists($baseDir) && !mkdir($baseDir)) {
            throw new RuntimeException('Creating tmp download dir failed');
        }
        if(!file_exists($this->serverDir) && !mkdir($this->serverDir)) {
            throw new RuntimeException('Creating server dir failed');
        }
        if(!file_exists($this->tmpDownloadDir) && !mkdir($this->tmpDownloadDir)) {
            throw new RuntimeException('Creating tmp download dir failed');
        }
        if(!file_exists($this->updateServerDir) && !mkdir($this->updateServerDir)) {
            throw new RuntimeException('Creating update server dir failed');
        }
    }

    /**
     * @AfterScenario
     */
    public function stopUpdateServer()
    {
        if(is_resource($this->updaterServerProcess)) {
            proc_terminate($this->updaterServerProcess);
            proc_close($this->updaterServerProcess);
        }
    }

    /**
     * @Given /the current (installed )?version is ([0-9.]+((beta|RC)[0-9]?)?|stable[0-9]+|master)/
     */
    public function theCurrentInstalledVersionIs($installed, $version)
    {
		if ($this->skipIt) {
			return;
		}
        // recursive deletion of server folder
        if(file_exists($this->serverDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->serverDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $fileInfo) {
                $action = $fileInfo->isDir() ? 'rmdir' : 'unlink';
                $action($fileInfo->getRealPath());
            }
            $state = rmdir($this->serverDir);
            if($state === false) {
                throw new \Exception('Could not rmdir ' . $this->serverDir);
            }
        }

        $filename = 'nextcloud-' . $version . '.zip';

        if (!file_exists($this->tmpDownloadDir . $filename)) {
            $fp = fopen($this->tmpDownloadDir . $filename, 'w+');
            $url = $this->downloadURL . $filename;
            if (strpos($version, 'RC') !== false || strpos($version, 'beta') !== false) {
                $url = $this->prereleasesDownloadURL . 'nextcloud-' . $version . '.zip';
            } else if(strpos($version, 'stable') !== false || strpos($version, 'master') !== false) {
                $url = $this->dailyDownloadURL . $version . '.zip';
            }
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Nextcloud Updater');
            if(curl_exec($ch) === false) {
                throw new \Exception('Curl error: ' . curl_error($ch));
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if($httpCode !== 200) {
                throw new \Exception('Download failed - HTTP code: ' . $httpCode);
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

        if($installed === '') {
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
    public function thereIsNoUpdateAvailable()
    {
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
	public function theCliUpdaterIsRunSuccessfully()
	{
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
    public function theCliUpdaterIsRun()
    {
		if ($this->skipIt) {
			return;
		}
        if(!file_exists($this->buildDir . 'updater.phar')) {
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
			'15.0.0RC1' => 'AQK4hJcQ1TZAPOpMfVy4ukOp6ny1DyzBY5ui7P9WhgBMHLeP4ed9Apmc07gcCOJF
Ya/+Bfc/ESNtVVeZQ3r9ubSz0NTTBgXDO7N7ymSCgA5wq4wqgjHW+bKZfDHoxt+1
WbRmf4trGwDdCA/kQ59LInfLR8KFfEiiOH2p2NijgXuWm49tdr7N1062diP4Dzwd
WlWfRoT8G5DDQPGdj5a72+sQLjf3ZhjqgeQbGLwht/NonWLZhmmW/NgmylbCb9Ob
a7NE4Vf792DrmBvq4HCHUexg+upaJ0s4sJLGqg3sWF8iUEnLyGePyL/Bw6MeLvjD
sSD0Xb5oawIjTVHVAuL+3Q==',
			'15.0.10' => '1FcyXT8cgPMctfn+S5QLGbPrkeXEWyaIGh1T9mP7BHaF5AHamuAGDzk04FKHYB/e
OylmXzDBE2w1LG5AOABAw3idwIJpfvH2dyi8tsBNdcGKY01DmMYEzn4y5i2r5Duv
lRjhRfuk/MrjUOH1XkGO1xPI8zXp8eI7Y53jHswbUayJGszzgCKcuC6z+QEUkf7v
p15duG8nbbF1c3CAh/dVsJ2AKKpcnLYJPC8UgGDXMtalSoPUv9QTWPrS7AqQRsVD
7xJThvWdx3aSV3aKREBzw5ddHUAIEENQ0aBdabgAXbBZEfLrMMG2XaHtdXGLpQs8
ypwcWVvLfYk9e+YEMdhMNg==',
			'16.0.3' => 'TYiUB/+l2uXNpiHGuMhchHzzyMn8eiL2mzBD+fmwqUXU29UYK4FFvTbDEWWXxXF9
XeOXXBTkWltQ6A5K+nwFx4Phf4VPznaGn3U/1hsLSLBy9p9qqBMQdmDvCxl4dJmP
R0Ttz6sGcC1AsYwW2Q5Z1lywpmk1Ax5YcJesbjOFTU9HXIOI2s9YyPX4bP3L1rkH
CaZqjW7yCedKhj64F/SuXnUwaPhqTNNoDdhCN14IKvXCFYKLxm5UoGXrddwIcRrL
rHSv7h5818aTjmj4sB1jsVrxNf32PgrUED8PUqgMYx1FxEzGyct4yj+GbIBi4D+K
b813iKq4+cn3CjTunREm6A==',



			'19.0.0beta4' => 'Cum4wIKpCRHNZuOQ/SfDYsIp39/4/Z3EIiMLTV7vFLf1pjsn+q1FRwk7HbT0ileU
9eGbmpJHrmbNFk73g6k2YLOeosznMD89+AtRrRRn7C+sJmXx90+Eejs1aWWBRi8j
wnC4PNYGZCqV10z+bMWNFWuZlkO2o2c+o8I/mIM93trRgnciFzA2YYAILnz4vZ5L
ZPLKMJmGqSbhxocFUKayXoyeUY1jv/rStYijfdLgcqY4EudstDJjFaLPsKLuTRQW
Ha/+m01i2qnkHvWuai0qaD7qYKMn21SeWfguSl07uH9LiL5wskOUvFzFy1KRL9lb
zjM6gntkGWUI0mO2YFzbbA==',
			'19.0.1' => 'uTYzr6YDYDK68A8fZ42IOzQEmilMNmsX4L1bypJkkAN/UOBP5ZK8tc/hgSrmXd4t
g0f7/c39nYo9CfMs8swPYCqTt7mbgx+z/LxlVJXNJeQkYEtLIA+kczQKNprY/c4E
wNk/dR5uVMqmlkAhQVXwJ8M4D30t1wJ8235XRLYtOfxowE+OQLLkYaDNEyU0pJK5
jCKI42xQJyO1bn0FAvGR5zqyWeHSXJKr5fleDw/L8ozPCZIgppGbigvwhOWE+Exs
bsf53THWtncb19OL626j8RbgQ3EuN4uSU7cp1pGqkg/kzQdBxR7TXcOqNMw8tiPC
CsKuFqRI37eQhbI/i2nrjg==',
			'19.0.2' => 'MfRJPu59KkLbJSICRyTyvtH4jLV6rwD5cTR3VTF7T5TqSwHLcOKDi7yEZK9qZKTm
qelJt17cUknifzJ+LKXwE26xua5SYA6KKGL1WIcaDKIJm8UMJhE3w7cMcxi79FVn
4Gs1jOc+TxTaM7hD32QzqIqLfYXmfXBjkV6zdNmqEXP8yHErdazU6+FSYbA5Z+JH
+LA46bRlAqZgf0NsfblCCpE5kAFlgMbZ4lpU6SfuNBWvIoX5k4iTtDvflQlpg58S
zY8iDQ+AZ2ttFoQ4MjfyTzM1VM0WDL5f70phVg4F+jG/JPxo9Qpyhq1Hy30ANPa0
dNqXHF6Re45NNc0vwNHjEA==',
			'19.0.3' => 'THIS IS AN INVALID SIGNATURE',
			'20.0.0' => 't9y2V2n1FSBQmXd1UawMRWvOJU98fEhTkYCBkoILHnJtkXWeobw5rNcsQ5R5oop4
+5Wlxqw520/M+SQF5+MUwpMfB3QJabFJbXm3kFKDbXeNjIcTmrfmcqiVpCGa3qtY
kqAtCh15IPYKR3dSHDH3pOSWlJpnL6rlOYZZSEWYwBIF1BQ9fTjW1i2mat0Fl2OE
CN6Aim4OoaZz4U7coPSi6pyRKcsV2KAaxlHIHzj71EjqV7gaoZRhzSnN/NomPu6D
hfOAOW47o1GsZXt4euDJMpye1h8PE1csaaeCvc0gla7C6KE4LfTO0nLTu/yTNcB9
HNkEiZqVg/EjiuZHcSXd/g==',
			'20.0.0RC1' => 'fLz3hBBrPXlJIbDkzAme++HDLayz/ES0bjb42ZtSur+9+UrwyVQdfNsVKQXYtZTr
akGbqmefYpQfVITDRz4Z5weMp/ti7mC9pQR7gjtCN9QmrG/34iURuJlJB8Ty6Mrk
Y6d4C8HjQ4CW2lvEc1/PvxZRI8SfMx+KQFuquJUKNmGInG6DOjtiCQ/mqcSaFt/9
JNJS7NBPkNM7QFJHeedEBdL2Q8Ssu9DdM464tNa0jBo6xrkvinwZLUkULFKeNNIP
SIEb7x+TVLDhCgNSLU1J9qPmscaAGZaYZK6UH3wM51T7pBaL69gIP8AP3HgTzgLK
RO/f0KLHqrWDl86w/ybMpg==',
			'20.0.1' => 'EDf7pKNahd9y/R6Gz69/i6DL/JcWddlqAvpibgvkKbBCX0cr5Oa7PZQzA26U2Ad8
fdLgOjnBIKhSo10gn1D8/5pHjuIltFqUepdKFyvx3G/+1znA+S8VfKbDgItwHf1D
gaTtuEcHuONoX7yyCib5kr6YQX4Cx0ICJmrS+KBB0CHzGwAo8Cj98havcDo8vYr0
wR+I/lo/45qAgfUrcNge2ZJdtlZj6VHn/I9zRhz2Q+MDNMRrKxZ/XN/8KhdDerTS
54QfoEoYbVRlLfjc45meKENdxM6/HK8XZckXkJI1Zc6XyubWsSYwnNWY1/GV5Zhd
mxDr4jUlvGQX1jFuzd4nLQ==',
			'20.0.8' => 'i49DH/MdR7skQ3wq4+iU/YJOhAqRwEygGQqiku66FXrJ5PDLdb8mWcKeYPkntPTb
jzi9gzOLWWQaqGYVXm4uqgYedU7gaUawP73aCZqUTSw+so/ktP4VNomWxDbOP+T1
qERrTdOOvC1N5wdqchvRIriMgtYIQU3ezwHGECw/+Ps7r4UJLMfBFn56NVqluUkh
yxgneDwLXdjHXIQ4DVsO4pKHkJmRyEzSvqDn0IxHvtELnV6qNAGjFghH5FC3Tnqu
Gm/nFuznVsq84U0YQKVVAA+ci0yXLB6cQCRwz5H3Qfn45v6ZivL7xjrk9aX423yK
CN45I0sFnm6aWxGPN1GegA==',
			'21.0.0' => 'Jd0TVUqo3cVPKWaN7/5dEbIcJKcS9Z0V334sBaJAfXe+YgV6lRmPHrGuTK34+tUc
l9/OHJrazOO7tEDRdtM4zpFE7y9kHsqTTR2MTEVLwIZ5JsgTPHE2/im0wlWLk3UI
4Oo5TPt1EOgNbYHfEBY8FPKCL6JyT2ySBaQKYdfJMgkxUZw00BMjplhNUgXEhvd2
f8tPNZ3cs7hyr0SQFx96gyFcCQ4+5KohnLGqZnNSyV7ovHbYEzXUCmmULlm02zSu
V367c1FDks7iKs/V96u21NuF14IQzI0mEzMzysPINrKbHC+OU1BKHKOHqRJjkHwQ
fNJsXi16UkYMGUXyQWQXHg==',
		];

    	if(isset($signatures[$version])) {
			return $signatures[$version];
		}

		return '';
    }

    /**
     * @Given /there is an update to version ([0-9.]+) available/
     */
    public function thereIsAnUpdateToVersionAvailable($version)
    {
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
	public function thereIsAnUpdateToPrereleaseVersionAvailable($version)
	{
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
	public function thereIsAnUpdateToDailyVersionAvailable($version)
	{
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
    protected function runUpdateServer()
	{
		$configFile = $this->serverDir . 'nextcloud/config/config.php';
		$content = file_get_contents($configFile);
		$content = preg_replace('!\$CONFIG\s*=\s*array\s*\(!', "\$CONFIG = array(\n 'updater.server.url' => 'http://localhost:8870/',", $content );
		file_put_contents($configFile, $content);

		if (!is_null($this->updaterServerProcess)) {
			throw new Exception('Update server already started');
		}

		$cmd = "php -S localhost:8870 -t " . $this->updateServerDir . " 2>/dev/null 1>/dev/null";
		$this->updaterServerProcess = proc_open($cmd, [], $pipes, $this->updateServerDir);

		if(!is_resource($this->updaterServerProcess)) {
			throw new Exception('Update server could not be started');
		}

		// to let the server start
		sleep(1);
	}

    /**
     * @Then /the installed version should be ([0-9.]+)/
     */
	public function theInstalledVersionShouldBe2($version)
    {
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
	public function maintenanceModeShouldBe($state)
	{
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
	public function theCurrentChannelIs($channel)
	{
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
	public function upgradeIs($state)
	{
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
	public function theReturnCodeShouldNotBe($expectedReturnCode)
	{
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
	public function theOutputShouldBe($expectedOutput)
	{
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
	public function theVersionNumberIsDecreasedInTheConfigPHPToEnforceUpgrade()
	{
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
	public function thereIsAFolderCalled($name)
	{
		if ($this->skipIt) {
			return;
		}
		mkdir($this->serverDir . 'nextcloud/' . $name);
	}

	/**
	 * @Given there is a config for a secondary apps directory called :name
	 */
	public function thereIsAConfigForASecondaryAppsDirectoryCalled($name)
	{
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
	public function phpIsAtLeastInVersion($version)
	{
		$this->skipIt = !version_compare($version, PHP_VERSION, '<');
	}
}
