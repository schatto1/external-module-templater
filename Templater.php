<?php
namespace RedcapConHack\Templater;

class Templater extends \ExternalModules\AbstractExternalModule {
	function generateTemplateFromPost($twig) {
		# build $data array from $_POST array so Twig can render our files
		$hookInfo = self::getHookInfo();
		$data = [
			'className' => $_POST['className'],
			'everyPageHooks' => $_POST['everyPageHooks'],
			'namespace' => $_POST['namespace'],
			'description' => $_POST['moduleDescription'],
			'authors' => [],
			'controlCenterLinks' => [],
			'crons' => [],
			'hooks' => [],
			'projectLinks' => []
		];
		$data['initialVersion'] = empty($_POST['moduleInitVersion']) ? '0.1' : $_POST['moduleInitVersion'];
		
		// determine directory name via given class name
		// preg_match_all('/[A-Z][a-z]+/', $data['className'], $matches);
		// $dirName = ($_POST['dirName']=="") ? join('_', array_map('strtolower', $matches[0])) . '_v' . $data['initialVersion'] : $_POST['dirName'];
		
		$dirName = ($_POST['dirName']=="") ? 'module_template_v' . $data['initialVersion'] : $_POST['dirName'];
		
		# authors
		$done = false;
		$i = 1;
		while (!$done) {
			if (isset($_POST["authorsName$i"])) {
				$data['authors'][$i] = [
					'name' => $_POST["authorsName$i"],
					'email' => $_POST["authorsEmail$i"],
					'org' => $_POST["authorsOrg$i"]
				];
			} else {
				$done = true;
			}
			$i++;
		}
		
		# hooks
		foreach (array_merge($hookInfo['redcap'], $hookInfo['exmod']) as $hook) {
			if (isset($_POST[$hook['name']])) {
				array_push($data['hooks'], $hook);
			}
		}
		
		# links
		$done = false;
		$i = 1;
		while (!$done) {
			if (isset($_POST["linksName$i"])) {
				# add link to twig $data variable
				$link = [
					'name' => $_POST["linksName$i"],
					'url' => $_POST["linksUrl$i"],
					'icon' => $_POST["linksIcon$i"]
				];
				if (isset($_POST["linksNOAUTH$i"])) {
					$link['NOAUTH'] = true;
				}
				if (isset($_POST["linksControlCenterCheckbox$i"])) {
					array_push($data['controlCenterLinks'], $link);
				}
				if (isset($_POST["linksProjectCheckbox$i"])) {
					array_push($data['projectLinks'], $link);
				}
			} else {
				$done = true;
			}
			$i++;
		}
		
		# crons
		$done = false;
		$i = 1;
		while (!$done) {
			if (isset($_POST["cronsName$i"])) {
				$data['crons'][$i] = [
					'name' => $_POST["cronsName$i"],
					'desc' => $_POST["cronsDescription$i"],
					'method' => $_POST["cronsMethod$i"],
					'freq' => $_POST["cronsFrequency$i"],
					'max' => $_POST["cronsMaxRunTime$i"]
				];
			} else {
				$done = true;
			}
			$i++;
		}
		
		// // uncomment to test print a page to browser
		// header('content-type: text/plain');
		// // print_r($data);
		// echo $twig->render('class.twig', $data);
		// exit;
		
		# render necessary files
		$classFile = $twig->render('class.twig', $data);
		$configFile = $twig->render('config.twig', $data);
		$readmeFile = $twig->render('README.twig', $data);
		
		# create zip file, open it, add files, close zip, and send
		$zip = new \ZipArchive();
		$file = tempnam(EDOC_PATH,"");
		$zip->open($file, \ZipArchive::CREATE);
		$zip->addFromString($data['className'] . '.php', $classFile);
		$zip->addFromString('config.json', $configFile);
		$zip->addFromString('README.md', $readmeFile);
		
		# add method files for links and crons? (e.g., generate_template.php)
		
		# add LICENSE?
		if (isset($_POST['includeLicense']) and isset($_POST['licenseText'])){
			$zip->addFromString('LICENSE', $_POST['licenseText']);
		}
		
		$zip->close();
		$zipFileName = $dirName . '.zip';
		header("Content-disposition: attachment; filename=$zipFileName");
		header('Content-type: application/zip');
		readfile($file);
		unlink($file);
	}
	
	public static function getHookInfo(){
		# Get array of Hook methods and their attributes
		$temp = \PluginDocs::getPluginMethods(\PluginDocs::HOOKS_CLASS);
		$hooks = [
			"redcap" => [],
			"exmod" => [
				"1" => [
					"name" => "redcap_module_system_enable",
					"description" => "Triggered when a module gets enabled on Control Center.",
					"function" => "void <b>redcap_module_system_enable</b> ( <b>\$version</b> )"
				],
				"2" => [
					"name" => "redcap_module_system_disable",
					"description" => "Triggered when a module gets disabled on Control Center.",
					"function" => "void <b>redcap_module_system_disable</b> ( <b>\$version</b> )"
				],
				"3" => [
					"name" => "redcap_module_system_change_version",
					"description" => "Triggered when a module version is changed.",
					"function" => "void <b>redcap_module_system_change_version</b> ( <b>\$version, \$old_version</b> )"
				],
				"4" => [
					"name" => "redcap_module_project_enable",
					"description" => "Triggered when a module gets enabled on a specific project.",
					"function" => "void <b>redcap_module_project_enable</b> ( <b>\$version, \$project_id</b> )"
				],
				"5" => [
					"name" => "redcap_module_project_disable",
					"description" => "Triggered when a module gets disabled on a specific project.",
					"function" => "void <b>redcap_module_project_disable</b> ( <b>\$version, \$project_id</b> )"
				],
				"6" => [
					"name" => "redcap_module_configure_button_display",
					"description" => "Triggered when each enabled module defined is rendered. Return <code>null</code> if you don't want to display the Configure button and <code>true</code> to display.",
					"function" => "void <b>redcap_module_configure_button_display</b> ( <b>\$project_id</b> )"
				],
				"7" => [
					"name" => "redcap_module_link_check_display",
					"description" => "Triggered when each link defined in config.json is rendered. Override this method and return <code>null</code> if you don't want to display the link, or modify and return the <code>\$link</code> parameter as desired. This method also controls whether pages will load if users access their URLs directly.",
					"function" => "void <b>redcap_module_link_check_display</b> ( <b>\$project_id, \$link</b> )"
				],
				"8" => [
					"name" => "redcap_module_save_configuration",
					"description" => "Triggered after a module configuration is saved.",
					"function" => "void <b>redcap_module_save_configuration</b> ( <b>\$project_id</b> )"
				]
			]
		];
		
		$i = 1;
		foreach ($temp as $name => $info) {
			$hooks['redcap']["$i"] = [
				"name" => $name,
				"description" => $info['SUMMARY'],
				"function" => $info['DESCRIPTION']
			];
			$i++;
		}
		
		foreach ($hooks as $setName => $set) {
			foreach ($set as $hookName => $hook) {
				preg_match('/\(.*\)/', $hook['function'], $matches);
				$hooks[$setName][$hookName]['args'] = str_replace("void", "", $matches[0]);
			}
		}
		
		return $hooks;
	}
}