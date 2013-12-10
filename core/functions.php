<?php
// ADDED [WIP] in comments above buggy code.

function text_split($in) {
	$len = count($in);
	$a = substr($in,0,$len/2);
	$b = substr($in,0,(0-($len/2)));
	return array($a,$b);
}
function update_hostname($nick,$host = false) {
	global $_PITC;
	$nick = strtolower($nick);
	if ($host != false) {
		$_PITC['hosts'][$nick] = $host;
	} else {
		unset($_PITC['hosts'][$nick]);
	}
}
function shutdown($message = "Shutdown",$isexec = false) {
	global $sid,$api_stop,$api,$core;
	$api->log($core->lang('CLOSING'));
	// START Handler/Hook
	$x = 0;
	while ($x != count($api_stop)) {
		$args = array(); // Empty for now
		call_user_func($api_stop[$x],$args);
		$x++;
	}
	global $cfg;
	// Save any changes to the config
	$cfg->save("core");
	if (isset($sid)) {
		system("stty sane");
		pitc_raw("QUIT :Leaving...");
		socket_close($sid);
	}
	if ($isexec) {
		die(shell_exec($message));
	}
	else {
		die($message);
	}
}

function connect($nick,$address,$port,$ssl = false,$password = false) {
	global $cfg,$domain,$sasl,$api,$core;
	if ($ssl) { $address = "ssl://".$address; }
	$core->internal(" ## Connecting to {$address} on port {$port} ##");
	$fp = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($cfg->get("core","bindip")) {
		socket_bind($fp,$cfg->get("core","bindip"));
	}
	//$fp = @fsockopen($address,$port, $errno, $errstr, 5);
	$result = @socket_connect($fp,$address,$port);
	if ($result) {
		if ($cfg->get("core","sasl")) {
			if (strtolower($cfg->get("core","sasl")) == "y") { pitc_raw("CAP REQ :sasl",$fp); }
		}
		if ($password) { pitc_raw("PASS :".$password,$fp); }
		pitc_raw("NICK ".$nick,$fp);
		$ed = explode("@",$cfg->get("core","email"));
        pitc_raw('USER '.$ed[0].' "'.$ed[1].'" "'.$address.'" :'.$cfg->get("core","realname"),$fp);
		return $fp;
	}
	else {
		$api->pecho(" = {$errstr} =");
		return false;
	}
}
function parse($rid) {
	global $core,$active,$cfg,$cnick,$rawlog;
	//echo "Handling bot with RID ".$rid."\n";
	if ($data = socket_read($rid,2048)) {
		$data = trim($data);
		$core->writeLog(true);
		flush();
		$lines = explode("\n",$data);
		foreach ($lines as $line) {
			$rawlog[] = "S: ".$line;
			$ex = explode(' ', $line);
			if ($ex[0] == "PING") {
				//$core->internal(" LINE ".__LINE__." DEBUG: PONG Reply");
				pitc_raw("PONG ".$ex[1]);
			}
			if (isset($ex[1]) && $ex[1] == "001") {
				$core->internal(" = Connected to IRC! =");
				// Ajoin!
				if ($cfg->get("core","ajoin")) {
					$chans = explode(" ",$cfg->get("core","ajoin"));
					$rawjoin = "JOIN ";
					foreach ($chans as $x => $chan) {
						if ($x != count($chans)-1) {
							$rawjoin .= "{$chan},";
						}
						else {
							$rawjoin .= $chan;
						}
					}
					$core->internal(" Attempting to automatically join {$chan}.");
					pitc_raw($rawjoin);
				}
			}
			if (isset($ex[1]) && $ex[1] == "433") {
				// Nick in use.
				$core->internal(" = Nick in use. Changing to alternate nick! =");
				$cnick = $cfg->get("core","altnick");
				pitc_raw("NICK :".$cnick);
			}
		}
	}
	return $data;
}
function pitc_raw($text,$sock = false) {
	global $sid,$rawlog,$core;
	if ($sock) { $fp = $sock; }
	else { $fp = $sid; }
	$rawlog[] = "C: {$text}";
	$core->writeLog(true);
	return socket_write($fp,"{$text}\n");
}
function load_script($file) {
	global $core;
	if (file_exists($file)) {
		$res = include($file);
		if ($res) {
			$core->internal(" = Loaded script '".$file."' = ");
		}
		else {
			$core->internal(" = Error loading script '".$file."' = - ".$res);
		}
	}
	else {
		$core->internal(" = Error loading script '".$file."' = - File does not exist.");
	}
}
function getWid($name) {
	//global $windows;
	//$wins = array_map('strtolower', $windows);
	//$id = array_search(strtolower($name), $wins);
	return strtolower($name);
}
function ctcpReply($nick,$ctcp,$text) {
	global $sid,$core,$colors;
	$core->internal($colors->getColoredString(" [".trim($nick)." ".$ctcp."] {$text}","light_red"));
	socket_write($sid,"NOTICE ".$nick." :".$ctcp." ".$text."\n");
}
function ctcp($nick,$ctcp) {
	global $sid;
	socket_write($sid,"PRIVMSG ".$nick." :".$ctcp."\n");
}
function getCtcp($ctcp) {
	global $ctcps,$version,$start_stamp,$append;
	$ctcps['VERSION'] = "PITC{$append} v".$version." by Thomas Edwards";
	$ctcps['UPTIME'] = string_duration(time(),$start_stamp);
	$ctcp = strtoupper($ctcp);
	if (isset($ctcps[$ctcp])) {
		return $ctcps[$ctcp];
	}
	else {
		return false;
	}
}
function version() {
	global $append,$version;
	return "PITC{$append} v".$version." by Thomas Edwards";
}
function ringBell() {
	global $core;
	$core->internal(chr(7));
}
function isHighlight($text,$nick) {
	if (is_array($text)) { $text = implode(" ",$text); }
	$nick = preg_quote($nick);
	return preg_match("/".$nick."/i", $text);
}
function pitcEval($text) {
	return $text;
}
function uListSort($users) {
	// Sorts all users depending on their Symbol.
	if (!is_array($users)) $users = explode(" ",$users);
	$owners = array();
	$owners_other = array(); // For '!' founders.
	$admins = array();
	$ops = array();
	$hops = array();
	$voices = array();
	$none = array();
	$x = 0;
	while ($x != count($users)) {
		$n = $users[$x];
		if ($n[0] == "~") {
			// Owner.
			$owners[] = $n;
		}
		else if ($n[0] == "!") {
			// Owner v2
			$owners_other[] = $n;
		}
		else if ($n[0] == "&") {
			// Admin
			$admins[] = $n;
		}
		else if ($n[0] == "@") {
			// Op
			$ops[] = $n;
		}
		else if ($n[0] == "%") {
			// Halfop
			$hops[] = $n;
		}
		else if ($n[0] == "+") {
			// Voice
			$voices[] = $n;
		}
		else {
			// None.
			$none[] = $n;
		}
		$x++;
	}
	natcasesort($owners);
	natcasesort($owners_other);
	natcasesort($admins);
	natcasesort($ops);
	natcasesort($hops);
	natcasesort($voices);
	natcasesort($none);
	$ulist = array_merge($owners,$owners_other,$admins,$ops,$hops,$voices,$none);
	$ulist = array_values($ulist);
	return $ulist;
}
function format_text($text) {
	$text = preg_replace('/0(.*)/is', "\033[1;37m$1\033[0m", $text); // White
	$text = preg_replace('/1(.*)/is', "\033[0;30m$1\033[0m", $text); // Black
	$text = preg_replace('/2(.*)/is', "\033[0;34m$1\033[0m", $text); // Blue
	$text = preg_replace('/3(.*)/is', "\033[0;32m$1\033[0m", $text); // Green
	$text = preg_replace('/4(.*)/is', "\033[1;31m$1\033[0m", $text); // Light Red
	$text = preg_replace('/5(.*)/is', "\033[0;31m$1\033[0m", $text); // Red
	$text = preg_replace('/6(.*)/is', "\033[0;35m$1\033[0m", $text); // Purple
	$text = preg_replace('/6(.*)/is', "\033[0;35m$1\033[0m", $text); // Purple
	return $text;
}
function string_duration($a,$b) {
	$uptime = $a - $b;
	$second = floor($uptime%60);
	$minute = floor($uptime/60%60);
	$hour = floor($uptime/3600);
	$day = floor($uptime/86400);
	$week = floor($uptime/604800);
	$month = floor($uptime/2419200);
	$year = floor($uptime/31536000);
	$uptime = "{$second}seconds";
	if ($minute) { $uptime = "{$minute}minutes " . $uptime; }
	if ($hour) { $uptime = "{$hour}hours " . $uptime; }
	if ($day) { $uptime = "{$day}days " . $uptime; }
	if ($week) { $uptime = "{$week}weeks " . $uptime; }
	if ($month) { $uptime = "{$month}months " . $uptime; }
	if ($year) { $uptime = "{$year}years " . $uptime; }
	return $uptime;
}
function ircexplode($str) {
	// Contributed by grawity
    $str = rtrim($str, "\r\n");
    $str = explode(" :", $str, 2);
    $params = explode(" ", $str[0]);
    if (count($str) > 1)
        $params[] = $str[1];
    return $params;
}
function data_get($url = false) {
	if ($url) {
		$data = file_get_contents($url);
		$array = json_decode($data);
		return $array;
	}
	else {
		return false;
	}
}
function is_connected() {
    $connected = @fsockopen("google.com",80); //website and port
    if ($connected) {
        $is_conn = true; //action when connected
        socket_close($connected);
    }
	else {
        $is_conn = false; //action in connection failure
    }
    return $is_conn;
}

