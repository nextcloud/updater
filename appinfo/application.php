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

namespace OCA\Updater\AppInfo;

use \OCP\AppFramework\App;

use \OCA\Updater\Controller\UpdateController;
use \OCA\Updater\Controller\BackupController;
use \OCA\Updater\Controller\AdminController;

use \OCA\Updater\Channel;
use \OCA\Updater\Config;
use \OCA\Updater\Helper;

class Application extends App {
	public function __construct (array $urlParams = []) {
		parent::__construct('updater', $urlParams);
		
		$container = $this->getContainer();
			
		$container->registerService('L10N', function($c) {
			return $c->query('ServerContainer')->getL10N($c->query('AppName'));
		});
		
		$container->registerService('Config', function($c) {
			return  new Config(
				$c->query('ServerContainer')->getConfig()
			);
		});
		
		$container->registerService('Channel', function($c) {
			return  new Channel(
				$c->query('L10N')
			);
		});
		
		//Startup
		if (\OC_Util::getEditionString() === ''){
			\OCP\App::registerAdmin('updater', 'admin');
			$appPath = $container->query('Config')->getBackupBase();
			if (!@file_exists($appPath)){
				Helper::mkdir($appPath);
			}
		}
	}
}
