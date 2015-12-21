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
		}
		try {
			// TODO: check if the current command needs a valid OC instance
			$this->assertOwncloudFound();
			$this->initDirectoryStructure();

			$configReader = $this->diContainer['utils.configReader'];
			$commandName = $this->getCommandName($input);
			if (!in_array(
					$commandName,
					['upgrade:executeCoreUpgradeScripts', 'upgrade:checkpoint', 'upgrade:maintenanceMode', 'help', 'list']
				)
			){
				try {
					$configReader->init();
				} catch (ProcessFailedException $e){
					$this->logException($e);
					$output->writeln("<error>Initialization failed with message:</error>");
					$output->writeln($e->getProcess()->getOutput());
					$output->writeln('<info>Use upgrade:checkpoint --list to view a list of checkpoints</info>');
					$output->writeln('<info>upgrade:checkpoint --restore [checkpointid] to revert to the last checkpoint</info>');
					$output->writeln('Please attach your update.log to the issues you reporting.');
					return 1;
				}
				
			}
			return parent::doRun($input, $output);
		} catch (\Exception $e) {
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
	protected function assertOwncloudFound(){
		$container = $this->getContainer();
		$locator = $container['utils.locator'];
		$pathToVersionFile = $locator->getPathToVersionFile();
		if (!file_exists($pathToVersionFile) || !is_file($pathToVersionFile)){
			throw new \RuntimeException('ownCloud is not found in ' . dirname($pathToVersionFile));
		}

		$pathToOccFile = $locator->getPathToOccFile();
		if (!file_exists($pathToOccFile) || !is_file($pathToOccFile)){
			throw new \RuntimeException('ownCloud is not found in ' . dirname($pathToOccFile));
		}
	}

	/**
	 * Create proper directory structure to store data
	 */
	protected function initDirectoryStructure(){
		$container = $this->getContainer();
		$locator = $container['utils.locator'];
		$fsHelper = $container['utils.filesystemhelper'];
		if (!file_exists($locator->getDataDir())){
			$fsHelper->mkdir($locator->getDataDir());
		}
		if (!file_exists($locator->getDownloadBaseDir())){
			$fsHelper->mkdir($locator->getDownloadBaseDir());
		}
		if (!file_exists($locator->getCheckpointDir())){
			$fsHelper->mkdir($locator->getCheckpointDir());
		}
	}

}
