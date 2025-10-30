<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


$currentTag = trim(shell_exec('git describe --tags') ?? '');
exec('git diff-files --quiet', $output, $returnValue);

$dirty = $returnValue === 0 ? '' : ' dirty';


$content = '<?php

namespace NC\Updater;

class Version {
	function get(): string {
		return \'' . $currentTag . $dirty . '\';
	}
}
';

file_put_contents('lib/Version.php', $content);
