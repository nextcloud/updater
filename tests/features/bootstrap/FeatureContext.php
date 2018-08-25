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
    		'9.0.54' => 'wVgtLXquICXsmfJwRUD8tJiFzYJAIdQfjAcOzvKGDYh96NMT6MGVEMYQgAfyYvq0
tuAcqsU87CDc1IQ14y2GmkSnwnXJrCXEJFaYqBGyXJtbzRukby5k+IVx2NTBaNjL
XMC1irGa7tnCC/pyn9K+RDDHSTa3aQ7W0Z2MIq+TpNuASwshOGaep9IP7bmVvEsS
CC8df8qp8xBkUA6PLxDkrHHGe1dTauuMYc0hUkzclC+fD2wFzV4ks1RpX6V4dLlZ
4+nzlDeepIAVOnoaIaxLv4DmITD5Mg408z/CCB2YBBntFC9wIlfErr9X4JqQWVEQ
Xpo6Rlr6bSFMHcDn3Bjn+Q==',
    		'9.0.55RC1' => 'uUGqJ6shKJCxoP9UvPclGewzN3jxX1blMyB27RsLN/jEf1Y6nnxz3kVyeXQcUuvV
P54w4cyKBnj8+mMJV57bnIUf42B1GFIiHTILpauDcC6KqsEt/kGIUDiHtZjZAJKr
jzuUjSAXKjTxeAQv0l9H7nQaa7Xs7LgWTV9LycQVLksYoV0MDBMOjBuxH017jgQH
AAzqdiQguM2pv5+j6AZcP6q1YRueLePTcM2q+AlDB52LYA1Dfmj81jsP1J9WmbXJ
IfbjOzrkifG5mJB+Q7/HOJjQYevaxjL6prSp0yym7nqSy63vH8GDxKa+gIK5Gfj+
0mo/sSnvB5vobDDb/TO8og==',
    		'10.0.1' => 'w64N/qoNW2jiStnNXWJBhVkD0eLs75ELvbAnDY2wCyFM5TlqTeKlBnwK7zuIOzwD
h5/k0tExXra1fBPic6qUF30Z9n1O6C60z0zKQYOaHrR4I4EN1SdHFsjbLyXxm/Ua
F3DwREXcYMT4r7XAl/PfX0zkpIMMmCu5GEd1GlxReY8sUcVcJmMf1/7x2NYNuQgV
UY4SEY7zFbybOcT8XfXNxod0isDkkOSj8B1TtTjlBZ6wyHSTVBtKg2fVKcTdOvxp
IlWHCJCnDFD9Nwz5bBTUd8ssNYqFBVNT2viyR696ObpIE1f7AJHpsgY9FJ4psDDf
D0kiMCecZa45XkBwFvg41Q==',
			'10.0.2RC1' => 'ScJSe22yoHYtwFH8nydZoWOgxRgWq6JlthFw7/BvTvUGMejaB4hD/s5hFKE4Luvs
GjV3/UtKpnBUpZSByQyEM/BbD+fyhbU3L+v6CQQYgjbtcRTyNmqdsFwZ+T1MXhTZ
Be10XmB2QB6Xi0jvxjD9d3AYUFPH8Chy8rF6Wth7V7Eexyuh+4secrvuv7pqbuNt
AmVRSYigLgIB5oVDd5BSvpeDe4ZhKN8qE1fDDTQX29iPvTn6M/FJZ3pH3ZrLRzgl
9n97PTVFgtC2NdLYc7GD9bHxTUL1/iKvp7s9I6Tp6oxyBfOJwC4AqRTwURnG1ouZ
EjMJzAibeG1AVDDbaU6irQ==',
			'11.0.0beta' => 'LflrJaVMjHH1ntRlQ1mkgvQ86VTSFxvw77kcPt33tWVk6GLSMxpy+/71V3nGNHM2
STd1czLzm+AUWdWWVluAci6XjJ7x+Tlsg5S7+ofuiwO1/m1nf5yXeHEf9q9ATqzy
UkzEhCEbMF+9Zxd5WZZkexUZyVf/cfx8MBEsvsqqysHGgZJKmoJEwN2jCnKYIQzL
yAvOWXjOA0xarzXxwdlTnkFEnzBvFeYAThcfBDdUBRbyCqVbpU7IBQoXZYCSAtac
FBKNj6b48LDvE4BDnid8y/91q88WdL4QLy4wVFVPxbMIObfDeAnyLvG+p7La7V5G
iPnnYL0Y2XIhbshkwWhS/Q==',
			'11.0.0beta2' => 'WAzWUB8rgr/deeOnlBn084ysJe3Z8J2dSunkJNlNEtbbCBr2ALwZh6Xe41MXr2F/
