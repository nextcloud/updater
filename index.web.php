<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

class Auth {
	public function __construct(
		private Updater $updater,
		private string $password,
	) {
		$this->updater = $updater;
		$this->password = $password;
	}

	/**
	 * Whether the current user is authenticated
	 */
	public function isAuthenticated(): bool {
		$storedHash = $this->updater->getConfigOptionString('updater.secret');

		// As a sanity check the stored hash can never be empty
		if ($storedHash === '' || $storedHash === null) {
			return false;
		}

		return password_verify($this->password, $storedHash);
	}
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Check if the config.php is at the expected place
try {
	$updater = new Updater(__DIR__);
	if ($updater->isDisabled()) {
		http_response_code(403);
		die('Updater is disabled, please use the command line');
	}
} catch (\Exception $e) {
	// logging here is not possible because we don't know the data directory
	http_response_code(500);
	die($e->getMessage());
}

// Check if the updater.log can be written to
try {
	$updater->log('[info] request to updater');
} catch (\Exception $e) {
	if (isset($_POST['step'])) {
		// mark step as failed
		http_response_code(500);
		header('Content-Type: application/json');
		echo(json_encode(['proceed' => false, 'response' => $e->getMessage()]));
		die();
	}
	// show logging error to user
	die($e->getMessage());
}

// Check for authentication
$password = ($_SERVER['HTTP_X_UPDATER_AUTH'] ?? $_POST['updater-secret-input'] ?? '');
if (!is_string($password)) {
	die('Invalid type ' . gettype($password) . ' for password');
}
$auth = new Auth($updater, $password);

// Check if already a step is in process
$currentStep = $updater->currentStep();
$stepNumber = 0;
if ($currentStep !== []) {
	$stepState = (string)$currentStep['state'];
	$stepNumber = (int)$currentStep['step'];
	$updater->log('[info] Step ' . $stepNumber . ' is in state "' . $stepState . '".');

	if ($stepState === 'start') {
		die(
			sprintf(
				'Step %d is currently in process. Please reload this page later or remove the following file to start from scratch: %s',
				$stepNumber,
				$updater->getUpdateStepFileLocation()
			)
		);
	}
}

if (isset($_POST['step']) && !is_array($_POST['step'])) {
	$updater->log('[info] POST request for step "' . $_POST['step'] . '"');
	set_time_limit(0);
	try {
		if (!$auth->isAuthenticated()) {
			throw new \Exception('Not authenticated');
		}

		$step = (int)$_POST['step'];
		if ($step > 12 || $step < 1) {
			throw new \Exception('Invalid step');
		}

		$updater->startStep($step);
		switch ($step) {
			case 1:
				$updater->checkForExpectedFilesAndFolders();
				break;
			case 2:
				$updater->checkWritePermissions();
				break;
			case 3:
				$updater->createBackup();
				break;
			case 4:
				$updater->downloadUpdate();
				break;
			case 5:
				$updater->verifyIntegrity();
				break;
			case 6:
				$updater->extractDownload();
				break;
			case 7:
				$updater->setMaintenanceMode(true);
				break;
			case 8:
				$updater->replaceEntryPoints();
				break;
			case 9:
				$updater->deleteOldFiles();
				break;
			case 10:
				$updater->moveNewVersionInPlace();
				break;
			case 11:
				$updater->setMaintenanceMode(false);
				break;
			case 12:
				$updater->finalize();
				break;
		}
		$updater->endStep($step);
		header('Content-Type: application/json');
		echo(json_encode(['proceed' => true]));
	} catch (UpdateException $e) {
		$data = $e->getData();

		try {
			$updater->log('[error] POST request failed with UpdateException');
			$updater->logException($e);
		} catch (LogException $logE) {
			$data[] = ' (and writing to log failed also with: ' . $logE->getMessage() . ')';
		}

		if (isset($step)) {
			$updater->rollbackChanges($step);
		}
		http_response_code(500);
		header('Content-Type: application/json');
		echo(json_encode(['proceed' => false, 'response' => $data]));
	} catch (\Exception $e) {
		$message = $e->getMessage();

		try {
			$updater->log('[error] POST request failed with other exception');
			$updater->logException($e);
		} catch (LogException $logE) {
			$message .= ' (and writing to log failed also with: ' . $logE->getMessage() . ')';
		}

		if (isset($step)) {
			$updater->rollbackChanges($step);
		}
		http_response_code(500);
		header('Content-Type: application/json');
		echo(json_encode(['proceed' => false, 'response' => $message]));
	}

	die();
}

$updater->log('[info] show HTML page');
$updater->logVersion();
?>

<html>
<head>
	<style>
		html, body, div, span, object, iframe, h1, h2, h3, h4, h5, h6, p, blockquote, pre, a, abbr, acronym, address, code, del, dfn, em, img, q, dl, dt, dd, ol, ul, li, fieldset, form, label, legend, table, caption, tbody, tfoot, thead, tr, th, td, article, aside, dialog, figure, footer, header, nav, section {
			margin: 0;
			padding: 0;
			border: 0;
			outline: 0;
			font-weight: inherit;
			font-size: 100%;
			font-family: inherit;
			vertical-align: baseline;
			cursor: default;
		}
		body {
			font-family: 'Open Sans', Frutiger, Calibri, 'Myriad Pro', Myriad, sans-serif;
			background-color: #ffffff;
			font-weight: 400;
			font-size: .8em;
			line-height: 1.6em;
			color: #000;
			height: auto;
		}
		a {
			border: 0;
			color: #000;
			text-decoration: none;
			cursor: pointer;
		}
		.external_link {
			text-decoration: underline;
		}
		ul {
			list-style: none;
		}
		.output ul {
			list-style: initial;
			padding: 0 30px;
		}
		#header {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			height: 45px;
			line-height: 2.5em;
			background-color: #0082c9;
			box-sizing: border-box;
		}
		.header-appname {
			color: #fff;
			font-size: 20px;
			font-weight: 300;
			line-height: 45px;
			padding: 0;
			margin: 0;
			display: inline-block;
			position: absolute;
			margin-left: 5px;
		}
		#header svg {
			margin: 5px;
		}

		#content-wrapper {
			position: absolute;
			height: 100%;
			width: 100%;
			overflow-x: hidden;
			padding-top: 45px;
			box-sizing: border-box;
		}

		#content {
			position: relative;
			height: 100%;
			margin: 0 auto;
		}
		#app-navigation {
			width: 250px;
			height: 100%;
			float: left;
			box-sizing: border-box;
			background-color: #fff;
			padding-bottom: 44px;
			-webkit-user-select: none;
			-moz-user-select: none;
			-ms-user-select: none;
			user-select: none;
			border-right: 1px solid #eee;
		}
		#app-navigation > ul {
			position: relative;
			height: 100%;
			width: inherit;
			overflow: auto;
			box-sizing: border-box;
		}
		#app-navigation li {
			position: relative;
			width: 100%;
			box-sizing: border-box;
		}
		#app-navigation li > a {
			display: block;
			width: 100%;
			line-height: 44px;
			min-height: 44px;
			padding: 0 12px;
			overflow: hidden;
			box-sizing: border-box;
			white-space: nowrap;
			text-overflow: ellipsis;
			color: #000;
			opacity: .57;
		}
		#app-navigation li:hover > a, #app-navigation li:focus > a {
			opacity: 1;
		}

		#app-content {
			position: relative;
			height: 100%;
			overflow-y: auto;
		}
		#progress {
			width: 600px;
		}
		.section {
			padding: 25px 30px;
		}
		.hidden {
			display: none;
		}

		li.step, .light {
			-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=57)";
			opacity: .57;
		}

		li.step h2 {
			padding: 5px 2px 5px 30px;
			margin-top: 12px;
			margin-bottom: 0;
			-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=57)";
			opacity: .57;
			background-position:8px 50%;
			background-repeat: no-repeat;
		}

		li.current-step, li.passed-step, li.failed-step, li.waiting-step {
			-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=100)";
			opacity: 1;
		}

		.current-step {
			background-repeat: no-repeat;
			background-position: center;
			min-width: 16px;
			min-height: 16px;
			position: relative;
		}
		.current-step:after {
			z-index: 2;
			content: '';
			height: 12px;
			width: 12px;
			margin: -8px 0 0 -8px;
			position: absolute;
			top: 14px;
			left: 16px;
			border-radius: 100%;
			-webkit-animation: rotate .8s infinite linear;
			animation: rotate .8s infinite linear;
			-webkit-transform-origin: center;
			-ms-transform-origin: center;
			transform-origin: center;
			border: 2px solid rgba(150, 150, 150, 0.5);
			border-top-color: #969696;
		}

		@keyframes rotate {
			from {
				transform: rotate(0deg);
			}
			to {
				transform: rotate(360deg);
			}
		}

		li.current-step h2, li.passed-step h2, li.failed-step h2, li.waiting-step h2 {
			-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=100)";
			opacity: 1;
		}

		li.passed-step h2 {
			background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGhlaWdodD0iMTYiIHdpZHRoPSIxNiIgdmVyc2lvbj0iMS4xIiB2aWV3Qm94PSIwIDAgMTYgMTYiPjxwYXRoIGQ9Im0yLjM1IDcuMyA0IDRsNy4zLTcuMyIgc3Ryb2tlPSIjNDZiYTYxIiBzdHJva2Utd2lkdGg9IjIiIGZpbGw9Im5vbmUiLz48L3N2Zz4NCg==);
		}

		li.failed-step {
			background-color: #ffd4d4;
			border-radius: 3px;
		}
		li.failed-step h2 {
			color: #000;
			background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGhlaWdodD0iMTYiIHdpZHRoPSIxNiIgdmVyc2lvbj0iMS4xIiB2aWV3Ym94PSIwIDAgMTYgMTYiPjxwYXRoIGQ9Im0xNCAxMi4zLTEuNyAxLjctNC4zLTQuMy00LjMgNC4zLTEuNy0xLjcgNC4zLTQuMy00LjMtNC4zIDEuNy0xLjcgNC4zIDQuMyA0LjMtNC4zIDEuNyAxLjctNC4zIDQuM3oiIGZpbGw9IiNkNDAwMDAiLz48L3N2Zz4NCg==);
		}

		li.step .output {
			position: relative;
			padding: 5px 5px 5px 32px;
		}

		h2 {
			font-size: 20px;
			font-weight: 300;
			margin-bottom: 12px;
			color: #555;
		}

		button, a.button {
			font-family: 'Open Sans', Frutiger, Calibri, 'Myriad Pro', Myriad, sans-serif;
			font-size: 13px;
			font-weight: 600;
			color: #545454;
			margin: 3px 3px 3px 0;
			padding: 6px 12px;
			background-color: #f7f7f7;
			border-radius: 3px;
			border: 1px solid #dbdbdb;
			cursor: pointer;
			outline: none;
			min-height: 34px;
			box-sizing: border-box;
		}

		button:hover, button:focus, a.button:hover, a.button:focus {
			border-color: #0082c9;
		}

		code {
			font-family: monospace;
			font-size: 1.2em;
			background-color: #eee;
			border-radius: 2px;
			padding: 2px 6px 2px 4px;
		}

		#login code {
			display: block;
			border-radius: 3px;
		}

		#login form {
			margin-top: 5px;
		}

		#login input {
			border-radius: 3px;
			border: 1px solid rgba(240,240,240,.9);
			margin: 3px 3px 3px 0;
			padding: 9px 6px;
			font-size: 13px;
			outline: none;
			cursor: text;
		}

		.section {
			max-width: 600px;
			margin: 0 auto;
		}

		pre {
			word-wrap: break-word;
		}

	</style>
