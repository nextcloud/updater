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

class Application extends App {
	public function __construct (array $urlParams = []) {
		parent::__construct('updater', $urlParams);
		//Startup
		if (\OC_Util::getEditionString() === ''){
			\OCP\App::registerAdmin('updater', 'admin');
		}
		
		$container = $this->getContainer();
		
		/**
		 * Controllers
		 */
		$container->registerService('UpdateController', function($c) {
			return new UpdateController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('L10N')
			);
		});
		$container->registerService('BackupController', function($c) {
			return new BackupController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('L10N')
			);
		});
		$container->registerService('AdminController', function($c) {
			return new AdminController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('L10N')
			);
		});
		
		$container->registerService('L10N', function($c) {
			return $c->query('ServerContainer')->getL10N($c->query('AppName'));
		});
	}
}
