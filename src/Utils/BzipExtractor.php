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

class BzipExtractor {

	protected $file;
	protected $path;

	public function __construct($file, $path){
		$this->file = $file;
		$this->path = $path;
	}

	public function extract(){
		if ($this->extractShell()){
			return true;
		}
		return $this->extractArchive();
	}

	private function extractShell(){
		$command = 'tar -jxvf  ' . ProcessUtils::escapeArgument($this->file) . ' -C ' . ProcessUtils::escapeArgument($this->path) . ' && chmod -R u+w ' . ProcessUtils::escapeArgument($this->path);
		$process = new Process($command);
		$process->setTimeout(null);
		$process->run();
		echo $process->getErrorOutput();
		return $process->isSuccessful();
	}

	private function extractArchive(){
		throw new \RuntimeException("Could not decompress the archive, GNU tar is missing or shell_exec is disabled.");
	}

}
