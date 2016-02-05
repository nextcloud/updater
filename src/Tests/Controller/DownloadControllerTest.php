<?php

namespace Owncloud\Updater\Tests\Http;

use Owncloud\Updater\Controller\DownloadController;
use Owncloud\Updater\Utils\Registry;
use Owncloud\Updater\Utils\Feed;

class DownloadControllerTest extends \PHPUnit_Framework_TestCase {

	protected $feedData = [
			'version' => '7.5.5',
			'versionstring' => 'version 7.5.5',
			'url' => 'http://nowhere.example.org',
			'web' => 'http://nowhere.example.org/rtfm',
		];


	public function testCheckFeedSuccess(){
		$feed = new Feed($this->feedData);

		$fetcherMock = $this->getMockBuilder('Owncloud\Updater\Utils\Fetcher')
				->disableOriginalConstructor()
				->getMock()
		;
		$fetcherMock->method('getFeed')
				->willReturn($feed)
		;
		$fsHelperMock = $this->getMockBuilder('Owncloud\Updater\Utils\FilesystemHelper')
				->disableOriginalConstructor()
				->getMock()
		;
		$downloadController = new DownloadController(
				$fetcherMock,
				new Registry(),
				$fsHelperMock
		);
		$result = $downloadController->checkFeed();
		$this->assertArraySubset(['success' => true, 'exception' => ''], $result);
		$this->assertEquals($feed, $result['data']['feed']);
	}

	public function testCheckFeedFailure(){
		$badNewsException = new \Exception('Bad news');
		$fetcherMock = $this->getMockBuilder('Owncloud\Updater\Utils\Fetcher')
				->disableOriginalConstructor()
				->getMock()
		;
		$fetcherMock->method('getFeed')
				->will($this->throwException($badNewsException))
		;
		$fsHelperMock = $this->getMockBuilder('Owncloud\Updater\Utils\FilesystemHelper')
				->disableOriginalConstructor()
				->getMock()
		;
		$downloadController = new DownloadController(
				$fetcherMock,
				new Registry(),
				$fsHelperMock
		);
		$result = $downloadController->checkFeed();
		$this->assertArraySubset(['success' => false, 'data' => []], $result);
		$this->assertEquals($badNewsException, $result['exception']);
	}

	public function testDownloadOwncloudSuccess(){
		$md5 = '911';
		$path = '/dev/null/o';
		$registry = new Registry();
		$registry->set('feed', new Feed($this->feedData));
		
		$fetcherMock = $this->getMockBuilder('Owncloud\Updater\Utils\Fetcher')
				->disableOriginalConstructor()
				->getMock()
		;
		$fetcherMock->method('getBaseDownloadPath')
				->willReturn($path)
		;
		$fetcherMock->method('getOwncloud')
				->willReturn(null)
		;
		$fetcherMock->method('getMd5')
				->willReturn($md5)
		;

		$fsHelperMock = $this->getMockBuilder('Owncloud\Updater\Utils\FilesystemHelper')
				->disableOriginalConstructor()
				->getMock()
		;
		$fsHelperMock->method('md5File')
				->willReturn($md5)
		;
		$fsHelperMock->method('fileExists')
				->willReturn(true)
		;

		$downloadController = new DownloadController(
				$fetcherMock,
				$registry,
				$fsHelperMock
		);
		$result = $downloadController->downloadOwncloud();
		$this->assertArraySubset(['success' => true, 'exception' => ''], $result);
		$this->assertEquals($path, $result['data']['path']);
	}

	public function testDownloadOwncloudFailure(){
		$md5 = '911';
		$path = '/dev/null/o';
		$registry = new Registry();
		$registry->set('feed', new Feed($this->feedData));
		$badNewsException = new \Exception('Bad news');

		$fetcherMock = $this->getMockBuilder('Owncloud\Updater\Utils\Fetcher')
				->disableOriginalConstructor()
				->getMock()
		;
		$fetcherMock->method('getBaseDownloadPath')
				->willReturn($path)
		;
		$fetcherMock->method('getOwncloud')
				->will($this->throwException($badNewsException))
		;
		$fetcherMock->method('getMd5')
				->willReturn($md5 . '0')
		;

		$fsHelperMock = $this->getMockBuilder('Owncloud\Updater\Utils\FilesystemHelper')
				->disableOriginalConstructor()
				->getMock()
		;
		$fsHelperMock->method('md5File')
				->willReturn($md5)
		;
		$fsHelperMock->method('fileExists')
				->willReturn(true)
		;

		$downloadController = new DownloadController(
				$fetcherMock,
				$registry,
				$fsHelperMock
		);
		$result = $downloadController->downloadOwncloud();
		$this->assertArraySubset(['success' => false, 'data' => []], $result);
		$this->assertEquals($badNewsException, $result['exception']);;
	}

}
