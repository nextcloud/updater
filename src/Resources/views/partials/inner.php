<script src="<?=$this->uri() . '/pub/' . $this->asset('js/main.js')?>"></script>
<header role="banner"><div id="header">
		<a href="#" id="owncloud" tabindex="1">
			<div class="logo-icon svg">
				<h1 class="hidden-visually">ownCloud</h1>
			</div>
		</a>

		<a href="#" class="header-appname-container" tabindex="2">
			<h1 class="header-appname">Updater <?= $this->e($version) ?></h1>
		</a>

		<div id="logo-claim" style="display:none;"></div>
	</div></header>
<div id="content-wrapper">
	<div id="content">

		<div id="app-navigation">
			<ul>
				<li><a href="#progress">Upgrade</a></li>
				<li><a href="#backup">Backups</a></li>
			</ul>
		</div>
		<div id="app-content">
			<div id="error" class="section hidden"></div>
			<div id="output" class="section hidden"></div>

			<ul id="progress" class="section">
				<li id="step-init" class="step icon-loading current-step">
					<h3>Initializing</h3>
					<div class="output hidden"></div>
				</li>
				<li id="step-check" class="step">
					<h3>Checking system</h3>
					<div class="output hidden"></div>
				</li>
				<li id="step-checkpoint" class="step">
					<h3>Creating a checkpoint</h3>
					<div class="output hidden"></div>
				</li>
				<li id="step-download" class="step">
					<h3>Downloading</h3>
					<div class="output hidden"></div>
				</li>
				<li id="step-coreupgrade" class="step">
					<h3>Upgrading core</h3>
					<div class="output hidden"></div>
				</li>
				<li id="step-appupgrade" class="step">
					<h3>Upgrading apps</h3>
					<div class="output hidden"></div>
				</li>
				<li id="step-finalize" class="step">
					<h3>Finishing the upgrade</h3>
					<div class="output hidden"></div>
				</li>
				<li id="step-done" class="step">
					<h3>Done</h3>
					<div class="output hidden"></div>
				</li>
			</ul>

			<div id="backup" class="section">
				<h2>This app will only backup core files (no personal data).</h2>
				<p>Please always do a separate backup of database and personal data before updating.</p>
				<table class="updater-backups-table">
					<thead>
						<tr>
							<th>Backup</th>
							<th>Done on</th>
							<th>Size</th>
							<th>&nbsp;</th>
						</tr>
					</thead>
					<tbody>
						<tr class="template">
							<td class="item"></td>
							<td class="item"></td>
							<td class="item"></td>
							<td class="item"></td>
						</tr>
						<?php foreach ($checkpoints as $checkpoint){ ?>
						<tr>
							<td class="item"><?= $this->e($checkpoint) ?></td>
							<td class="item"></td>
							<td class="item"></td>
							<td class="item"></td>
						</tr>
						<?php } ?>
					</tbody>
				</table>
				<button id="create-checkpoint">Create a checkpoint</button>
			</div>
		</div>
	</div>
</div>
