<?php

/**
 * Copyright (c) 2014 Victor Dubiniuk <victor.dubiniuk@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

 class Test_Updater_App extends  \PHPUnit_Framework_TestCase {
	public function testGetFeed(){
		$data = OCA\Updater\App::getFeed();
		$this->assertNotNull($data);
	}
 }