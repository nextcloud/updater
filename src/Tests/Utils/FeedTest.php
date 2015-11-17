<?php

namespace Owncloud\Updater\Tests\Utils;

use Owncloud\Updater\Utils\Feed;

class FeedTest extends \PHPUnit_Framework_TestCase {

	public function resultProvider(){
		return [
			[ [], false ],
			[ [ 'url'=>'123' ], false ],
			[ [ 'url'=>'123', 'version' => '123' ], false ],
			[ [ 'url'=>'123', 'version' => '123', 'versionstring' => '123' ], true ],
		];
	}

	/**
	 * @dataProvider resultProvider
	 */
	public function testValidity($feedData, $expectedValidity){
		$feed = new Feed($feedData);
		$this->assertEquals($expectedValidity, $feed->isValid());
	}
}
