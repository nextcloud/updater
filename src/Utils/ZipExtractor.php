<?php
/**
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Owncloud\Updater\Utils;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;
use \ZipArchive;

class ZipExtractor {

	protected $file;
	protected $path;

	/**
	 * @param string $file
	 * @param string $path
	 */
	public function __construct($file, $path){
		$this->file = $file;
		$this->path = $path;
	}

	public function extract(){
		if ($this->extractShell()){
			return true;
		}
		if (!class_exists('ZipArchive')){
			throw new \RuntimeException("Could not decompress the archive, enable the PHP zip extension or install unzip.");
		}
		return $this->extractZipArchive();
	}

	private function extractShell(){
		$command = 'unzip ' . ProcessUtils::escapeArgument($this->file) . ' -d ' . ProcessUtils::escapeArgument($this->path) . ' && chmod -R u+w ' . ProcessUtils::escapeArgument($this->path);
		$process = new Process($command);
		$process->setTimeout(null);
		$process->run();
		echo $process->getErrorOutput();
		return $process->isSuccessful();
	}

	private function extractZipArchive(){
		$zipArchive = new ZipArchive();

		if (true !== ($retval = $zipArchive->open($this->file))){
			throw new \UnexpectedValueException($this->getErrorMessage($retval), $retval);
		}

		if (true !== $zipArchive->extractTo($this->path)){
			throw new \RuntimeException("There was an error extracting the ZIP file. Corrupt file?");
		}

		$zipArchive->close();
		return true;
	}

	protected function getErrorMessage($retval){
		switch ($retval){
			case ZipArchive::ER_EXISTS:
				return sprintf("File '%s' already exists.", $this->file);
			case ZipArchive::ER_INCONS:
				return sprintf("Zip archive '%s' is inconsistent.", $this->file);
			case ZipArchive::ER_INVAL:
				return sprintf("Invalid argument (%s)", $this->file);
			case ZipArchive::ER_MEMORY:
				return sprintf("Malloc failure (%s)", $this->file);
			case ZipArchive::ER_NOENT:
				return sprintf("No such zip file: '%s'", $this->file);
			case ZipArchive::ER_NOZIP:
				return sprintf("'%s' is not a zip archive.", $this->file);
			case ZipArchive::ER_OPEN:
				return sprintf("Can't open zip file: %s", $this->file);
			case ZipArchive::ER_READ:
				return sprintf("Zip read error (%s)", $this->file);
			case ZipArchive::ER_SEEK:
				return sprintf("Zip seek error (%s)", $this->file);
			default:
				return sprintf("'%s' is not a valid zip archive, got error code: %s", $this->file, $retval);
		}
	}

}
