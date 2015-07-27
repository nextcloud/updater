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

namespace OCA\Updater\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http\DataDownloadResponse;
use \OCP\IRequest;
use \OCP\IL10N;

use \OCA\Updater\App;
use \OCA\Updater\Helper;

class BackupController extends Controller{
	private $l10n;

	public function __construct($appName, IRequest $request, IL10N $l10n){
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
	}
	
	public function index(){
		try {
			$list = Helper::scandir(App::getBackupBase());
		} catch (\Exception $e) {
			$list = [];
		}
		clearstatcache();
		$result = [];
		foreach ($list as $item){
			if (in_array($item, ['.', '..'])) {
				continue;
			}
			$result[] = [
				'title' => $item,
				'date' => date ("F d Y H:i:s", filectime(App::getBackupBase() . '/' . $item)),
				'size' => \OCP\Util::humanFileSize(filesize(App::getBackupBase() . '/' . $item))
			];
		}

		return [
			'status' => 'success',
			'data' => $result
		];
	}
	
	public function download($filename){
		$file = basename($filename);
		$filename = App::getBackupBase() . $file;
		// Prevent directory traversal
		if (strlen($file)<3 || !@file_exists($filename)) {
			exit();
		}

		$mime = \OCP\Files::getMimeType($filename);
		
		return new DataDownloadResponse(file_get_contents($filename), $file, $mime);

	}
	
	public function delete($filename){
		// Prevent directory traversal
		$file = basename($filename);
		if (strlen($file)<3) {
			exit();
		}

		$filename = App::getBackupBase() . $file;
		Helper::removeIfExists($filename);
	}
}

 