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
use Symfony\Component\Process\Exception\ProcessFailedException;
use Owncloud\Updater\Utils\Locator;

class OccRunner {
	/**
	 * @var Locator $locator
	 */
	protected $locator;

	/**
	 *
	 * @param Locator $locator
	 */
	public function __construct(Locator $locator){
		$this->locator = $locator;
	}

	public function run($args){
		$occPath = $this->locator->getPathToOccFile();
		$cmd = "php $occPath $args";
		$process = new Process($cmd);
		$process->setTimeout(null);
		$process->run();

		if (!$process->isSuccessful()){
			throw new ProcessFailedException($process);
		}
		return $process->getOutput();
	}

	public function runJson($args){
		$plain = $this->run('--no-warnings ' . $args . '  --output "json"');
		$decoded = json_decode($plain, true);
		if (!is_array($decoded)){
			throw new \UnexpectedValueException('Could not parse a response for ' . $args . '. Please check if the current shell user can run occ command. Raw output: ' . PHP_EOL . $plain);
		}
		return $decoded;
	}

}
