<?php

/**
 * ownCloud - Updater plugin
 *
 * @author Victor Dubiniuk
 * @copyright 2014 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Updater;

class App {

	const APP_ID = 'updater';
	
	public static function flushCache(){
		\OC::$server->getConfig()->setAppValue('core', 'lastupdatedat', 0);
	}
	
	public static function getFeed(){
		$helper = \OC::$server->getHTTPHelper();
		$config = \OC::$server->getConfig();
		$updater = new \OC\Updater($helper, $config);
		$data = $updater->check('https://updates.owncloud.com/server/');
		if (!is_array($data)){
			$data = [];
		}
		return $data;
	}

	/**
	 * Get app working directory
	 * @return string
	 */
	public static function getBackupBase() {
		return \OC::$server->getConfig()->getSystemValue("datadirectory", \OC::$SERVERROOT . "/data") . '/updater_backup/';
	}
	
	public static function getTempBase(){
		return \OC::$SERVERROOT . "/_oc-upgrade/";
	}
}