</head>
<body>
<div id="header">
	<svg xmlns="http://www.w3.org/2000/svg" version="1.1" xml:space="preserve" height="34" width="62" enable-background="new 0 0 196.6 72" y="0px" x="0px" viewBox="0 0 62.000002 34"><path style="color-rendering:auto;text-decoration-color:#000000;color:#000000;isolation:auto;mix-blend-mode:normal;shape-rendering:auto;solid-color:#000000;block-progression:tb;text-decoration-line:none;image-rendering:auto;white-space:normal;text-indent:0;enable-background:accumulate;text-transform:none;text-decoration-style:solid" fill="#fff" d="m31.6 4.0001c-5.95 0.0006-10.947 4.0745-12.473 9.5549-1.333-2.931-4.266-5.0088-7.674-5.0092-4.6384 0.0005-8.4524 3.8142-8.453 8.4532-0.0008321 4.6397 3.8137 8.4544 8.4534 8.455 3.4081-0.000409 6.3392-2.0792 7.6716-5.011 1.5261 5.4817 6.5242 9.5569 12.475 9.5569 5.918 0.000457 10.89-4.0302 12.448-9.4649 1.3541 2.8776 4.242 4.9184 7.6106 4.9188 4.6406 0.000828 8.4558-3.8144 8.4551-8.455-0.000457-4.6397-3.8154-8.454-8.4551-8.4533-3.3687 0.0008566-6.2587 2.0412-7.6123 4.9188-1.559-5.4338-6.528-9.4644-12.446-9.464zm0 4.9623c4.4687-0.000297 8.0384 3.5683 8.0389 8.0371 0.000228 4.4693-3.5696 8.0391-8.0389 8.0388-4.4687-0.000438-8.0375-3.5701-8.0372-8.0388 0.000457-4.4682 3.5689-8.0366 8.0372-8.0371zm-20.147 4.5456c1.9576 0.000226 3.4908 1.5334 3.4911 3.491 0.000343 1.958-1.533 3.4925-3.4911 3.4927-1.958-0.000228-3.4913-1.5347-3.4911-3.4927 0.0002284-1.9575 1.5334-3.4907 3.4911-3.491zm40.205 0c1.9579-0.000343 3.4925 1.533 3.4927 3.491 0.000457 1.9584-1.5343 3.493-3.4927 3.4927-1.958-0.000228-3.4914-1.5347-3.4911-3.4927 0.000221-1.9575 1.5335-3.4907 3.4911-3.491z"/></svg>
	<h1 class="header-appname">Updater</h1>
