<?php

namespace Owncloud\Updater\Tests\Utils;

use Owncloud\Updater\Utils\Checkpoint;
use Owncloud\Updater\Utils\FilesystemHelper;
use Owncloud\Updater\Utils\Locator;

class CheckpointTest extends \PHPUnit_Framework_TestCase {
	public function testGetAll(){
		$checkpointList = ['a', 'b', 'c'];
		$fsHelper = $this->getMockBuilder('Owncloud\Updater\Utils\FilesystemHelper')
				->disableOriginalConstructor()
				->getMock()
		;
		$fsHelper->method('scandir')
				->willReturn($checkpointList)
		;
		$locator = $this->getMockBuilder('Owncloud\Updater\Utils\Locator')
				->disableOriginalConstructor()
				->getMock()
		;
		$checkpointMock = $this->getCheckpointInstance($locator, $fsHelper);
		$actual = $checkpointMock->getAll();
		$this->assertEquals($checkpointList, $actual);
	}



	protected function getCheckpointInstance(Locator $locator, FilesystemHelper $fsHelper){
		return new Checkpoint($locator, $fsHelper);
	}
}
