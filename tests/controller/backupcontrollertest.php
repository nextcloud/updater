<?php

/**
 * Copyright (c) 2015 Victor Dubiniuk <victor.dubiniuk@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Updater\Controller;

class Test_Updater_BackupControllerTest extends  \PHPUnit_Framework_TestCase {
	private $appName = 'updater';
	private $request;
	private $config;
	private $l10n;
	private $controller;
	
	public function setUp(){
		$this->request = $this->getMockBuilder('\OCP\IRequest')
			->disableOriginalConstructor()
			->getMock()
		;	
		$this->config = $this->getMockBuilder('\OCA\Updater\Config')
			->disableOriginalConstructor()
			->getMock()
		;
		$this->l10n = $this->getMockBuilder('\OCP\IL10N')
			->disableOriginalConstructor()
			->getMock()
		;
		$this->controller = new BackupController(
			$this->appName,
			$this->request,
			$this->config,
			$this->l10n
		);
	}
	
	public function testIndex(){
		$response = $this->controller->index();
		$this->assertEquals('success', $response['status']);
	}
	
	public function testDelete(){
		$response = $this->controller->delete('/somepath/anyfile.txt');
		$this->assertNull($response);
	}
}
