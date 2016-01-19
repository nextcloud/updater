<?php

namespace Owncloud\Updater\Tests\Utils;

use Owncloud\Updater\Utils\Registry;

class RegistryTest extends \PHPUnit_Framework_TestCase {
	public function testGetUnsetValue(){
		$registry = new Registry();
		$value = $registry->get('random_key');
		$this->assertNull($value);
	}

	public function testGetExistingValue(){
		$data = ['someKey' => 'someValue' ];
		$registry = new Registry();
		$registry->set('key', $data);
		$value = $registry->get('key');

		$this->assertEquals($data, $value);
	}

}
