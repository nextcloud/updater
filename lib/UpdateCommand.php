<?php
/**
 * Created by PhpStorm.
 * User: morrisjobke
 * Date: 27.10.16
 * Time: 10:39
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
            ->setHelp("This command fetches the latest code that is announced via the updater server and safely replaces the existing code with the new one.")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        // TODO
    }
}