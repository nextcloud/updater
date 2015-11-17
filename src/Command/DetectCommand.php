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
use Symfony\Component\Console\Question\ChoiceQuestion;
use GuzzleHttp\Event\ProgressEvent;
use Owncloud\Updater\Utils\Fetcher;
use Owncloud\Updater\Utils\Feed;
use Owncloud\Updater\Utils\ConfigReader;

class DetectCommand extends Command {

	/**
	 * @var Fetcher $fetcher
	 */
	protected $fetcher;

	/**
	 * @var ConfigReader $configReader
	 */
	protected $configReader;

	/**
	 * Constructor
	 *
	 * @param Fetcher $fetcher
	 * @param ConfigReader $configReader
	 */
	public function __construct(Fetcher $fetcher, ConfigReader $configReader){
		parent::__construct();
		$this->fetcher = $fetcher;
		$this->configReader = $configReader;
	}

	protected function configure(){
		$this
				->setName('upgrade:detect')
				->setDescription('Detect
- 1. currently existing code, 
- 2. version in config.php, 
- 3. online available verison.
(ASK) what to do? (download, upgrade, abort, …)')
				->addOption(
						'exit-if-none', null, InputOption::VALUE_NONE, 'exit with non-zero status code if new version is not found'
				)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		$registry = $this->container['utils.registry'];
		$registry->set('feed', false);

		$locator = $this->container['utils.locator'];
		$fsHelper = $this->container['utils.filesystemhelper'];
		try{
			$currentVersion = $this->configReader->getByPath('system.version');
			if (!strlen($currentVersion)){
				throw new \UnexpectedValueException('Could not detect installed version.');
			}

			$this->getApplication()->getLogger()->info('ownCloud ' . $currentVersion . ' found');
			$output->writeln('Current version is ' . $currentVersion);

			$feed = $this->fetcher->getFeed();
			if ($feed->isValid()){
				$output->writeln($feed->getVersionString() . ' is found online');

				$helper = $this->getHelper('question');
				$question = new ChoiceQuestion(
					'What would you do next?',
					['download', 'upgrade', 'abort'],
					'1'
				);
				$action = $helper->ask($input, $output, $question);

				if ($action === 'abort'){
					$output->writeln('Abort has been choosed. Exiting.');
					return 128;
				}

				$path = $this->fetcher->getBaseDownloadPath($feed);
				$fileExists = $this->isCached($feed, $output);
				if (!$fileExists){
					$this->fetcher->getOwncloud($feed, function (ProgressEvent $e) use ($output) {
					$percentString = '';
					if ($e->downloadSize){
						$percent = intval(100* $e->downloaded / $e->downloadSize );
						$percentString = $percent . '%';
					}
    $output->write( 'Downloaded ' . $percentString . ' (' . $e->downloaded . ' of ' . $e->downloadSize . ")\r");
});
					if (md5_file($path) !== $this->fetcher->getMd5($feed)){
						$output->writeln('Downloaded ' . $feed->getDownloadedFileName() . '. Checksum is incorrect.');
						@unlink($path);
					} else {
						$fileExists = true;
					}
				}
				if ($action === 'download'){
					$output->writeln('Downloading has been completed. Exiting.');
					return 64;
				}
				if ($fileExists){
					$registry->set('feed', $feed);
				}
			} else {
				$output->writeln('No updates found online.');
				if ($input->getOption('exit-if-none')){
					return 4;
				}
			}
		} catch (\Exception $e){
			$this->getApplication()->getLogger()->error($e->getMessage());
			return 2;
		}
	}

	public function isCached(Feed $feed, OutputInterface $output){
		$path = $this->fetcher->getBaseDownloadPath($feed);
		$fileExists = file_exists($path);
		if ($fileExists){
			if (md5_file($path) === $this->fetcher->getMd5($feed)){
				$output->writeln('Already downloaded ' . $feed->getVersion() . ' with a correct checksum found. Reusing.');
			} else {
				$output->writeln('Already downloaded ' . $feed->getVersion() . ' with an invalid checksum found. Removing.');
				@unlink($path);
				$fileExists = false;
			}
		}
		return $fileExists;
	}

}
