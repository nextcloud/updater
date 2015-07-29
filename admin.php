<?php

/**
 * ownCloud - Updater plugin
 *
 * @author Victor Dubiniuk
 * @copyright 2012-2013 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

namespace OCA\Updater;

use \OCA\Updater\AppInfo\Application;

$app = new Application();
$container = $app->getContainer();
$response = $container->query('\OCA\Updater\Controller\AdminController')->index();
return $response->render();
