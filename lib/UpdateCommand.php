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

		// TODO
	}

}