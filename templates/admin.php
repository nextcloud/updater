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
<?php $isNewVersionAvailable = $_['isNewVersionAvailable']?>
<div ng-app="updater" ng-init="navigation='backup'" class="updater-admin">
	<div class="section" ng-controller="updateCtrl">
		<h2><?php p($l->t('Updates')) ?></h2>
		<p id="update-info" ng-show="<?php p($isNewVersionAvailable) ?>">
			<?php print_unescaped($l->t('<strong>A new version is available: %s</strong>', array($_['version']))) ?>
		</p>
		<p ng-show="<?php p(!$isNewVersionAvailable) ?>">
			<?php print_unescaped($l->t('<strong>Up to date.</strong> <em>Checked on %s</em>', array($_['checkedAt']))) ?>
		</p>
		<div id="upd-step-title" style="display:none;">
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
		<button ng-click="update()" ng-show="<?php p($isNewVersionAvailable) ?>" id="updater-start">
			<?php p($l->t('Update')) ?>
		</button>
	</div>
	<div class="section" ng-controller="backupCtrl">
		<h2><?php p($l->t('Updater-Backups')) ?></h2>
		<div class="updater-update">
			<h3><?php p($l->t('Note')); ?></h3>
			<p>
				<?php print_unescaped($l->t('Here you can find backups of the core of your ownCloud <strong>(excluding your data)</strong> after starting an update to a newer version.')); ?>
			</p>
			<p>	
				<?php print_unescaped($l->t('<strong>Please always backup your data separately before updating!</strong>')); ?>
			</p>
		</div>
		<p>
			<?php p($l->t('Backup directory')) ?>:
			<?php p(\OCA\Updater\App::getBackupBase()); ?>
		</p>
		<p ng-show="!entries.length"><?php p($l->t('No backups found.')) ?></p>
		<table ng-hide="!entries.length" class="updater-backups-table">
			<thead>
				<tr>
					<th>&nbsp;</th>
					<th><?php p($l->t('Backup')) ?></th>
					<th><?php p($l->t('Done on')) ?></th>
					<th><?php p($l->t('Size')) ?></th>
				</tr>
			</thead>
			<tbody>
				<tr ng-repeat="entry in entries">
					<td title="<?php p($l->t('Delete')) ?>" class="item icon-delete" ng-confirm-click="<?php p($l->t('Are you sure you want to delete {{entry.title}}')); ?>" ng-click="doDelete(entry.title)"></td>
					<td title="<?php p($l->t('Download')) ?>" class="item" ng-click="doDownload(entry.title)">{{entry.title}}</td>
					<td title="<?php p($l->t('Download')) ?>" class="item" ng-click="doDownload(entry.title)">{{entry.date}}</td>
					<td title="<?php p($l->t('Download')) ?>" class="item" ng-click="doDownload(entry.title)">{{entry.size}}</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
