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

		$feed = $registry->get('feed');

		if ($feed){
			$path = $fetcher->getBaseDownloadPath($feed);
			$fullExtractionPath = $locator->getExtractionBaseDir() . '/' . $feed->getVersion();

			if (!file_exists($fullExtractionPath)){
				try{
					$fsHelper->mkdir($fullExtractionPath, true);
				} catch (\Exception $ex){
					$output->writeln('Unable create directory ' . $fullExtractionPath);
				}
			} else {
				$fsHelper->removeIfExists($fullExtractionPath);
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
			
			$tmpDir = $locator->getExtractionBaseDir() . '/' . implode('.', $locator->getInstalledVersion());
			$fsHelper->removeIfExists($tmpDir);
			$fsHelper->mkdir($tmpDir);
			$fsHelper->mkdir($tmpDir . '/config');
			$oldSourcesDir = $locator->getOwncloudRootPath();
			$newSourcesDir = $fullExtractionPath . '/owncloud';
			$newSources = $locator->getRootDirContent();
			foreach ($newSources as $dir){
				$this->getApplication()->getLogger()->debug('Moving ' . $dir);
				if (file_exists($oldSourcesDir . '/' . $dir)){
					$fsHelper->move($oldSourcesDir . '/' . $dir, $tmpDir . '/' . $dir);
				}
				if (file_exists($newSourcesDir . '/' . $dir)){
					$fsHelper->move($newSourcesDir . '/' . $dir, $oldSourcesDir . '/' . $dir);
				}
			}

			$plain = $this->occRunner->run('upgrade');
			$output->writeln($plain);

		}

	}

}
