#!/usr/bin/php
<?php
/*
 Helper script and class for Transifex used by jVitals. See help for more information.
 */
 
$transifex = new JvitalsTranisfex();
$transifex->execute();

class JvitalsTranisfex {
	public $component;
	public $action;
	public $lang;
	public $langName;
	public $version;
	public $test;
	public $isValidLang;
	public $conf;
	public $help;
	
	public function __construct() {
		$options = getopt('', array('com:', 'do::', 'lang:', 'version:', 'test', 'help'));
		$this->component = isset($options['com']) ? $options['com'] : '';
		$this->action = isset($options['do']) ? $options['do'] : '';
		$this->lang = isset($options['lang']) ? $options['lang'] : '';
		$this->version = isset($options['version']) ? $options['version'] : '';
		$this->test = isset($options['test']);
		$this->help = isset($options['help']);
		if ($this->help) {
			$this->action = 'help';
			return;
		}
		
		if (!$this->component) {
			echo 'Please specify a valid Joomla extension!' . "\n\n" . $this->show_help();
			exit;
		}
		if (!in_array($this->action, array('copy', 'config', 'package', 'pull', 'create', 'help'))) {
			echo 'Please specify a valid action!' . "\n\n" . $this->show_help();
			exit;
		}
		
		$component_json = $this->component . '.json';
		if (!is_file($component_json)) {
			echo 'Invalid component or the component json doesn\'t exist!' . "\n\n" . $this->show_help();
			exit;
		}
		
		$this->conf = json_decode(file_get_contents($component_json));
		
		if (is_null($this->conf)) {
			echo 'Incorrect json syntax in ' . $this->component . '.json' . "\n\n" . $this->show_help();
			exit;
		}
		
		$this->conf->git_repo = rtrim($this->conf->git_repo, '/') . '/';
		$this->conf->transifex_working_dir = rtrim($this->conf->transifex_working_dir, '/') . '/';
		$this->conf->transifex_working_dir_gb = $this->conf->transifex_working_dir . 'en-GB/';
		$this->conf->transifex_working_dir_us = $this->conf->transifex_working_dir . 'en-US/';
		$this->conf->build_dir = rtrim($this->conf->build_dir, '/') . '/';
		
		$this->isValidLang = false;
		$this->langName = '';
		if ($this->lang) {
			$lang = $this->lang;
			foreach ($this->conf->languages as $obj) {
				if (isset($obj->$lang)) {
					$this->isValidLang = true;
					$this->langName = $obj->$lang;
					break;
				}
			}
		}
	}
	
	public function execute() {
		$action = $this->action;
		$this->$action();
	}
	
	public function copy() {
		foreach (array('frontend', 'backend') as $end) {
			$which = $end . '_files';
			foreach ($this->conf->$which as $file) {
				$fileName = basename($file);
				$strippedFileName = str_replace(array('en-GB.'), '', $fileName);
				$from = $this->conf->git_repo . $file;
				$toGB = $this->conf->transifex_working_dir_gb . $end . '/' . $fileName;
				$toUS = $this->conf->transifex_working_dir_us . $end . '/en-US.' . $strippedFileName;
				if (!$this->test) {
					copy($from, $toGB);
					copy($from, $toUS);
				} else {
					echo "$from \n\t=>$toGB\n\t=>$toUS \n";
				}
			}
		}
	}
	
	public function config() {
		$return = '';
		foreach (array('frontend', 'backend') as $end) {
			$which = $end . '_files';
			foreach ($this->conf->$which as $file) {
				$fileName = basename($file);
				$strippedResourceName = $end . '_' . str_replace(array('en-GB.', '.ini', $this->component . '_adn', $this->component . '_conv', '.sys',  'com_' . $this->component), array('', '', 'adn', 'conv', '_sys', 'component'), $fileName);
				if (!in_array($strippedResourceName, array('backend_component', 'backend_component_sys', 'frontend_component', 'frontend_component_sys'))) $strippedResourceName = str_replace('component_', '', $strippedResourceName);
				
				$strippedFileName = str_replace(array('en-GB.'), '', $fileName);
				$return .= '[' . $this->conf->transifex_projectname . '.' . $strippedResourceName . "]\n";
				$return .= 'file_filter = <lang>/' . $end . '/<lang>.' . $strippedFileName . "\n";
				$return .= 'source_file = en-GB/' . $end . '/' . $fileName . "\n";
				$return .= 'source_lang = en_GB' . "\n\n";
			}
		}
		echo $return;
	}
	
