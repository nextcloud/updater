<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace NC\Updater\Tests;

use NC\Updater\Updater;
use PHPUnit\Framework\TestCase;

class UpdaterTest extends TestCase {
	private string $tempDir = '';
	private string $dataDir = '';
	private string $configDir = '';

	protected function setUp(): void {
		$this->tempDir = sys_get_temp_dir() . '/nextcloud-updater-test-' . uniqid();
		$this->dataDir = sys_get_temp_dir() . '/nextcloud-updater-data-' . uniqid();
		$this->configDir = sys_get_temp_dir() . '/nextcloud-config-test-' . uniqid();

		mkdir($this->tempDir . '/updater', 0755, true);
		mkdir($this->dataDir, 0755, true);
		mkdir($this->configDir, 0755, true);

		// Write a minimal config.php with required keys
		file_put_contents($this->configDir . '/config.php', '<?php $CONFIG = [' .
			'"datadirectory" => "' . $this->dataDir . '",' .
			'"version" => "30.0.0.1",' .
			'"instanceid" => "testid",' .
		'];');

		putenv('NEXTCLOUD_CONFIG_DIR=' . $this->configDir);
	}

	protected function tearDown(): void {
		putenv('NEXTCLOUD_CONFIG_DIR=');

		$this->removeDirectory($this->configDir);
		$this->removeDirectory($this->dataDir);
		$this->removeDirectory($this->tempDir);
	}

	private function removeDirectory(string $dir): void {
		if ($dir === '' || !is_dir($dir)) {
			return;
		}
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($items as $item) {
			if ($item->isDir()) {
				rmdir($item->getRealPath());
			} else {
				unlink($item->getRealPath());
			}
		}
		rmdir($dir);
	}

	private function makeUpdater(): Updater {
		return new Updater($this->tempDir . '/updater');
	}

	// -------------------------------------------------------------------------
	// buildPath() unit tests
	// -------------------------------------------------------------------------

	public function testBuildPathWithSimpleSuffix(): void {
		$updater = $this->makeUpdater();
		$this->assertSame($this->tempDir . '/config/config.php', $updater->buildPath('config/config.php'));
	}

	public function testBuildPathWithLeadingSlash(): void {
		$updater = $this->makeUpdater();
		// A suffix with a leading slash must produce the same result as without
		$this->assertSame(
			$updater->buildPath('config/config.php'),
			$updater->buildPath('/config/config.php')
		);
	}

	public function testBuildPathWithMultipleLeadingSlashes(): void {
		$updater = $this->makeUpdater();
		// Multiple leading slashes must all be stripped
		$this->assertSame(
			$updater->buildPath('config/config.php'),
			$updater->buildPath('///config/config.php')
		);
	}

	public function testBuildPathResultHasNoDoubleSlash(): void {
		$updater = $this->makeUpdater();
		$this->assertStringNotContainsString('//', $updater->buildPath('/some/path'));
	}

	public function testBuildPathPreservesRelativePathStructure(): void {
		$updater = $this->makeUpdater();
		$this->assertSame($this->tempDir . '/apps/myapp', $updater->buildPath('apps/myapp'));
	}

	// -------------------------------------------------------------------------
	// Constructor validation tests
	// -------------------------------------------------------------------------

	public function testConstructorThrowsOnEmptyBaseDir(): void {
		putenv('NEXTCLOUD_CONFIG_DIR=');
		$this->expectException(\Exception::class);
		new Updater('');
	}

	public function testConstructorThrowsOnInvalidBaseDir(): void {
		$this->expectException(\Exception::class);
		// Use a path whose parent doesn't exist
		new Updater('/nonexistent/path/that/does/not/exist/updater');
	}

	// -------------------------------------------------------------------------
	// Regression tests for issue #711
	// https://github.com/nextcloud/updater/issues/711
	//
	// The bug: createBackup() used explode($nextcloudDir, $absolutePath)[1]
	// to extract the relative path.  When the install-directory name appears
	// anywhere inside the file path (e.g. Nextcloud installed at /nextcloud
	// with a file called nextcloud.html), explode() splits on EVERY occurrence
	// of the delimiter, returning a directory instead of the full file path.
	// PHP's copy() then fails with "cannot be a directory".
	//
	// The fix: ltrim(substr($absolutePath, strlen($nextcloudDir)), '/')
	// -------------------------------------------------------------------------

	/**
	 * Pure logic regression: shows exactly why explode() was wrong and that
	 * the substr() replacement is correct.
	 */
	public function testRelativePathExtractionForIssue711(): void {
		// Simulate Nextcloud installed at /nextcloud (as in the bug report)
		$installDir = '/nextcloud';
		// File deep inside core/doc with the same name as the install dir
		$filePath = '/nextcloud/core/doc/admin/configuration_files/external_storage/nextcloud.html';

		// Old (buggy) approach: explode splits on ALL occurrences of $installDir.
		// '/nextcloud' appears at the start AND inside '.../external_storage/nextcloud.html'
		// (because '/nextcloud.html' begins with '/nextcloud').
		$oldRelativePath = explode($installDir, $filePath)[1];
		$this->assertSame(
			'/core/doc/admin/configuration_files/external_storage',
			$oldRelativePath,
			'The old explode() approach incorrectly returns a directory path'
		);
		$this->assertStringNotContainsString('nextcloud.html', $oldRelativePath);

		// New (fixed) approach: substr() always takes exactly the right suffix.
		$newRelativePath = ltrim(substr($filePath, strlen($installDir)), '/');
		$this->assertSame(
			'core/doc/admin/configuration_files/external_storage/nextcloud.html',
			$newRelativePath,
			'The substr() approach correctly returns the full relative file path'
		);
	}

	/**
	 * Integration regression: createBackup() must copy a deeply nested file
	 * that has the install-directory name as part of its own name, without
	 * throwing and without producing a double-slash in the destination path.
	 *
	 * @see https://github.com/nextcloud/updater/issues/711
	 */
	public function testCreateBackupCopiesNestedFilesCorrectly(): void {
		// Reproduce the directory structure from the bug report:
		// core/doc/admin/configuration_files/external_storage/nextcloud.html
		$nestedDir = $this->tempDir . '/core/doc/admin/configuration_files/external_storage';
		mkdir($nestedDir, 0755, true);
		file_put_contents($nestedDir . '/nextcloud.html', '<html>nextcloud docs</html>');

		$updater = $this->makeUpdater();
		// Must not throw
		$updater->createBackup();

		// Find the backup dir (contains a timestamp so we use glob)
		$backupPattern = $this->dataDir . '/updater-testid/backups/nextcloud-30.0.0.1-*';
		$backupDirs = glob($backupPattern, GLOB_ONLYDIR);
		$this->assertCount(1, $backupDirs, 'Expected exactly one backup directory');

		// Backup folder ends with '/' by design; build the expected file path
		$backedUpFile = rtrim($backupDirs[0], '/') . '/core/doc/admin/configuration_files/external_storage/nextcloud.html';

		$this->assertFileExists($backedUpFile, 'Backed-up file must exist at the correct nested path');
		$this->assertStringNotContainsString('//', $backedUpFile, 'Backup path must not contain double slashes');
		$this->assertSame('<html>nextcloud docs</html>', file_get_contents($backedUpFile));
	}
}
