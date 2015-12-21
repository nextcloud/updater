<?php

namespace Owncloud\Updater\Tests\Utils;

use Owncloud\Updater\Utils\Fetcher;

class FetcherTest extends \PHPUnit_Framework_TestCase {

	protected $httpClient;
	protected $locator;
	protected $configReader;

	public function setUp(){
		$this->httpClient = $this->getMockBuilder('GuzzleHttp\Client')
				->disableOriginalConstructor()
				->getMock()
		;
		$this->locator = $this->getMockBuilder('Owncloud\Updater\Utils\Locator')
				->disableOriginalConstructor()
				->getMock()
		;
		$this->configReader = $this->getMockBuilder('Owncloud\Updater\Utils\ConfigReader')
				->disableOriginalConstructor()
				->getMock()
		;
		
		$map = [
			['apps.core.installedat', '100500'],
			['apps.core.lastupdatedat', '500100'],
			['apps.core.OC_Channel', 'stable'],
			['system.version', '8.2.0.3'],
		];

		$this->configReader
				->method('getByPath')
				->will($this->returnValueMap($map))
		;
		$this->configReader
				->method('getEdition')
				->willReturn('')
		;
		$this->locator
				->method('getBuild')
				->willReturn('2015-03-09T13:29:12+00:00 8db687a1cddd13c2a6fb6b16038d20275bd31e17')
		;
	}

	public function testGetValidFeed(){
		$responseMock = $this->getResponseMock('<?xml version="1.0"?><owncloud>  <version>8.1.3.0</version><versionstring>ownCloud 8.1.3</versionstring>
  <url>https://download.owncloud.org/community/owncloud-8.1.3.zip</url>
  <web>https://doc.owncloud.org/server/8.1/admin_manual/maintenance/upgrade.html</web>
</owncloud>');
		$this->httpClient
				->method('get')
				->willReturn($responseMock)
		;
		$fetcher = new Fetcher($this->httpClient, $this->locator, $this->configReader);
		$feed = $fetcher->getFeed();
		$this->assertInstanceOf('Owncloud\Updater\Utils\Feed', $feed);
		$this->assertTrue($feed->isValid());
		$this->assertEquals('8.1.3.0', $feed->getVersion());
	}


	public function testGetEmptyFeed(){
		$responseMock = $this->getResponseMock('');
		$this->httpClient
				->method('get')
				->willReturn($responseMock)
		;
		$fetcher = new Fetcher($this->httpClient, $this->locator, $this->configReader);
		$feed = $fetcher->getFeed();
		$this->assertInstanceOf('Owncloud\Updater\Utils\Feed', $feed);
		$this->assertFalse($feed->isValid());
	}

	public function testGetGarbageFeed(){
		$responseMock = $this->getResponseMock('<!DOCTYPE html><html lang="en"> <head><meta charset="utf-8">');
		$this->httpClient
				->method('get')
				->willReturn($responseMock)
		;
		$fetcher = new Fetcher($this->httpClient, $this->locator, $this->configReader);
		$feed = $fetcher->getFeed();
		$this->assertInstanceOf('Owncloud\Updater\Utils\Feed', $feed);
		$this->assertFalse($feed->isValid());
	}

	private function getResponseMock($body){
		$bodyMock = $this->getMockBuilder('Owncloud\Updater\Tests\StreamInterface')
				->disableOriginalConstructor()
				->getMock()
		;
		$bodyMock
				->expects($this->any())
				->method('getContents')
				->willReturn($body)
		;

		$responseMock = $this->getMockBuilder('GuzzleHttp\Message\ResponseInterface')
				->disableOriginalConstructor()
				->getMock()
		;
		$responseMock
				->expects($this->any())
				->method('getStatusCode')
				->willReturn(200)
		;
		$responseMock
				->expects($this->any())
				->method('getBody')
				->willReturn($bodyMock)
		;
		return $responseMock;
	}

}