S6riPZk2uRmx5W2XZ+IUHSTVlAOKOQaflTlo3xConuKfcyojJ3PGsO8x7awtRNA7
2GBBBTNKY9/q8eONztsBWY7O2pWMTqJKEuXiXQyAQ3IkqxQkMWoyxKU/zGWBdZad
QYwN9zVszIoWo31nlVz7QSzUZ4iyEVnbY4gT5EVPKfflIPQblp2bN485pYamuPe5
CYB6S4AUzuyYS2zxt7MMdoaWHXY8hxdst1AF/kGKZ5Bdct4qVw7pup77c+uMNBvT
jFSE6+KKI+HAE132eaXY5A==',
			'11.0.2' => 'hvw4zZs6gSeP4TlU0kkfpJ1tgaSrD2M8V/MANX/YqwZBy9mG8z67Mtt07sbYByHh
kWVd2uVmVoiEcmNEtiJUE1WEcrC+YSAFUTl8P4MjUa2jEC3k37zIn1WcFI8ZqRiH
EBYiSef87rZXjcvuta5fC4O0cOaxU3pVkNVqeP9T0tHEI4Oorj5Uj8qoiuIH2Xbc
chLfk+x/EatNAlTE6NJo6rJnquCErooOPgLl6k48oOcgJZZtOQ1xDhb69Yug25bv
V12smv+3iUGpQBIJnBhIZY+Ww7SOCCca/ss1f+/uEMr3NFGiDgJ4KYoxF/pYaGo4
MgK0pHPeAIesiUnEEq8y6w==',
			'11.0.4' => 'trn0fADgH12IioNSPDzYlkIAhlXg2ETpmkm+dENefK2HqVdDJBJX62kCYug4WovB
PTS455VNR43dFFCgqjvQiip/XTLHpG2ppAZq35gDslHbD8HcACS+T0OpW/mJYseD
1+eCbaYShguIcCLlTXaPhbesIh2iO2guBzv6xODSFDKlAWHXwZ3xcumX8QE+7oex
E9HDBL7XYkvMavCvMQjYgAQ6CTCgAxe0wYpa6O8HWhk0AgidDJgevHyHFOssxrTm
TCDZ2VgqwydUVcs+pfKC3VJkutrPOOH2JcltremBpYjkL4d25BDqPNDGi5FOKWyI
tJejM2uk8UEjo4mJ6q7BIA==',
			'12.0.0' => 'My4ZSUly2nD1t+pKhS4uzzlkVt08TQi64DbICxO2nIGB1I3RpyqXgd+BXWobTG35
116	J7DiJ6LEbHw/YMuzYj/vi+XJxbmkGrsHxWhlc5jdn3sc406Dgr4ywyZztiS2SCMy
117	tTAg0vwiH3pBP68rx/2ltPN7hS3HkReqb/28rAegntXVd35R9w9YGbOl4zfqmnSJ
118	zUBXGiOmKhh6KPcPaiavxDXCgR+4v2pgu9J3RmPgkq0m2AsDl6YPOxN+FGVHKbBP
119	TJw0efCxt7Q3Mdo70zmJbyE+Dal+UV+EgZC3tU+zd2vUckrs6B2xNoSBlo1UOjo2
120	dL+1Gy7Ry+R0Y1eFw+XYJQ==',
			'12.0.0beta1' => 'ho8LV/2eB0kSI89JJDTn8BtDnUBlAnFOVaDgcBim+N2yUUzsGy8Q+nWeffrL0bPU
951fuSIHpjByfJxiSL5GipoT6555992PV6B8BckyTgVvWXxKGH2htQVdYUDTKfaB
DCe59CvjNe4YR/qqBitTyJYeWqGD4FCrmAGmbQmhINm70H1TUl2zHBFi7rqFKwcw
et3H3uSKf1UNGKx/HE1RSlGCukTc/o+UcwT7wAPlm3YfIMG9vrLX5s27JG6p5MlW
N8VZ0VTtBZY0EAqedrHWZ4FxFOwmmxfdVoUbJgq8ZUlWBmAIj9t4vbHzXAVxu437
6Jx1KWDwCnir1GssOSy2FQ==',
			'12.0.0beta2' => 'd8f33pXCXVpkSpqlYYXWgjbq0uj5NrIDzwWCvtaU0C/B9g42hK4vJwOPGeQcdHh/
