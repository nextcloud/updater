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

class AdminController extends Controller{
	private $l10n;

	public function __construct($appName, IRequest $request, IL10N $l10n){
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
	}
	
	public function index(){
		if (!@file_exists(App::getBackupBase())){
			Helper::mkdir(App::getBackupBase());
		}

		$feed = App::getFeed();
		$isNewVersionAvailable = !empty($feed['version']);

		$lastCheck =  \OC::$server->getConfig()->getAppValue('core', 'lastupdatedat');

		$data = [
			'checkedAt' => \OC::$server->getDateTimeFormatter()->formatDate($lastCheck),
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
				$lastCheck = \OC::$server->getConfig()->getAppValue('core', 'lastupdatedat');
				$data['checkedAt'] = \OC::$server->getDateTimeFormatter()->formatDate($lastCheck);
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