</div>
<input type="hidden" id="updater-access-key" value="<?php echo htmlentities($password) ?>"/>
<input type="hidden" id="updater-step-start" value="<?php echo $stepNumber ?>" />
<div id="content-wrapper">
	<div id="content">

		<div id="app-content">
		<?php if ($auth->isAuthenticated()): ?>
			<ul id="progress" class="section">
				<li id="step-init" class="step icon-loading passed-step">
					<h2>Initializing</h2>
					<div class="output">Current version is <?php echo($updater->getCurrentVersion()); ?>.<br>
						<?php echo($updater->checkForUpdate()); ?><br>

						<?php
						if ($updater->updateAvailable() || $stepNumber > 0) {
							$buttonText = 'Start update';
							if ($stepNumber > 0) {
								$buttonText = 'Continue update';
							} ?>
							<button id="startUpdateButton"><?php echo $buttonText ?></button>
							<?php
						}
			?>
						<button id="retryUpdateButton" class="hidden">Retry update</button>
						</div>
				</li>
				<li id="step-check-files" class="step <?php if ($stepNumber >= 1) {
					echo 'passed-step';
				}?>">
					<h2>Check for expected files</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-check-permissions" class="step <?php if ($stepNumber >= 2) {
					echo 'passed-step';
				}?>">
					<h2>Check for write permissions</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-backup" class="step <?php if ($stepNumber >= 3) {
					echo 'passed-step';
				}?>">
					<h2>Create backup</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-download" class="step <?php if ($stepNumber >= 4) {
					echo 'passed-step';
				}?>">
					<h2>Downloading</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-verify-integrity" class="step <?php if ($stepNumber >= 5) {
					echo 'passed-step';
				}?>">
					<h2>Verifying integrity</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-extract" class="step <?php if ($stepNumber >= 6) {
					echo 'passed-step';
				}?>">
					<h2>Extracting</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-enable-maintenance" class="step <?php if ($stepNumber >= 7) {
					echo 'passed-step';
				}?>">
					<h2>Enable maintenance mode</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-entrypoints" class="step <?php if ($stepNumber >= 8) {
					echo 'passed-step';
				}?>">
					<h2>Replace entry points</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-delete" class="step <?php if ($stepNumber >= 9) {
					echo 'passed-step';
				}?>">
					<h2>Delete old files</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-move" class="step <?php if ($stepNumber >= 10) {
					echo 'passed-step';
				}?>">
					<h2>Move new files in place</h2>
					<div class="output hidden"></div>
				</li>
				<li id="step-maintenance-mode" class="step <?php if ($stepNumber >= 11) {
					echo 'passed-step';
				}?>">
					<h2>Continue with web based updater</h2>
					<div class="output hidden">
						<button id="maintenance-disable">Disable maintenance mode and continue in the web based updater</button>
					</div>
				</li>
				<li id="step-done" class="step <?php if ($stepNumber >= 12) {
					echo 'passed-step';
				}?>">
					<h2>Done</h2>
					<div class="output hidden">
						<a id="back-to-nextcloud" class="button">Go back to your Nextcloud instance to finish the update</a>
					</div>
				</li>
			</ul>
		<?php else: ?>
			<div id="login" class="section">
				<h2>Authentication</h2>
				<p>To login you need to provide the unhashed value of "updater.secret" in your config file.</p>
				<p>If you don't know that value, you can access this updater directly via the Nextcloud admin screen or generate
				your own secret:</p>
				<code>php -r '$password = trim(shell_exec("openssl rand -base64 48"));if(strlen($password) === 64) {$hash = password_hash($password, PASSWORD_DEFAULT) . "\n"; echo "Insert as \"updater.secret\": ".$hash; echo "The plaintext value is: ".$password."\n";}else{echo "Could not execute OpenSSL.\n";};'</code>
				<form method="post" name="login">
					<fieldset>
						<input type="password" name="updater-secret-input" value=""
							   placeholder="Secret"
							   autocomplete="on" required>
						<button id="updater-secret-submit">Login</button>
					</fieldset>
				</form>
				<?php if (isset($_POST['updater-secret-input']) && !$auth->isAuthenticated()): ?>
				<p>Invalid password</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		</div>
	</div>
