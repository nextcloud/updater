<?php $this->layout('base', ['title' => $title, 'bodyId' => 'body-settings', 'token' => $token]) ?>
<?php $this->start('inner') ?>
<header role="banner"><div id="header">
		<a href="#" id="owncloud" tabindex="1">
			<div class="logo-icon svg">
				<h1 class="hidden-visually">ownCloud</h1>
			</div>
		</a>

		<a href="#" class="header-appname-container" tabindex="2">
			<h1 class="header-appname">Updater</h1>
		</a>

		<div id="logo-claim" style="display:none;"></div>
	</div></header>
<div id="content-wrapper">
	<div id="content">
		<div class="updater-admin">
					<div id="output"></div>
					<button id="create-checkpoint">Create a checkpoint</button>
		</div>
	</div>
</div>
<?php $this->stop() ?>
