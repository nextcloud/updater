<?php

$currentTag = trim(shell_exec('git describe --tags'));
exec('git diff-files --quiet', $output, $returnValue);

$dirty = $returnValue === 0 ? '' : ' dirty';


$content = '<?php

namespace NC\Updater;

class Version {
	function get() {
		return \'' . $currentTag . $dirty . '\';
	}
}
';

file_put_contents('lib/Version.php', $content);
