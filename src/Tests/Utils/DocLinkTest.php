<?php

namespace Owncloud\Updater\Tests\Utils;

use Owncloud\Updater\Utils\DocLink;

class DocLinkTest extends \PHPUnit_Framework_TestCase {
	public function testGetServerUrl(){
		$expected = 'https://doc.owncloud.org/server/9.0/admin_manual/installation/installation_wizard.html#strong-perms-label';

		$version = '9.0';
		$relativePart = 'installation/installation_wizard.html#strong-perms-label';

		$docLink = new DocLink($version);
		$this->assertEquals($expected, $docLink->getAdminManualUrl($relativePart));
	}

	public function versionDataProvider(){
		return [
			[ '1.2.3.4', 'https://doc.owncloud.org/server/1.2/admin_manual/' ],
			[ '41.421.31.4.7.5.5', 'https://doc.owncloud.org/server/41.421/admin_manual/' ],
			[ '42.24', 'https://doc.owncloud.org/server/42.24/admin_manual/' ],
		];
	}

	/**
	 * @dataProvider versionDataProvider
	 * @param string $version
	 * @param string $expected
	 */
	public function testTrimVersion($version, $expected){
		$docLink = new DocLink($version);
		$trimmedVersion = $docLink->getAdminManualUrl('');
		$this->assertEquals($expected, $trimmedVersion);
	}
}
