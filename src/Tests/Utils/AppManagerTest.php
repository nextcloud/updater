<?php

namespace Owncloud\Updater\Tests\Utils;

use Owncloud\Updater\Utils\AppManager;

class AppManagerTest extends \PHPUnit_Framework_TestCase {

	public function testDisableApp(){
		$appId = 'anyapp';
		$appManager = new AppManager($this->getOccRunnerMock(''));
		$result = $appManager->disableApp($appId);
		$this->assertTrue($result);
	}

	public function testEnableApp(){
		$appId = 'anyapp';
		$appManager = new AppManager($this->getOccRunnerMock(''));
		$result = $appManager->enableApp($appId);
		$this->assertTrue($result);
	}

	public function appListProvider(){
		return [

					[
						[
							'enabled' => [ 'app1' => '1.0.1', 'app2' => '2.4.1' ],
							'disabled' => [	'dapp1' => '0.0.1',	'dapp2' => '5.1.1' ]
						],
						[ 'app1', 'app2', 'dapp1', 'dapp2'	]
					]
		];
	}

	/**
	 * @dataProvider appListProvider
	 */
	public function testGetAllApps($apps, $expected){
		$encoded = json_encode($apps);
		$appManager = new AppManager($this->getOccRunnerMock($encoded));
		$actual = $appManager->getShippedApps();
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @dataProvider appListProvider
	*/
	public function testGetShippedApps($apps, $expected){
		$encoded = json_encode($apps);
		$appManager = new AppManager($this->getOccRunnerMock($encoded));
		$actual = $appManager->getShippedApps();
		$this->assertEquals($expected, $actual);
	}

	public function testGetAppPath(){
		$expected = '/dev/null';
		$appId = 'anyapp';
		$appManager =  new AppManager($this->getOccRunnerMock($expected));
		$actual = $appManager->getAppPath($appId);
		$this->assertEquals($expected, $actual);
	}

	protected function getOccRunnerMock($result){
		$runnerMock = $this->getMockBuilder('Owncloud\Updater\Utils\OccRunner')
				->setMethods(['run'])
				->disableOriginalConstructor()
				->getMock()
		;
		$runnerMock
				->expects($this->any())
				->method('run')
				->willReturn($result)
		;
		return $runnerMock;
	}

}
