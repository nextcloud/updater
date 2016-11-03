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
		// TODO echo($updater->checkForUpdate());

		if(!$this->updater->updateAvailable()) {
			$output->writeln('Everything is up to date.');
			return 0;
		}

			    /** initial view */

    /*
<ul id="progress" class="section">
<li id="step-init" class="step icon-loading passed-step">
<h2>Initializing</h2>
<div class="output">Current version is <?php echo($updater->getCurrentVersion()); ?>.<br>
<?php echo($updater->checkForUpdate()); ?><br>

<?php
if ($updater->updateAvailable() || $stepNumber > 0) {
$buttonText = 'Start update';
if($stepNumber > 0) {
$buttonText = 'Continue update';
}
?>
<button id="startUpdateButton"><?php echo $buttonText ?></button>
<?php
}
?>
<button id="retryUpdateButton" class="hidden">Retry update</button>
</div>
</li>
<li id="step-check-files" class="step <?php if($stepNumber >= 1) { echo 'passed-step'; }?>">
	<h2>Check for expected files</h2>
	<div class="output hidden"></div>
</li>
<li id="step-check-permissions" class="step <?php if($stepNumber >= 2) { echo 'passed-step'; }?>">
	<h2>Check for write permissions</h2>
	<div class="output hidden"></div>
</li>
<li id="step-enable-maintenance" class="step <?php if($stepNumber >= 3) { echo 'passed-step'; }?>">
	<h2>Enable maintenance mode</h2>
	<div class="output hidden"></div>
</li>
<li id="step-backup" class="step <?php if($stepNumber >= 4) { echo 'passed-step'; }?>">
	<h2>Create backup</h2>
	<div class="output hidden"></div>
</li>
<li id="step-download" class="step <?php if($stepNumber >= 5) { echo 'passed-step'; }?>">
	<h2>Downloading</h2>
	<div class="output hidden"></div>
</li>
<li id="step-extract" class="step <?php if($stepNumber >= 6) { echo 'passed-step'; }?>">
	<h2>Extracting</h2>
	<div class="output hidden"></div>
</li>
<li id="step-entrypoints" class="step <?php if($stepNumber >= 7) { echo 'passed-step'; }?>">
	<h2>Replace entry points</h2>
	<div class="output hidden"></div>
</li>
<li id="step-delete" class="step <?php if($stepNumber >= 8) { echo 'passed-step'; }?>">
	<h2>Delete old files</h2>
	<div class="output hidden"></div>
</li>
<li id="step-move" class="step <?php if($stepNumber >= 9) { echo 'passed-step'; }?>">
	<h2>Move new files in place</h2>
	<div class="output hidden"></div>
</li>
<li id="step-maintenance-mode" class="step <?php if($stepNumber >= 10) { echo 'passed-step'; }?>">
	<h2>Keep maintenance mode active?</h2>
	<div class="output hidden">
		<button id="maintenance-enable">Yes (for usage with command line tool)</button>
		<button id="maintenance-disable">No (for usage of the web based updater)</button>
	</div>
</li>
<li id="step-done" class="step <?php if($stepNumber >= 11) { echo 'passed-step'; }?>">
	<h2>Done</h2>
	<div class="output hidden">
		<a class="button" href="<?php echo str_replace('/index.php', '/../', $updaterUrl); ?>">Go to back to your Nextcloud instance to finish the update</a>
	</div>
</li>
</ul>
*/
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