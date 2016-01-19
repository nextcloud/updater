<?php

namespace Owncloud\Updater\Tests\Utils;

use Owncloud\Updater\Utils\ConfigReader;

class ConfigReaderTest extends \PHPUnit_Framework_TestCase {

	protected $config = [
		"system" => [
			"instanceid" => "oc8v9kkjo6bh",
			"ldapIgnoreNamingRules" => false,
		],
		"apps" => [
			"backgroundjob" => [
				"lastjob" => "3"
			],
			"core" => [
				"installedat" => "1423763974.698",
				"lastupdatedat" => "1450277990",
				"lastcron" => "1444753126",
				"OC_Channel" => "beta",
			],
			"dav" => [
				"installed_version" => "0.1.3",
				"types" => "filesystem",
				"enabled" => "yes"
			]
		]
	];

	public function testGetByPathProvider(){
		return [
				[ 'apps.core.OC_Channel', 'beta']
		];
	}

	/**
	 * @dataProvider testGetByPathProvider
	 */
	public function testGetByPath($key, $expected){
		$occRunnerMock = $this->getOccRunnerMock(json_encode($this->config));
		$configReader = new ConfigReader($occRunnerMock);
		$configReader->init();
		$value = $configReader->getByPath($key);
		$this->assertEquals($expected, $value);
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
