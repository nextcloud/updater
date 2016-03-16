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
use Owncloud\Updater\Utils\OccRunner;
use Owncloud\Updater\Utils\ZipExtractor;
use Owncloud\Updater\Utils\BzipExtractor;

class ExecuteCoreUpgradeScriptsCommand extends Command {

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
				->setName('upgrade:executeCoreUpgradeScripts')
				->setDescription('execute core upgrade scripts [danger, might take long]');
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		$locator = $this->container['utils.locator'];
		$fsHelper = $this->container['utils.filesystemhelper'];
		$registry = $this->container['utils.registry'];
		$fetcher = $this->container['utils.fetcher'];

		$installedVersion = implode('.', $locator->getInstalledVersion());
		$registry->set('installedVersion', $installedVersion);
		
		$feed = $registry->get('feed');

		if ($feed){
			$path = $fetcher->getBaseDownloadPath($feed);
			$fullExtractionPath = $locator->getExtractionBaseDir() . '/' . $feed->getVersion();

			if (file_exists($fullExtractionPath)){
				$fsHelper->removeIfExists($fullExtractionPath);
			}
			try{
				$fsHelper->mkdir($fullExtractionPath, true);
			} catch (\Exception $e){
					$output->writeln('Unable create directory ' . $fullExtractionPath);
					throw $e;
			}

			$output->writeln('Extracting source into ' . $fullExtractionPath);
			if (preg_match('|\.tar\.bz2$|', $path)){
				$extractor = new BzipExtractor($path, $fullExtractionPath);
			} else {
				$extractor = new ZipExtractor($path, $fullExtractionPath);
			}
			try{
				$extractor->extract();
			} catch (\Exception $e){
				$output->writeln('Extraction has been failed');
				$fsHelper->removeIfExists($locator->getExtractionBaseDir());
				throw $e;
			}

			$tmpDir = $locator->getExtractionBaseDir() . '/' . $installedVersion;
			$fsHelper->removeIfExists($tmpDir);
			$fsHelper->mkdir($tmpDir);
			$fsHelper->mkdir($tmpDir . '/config');
			$oldSourcesDir = $locator->getOwncloudRootPath();
			$newSourcesDir = $fullExtractionPath . '/owncloud';

			foreach ($locator->getRootDirContent() as $dir){
				$this->getApplication()->getLogger()->debug('Moving ' . $dir);
				$fsHelper->tripleMove($oldSourcesDir, $newSourcesDir, $tmpDir, $dir);
			}
			
			try {
				$fsHelper->move($oldSourcesDir . '/apps', $oldSourcesDir . '/__apps');
				$fsHelper->mkdir($oldSourcesDir . '/apps');
				$plain = $this->occRunner->run('upgrade');
				$fsHelper->removeIfExists($oldSourcesDir . '/apps');
				$fsHelper->move($oldSourcesDir . '/__apps', $oldSourcesDir . '/apps');
				$output->writeln($plain);
			} catch (ProcessFailedException $e){
				$fsHelper->removeIfExists($oldSourcesDir . '/apps');
				$fsHelper->move($oldSourcesDir . '/__apps', $oldSourcesDir . '/apps');
				if ($e->getProcess()->getExitCode() != 3){
					throw ($e);
				}
			}

		}

	}

}