</div>

</body>
<?php if ($auth->isAuthenticated()): ?>
	<script>
        var nextcloudUrl = window.location.href.replace('updater/', '').replace('index.php', '');

        var backToButton = document.getElementById('back-to-nextcloud');
        if (backToButton) {
            backToButton.href = nextcloudUrl;
        }

		function escapeHTML(s) {
			return s.toString().split('&').join('&amp;').split('<').join('&lt;').split('>').join('&gt;').split('"').join('&quot;').split('\'').join('&#039;');
		}

		var done = false;
		var started = false;
		var updaterStepStart = parseInt(document.getElementById('updater-step-start').value);
		var elementId =false;
		function addStepText(id, text) {
			var el = document.getElementById(id);
			var output = el.getElementsByClassName('output')[0];
			if(typeof text === 'object') {
				text = JSON.stringify(text);
			}
			output.innerHTML = output.innerHTML + text;
			output.classList.remove('hidden');
		}
		function removeStepText(id) {
			var el = document.getElementById(id);
			var output = el.getElementsByClassName('output')[0];
			output.innerHTML = '';
			output.classList.add('hidden');
		}

		function currentStep(id) {
			var el = document.getElementById(id);
			el.classList.remove('failed-step');
			el.classList.remove('passed-step');
			el.classList.remove('waiting-step');
			el.classList.add('current-step');
		}

		function errorStep(id, numericId) {
			var el = document.getElementById(id);
			el.classList.remove('passed-step');
			el.classList.remove('current-step');
			el.classList.remove('waiting-step');
			el.classList.add('failed-step');

			// set start step to previous one
			updaterStepStart = numericId - 1;
			elementId = id;

			// show restart button
			var button = document.getElementById('retryUpdateButton');
			button.classList.remove('hidden');
		}

		function successStep(id) {
			var el = document.getElementById(id);
			el.classList.remove('failed-step');
			el.classList.remove('current-step');
			el.classList.remove('waiting-step');
			el.classList.add('passed-step');
		}

		function waitingStep(id) {
			var el = document.getElementById(id);
			el.classList.remove('failed-step');
			el.classList.remove('current-step');
			el.classList.remove('passed-step');
			el.classList.add('waiting-step');
		}

		function performStep(number, callback) {
			started = true;
			var httpRequest = new XMLHttpRequest();
			httpRequest.open('POST', window.location.href);
			httpRequest.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
			httpRequest.setRequestHeader('X-Updater-Auth', document.getElementById('updater-access-key').value);
			httpRequest.onreadystatechange = function () {
				if (httpRequest.readyState != 4) { // 4 - request done
					return;
				}

				if (httpRequest.status != 200) {
					// failure
				}

				if(httpRequest.responseText.substr(0,1) !== '{') {
					// it seems that this is not a JSON object
					var response = {
						processed: false,
						response: 'Parsing response failed.',
						detailedResponseText: httpRequest.responseText,
					};
					callback(response);
				} else {
					// parse JSON
					callback(JSON.parse(httpRequest.responseText));
				}

			};
			httpRequest.send("step="+number);
		}


		var performStepCallbacks = {
			0: function() { // placeholder that is called on start of the updater
				currentStep('step-check-files');
				performStep(1, performStepCallbacks[1]);
			},
			1: function(response) {
				if(response.proceed === true) {
					successStep('step-check-files');
					currentStep('step-check-permissions');
					performStep(2, performStepCallbacks[2]);
				} else {
					errorStep('step-check-files', 1);

					var text = '';
					if (typeof response['response'] === 'string') {
						text = escapeHTML(response['response']);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response['detailedResponseText']) + '</code></pre></details>';
					} else {
						text = 'Unknown files detected within the installation folder. This can be fixed by manually removing (or moving) these files. The following extra files have been found:<ul>';
						response['response'].forEach(function(file) {
							text += '<li>' + escapeHTML(file) + '</li>';
						});
						text += '</ul>';
					}
					addStepText('step-check-files', text);
				}
			},
			2: function(response) {
				if(response.proceed === true) {
					successStep('step-check-permissions');
					currentStep('step-backup');
					performStep(3, performStepCallbacks[3]);
				} else {
					errorStep('step-check-permissions', 2);

					var text = '';
					if (typeof response['response'] === 'string') {
						text = escapeHTML(response['response']);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response['detailedResponseText']) + '</code></pre></details>';
					} else {
						text = 'The following places can not be written to:<ul>';
						response['response'].forEach(function(file) {
							text += '<li>' + escapeHTML(file) + '</li>';
						});
						text += '</ul>';
					}
					addStepText('step-check-permissions', text);
				}
			},
			3: function (response) {
				if (response.proceed === true) {
					successStep('step-backup');
					currentStep('step-download');
					performStep(4, performStepCallbacks[4]);
				} else {
					errorStep('step-backup', 3);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-backup', text);
					}
				}
			},
			4: function (response) {
				if (response.proceed === true) {
					successStep('step-download');
					currentStep('step-verify-integrity');
					performStep(5, performStepCallbacks[5]);
				} else {
					errorStep('step-download', 4);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-download', text);
					}
				}
			},
			5: function (response) {
				if (response.proceed === true) {
					successStep('step-verify-integrity');
					currentStep('step-extract');
					performStep(6, performStepCallbacks[6]);
				} else {
					errorStep('step-verify-integrity', 5);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-verify-integrity', text);
					}
				}
			},
			6: function (response) {
				if (response.proceed === true) {
					successStep('step-extract');
					currentStep('step-enable-maintenance');
					performStep(7, performStepCallbacks[7]);
				} else {
					errorStep('step-extract', 6);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-extract', text);
					}
				}
			},
			7: function (response) {
				if (response.proceed === true) {
					successStep('step-enable-maintenance');
					currentStep('step-entrypoints');
					performStep(8, performStepCallbacks[8]);
				} else {
					errorStep('step-enable-maintenance', 7);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-enable-maintenance', text);
					}
				}
			},
			8: function (response) {
				if (response.proceed === true) {
					successStep('step-entrypoints');
					currentStep('step-delete');
					performStep(9, performStepCallbacks[9]);
				} else {
					errorStep('step-entrypoints', 8);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-entrypoints', text);
					}
				}
			},
			9: function (response) {
				if (response.proceed === true) {
					successStep('step-delete');
					currentStep('step-move');
					performStep(10, performStepCallbacks[10]);
				} else {
					errorStep('step-delete', 9);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-delete', text);
					}
				}
			},
			10: function (response) {
				if (response.proceed === true) {
					successStep('step-move');

					waitingStep('step-maintenance-mode');
					// show buttons to decide on maintenance mode
					var el = document.getElementById('step-maintenance-mode')
						.getElementsByClassName('output')[0];
					el.classList.remove('hidden');
				} else {
					errorStep('step-move', 10);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-move', text);
					}
				}
			},
			11: function (response) {
				if (response.proceed === true) {
					successStep('step-maintenance-mode');
					currentStep('step-done');
					performStep(12, performStepCallbacks[12]);
				} else {
					errorStep('step-maintenance-mode', 11);

					if(response.response) {
						var text = escapeHTML(response.response);
						text += '<br><details><summary>Show detailed response</summary><pre><code>' +
							escapeHTML(response.detailedResponseText) + '</code></pre></details>';
						addStepText('step-maintenance-mode', text);
					}
				}
			},
			12: function (response) {
				done = true;
				window.removeEventListener('beforeunload', confirmExit);
				if (response.proceed === true) {
					successStep('step-done');

					// show button to get to the web based migration steps
					var el = document.getElementById('step-done')
						.getElementsByClassName('output')[0];
					el.classList.remove('hidden');

					// above is the fallback if the Javascript redirect doesn't work
					window.location.href = nextcloudUrl;
				} else {
					errorStep('step-done', 12);
					var text = escapeHTML(response.response);
					text += '<br><details><summary>Show detailed response</summary><pre><code>' +
						escapeHTML(response.detailedResponseText) + '</code></pre></details>';
					addStepText('step-done', text);
				}
			},
		};

		function startUpdate() {
			performStepCallbacks[updaterStepStart]({
				proceed: true
			});
		}

		function retryUpdate() {
			//remove failed log
			if (elementId !== false) {
				var el = document.getElementById(elementId);
				el.classList.remove('passed-step');
				el.classList.remove('current-step');
				el.classList.remove('waiting-step');
				el.classList.remove('failed-step');

				removeStepText(elementId);

				elementId = false;
			}

			// hide restart button
			var button = document.getElementById('retryUpdateButton');
			button.classList.add('hidden');

			startUpdate();
		}

		function askForMaintenance() {
			var el = document.getElementById('step-maintenance-mode')
				.getElementsByClassName('output')[0];
			el.innerHTML = 'Maintenance mode will get disabled.<br>';
			currentStep('step-maintenance-mode');
			performStep(11, performStepCallbacks[11]);
		}

		if(document.getElementById('startUpdateButton')) {
			document.getElementById('startUpdateButton').onclick = function (e) {
				e.preventDefault();
				this.classList.add('hidden');
				startUpdate();
			};
		}
		if(document.getElementById('retryUpdateButton')) {
			document.getElementById('retryUpdateButton').onclick = function (e) {
				e.preventDefault();
				retryUpdate();
			};
		}
		if(document.getElementById('maintenance-disable')) {
			document.getElementById('maintenance-disable').onclick = function (e) {
				e.preventDefault();
				askForMaintenance();
			};
		}

		// Show a popup when user tries to close page
		function confirmExit() {
			if (done === false && started === true) {
				return 'Update is in progress. Are you sure, you want to close?';
			}
		}
		// this is unregistered in step 12
		window.addEventListener('beforeunload', confirmExit);
	</script>
<?php endif; ?>

</html>

