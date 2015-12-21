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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessUtils;
use Owncloud\Updater\Utils\OccRunner;

class MaintenanceModeCommand extends Command {

	/**
	 * @var OccRunner $occRunner
	 */
	protected $occRunner;

	/**
	 * Constructor
	 *
	 * @param OccRunner $occRunner
	 */
	public function __construct(OccRunner $occRunner){
		parent::__construct();
		$this->occRunner = $occRunner;
	}

	protected function configure(){
		$this
				->setName('upgrade:maintenanceMode')
				->setDescription('Toggle maintenance mode')
				->addOption(
						'on', null, InputOption::VALUE_NONE, 'enable maintenance mode'
				)
				->addOption(
						'off', null, InputOption::VALUE_NONE, 'disable maintenance mode'
				)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		$mode = '';
		if ($input->getOption('on')){
			$mode = '--on';
		} elseif ($input->getOption('off')){
			$mode = '--off';
		}

		if ($mode !== ''){
			$mode = ProcessUtils::escapeArgument($mode);
		}

		$response =  $this->occRunner->run('maintenance:mode ' . $mode);
		$output->writeln($response);
	}

}
