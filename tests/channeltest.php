<?php

/**
 * Copyright (c) 2014 Victor Dubiniuk <victor.dubiniuk@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

class Test_Updater_Channel extends  \PHPUnit_Framework_TestCase {
	public function testGetFeed(){
		$mockedConfig = $this->getMockBuilder('\OCP\IConfig')
				->disableOriginalConstructor()
				->getMock()
		;
		$mockedL10n = $this->getMockBuilder('\OCP\IL10N')
				->disableOriginalConstructor()
				->getMock()
		;

		$certificateManager = $this->getMock('OCP\Http\Client\IClientService');
		$mockedHTTPHelper = $this->getMockBuilder('\OC\HTTPHelper')
				->setConstructorArgs(array(\OC::$server->getConfig(), $certificateManager))
				->getMock()
		;

		$mockedHTTPHelper->expects($this->once())->method('getUrlContent')->will($this->returnValue(''));
		
		$channel = new \OCA\Updater\Channel($mockedL10n);
		
		$data = $channel->getFeed($mockedHTTPHelper, $mockedConfig);
		$this->assertNotNull($data);
	}
}
