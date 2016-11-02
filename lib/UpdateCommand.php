<?php
/**
 * @copyright Copyright (c) 2016 Morris Jobke <hey@morrisjobke.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace NC\Updater;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command {

	/** @var Updater */
	protected $updater;

	protected function configure() {
		$this
			->setName('update-code')
			->setDescription('Updates the code of an Nextcloud instance')
			->setHelp("This command fetches the latest code that is announced via the updater server and safely replaces the existing code with the new one.");
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		if (class_exists('NC\Updater\Version')) {
			$versionClass = new Version();
			$version = $versionClass->get();
		} else {
			$version = 'directly run from git checkout';
		}
		$output->writeln('Nextcloud Updater - version: ' . $version);
		$output->writeln('');

		// Check if the config.php is at the expected place
		try {
			$this->updater = new Updater();
		} catch (\Exception $e) {
			// logging here is not possible because we don't know the data directory
			$output->writeln($e->getMessage());
			return -1;
		}

		// Check if the updater.log can be written to
		try {
			$this->updater->log('[info] updater cli is executed');
		} catch (\Exception $e) {
			// show logging error to user
			$output->writeln($e->getMessage());
			return -1;
		}

		// Check if already a step is in process
		$currentStep = $this->updater->currentStep();
		$stepNumber = 0;
		if($currentStep !== []) {
			$stepState = $currentStep['state'];
			$stepNumber = $currentStep['step'];
			$this->updater->log('[info] Step ' . $stepNumber . ' is in state "' . $stepState . '".');

			if($stepState === 'start') {
				$output->writeln(
					sprintf(
						'Step %s is currently in process. Please call this command later.',
						$stepNumber
					)
				);
				return -1;
			}
		}
    }

	/**
	 * @param $step integer
	 * @return array with options 'proceed' which is a boolean and defines if the step succeeded and an optional 'response' string
	 */
    protected function executeStep($step) {
		$this->updater->log('[info] executeStep request for step "' . $step . '"');
		try {
			if($step > 11 || $step < 1) {
				throw new \Exception('Invalid step');
			}

			$this->updater->startStep($step);
			switch ($step) {
				case 1:
					$this->updater->checkForExpectedFilesAndFolders();
					break;
				case 2:
					$this->updater->checkWritePermissions();
					break;
				case 3:
					$this->updater->setMaintenanceMode(true);
					break;
				case 4:
					$this->updater->createBackup();
					break;
				case 5:
					$this->updater->downloadUpdate();
					break;
				case 6:
					$this->updater->extractDownload();
					break;
				case 7:
					$this->updater->replaceEntryPoints();
					break;
				case 8:
					$this->updater->deleteOldFiles();
					break;
				case 9:
					$this->updater->moveNewVersionInPlace();
					break;
				case 10:
					// this is not needed in the CLI updater
					//$this->updater->setMaintenanceMode(false);
					break;
				case 11:
					$this->updater->finalize();
					break;
			}
			$this->updater->endStep($step);
			return ['proceed' => true];
		} catch (UpdateException $e) {
			$message = $e->getData();

			try {
				$this->updater->log('[error] executeStep request failed with UpdateException');
				$this->updater->logException($e);
			} catch (LogException $logE) {
				$message .= ' (and writing to log failed also with: ' . $logE->getMessage() . ')';
			}

			$this->updater->rollbackChanges($step);
			return ['proceed' => false, 'response' => $message];
		} catch (\Exception $e) {
			$message = $e->getMessage();

			try {
				$this->updater->log('[error] executeStep request failed with other exception');
				$this->updater->logException($e);
			} catch (LogException $logE) {
				$message .= ' (and writing to log failed also with: ' . $logE->getMessage() . ')';
			}

			$this->updater->rollbackChanges($step);
			return ['proceed' => false, 'response' => $message];
		}
	}
}