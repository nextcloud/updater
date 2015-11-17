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

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessUtils;
use Owncloud\Updater\Utils\OccRunner;

class AppManager {

	/**
	 * @var OccRunner $occRunner
	 */
	protected $occRunner;

	/**
	 * @var array $disabledApps
	 */
	protected $disabledApps = [];

	/**
	 *
	 * @param OccRunner $occRunner
	 */
	public function __construct(OccRunner $occRunner){
		$this->occRunner = $occRunner;
	}

	public function disableApp($appId){
		try{
			$this->occRunner->run('app:disable ' . ProcessUtils::escapeArgument($appId));
		} catch (\Exception $e){
			return false;
		}
		return true;
	}

	public function enableApp($appId){
		try{
			$this->occRunner->run('app:enable ' . ProcessUtils::escapeArgument($appId));
			array_unshift($this->disabledApps, $appId);
		} catch (\Exception $e){
			return false;
		}
		return true;
	}

	public function disableNotShippedApps(OutputInterface $output = null){
		$notShippedApps = $this->occRunner->runJson('app:list --shipped false');
		$appsToDisable = array_keys($notShippedApps['enabled']);
		foreach ($appsToDisable as $appId){
			$result = $this->disableApp($appId);
			$status = $result ? '<info>success</info>' : '<error>failed</error>';
			if (!is_null($output)){
				$message = sprintf('Disable app %s: [%s]', $appId, $status);
				$output->writeln($message);
			}
		}
	}

	public function reenableNotShippedApps(OutputInterface $output = null){
		foreach ($this->disabledApps as $appId){
			$result = $this->enableApp($appId);
			$status = $result ? '<info>success</info>' : '<error>failed</error>';
			if (!is_null($output)){
				$message = sprintf('Enable app %s: [%s]', $appId, $status);
				$output->writeln($message);
			}
		}
	}

	public function getAllApps(){
		$shippedApps = $this->occRunner->runJson('app:list');
		$allApps = array_merge(array_keys($shippedApps['enabled']), array_keys($shippedApps['disabled']));
		return $allApps;
	}

	public function getShippedApps(){
		$shippedApps = $this->occRunner->runJson('app:list --shipped true');
		$allApps = array_merge(array_keys($shippedApps['enabled']), array_keys($shippedApps['disabled']));
		return $allApps;
	}

	public function getAppPath($appId){
		try {
			$response = $this->occRunner->run('app:getpath ' . ProcessUtils::escapeArgument($appId));
		} catch (\Exception $e) {
			return '';
		}
		return trim($response);
	}

}