function nick_tab($nicks,$text,$tab = 0) {
	// Allows you to tab a nickname.
	// Get last letter or word.
	$data = explode(" ",$text);
	$data = $data[count($data)-1];
	
	$nicknames = array();
	foreach ($nicks as $name) {
		$nicknames[] = trim(strtolower($name),"!~&@%+");
	}
	$data = strtolower($data);
	$ret = preg_grep("/^{$data}.*/", $nicknames);
	if ($ret != FALSE) {
		reset($ret);
		$key = key($ret);
		$ret = array_values($ret);
		$ret = $ret[$tab];
		echo "I found {$ret}!\n";
		return trim($nicks[$key],"!~&@%+");
	}
	else {
		return false;
	}
}
function get_prefix($nick,$nicks = array()) {
	$old = $nick;
	$nicknames = array();
	if ($nicks > 0) {
		foreach ($nicks as $name) {
			$nicknames[] = strtolower($name);
		}
		$nick = strtolower($nick);
		$ret = preg_grep("/(!|~|&|@|\%|\+|){$nick}$/", $nicknames);
		if ($ret > 0 && $ret != FALSE) {
			reset($ret);
			$key = key($ret);
			return $nicks[$key];
		}
		else {
			return $old;
		}
	}
	else {
		return $old;
	}
}
?>