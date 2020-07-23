<?php
/**
 * @copyright Copyright (c) 2016 Morris Jobke <hey@morrisjobke.de>
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
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
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UpdateCommand extends Command {

	/** @var Updater */
	protected $updater;

	/** @var bool */
	protected $shouldStop = false;

	/** @var bool */
	protected $skipBackup = false;

	/** @var array strings of text for stages of updater */
	protected $checkTexts = [
		0 => '',
		1 => 'Check for expected files',
		2 => 'Check for write permissions',
		3 => 'Create backup',
		4 => 'Downloading',
		5 => 'Verify integrity',
		6 => 'Extracting',
		7 => 'Enable maintenance mode',
		8 => 'Replace entry points',
		9 => 'Delete old files',
		10 => 'Move new files in place',
		11 => 'Keep maintenance mode active?',
		12 => 'Done',
	];

	protected function configure() {
		$this
			->setName('update')
			->setDescription('Updates the code of an Nextcloud instance')
			->setHelp("This command fetches the latest code that is announced via the updater server and safely replaces the existing code with the new one.")
			->addOption('no-backup', null, InputOption::VALUE_NONE, 'Skip backup of current Nextcloud version');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->skipBackup = $input->getOption('no-backup');

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
			$path = dirname(__DIR__); // dirname() because we are inside the lib/ subfolder
			$pharPath = \Phar::running(false);
			if ($pharPath !== '') {
				$path = dirname($pharPath);
			}
			$this->updater = new Updater($path);
		} catch (\Exception $e) {
			// logging here is not possible because we don't know the data directory
			$output->writeln($e->getMessage());
			return -1;
		}

		if (!function_exists('posix_getuid')) {
			$output->writeln("The posix extensions are required - see http://php.net/manual/en/book.posix.php");
			return -1;
		}

		if($dir = getenv('NEXTCLOUD_CONFIG_DIR')) {
			$configFileName = rtrim($dir, '/') . '/config.php';
		} else {
			$configFileName = $path . '/../config/config.php';
		}
		$user = posix_getpwuid(posix_getuid());
		$configUser = posix_getpwuid(fileowner($configFileName));
		if ($user['name'] !== $configUser['name']) {
			$output->writeln("Console has to be executed with the user that owns the file config/config.php");
			$output->writeln("Current user: " . $user['name']);
			$output->writeln("Owner of config.php: " . $configUser['name']);
			$output->writeln("Try adding 'sudo -u " . $configUser['name'] . " ' to the beginning of the command (without the single quotes)");
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

		$this->updater->logVersion();

		$output->writeln('Current version is ' . $this->updater->getCurrentVersion() . '.');

		// needs to be called that early because otherwise updateAvailable() returns false
		$updateString = $this->updater->checkForUpdate();

		$output->writeln('');

		$lines = explode('<br />', $updateString);

		foreach ($lines as $line) {
			// strip HTML
			$output->writeln('<info>' . preg_replace('/<[^>]*>/', '', $line) . '</info>');
		}

		$output->writeln('');

		if(!$this->updater->updateAvailable() && $stepNumber === 0) {
			$output->writeln('Nothing to do.');
			return 0;
		}

		$questionText = 'Start update';
		if ($stepNumber > 0) {
			$questionText = 'Continue update';
		}

		if ($input->isInteractive()) {

			$this->showCurrentStatus($output, $stepNumber);

			$output->writeln('');

			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion($questionText . '? [y/N] ', false);

			if (!$helper->ask($input, $output, $question)) {
				$output->writeln('Updater stopped.');
				$this->updater->log('[info] updater stopped');
				return 0;
			}
		} else {
			$this->updater->log('[info] updater run in non-interactive mode');
			$output->writeln('Updater run in non-interactive mode.');
			$output->writeln('');
			$output->writeln($questionText);
		}
		$this->updater->log('[info] updater started');

		$output->writeln('');

		if(function_exists('pcntl_signal')) {
			// being able to handle stop/terminate command (Ctrl - C)
			pcntl_signal(SIGTERM, [$this, 'stopCommand']);
			pcntl_signal(SIGINT, [$this, 'stopCommand']);

			$output->writeln('Info: Pressing Ctrl-C will finish the currently running step and then stops the updater.');
			$output->writeln('');
		} else {
			$output->writeln('Info: Gracefully stopping the updater via Ctrl-C is not possible - PCNTL extension is not loaded.');
			$output->writeln('');
		}

		// print already executed steps
		for($i = 1; $i <= $stepNumber; $i++) {
			if ($i === 11) {
				// no need to ask for maintenance mode on CLI - skip it
				continue;
			}
			$output->writeln('<info>[✔] ' . $this->checkTexts[$i] . '</info>');
		}

		$i = $stepNumber;
		while ($i < 12) {
			$i++;

			if ($i === 11) {
				// no need to ask for maintenance mode on CLI - skip it
				continue;
			}

			if (function_exists('pcntl_signal_dispatch')) {
				pcntl_signal_dispatch();
				if ( $this->shouldStop ) {
					break;
				}
			}

			$output->write('[ ] ' . $this->checkTexts[$i] . ' ...');

			$result = $this->executeStep($i);

			// Move the cursor to the beginning of the line
			$output->write("\x0D");

			// Erase the line
			$output->write("\x1B[2K");

			if ($result['proceed'] === true) {
				$output->writeln('<info>[✔] ' . $this->checkTexts[$i] . '</info>');
			} else {
				$output->writeln('<error>[✘] ' . $this->checkTexts[$i] . ' failed</error>');

				if ($i === 1) {
					if(is_string($result['response'])) {
						$output->writeln('<error>' . $result['response'] . '</error>');
					} else {
						$output->writeln('<error>The following extra files have been found:</error>');
						foreach ($result['response'] as $file) {
							$output->writeln('<error>    ' . $file . '</error>');
						}
					}
				} elseif ($i === 2) {
					if(is_string($result['response'])) {
						$output->writeln('<error>' . $result['response'] . '</error>');
					} else {
						$output->writeln('<error>The following places can not be written to:</error>');
						foreach ($result['response'] as $file) {
							$output->writeln('<error>    ' . $file . '</error>');
						}
					}
				} else {
					if (is_string($result['response'])) {
						$output->writeln('<error>' . $result['response'] .  '</error>');
					} else {
						$output->writeln('<error>Something has gone wrong. Please check the log file in the data dir.</error>');
					}
				}
				break;
			}
		}

		$output->writeln('');
		if ($i === 12) {
			$this->updater->log('[info] update of code successful.');
			$output->writeln('Update of code successful.');

			if ($input->isInteractive()) {

				$output->writeln('');

				$helper = $this->getHelper('question');
				$question = new ConfirmationQuestion('Should the "occ upgrade" command be executed? [Y/n] ', true);

				if (!$helper->ask($input, $output, $question)) {
					$output->writeln('Please now execute "./occ upgrade" to finish the upgrade.');
					$this->updater->log('[info] updater finished');
					return 0;
				}
			} else {
				$this->updater->log('[info] updater run in non-interactive mode - occ upgrade is started');
				$output->writeln('Updater run in non-interactive mode - will start "occ upgrade" now.');
				$output->writeln('');
			}

			chdir($path . '/..');
			chmod('occ', 0755); # TODO do this in the updater
			system(PHP_BINARY . ' ./occ upgrade -v', $returnValue);

			$output->writeln('');
			if ($input->isInteractive()) {

				$helper = $this->getHelper('question');
				$question = new ConfirmationQuestion($this->checkTexts[11] . ' [y/N] ', false);

				if ($helper->ask($input, $output, $question)) {
					$output->writeln('Maintenance mode kept active');
					$this->updater->log('[info] updater finished - maintenance mode kept active');
					return $returnValue;
				}
			} else {
				$this->updater->log('[info] updater run in non-interactive mode - disabling maintenance mode');
				$output->writeln('Updater run in non-interactive mode - will disable maintenance mode now.');
			}

			try {
				system(PHP_BINARY . ' ./occ maintenance:mode --off', $returnValueMaintenanceMode);
				$this->updater->log('[info] maintenance mode is disabled - return code: ' . $returnValueMaintenanceMode);
				$output->writeln('');
				$output->writeln('Maintenance mode is disabled');
			} catch (\Exception $e) {
				$this->updater->log('[info] maintenance mode can not be disabled');
				$this->updater->logException($e);
				$output->writeln('');
				$output->writeln('Maintenance mode can not be disabled');
			}

			return $returnValue;
		} else {
			if ($this->shouldStop) {
				$output->writeln('<error>Update stopped. To resume or retry just execute the updater again.</error>');
			} else {
				$output->writeln('<error>Update failed. To resume or retry just execute the updater again.</error>');
			}
			return -1;
		}
    }

	/**
	 * @param $step integer
	 * @return array with options 'proceed' which is a boolean and defines if the step succeeded and an optional 'response' string
	 */
    protected function executeStep($step) {
		$this->updater->log('[info] executeStep request for step "' . $step . '"');
		try {
			if($step > 12 || $step < 1) {
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
					if ($this->skipBackup === false) {
						$this->updater->createBackup();
					}
					break;
				case 4:
					$this->updater->downloadUpdate();
					break;
				case 5:
					$this->updater->verifyIntegrity();
					break;
				case 6:
					$this->updater->extractDownload();
					break;
				case 7:
					$this->updater->setMaintenanceMode(true);
					break;
				case 8:
					$this->updater->replaceEntryPoints();
					break;
				case 9:
					$this->updater->deleteOldFiles();
					break;
				case 10:
					$this->updater->moveNewVersionInPlace();
					break;
				case 11:
					// this is not needed in the CLI updater
					//$this->updater->setMaintenanceMode(false);
					break;
				case 12:
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

	/**
	 * @param OutputInterface $output
	 * @param integer $stepNumber
	 */
	protected function showCurrentStatus(OutputInterface $output, $stepNumber) {
		$output->writeln('Steps that will be executed:');
		for ($i = 1; $i < sizeof($this->checkTexts); $i++) {
			if ($i === 11) {
				// no need to ask for maintenance mode on CLI - skip it
				continue;
			}
			$statusBegin = '[ ] ';
			$statusEnd = '';
			if ($i <= $stepNumber) {
				$statusBegin = '<info>[✔] ';
				$statusEnd = '</info>';
			}
			$output->writeln($statusBegin . $this->checkTexts[$i] . $statusEnd);
		}
	}

	/**
	 * gets called by the PCNTL listener once the stop/terminate signal
	 */
	public function stopCommand() {
		$this->shouldStop = true;
	}


}
