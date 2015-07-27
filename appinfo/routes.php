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

$application = new \OCA\Updater\AppInfo\Application();

/** @var $this \OC\Route\Router */
$application->registerRoutes($this, [
	'routes' => [
		['name' => 'update#download', 'url' => 'update/download', 'verb' => 'POST'],
		['name' => 'update#backup', 'url' => 'update/backup', 'verb' => 'GET'],
		['name' => 'update#update', 'url' => 'update/update', 'verb' => 'POST'],
		['name' => 'backup#index', 'url' => 'backup/index', 'verb' => 'GET'],
		['name' => 'backup#download', 'url' => 'backup/download', 'verb' => 'GET'],
		['name' => 'backup#delete', 'url' => 'backup/delete', 'verb' => 'GET'],
		['name' => 'admin#setChannel', 'url' => 'admin/setChannel/{newChannel}', 'verb' => 'POST'],
	]
]);