	public function package() {
		if (!$this->lang) {
			echo 'Please specify a language!' . "\n\n" . $this->show_help();
		} elseif(!$this->isValidLang) {
			echo 'Please specify a valid language!' . "\n\n" . $this->show_help();
		} elseif(!$this->version) {
			echo 'Please specify a package version!' . "\n\n" . $this->show_help();
		} else {
			$front = '';
			$back = '';
			foreach (array('frontend', 'backend') as $end) {
				$which = $end . '_files';
				foreach ($this->conf->$which as $file) {
					$fileName = basename($file);
					$strippedFileName = str_replace(array('en-GB.'), '', $fileName);
					$filename = '			<filename>' . $this->lang . '.' . $strippedFileName . '</filename>' . "\n";
					if ($end == 'frontend') {
						$front .= $filename;
					} else {
						$back .= $filename;
					}
				}
			}
			
			$manifest_xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n"
				. '<extension version="2.5" type="file" method="upgrade">' . "\n"
				. '	<name>' . $this->conf->description . ' ' . $this->lang . '</name>' . "\n"
				. '	<version>' . $this->version . '</version>' . "\n"
				. '	<creationDate>' . date('Y-m-d') . '</creationDate>' . "\n"
				. '	<author>' . $this->conf->author . '</author>' . "\n"
				. '	<authorEmail>' . $this->conf->author_email . '</authorEmail>' . "\n"
				. '	<authorUrl>' . $this->conf->author_url . '</authorUrl>' . "\n"
				. '	<copyright>' . $this->conf->copyright . '</copyright>' . "\n"
				. '	<license>' . $this->conf->license . '</license>' . "\n"
				. '	<description>' . $this->conf->description . ' (' . $this->langName . ')</description>' . "\n"
				. '	<fileset>' . "\n"
				. '		<files folder="frontend" target="language/' . $this->lang . '">' . "\n"
				. rtrim($front) . "\n"
				. '		</files>' . "\n"
				. '		<files folder="backend" target="administrator/language/' . $this->lang . '">' . "\n"
				. rtrim($back) . "\n"
				. '		</files>' . "\n"
				. '	</fileset>' . "\n"
				. '</extension>';
			$manifest_file = $this->conf->transifex_working_dir . $this->lang . '/manifest.xml';
			file_put_contents($manifest_file, $manifest_xml);
			
			$zipname = $this->lang . '.languagepack.com_' . $this->conf->component . '_' . $this->version . '.zip';
			exec('cd ' . $this->conf->transifex_working_dir . $this->lang . ' && zip -r -q ' . $this->conf->build_dir . $zipname . ' *');
			unlink($manifest_file);
		}
	}
	
	public function pull() {
		if (!$this->lang) {
			echo 'Please specify a language!' . "\n\n" . $this->show_help();
		} elseif(!$this->isValidLang) {
			echo 'Please specify a valid language!' . "\n\n" . $this->show_help();
		} else {
			exec('cd ' . $this->conf->transifex_working_dir . ' && tx pull -l ' . $this->lang, $output);
			echo implode("\n" , $output) . "\n";
		}
	}
	
	public function createResource() {
		$header = ';;;;' . "\n"
			. '; @package		' . $this->conf->name  . "\n"
			. '; @version		' . $this->conf->header_version . "\n"
			. '; @date			' . $this->conf->header_date . "\n"
			. '; @copyright	' . $this->conf->copyright . "\n"
			. '; @license    	' . $this->conf->license . "\n"
			. '; @link     	' . $this->conf->author_url . "\n"
			. ';;;;' . "\n\n"
			. '; Note : All ini files need to be saved as UTF-8' . "\n\n";
		foreach ($this->conf->languages as $language) {
			$language = (array)$language;
			$lang_code = key($language);
			foreach (array('frontend', 'backend') as $end) {
				$which = $end . '_files';
				foreach ($this->conf->$which as $file) {
					$fileName = basename($file);
					$strippedFileName = str_replace(array('en-GB.'), '', $fileName);
					$new_file = $this->conf->transifex_working_dir . $lang_code . '/' . $end . '/' . $lang_code . '.' . $strippedFileName;
					if (!is_file($new_file)) {
						if ($this->test) {
							echo $new_file . "\n";
						} else {
							file_put_contents($new_file, $header);
						}
					}
				}
			}
		}
	}
	
	public function show_help() {
		$return = 'Usage: php transifex.php [OPTIONS]' . "\n\n";
		$return .= '  --com=component   specify component as in com_component' . "\n";
		$return .= '  --do=action       specify action (copy, config, package, pull)' . "\n";
		$return .= '  --lang=lang       specify lang when action=package' . "\n";
		$return .= '  --version=ver     version of the zip (only for --do=package)' . "\n";
		$return .= '  --help            displays this help message' . "\n";
		$return .= '  --test            test run' . "\n";
		$return .= "\n";
		$return .= 'Available actions:' . "\n";
		$return .= '  copy              copy source files (en-GB) from git repo to transifex repo in order to push them to transifex' . "\n";
		$return .= '  config            display resource blocks for each language file the way they must be added to .tx/config ' . "\n";
		$return .= '  package           create a Joomla package for the given language. You might want to first run the pull action to have the latest translations' . "\n";
		$return .= '  pull              pull the latest translations for the given language' . "\n";
		$return .= "\n";
		$return .= 'Component configuration files (e.g agorapro.json):' . "\n";
		$return .= '  transifex_projectname    name of the project on transifex' . "\n";
		$return .= '  git_repo                 local path to the working copy of the component repo' . "\n";
		$return .= '  transifex_working_dir    local path to the transifex working dir for the project (.tx/config file is expected)' . "\n";
		$return .= '  build_dir                local path to a directory where the zip package will be created' . "\n";
		$return .= "\n";
		$return .= 'Configuration files need to be in thedirectory where the script is invoked.' . "\n";
		$return .= "\n";
		return $return;
	}
	
	public function help() {
		echo $this->show_help();
	}
}

?>

