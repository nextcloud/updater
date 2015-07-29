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
