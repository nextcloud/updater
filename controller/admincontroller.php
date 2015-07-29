<?php

/**
 * ownCloud - Updater plugin
 *
 * @author Victor Dubiniuk
 * @copyright 2015 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Updater\Controller;

use \OCP\AppFramework\Controller;
use \OCP\IRequest;
use \OCP\IL10N;

use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Http\JSONResponse;

use \OCA\Updater\Channel;
use \OCA\Updater\Config;

class AdminController extends Controller{
	/** @var Config */
	private $config;
	
	/** @var IL10N */
	private $l10n;

	public function __construct($appName, IRequest $request, Config $config, IL10N $l10n){
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->l10n = $l10n;
	}
	
	public function index(){
		$feed = Channel::getFeed();
		$isNewVersionAvailable = !empty($feed['version']);

		$data = [
			'checkedAt' => \OC::$server->getDateTimeFormatter()->formatDate(
				Channel::getLastCheckedAt()
			),
			'backupDir' => $this->config->getBackupBase(),
			'isNewVersionAvailable' => $isNewVersionAvailable ? 'true' : 'false',
			'channels' => Channel::getChannels(),
			'currentChannel' => Channel::getCurrentChannel(),
			'version' => isset($feed['versionstring']) ? $feed['versionstring'] : ''
		];
		
		return new TemplateResponse('updater', 'admin', $data, 'blank');
		
	}
	
	
	/**
	* @UseSession
	*/
	public function setChannel($newChannel){
		if ($newChannel){
			Channel::flushCache();
			$channel = Channel::setCurrentChannel($newChannel);
			if ($channel){
				$data = Channel::getFeed();
				$data['checkedAt'] = \OC::$server->getDateTimeFormatter()->formatDate(
					Channel::getLastCheckedAt()
				);
				$data['channel'] = $channel;
				$data['data']['data']['message'] = '';
		
				$result = array_merge($data, ['status' => 'success']);
			} else {
				$result = [
					'status' => 'error',
					'message' => $this->l10n->t('Unable to switch channel.')
				];
			}
			
			return $result;
		}
	}
}
