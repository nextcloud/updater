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

if ($newChannel){
	App::flushCache();
	$channel = Channel::setCurrentChannel($newChannel);
	if ($channel){
		$data = App::getFeed();
		$lastCheck = \OC_Appconfig::getValue('core', 'lastupdatedat');
		$data['checkedAt'] = \OCP\Util::formatDate($lastCheck);
		$data['channel'] = $channel;
		$data['data']['message'] = '';
		
		\OCP\JSON::success(
				$data
		);
	} else {
		\OCP\JSON::error(
				['data' =>
					[
						'message' => App::$l10n->t('Unable to switch channel.')
					]
				]
		);
	}
}
