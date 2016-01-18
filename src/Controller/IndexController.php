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

namespace Owncloud\Updater\Controller;

use League\Plates\Extension\URI;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Owncloud\Updater\Formatter\HtmlOutputFormatter;
use Owncloud\Updater\Http\Request;
use League\Plates\Engine;
use League\Plates\Extension\Asset;

class IndexController {

	/** @var \Pimple\Container */
	protected $container;

	/** @var Request */
	protected $request;

	/** @var string $command */
	protected $command;

	public function __construct(\Pimple\Container $container, $request = null){
		$this->container = $container;
		if (is_null($request)){
			$this->request = new Request(['post' => $_POST]);
		} else {
			$this->request = $request;
		}

		$this->command = $this->request->postParameter('command');
	}

	public function dispatch(){
		if (is_null($this->command)){
			// strip index.php and query string (if any) to get a real base url
			$baseUrl = preg_replace('/(index\.php.*|\?.*)$/', '', $_SERVER['REQUEST_URI']);

			$templates = new Engine(CURRENT_DIR . '/src/Resources/views/');
			$templates->loadExtension(new Asset(CURRENT_DIR . '/pub/', false));
			$templates->loadExtension(new URI($baseUrl));

			// TODO: Check for user permissions
			//$content = $templates->render('partials/login', ['title' => 'Login Required']);
			$content = $templates->render('partials/inner', ['title' => 'Updater']);
		} else {
			header('Content-Type: application/json');
			$content = json_encode($this->ajaxAction(), JSON_UNESCAPED_SLASHES);
		}
		return $content;
	}

	public function ajaxAction(){
		$application = $this->container['application'];

		$input = new StringInput($this->command);
		$input->setInteractive(false);

		$output = new BufferedOutput();
		$formatter = $output->getFormatter();
		$formatter->setDecorated(true);
		$output->setFormatter(new HtmlOutputFormatter($formatter));

		$application->setAutoExit(false);
// Some commands  dump things out instead of returning a value
		ob_start();
		$errorCode = $application->run($input, $output);
		if (!$result = $output->fetch()){
			$result = ob_get_contents(); // If empty, replace it by the catched output
		}
		ob_end_clean();

		return [
			'input' => $this->command,
			'output' => nl2br($result),
			'environment' => '',
			'error_code' => $errorCode
		];
	}

}
