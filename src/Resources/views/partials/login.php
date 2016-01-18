<?php $this->layout('base', ['title' => $title, 'token'=>$token]) ?>

<?php $this->start('login') ?>
		<div class="wrapper">
			<div class="v-align">
				<header role="banner">
					<div id="header">
						<div class="logo svg">
							<h1 class="hidden-visually">ownCloud</h1>
						</div>
						<div id="logo-claim" style="display:none;"></div>
					</div>
				</header>
				<form method="post" name="login">
					<fieldset>
						<div id="message" class="hidden">
							<img class="float-spinner" alt=""
								 src="pub/<?=$this->asset('img/loading-dark.gif')?>">
							<span id="messageText"></span>
							<!-- the following div ensures that the spinner is always inside the #message div -->
							<div style="clear: both;"></div>
						</div>
						<p class="grouptop">
							<input type="text" name="user" id="user"
								   placeholder="Username"
								   value="<?=$this->e($username)?>"
								   autofocus				autocomplete="on" autocapitalize="off" autocorrect="off" required>
							<label for="user" class="infield">Username</label>
						</p>

						<p class="groupbottom">
							<input type="password" name="password" id="password" value="<?=$this->e($password)?>"
								   placeholder="Password"
								   autocomplete="on" autocapitalize="off" autocorrect="off" required>
							<label for="password" class="infield">Password</label>
							<input type="submit" id="submit" class="login primary icon-confirm svg" title="Log in" value="" disabled="disabled"/>
						</p>
						<input type="hidden" name="requesttoken" value="<?=$this->e($token)?>">
					</fieldset>
				</form>
				<div class="push"></div><!-- for sticky footer -->
			</div>
		</div>
		<footer role="contentinfo">
			<p class="info">
				<a href="https://owncloud.org" target="_blank" rel="noreferrer">ownCloud</a> – web services under your control			</p>
		</footer>
<?php $this->stop() ?>
