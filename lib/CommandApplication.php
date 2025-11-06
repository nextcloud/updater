<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace NC\Updater;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class CommandApplication
 *
 * see http://symfony.com/doc/current/components/console/single_command_tool.html
 *
 * @package NC\Updater
 */
class CommandApplication extends Application {
	public function __construct(string $name = 'update') {
		parent::__construct($name, UpdateCommand::getUpdaterVersion());
	}

	/**
	 * Gets the name of the command based on input.
	 *
	 * @param InputInterface $input The input interface
	 *
	 * @return string The command name
	 */
	protected function getCommandName(InputInterface $input): ?string {
		// This should return the name of your command.
		return 'update';
	}

	/**
	 * Gets the default commands that should always be available.
	 *
	 * @return Command[] An array of default Command instances
	 */
	protected function getDefaultCommands(): array {
		// Keep the core default commands to have the HelpCommand
		// which is used when using the --help option
		$defaultCommands = parent::getDefaultCommands();

		$defaultCommands[] = new UpdateCommand();

		return $defaultCommands;
	}

	/**
	 * Overridden so that the application doesn't expect the command
	 * name to be the first argument.
	 */
	public function getDefinition(): InputDefinition {
		$inputDefinition = parent::getDefinition();
		// clear out the normal first argument, which is the command name
		$inputDefinition->setArguments();

		return $inputDefinition;
	}
}
