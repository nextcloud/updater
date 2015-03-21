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

\OCP\JSON::checkAdminUser();
\OCP\JSON::callCheck();

$newChannel = isset($_POST['newChannel']) ? $_POST['newChannel'] : false;

if ($newChannel) {
	App::flushCache();
	$channel = Channel::setCurrentChannel($newChannel);
	$data = App::getFeed();
	
	$data['channel'] = $channel;
	
	\OCP\JSON::success(
		$data
	);
}
