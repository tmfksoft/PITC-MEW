<?php
function run_config() {
	global $windows,$scrollback,$active;
	if (file_exists($_SERVER['PWD']."/core/config.cfg")) { $default = load_config(); } else { $default = false; }
	// Load Config script.
	die("PITCBots is not Configured!\n");
}
function load_config() {
	global $core;
	if (file_exists($_SERVER['PWD']."/core/config.cfg")) {
		$_CONFIG = explode("\n",file_get_contents($_SERVER['PWD']."/core/config.cfg"));
		$x = 0;
		while($x != count($_CONFIG)) {
			$data = explode("=",$_CONFIG[$x]);
			$_CONFIG[$data[0]] = trim(urldecode($data[1]));
			unset($_CONFIG[$x]);
			$x++;
		}
		return $_CONFIG;
	}
	else {
		$core->internal("Error Loading config!");
		return false;
	}
}
function save_config($array) {
	$config = array();
	foreach ($array as $x => $data) { $config[] = $x."=".urlencode($data); }
	file_put_contents($_SERVER['PWD']."/core/config.cfg",implode("\n",$config));
	return true;
}
?>