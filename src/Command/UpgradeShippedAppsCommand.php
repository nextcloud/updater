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

namespace Owncloud\Updater\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

class UpgradeShippedAppsCommand extends Command {

	/**
	 * @var OccRunner $occRunner
	 */
	protected $occRunner;

	public function __construct($occRunner){
		parent::__construct();
		$this->occRunner = $occRunner;
	}
	
	protected function configure(){
		$this
				->setName('upgrade:upgradeShippedApps')
				->setDescription('upgrade shipped apps [danger, might take long]')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		$registry = $this->container['utils.registry'];
		$feed = $registry->get('feed');

		if ($feed){
			$locator = $this->container['utils.locator'];
			$fsHelper = $this->container['utils.filesystemhelper'];
			
			$fullExtractionPath = $locator->getExtractionBaseDir() . '/' . $feed->getVersion();
			$newAppsDir = $fullExtractionPath . '/owncloud/apps';
			$newAppsList = $fsHelper->scandirFiltered($newAppsDir);
			foreach ($newAppsList as $appId){
				$output->writeln('Copying the application ' . $appId);
				$fsHelper->copyr($newAppsDir . '/' . $appId, $locator->getOwnCloudRootPath() . '/apps/' . $appId, false);
			}

			try {
				$plain = $this->occRunner->run('upgrade');
				$output->writeln($plain);
			} catch (ProcessFailedException $e){
				if ($e->getProcess()->getExitCode() !== 3){
					throw ($e);
				}
			}
		}
	}
}
