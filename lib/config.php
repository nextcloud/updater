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

use \OCP\IConfig;

class Config{
	/** @var IConfig */
	protected $config;

	/**
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config){
		$this->config = $config;
	}
	
	/**
	 * Get updater app working directory
	 * @return string
	 */
	public function getBackupBase() {
		return $this->config->getSystemValue("datadirectory", \OC::$SERVERROOT . "/data") . '/updater_backup/';
	}
	
	/**
	 * Get directory to extract sources before replacement
	 * @return string
	 */
	public function getTempBase(){
		return \OC::$SERVERROOT . "/_oc-upgrade/";
	}
}
