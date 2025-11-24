<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace NC\Updater;

use Override;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UpdateCommand extends Command {
	protected ?Updater $updater = null;

	protected bool $shouldStop = false;

	protected bool $skipBackup = false;

	protected bool $skipUpgrade = false;

	protected bool $ignoreState = false;

	protected string $urlOverride = '';

	/** Strings of text for stages of updater */
	protected array $checkTexts = [
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

	#[Override]
	protected function configure(): void {
		$this
			->setName('update')
			->setDescription('Updates the code of a Nextcloud instance')
			->setHelp('This command fetches the latest code that is announced via the updater server and safely replaces the existing code with the new one.')
			->addOption('no-backup', null, InputOption::VALUE_NONE, 'Skip backup of current Nextcloud version')
			->addOption('no-upgrade', null, InputOption::VALUE_NONE, "Don't automatically run occ upgrade")
			->addOption('url', null, InputOption::VALUE_OPTIONAL, 'The URL of the Nextcloud release to download')
			->addOption('ignore-state', null, InputOption::VALUE_NONE, 'Ignore known state from .step file, do a complete update')
		;
	}

	public static function getUpdaterVersion(): string {
		if (class_exists(Version::class)) {
			$versionClass = new Version();
			return $versionClass->get();
		}

		return 'git';
	}

	#[Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->skipBackup = (bool)$input->getOption('no-backup');
		$this->skipUpgrade = (bool)$input->getOption('no-upgrade');
		$this->urlOverride = (string)$input->getOption('url');
		$this->ignoreState = (bool)$input->getOption('ignore-state');

		$version = static::getUpdaterVersion();
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
		} catch (\Exception $exception) {
			// logging here is not possible because we don't know the data directory
			$output->writeln($exception->getMessage());
			return -1;
		}

		if (!function_exists('posix_getuid')) {
			$output->writeln('The posix extensions are required - see http://php.net/manual/en/book.posix.php');
			return -1;
		}

		$dir = (string)getenv('NEXTCLOUD_CONFIG_DIR');
		$configFileName = $dir === ''
			? $configFileName = $path . '/../config/config.php'
			: $configFileName = rtrim($dir, '/') . '/config.php';

		$user = posix_getpwuid(posix_getuid());
		$fileowner = fileowner($configFileName);
		if ($fileowner === false) {
			throw new \Exception('Unable to read configuration file owner');
		}

		$configUser = posix_getpwuid($fileowner);
		if ($user['name'] !== $configUser['name']) {
			$output->writeln('Console has to be executed with the user that owns the file config/config.php');
			$output->writeln('Current user: ' . $user['name']);
			$output->writeln('Owner of config.php: ' . $configUser['name']);
			$output->writeln("Try adding 'sudo -u " . $configUser['name'] . " ' to the beginning of the command (without the single quotes)");
			return -1;
		}

		// Check if the updater.log can be written to
		try {
			$this->updater->log('[info] updater cli is executed');
		} catch (\Exception $exception) {
			// show logging error to user
			$output->writeln($exception->getMessage());
			return -1;
		}

		// Check if already a step is in process
		if ($this->ignoreState) {
			$currentStep = [];
		} else {
			$currentStep = $this->updater->currentStep();
		}
		$stepNumber = 0;
		if ($currentStep !== []) {
			$stepState = $currentStep['state'] ?? '';
			$stepNumber = $currentStep['step'] ?? 0;
			$this->updater->log('[info] Step ' . $stepNumber . ' is in state "' . $stepState . '".');

			if ($stepState === 'start') {
				$output->writeln(
					sprintf(
						'Step %d is currently in process. Please call this command later or remove the following file to start from scratch: %s',
						$stepNumber,
						$this->updater->getUpdateStepFileLocation()
					)
				);
				return -1;
			}
			$output->writeln('Found an ongoing update, continue from step ' . $stepNumber);
		}

		$this->updater->logVersion();

		$output->writeln('Current version is ' . $this->updater->getCurrentVersion() . '.');

		// needs to be called that early because otherwise updateAvailable() returns false
		if ($this->urlOverride !== '') {
			$this->updater->log('[info] Using URL override: ' . $this->urlOverride);
			$updateString = 'Update check forced with URL override: ' . $this->urlOverride;
		} else {
			$updateString = $this->updater->checkForUpdate();
		}

		$output->writeln('');

		$lines = explode('<br />', $updateString);

		foreach ($lines as $line) {
			// strip HTML
			$output->writeln('<info>' . preg_replace('/<[^>]*>/', '', $line) . '</info>');
		}

		$output->writeln('');

		if ($this->urlOverride === '' && !$this->updater->updateAvailable() && $stepNumber === 0) {
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

			/** @var QuestionHelper */
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

		if (function_exists('pcntl_signal')) {
			// being able to handle stop/terminate command (Ctrl - C)
			pcntl_signal(SIGTERM, $this->stopCommand(...));
			pcntl_signal(SIGINT, $this->stopCommand(...));

			$output->writeln('Info: Pressing Ctrl-C will finish the currently running step and then stops the updater.');
			$output->writeln('');
		} else {
			$output->writeln('Info: Gracefully stopping the updater via Ctrl-C is not possible - PCNTL extension is not loaded.');
			$output->writeln('');
		}

		// print already executed steps
		for ($i = 1; $i <= $stepNumber; $i++) {
			if ($i === 11) {
				// no need to ask for maintenance mode on CLI - skip it
				continue;
			}

			$output->writeln('<info>[SKIP] ' . $this->checkTexts[$i] . '</info>');
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
				if ($this->shouldStop) {
					break;
				}
			}

			$output->write('[ ] ' . $this->checkTexts[$i] . ' ...');

			$result = $this->executeStep($i, $output);

			// Move the cursor to the beginning of the line
			$output->write("\x0D");

			// Erase the line
			$output->write("\x1B[2K");

			if ($result['proceed'] === true) {
				$output->writeln('<info>[✔] ' . $this->checkTexts[$i] . '</info>');
			} else {
				$output->writeln('<error>[✘] ' . $this->checkTexts[$i] . ' failed</error>');

				if ($i === 1) {
					if (is_string($result['response'])) {
						$output->writeln('<error>' . $result['response'] . '</error>');
					} else {
						$output->writeln('<error>Unknown files detected within the installation folder. This can be fixed by manually removing (or moving) these files. The following extra files have been found:</error>');
						foreach ($result['response'] as $file) {
							$output->writeln('<error>    ' . $file . '</error>');
						}
					}
				} elseif ($i === 2) {
					if (is_string($result['response'])) {
						$output->writeln('<error>' . $result['response'] . '</error>');
					} else {
						$output->writeln('<error>The following places can not be written to:</error>');
						foreach ($result['response'] as $file) {
							$output->writeln('<error>    ' . $file . '</error>');
						}
					}
				} elseif (is_string($result['response'])) {
					$output->writeln('<error>' . $result['response'] . '</error>');
				} else {
					$output->writeln('<error>Something has gone wrong. Please check the log file in the data dir.</error>');
				}

				break;
			}
		}

		$output->writeln('');
		if ($i === 12) {
			$this->updater->log('[info] update of code successful.');
			$output->writeln('Update of code successful.');

			//
			// Handle `occ upgrade` run
			//

			if ($this->skipUpgrade) {
				$this->updater->log('[info] "occ upgrade" was skipped');
				$this->updater->log('[info] updater finished');
				$output->writeln('Please execute "./occ upgrade" manually to finish the upgrade.');
				return 0;
			}

			if ($input->isInteractive()) {
				$output->writeln('');

				/** @var QuestionHelper */
				$helper = $this->getHelper('question');
				$question = new ConfirmationQuestion('Should the "occ upgrade" command be executed? [Y/n] ', true);

				if (!$helper->ask($input, $output, $question)) {
					$this->updater->log('[info] "occ upgrade" was skipped');
					$this->updater->log('[info] updater finished');
					$output->writeln('Please execute "./occ upgrade" manually to finish the upgrade.');
					return 0;
				}
			} else {
				$this->updater->log('[info] updater run in non-interactive mode - will start "occ upgrade" now');
				$output->writeln('Updater run in non-interactive mode - will start "occ upgrade" now.');
				$output->writeln('');
			}

			$occPath = $path . '/../occ';
			if (!file_exists($occPath)) {
				$this->updater->log('[error] FATAL: "occ" is missing from: ' . $occPath);
				$output->writeln('');
				throw new \Exception('FATAL: "occ" is missing from: ' . $occPath);
			}

			if (chmod($occPath, 0755) === false) { # TODO do this in the updater
				throw new \Exception('FATAL: Unable to make "occ" executable: ' . $occPath);
			}

			$occRunCommand = PHP_BINARY . ' ' . $occPath;

			$this->updater->log('[info] Starting "occ upgrade"');
			system($occRunCommand . ' upgrade -v', $returnValue);
			if ($returnValue === 0) {
				$this->updater->log('[info] "occ upgrade" finished');
				$output->writeln('');
				$output->writeln('"occ upgrade" finished');
			} else { // something went wrong
				$this->updater->log('[info] "occ upgrade" failed - return code: ' . $returnValue);
				$output->writeln('');
				$output->writeln('"occ upgrade" failed - return code: ' . $returnValue);
				$this->updater->log('[info] updater finished - with errors');
				return $returnValue;
			}

			//
			// Handle maintenance mode toggle
			//

			$output->writeln('');
			if ($input->isInteractive()) {
				/** @var QuestionHelper */
				$helper = $this->getHelper('question');
				$question = new ConfirmationQuestion($this->checkTexts[11] . ' [y/N] ', false);

				if ($helper->ask($input, $output, $question)) {
					$this->updater->log('[info] maintenance mode kept active');
					$output->writeln('Please execute "./occ maintenance:mode --off" manually to finish the upgrade.');
					$this->updater->log('[info] updater finished');
					return 0;
				}
			} else {
				$this->updater->log('[info] updater run in non-interactive mode - will disable maintenance mode now');
				$output->writeln('Updater run in non-interactive mode - will disable maintenance mode now.');
				$output->writeln('');
			}

			$this->updater->log('[info] Disabling maintenance mode');
			$systemOutput = system($occRunCommand . ' maintenance:mode --off', $returnValueMaintenanceMode);
			if ($returnValueMaintenanceMode === 0) {
				$this->updater->log('[info] maintenance mode disabled');
				$output->writeln('');
				$output->writeln('Maintenance mode is disabled');
				return 0;
			}

			// something went wrong
			$this->updater->log('[info] Disabling maintenance mode failed - return code: ' . $returnValueMaintenanceMode);
			$output->writeln('');
			$output->writeln('Disabling Maintenance mode failed - return code:' . $returnValueMaintenanceMode);
			if ($systemOutput === false) {
				$this->updater->log('[info] System call failed');
				$output->writeln('System call failed');
			} else {
				$this->updater->log('[info] occ output: ' . $systemOutput);
				$output->writeln('occ output: ' . $systemOutput);
			}

			$this->updater->log('[info] updater finished - with errors');
			return $returnValueMaintenanceMode;
		}

		if ($this->shouldStop) {
			$output->writeln('<error>Update stopped. To resume or retry just execute the updater again.</error>');
		} else {
			$output->writeln('<error>Update failed. To resume or retry just execute the updater again.</error>');
		}

		return -1;
	}

	/**
	 * @return array{proceed:bool,response:string|list<string>} with options 'proceed' which is a boolean and defines if the step succeeded and an optional 'response' string or array
	 */
	protected function executeStep(int $step, OutputInterface $output): array {
		if (!$this->updater instanceof Updater) {
			return ['proceed' => false, 'response' => 'Initialization problem'];
		}

		$this->updater->log('[info] executeStep request for step "' . $step . '"');
		try {
			if ($step > 12 || $step < 1) {
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
					// Ensure that we have the same number of characters, that we want to override in the progress method
					$output->write(str_pad(' 0%', 5, ' ', STR_PAD_LEFT));

					$this->updater->downloadUpdate($this->urlOverride, function (int $progress) use ($output) {
						// Move cursor 5 to the left and write the new progress
						$output->write("\x1B[5D");
						$output->write(str_pad(' ' . $progress . '%', 5, ' ', STR_PAD_LEFT));
					});
					break;
				case 5:
					$this->updater->verifyIntegrity($this->urlOverride);
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
			return ['proceed' => true, 'response' => ''];
		} catch (UpdateException $e) {
			$data = $e->getData();

			try {
				$this->updater->log('[error] executeStep request failed with UpdateException');
				$this->updater->logException($e);
			} catch (LogException $logE) {
				$data[] = ' (and writing to log failed also with: ' . $logE->getMessage() . ')';
			}

			$this->updater->rollbackChanges($step);
			return ['proceed' => false, 'response' => $data];
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

	protected function showCurrentStatus(OutputInterface $output, int $stepNumber): void {
		$output->writeln('Steps that will be executed:');
		$counter = count($this->checkTexts);
		for ($i = 1; $i < $counter; $i++) {
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
	public function stopCommand(): void {
		$this->shouldStop = true;
	}
}
