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


namespace OCA\Updater;

use \OCP\IL10N;

class Channel {
	const CHANNEL_DAILY = 'daily';
	const CHANNEL_BETA = 'beta';
	const CHANNEL_STABLE = 'stable';
	const CHANNEL_PRODUCTION ='production';
	const CHANNEL_NONE ='none';
	
	/** @var IL10N */
	private $l10n;
	
	public function __construct(IL10N $l10n){
		$this->l10n = $l10n;
	}
	
	/**
	 * All available values
	 * @return array
	 */
	public function getChannels(){
		return [
			self::CHANNEL_PRODUCTION => $this->l10n->t('Production'),
			self::CHANNEL_STABLE => $this->l10n->t('Stable'),
			self::CHANNEL_BETA => $this->l10n->t('Beta'),
			self::CHANNEL_DAILY => $this->l10n->t('Daily'),
		];
	}
	
	/**
	 * Get current value
	 * @return string
	 */
	public function getCurrentChannel(){
		return \OCP\Util::getChannel();
	}

	/**
	 * Set a new value
	 * @return string
	 */
	public function setCurrentChannel($newChannel){
		$cleanValue = preg_replace('/[^A-Za-z0-9]/', '', $newChannel);
		\OCP\Util::setChannel($cleanValue);
		return $cleanValue;
	}
	
	public function getLastCheckedAt(){
		return \OC::$server->getDateTimeFormatter()->formatDateTime(
			\OC::$server->getConfig()->getAppValue('core', 'lastupdatedat')
		);
	}
	
	public function flushCache(){
		\OC::$server->getConfig()->setAppValue('core', 'lastupdatedat', 0);
	}
	
	public function getFeed($helper = null, $config = null){
		$helper = is_null($helper) ? \OC::$server->getHTTPHelper() : $helper;
		$config = is_null($config) ? \OC::$server->getConfig() : $config;
		$updater = new \OC\Updater($helper, $config);
		
		$data = $updater->check('https://updates.owncloud.com/server/');
		if (!is_array($data)){
			$data = [];
		}
		return $data;
	}
}
