<?php

/**
 * ownCloud - Updater plugin
 *
 * @author Victor Dubiniuk
 * @copyright 2012-2013 Victor Dubiniuk victor.dubiniuk@gmail.com
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 */

?>
<?php style('updater', 'updater'); ?>
<?php script('updater', '3rdparty/angular'); ?>
<?php script('updater', 'app'); ?>
<?php script('updater', 'controllers'); ?>
<?php $isNewVersionAvailable = $_['isNewVersionAvailable']?>
<div ng-app="updater" ng-init="navigation='backup'" class="updater-admin">
	<div class="section" ng-controller="updateCtrl" ng-init="hasUpdate=<?php p($isNewVersionAvailable) ?>;">
		<h2><?php p($l->t('Updates')) ?></h2>
		<p id="update-info" ng-show="hasUpdate"
		   ng-init="newVersion='<?php p($_['version']) ?>'">
			<?php print_unescaped($l->t('<strong>A new version is available: {{newVersion}}</strong>')) ?>
		</p>
		<p ng-show="!hasUpdate" ng-init="checkedAt='<?php p($_['checkedAt']) ?>'">
			<?php print_unescaped($l->t('<strong>Your version is up to date.</strong>')) ?>
			<span class="icon-info svg" title="<?php p($l->t('Checked on {{checkedAt}}')) ?>"></span>
		</p>
		<p ng-show="!hasUpdate">
			<em><?php p($l->t('Updates will be available here within a few days after the announcement.')) ?></em>
		</p>
		<div class="upd-step-title" style="display:none;">
			<ul class="track-progress" data-steps="3">
				<li class="icon-breadcrumb">
					<?php p($l->t('1. Check & Backup')) ?>
					<span class="updater-spinner icon-loading-small"></span>
				</li>
				<li class="icon-breadcrumb">
					<?php p($l->t('2. Download & Extract')) ?>
					<span class="updater-spinner icon-loading-small"></span>
				</li>
				<li>
					<?php p($l->t('3. Replace')) ?>
					<span class="updater-spinner icon-loading-small"></span>
				</li>
			</ul>
		</div>
		<div class="updater-progress" style="display:none;"><div></div></div>
		<p><button ng-click="update()" ng-show="hasUpdate" id="updater-start">
			<?php p($l->t('Update')) ?>
		</button></p>
		<p>
			<label for="release-channel"><?php p($l->t('Update channel:')) ?></label>
			<select id="release-channel" ng-change="updateChannel()"
				ng-model="releaseChannel" ng-init="releaseChannel='<?php p($_['currentChannel']) ?>'">
				<?php foreach ($_['channels'] as $channel => $channelTitle){ ?>
				<option value="<?php p($channel) ?>">
					<?php p($channelTitle) ?>
				</option>
				<?php } ?>
			</select>
			<span id="channel_save_msg"></span>
		</p>
		<p>
			<em><?php print_unescaped($l->t('You can always update to a newer version / experimental channel. But you can never downgrade to a more stable channel.')); ?></em>
		</p>
		<br>
		<div ng-controller="backupCtrl">
			<h3><?php p($l->t('Updater-Backups')) ?></h3>
			<p>
				<em><strong><?php print_unescaped($l->t('This app will only backup core files (no personal data).')); ?></strong> <?php print_unescaped($l->t('Please always do a separate backup of database and personal data before updating.')); ?></em>
			</p>
			<p ng-show="!entries.length"><?php p($l->t('No backups found.')) ?></p>
			<table ng-hide="!entries.length" class="updater-backups-table">
				<thead>
					<tr>
						<th><?php p($l->t('Backup')) ?></th>
						<th><?php p($l->t('Done on')) ?></th>
						<th><?php p($l->t('Size')) ?></th>
						<th>&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<tr ng-repeat="entry in entries">
						<td title="<?php p($l->t('Download')) ?>" class="item" ng-click="doDownload(entry.title)">{{entry.title}}</td>
						<td title="<?php p($l->t('Download')) ?>" class="item" ng-click="doDownload(entry.title)">{{entry.date}}</td>
						<td title="<?php p($l->t('Download')) ?>" class="item" ng-click="doDownload(entry.title)">{{entry.size}}</td>
						<td title="<?php p($l->t('Delete')) ?>" class="item icon-delete" ng-confirm-click="<?php p($l->t('Are you sure you want to delete {{entry.title}}')); ?>" ng-click="doDelete(entry.title)"></td>
					</tr>
				</tbody>
			</table>
			<p>
				<em>
					<?php p($l->t('Backup directory')) ?>:
					<?php p($_['backupDir']); ?>
				</em>
			</p>
		</div>
	</div>
</div>
