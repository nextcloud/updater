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
	
	/** @var Channel */
	private $channel;

	public function __construct($appName, IRequest $request, Channel $channel, Config $config, IL10N $l10n){
		parent::__construct($appName, $request);
		$this->channel = $channel;
		$this->config = $config;
		$this->l10n = $l10n;
	}
	
	public function index(){
		$feed = $this->channel->getFeed();
		$isNewVersionAvailable = !empty($feed['version']);

		$data = [
			'checkedAt' => $this->channel->getLastCheckedAt(),
			'backupDir' => $this->config->getBackupBase(),
			'isNewVersionAvailable' => $isNewVersionAvailable ? 'true' : 'false',
			'channels' => $this->channel->getChannels(),
			'currentChannel' => $this->channel->getCurrentChannel(),
			'version' => isset($feed['versionstring']) ? $feed['versionstring'] : ''
		];
		
		return new TemplateResponse('updater', 'admin', $data, 'blank');
		
	}
	
	
	/**
	* @UseSession
	*/
	public function setChannel($newChannel){
		if ($newChannel){
			$this->channel->flushCache();
			$channel = $this->channel->setCurrentChannel($newChannel);
			if ($channel){
				$data = $this->channel->getFeed();
				$data['checkedAt'] = $this->channel->getLastCheckedAt();
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
