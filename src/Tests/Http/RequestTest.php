<?php

namespace Owncloud\Updater\Tests\Http;

use Owncloud\Updater\Http\Request;

class RequestTest extends \PHPUnit_Framework_TestCase {

	public function varsProvider(){
		return [
			[ [], 'abcd', null ],
			[ [ 'post'=> [ 'command' => 'jump'] ], 'dummy',  null ],
			[ [ 'post'=> [ 'command' => 'jump'] ], 'command', 'jump' ],
			[ [ 'post'=> [ 'testArray' => ['key' => 'value'] ] ], 'testArray',  ['key' => 'value'] ],
		];
	}

	/**
	 * @dataProvider varsProvider
	 */
	public function testPostParameter($vars, $key, $expected){
		$request = new Request($vars);
		$actual = $request->postParameter($key);
		$this->assertEquals($expected, $actual);
	}
}
