<?php

traverseDirs();

function traverseDirs($path = './') {
	if ($handle = opendir($path)) {
		while (false !== ($entry = readdir($handle))) {
			if ($entry == "en-GB" || $entry == "en-US") {
				continue;
			} elseif (is_dir($path . $entry)) {
				if ($entry != "." && $entry != ".." && $entry != ".git" && $entry != ".tx") {
					//~ echo "$path$entry\n";
					traverseDirs($path . $entry . '/');
				}
			} elseif (is_file($path . $entry) && preg_match('~^.+?\.ini$~', $entry)) {
				$tmp = explode('.', $entry);
				$langTag = $tmp[0];
				
				$workFile = $path . $entry;
				$sourceFile = str_replace($langTag, 'en-GB', $workFile);
				
				$workContents = file_get_contents($workFile);
				$workStrings = @parse_ini_string($workContents);
				
				$sourceContents = file_get_contents($sourceFile);
				$sourceStrings = @parse_ini_string($sourceContents);
				
				$newContents = '';
				foreach ($sourceStrings as $key => $string) {
					if (isset($workStrings[$key])) {
						if ($workStrings[$key] != $string) {
							// Save it
							$newContents .= $key . '="' . $workStrings[$key] . '"' . "\n";
						}
					}
				}
				$newContents .= 'TMP_STRING_THAT_IS_NOT_USED="TMP_STRING_THAT_IS_NOT_USED"' . "\n";
				
				file_put_contents($workFile, $newContents);
			}
		}
		closedir($handle);
	}
}