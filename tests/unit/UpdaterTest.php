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
	private string $configDir = '';

	protected function setUp(): void {
		$this->tempDir = sys_get_temp_dir() . '/nextcloud-updater-test-' . uniqid();
		$this->configDir = sys_get_temp_dir() . '/nextcloud-config-test-' . uniqid();

		mkdir($this->tempDir . '/updater', 0755, true);
		mkdir($this->configDir, 0755, true);

		// Write a minimal config.php with required keys
		file_put_contents($this->configDir . '/config.php', '<?php $CONFIG = [' .
			'"datadirectory" => "/tmp/nc-test-data",' .
			'"version" => "30.0.0.1",' .
			'"instanceid" => "testid",' .
		'];');

		putenv('NEXTCLOUD_CONFIG_DIR=' . $this->configDir);
	}

	protected function tearDown(): void {
		putenv('NEXTCLOUD_CONFIG_DIR=');

		// Clean up temp directories
		if ($this->configDir !== '') {
			@unlink($this->configDir . '/config.php');
			@rmdir($this->configDir);
		}
		if ($this->tempDir !== '') {
			@rmdir($this->tempDir . '/updater');
			@rmdir($this->tempDir);
		}
	}

	private function makeUpdater(): Updater {
		return new Updater($this->tempDir . '/updater');
	}

	public function testBuildPathWithSimpleSuffix(): void {
		$updater = $this->makeUpdater();
		$this->assertSame($this->tempDir . '/config/config.php', $updater->buildPath('config/config.php'));
	}

	public function testBuildPathWithLeadingSlash(): void {
		$updater = $this->makeUpdater();
		// A suffix with a leading slash should produce the same result as without
		$this->assertSame(
			$updater->buildPath('config/config.php'),
			$updater->buildPath('/config/config.php')
		);
	}

	public function testBuildPathWithMultipleLeadingSlashes(): void {
		$updater = $this->makeUpdater();
		// Multiple leading slashes should be stripped
		$this->assertSame(
			$updater->buildPath('config/config.php'),
			$updater->buildPath('///config/config.php')
		);
	}

	public function testBuildPathResultHasNoDoubleSlash(): void {
		$updater = $this->makeUpdater();
		$result = $updater->buildPath('/some/path');
		$this->assertStringNotContainsString('//', $result);
	}

	public function testBuildPathPreservesRelativePathStructure(): void {
		$updater = $this->makeUpdater();
		$this->assertSame($this->tempDir . '/apps/myapp', $updater->buildPath('apps/myapp'));
	}

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
}
