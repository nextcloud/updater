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

class Channel {
	const CHANNEL_DAILY = 'daily';
	const CHANNEL_BETA = 'beta';
	const CHANNEL_STABLE = 'stable';
	const CHANNEL_PRODUCTION ='production';
	const CHANNEL_NONE ='none';
	
	/**
	 * All available values
	 * @return array
	 */
	public static function getChannels(){
		$l10n = App::$l10n;
		return [
			self::CHANNEL_PRODUCTION => $l10n->t('Production'),
			self::CHANNEL_STABLE => $l10n->t('Stable'),
			self::CHANNEL_DAILY => $l10n->t('Daily'),
			self::CHANNEL_BETA => $l10n->t('Beta'),
		];
	}
	
	/**
	 * Get current value
	 * @return string
	 */
	public static function getCurrentChannel(){
		return \OC_Util::getChannel();
	}

	public static function setCurrentChannel($newChannel){
		$cleanValue = preg_replace('/[^A-Za-z0-9]/', '', $newChannel);
		$filename = \OC::$SERVERROOT . '/version.php';
		$content = preg_replace(
			'/(\$OC_Channel\w*=\w*[\'"])([^\'"])*/', 
			'\1'.$cleanValue, 
			file_get_contents($filename)
		);

		if (file_put_contents($filename, $content)){
			return $cleanValue;
		}
		return false;
	}
}
