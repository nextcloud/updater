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

namespace Owncloud\Updater\Console;

use Owncloud\Updater\Utils\Locator;
use Pimple\Container;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use \Symfony\Component\Process\Exception\ProcessFailedException;

class Application extends \Symfony\Component\Console\Application {

	/** @var Container */
	public static $container;

	/** @var Container */
	protected $diContainer;

	/** @var array */
	protected $allowFailure = [
		'upgrade:executeCoreUpgradeScripts',
		'upgrade:checkpoint',
		'upgrade:maintenanceMode',
		'help',
		'list'
	];

	/**
	 * Pass Pimple container into application
	 * @param Container $container
	 */
	public function setContainer(Container $container){
		$this->diContainer = $container;
		self::$container = $container;
	}

	/**
	 * Get Pimple container
	 * @return Container
	 */
	public function getContainer(){
		return $this->diContainer;
	}

	/**
	 * Get logger instance
	 * @return \Psr\Log\LoggerInterface
	 */
	public function getLogger(){
		return $this->diContainer['logger'];
	}

	/**
	 * Log exception with trace
	 * @param \Exception $e
	 */
	public function logException($e){
		$buffer = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
		$this->renderException($e, $buffer);
		$this->getLogger()->error($buffer->fetch());
	}

	public function doRun(InputInterface $input, OutputInterface $output){
		if (!($this->diContainer['logger.output'] instanceof StreamOutput)){
			$output->writeln('[Warning] Failed to init logger. Logging is disabled.');
			$output->writeln(CURRENT_DIR . ' is not writable');
		}
		try{
			$configReader = $this->diContainer['utils.configReader'];
			$commandName = $this->getCommandName($input);

			try{
				$configReader->init();
			} catch (ProcessFailedException $e){
				if (!in_array($commandName, $this->allowFailure)){
					$this->logException($e);
					$output->writeln("<error>Initialization failed with message:</error>");
					$output->writeln($e->getProcess()->getOutput());
					$output->writeln('<info>Use upgrade:checkpoint --list to view a list of checkpoints</info>');
					$output->writeln('<info>upgrade:checkpoint --restore [checkpointid] to revert to the last checkpoint</info>');
					$output->writeln('Please attach your update.log to the issues you reporting.');
					return 1;
				}
			}
			// TODO: check if the current command needs a valid OC instance
			$this->assertOwnCloudFound();
			
			return parent::doRun($input, $output);
		} catch (\Exception $e){
			$this->logException($e);
			throw $e;
		}
	}

	protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output){
		if ($command instanceof \Owncloud\Updater\Command\Command){
			$command->setContainer($this->getContainer());
			$commandName = $this->getCommandName($input);
			$this->getLogger()->info('Execution of ' . $commandName . ' command started');
			if (!empty($command->getMessage())){
				$message = sprintf('<info>%s</info>', $command->getMessage());
				$output->writeln($message);
			}
			$exitCode = parent::doRunCommand($command, $input, $output);
			$this->getLogger()->info(
					'Execution of ' . $commandName . ' command stopped. Exit code is ' . $exitCode
			);
		} else {
			$exitCode = parent::doRunCommand($command, $input, $output);
		}
		return $exitCode;
	}

	/**
	 * Check for owncloud instance
	 * @throws \RuntimeException
	 */
	protected function assertOwnCloudFound(){
		$container = $this->getContainer();
		/** @var Locator $locator */
		$locator = $container['utils.locator'];
		$fsHelper = $container['utils.filesystemhelper'];

		// assert minimum version
		$installedVersion = implode('.', $locator->getInstalledVersion());
		if (version_compare($installedVersion, '9.0.0', '<')){
			throw new \RuntimeException("Minimum ownCloud version 9.0.0 is required for the updater - $installedVersion was found in " . $locator->getOwncloudRootPath());
		}

		// has to be installed
		$file = $locator->getPathToConfigFile();
		if (!file_exists($file) || !is_file($file)){
			throw new \RuntimeException('ownCloud in ' . dirname(dirname($file)) . ' is not installed.');
		}

		// version.php should exist
		$file = $locator->getPathToVersionFile();
		if (!file_exists($file) || !is_file($file)){
			throw new \RuntimeException('ownCloud is not found in ' . dirname($file));
		}

		// datadir should exist
		$dataDir = $locator->getDataDir();
		if (!$fsHelper->fileExists($dataDir)){
			throw new \RuntimeException('Datadirectory ' . $dataDir . ' does not exist.');
		}

		// datadir should be writable
		if (!$fsHelper->isWritable($dataDir)){
			throw new \RuntimeException('Datadirectory ' . $dataDir . ' is not writable.');
		}

		if (!$fsHelper->fileExists($locator->getUpdaterBaseDir())){
			$fsHelper->mkdir($locator->getUpdaterBaseDir());
		}

		if (!$fsHelper->fileExists($locator->getDownloadBaseDir())){
			$fsHelper->mkdir($locator->getDownloadBaseDir());
		}
		if (!$fsHelper->fileExists($locator->getCheckpointDir())){
			$fsHelper->mkdir($locator->getCheckpointDir());
		}
	}

}