A4xDB/BtSv01KvSd1kECrGFW7rH9HlHFq1LmMRAOj3slnKHZN4XLmJ5whMobfTip
qfoxA4FJuhzInr3MK3Kbg8VsiX1BHlD6UDS1Uyif5JinUR1BvYaDhTQAzzyRI8hC
OXJ3Dug6SCBQFbvRsBSWoAVufyjENg3JSOmKl6X0ROKw4nPg+BL8ZZhiF9t8gldZ
iXeCFpAF+JdF9xEmO0KqTi10DuLG7Svr0gzA5B8joLWQHGGOJY3+8mr9RaF4QMcH
VKXfKG/PZfQKKIk6qALaaw==',
			'12.0.1' => 'R6+aQoW/L0L7RyM/ER06kH7XJHyskvmuOHcUKIydWGbFN1PQTjblqXiiUcsmFtsD
+ZLeiPtWg3wquzuA3GWk7vrJrRvIKguULBEdtaDv76jeOxX8IVtPZYyq9ywSleHU
qd9CCurtREBZVmaQLy79+aXvan+pSvq4f9kl9jVVDp/v9QDpaHSLtKrUCHGgyQZO
0APf4QTMn+Jlw40JD3vbQSPkxWb7dcQEE6gGg6htN7iLHRRVXf8sJWFrLGotCHCH
RVY+A9o0d/+e49hXBsqNA03y1ZETQkVle1X7618aFj+Rfekq+yANXG1w2yD69Erv
DB8RQvd5QS/1Igt4LyTdMw==',
			'13.0.0beta2' => 'nSIJYm2Bys4Hp3Vpv5aOEo7QY+rgdVKKqL5J9wv/VSRRcJIL2JcuJmdrHtXc5fsR
aBdpLVRrtBuXi6l92M3/8+GnycNBj62OEKECa5KNl1pOInwTTrmwLFNa/S9ooG4y
Ntb9nkGUIpQjfFbgYSBdLPNuP6tZcA3AGM7IjNNCEE4ai+4n5Q+j/d/9mcV54qzV
BZjRQzqQ9QPTxq45i9ZO8GZhKBIXzWzt6TwtKQY8pds6uCrilHuw/QI6fCmOHfSH
mlGSfQdip562QrdH1YE1bmgPdSZ2eC4k8rAbMQSRQc6XZhbNpwGKs0lcPxvaVTqP
xofdQ77i4w95e0fNOH3mzQ==',
			'13.0.1' => 'AG3egWHJkQaqCraVLiRq5Q7GvqArmBgVv8PNAfVYTOXPN6xWmw8cbUeBvBZ3OS1/
bup1ktLDwTLDjWm1XMBUhhQCxVDLtEd3A6WfaVJz9BWoz1MyxUGlaIJSzTHLoEfz
nFVUdoGH0mAdq8WtxRQSNwQWeKn+iF1jpbtIbDc29POtWvvzEgT/KW8MDzeidx6s
W78tH4vldR0/aBn1D3hwnkQEQ8+Kz+Y4ItjHHi6XpJEfRQzYD0j9T+VTQ9IX4Xf/
XqTrcaUCqwOlSC4pM7aUUzgaePPcYU2zrRDRaEgLma9eSkVMzkkc4kfM3izBG0Iv
STb5hZFB2HMLyJxuj1l05w==',
			'13.0.4' => 'OogV1NE98yxer6atJPOgXBxnVgAuME94UoZ1TlgTGUw0KYqvKNwKKQ+gqzJ3ZL9t
XJuHAYQi2Uk0iblIA3TFuDALU/wA3th2PMoobHtzR4FFzDUvb88FdgKxBAOhh9n0
1PwiLBYT1FuDJn+fQLmbXWbPVlfFOOsyPFxysl1nXztxSJ7FsPgIE/MGvqKnk4L3
5iG3o/UAbqmmKNQpn7gJw4BYdf3mkJcBOgdRrcy3MXqLh9dIAXkK5HcKOD3A+Uvy
amApr7+j7zp0QlnhrObLBWramdSqmS2Udt/TdN1XhMF/9Nzq+qod6aJ8qCz9aqEb
PUPFcPmm6YQgra/1OoMTpg==',
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
        // Hack for version number mapping
        $installedVersion = str_replace(['9.1', '9.2'], ['10.0', '11.0'], $installedVersion);

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
