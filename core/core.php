<?php
/*
	#############################
	#   PITC IRC BOT FRAMEWORK  #
	#    By Thomas Edwards      #
	#COPYRIGHT TMFKSOFT 2012-13 #
	#############################
 */
 
// One line you may want to tweak.
$refresh = "5000";

// DO NOT EDIT ANY CODE IN THIS FILE, You not longer need to.
 
 // DEBUG
	$keybuff_arr = array();
 // END DEBUG
 
echo " [PITC] Loading...\n";
declare(ticks = 1);
@ini_set("memory_limit","8M"); // Ask for more memory
stream_set_blocking(STDIN, 0);
stream_set_blocking(STDOUT, 0);
error_reporting(0); // Shut PHP's Errors up so we can handle it ourselves.
set_error_handler("pitcError");
register_shutdown_function("pitcFatalError");

// Some Variables
$log_irc = true; // Log IRC to the main window as well?
$rawlog = array();
$start_stamp = time();
$rawlog = array();
$ctcps = array();
$error_log = array();
$timers = array();
$_PITC = array(); // Everything useful in this.

$_DEBUG = array(); // Used to set global vars for /dump from within functions.
$loaded = array();

if (isset($argv[1]) && $argv[1] == "-a") {
	$autoconnect = true;
}
else {
	$autoconnect = false;
}

if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
	system("stty -icanon"); // Only Linux can do this :D
	$shell_cols = exec('tput cols');
	$shell_rows = exec('tput lines');
}
else {
	$shell_cols = "80";
	$shell_rows = "24";
}

// Init some Variables.
$version = "1.5"; // Do not change this!
$append = "Bots"; // Likewise Unless you're forking

if (file_exists($_SERVER['PWD']."/core/functions.php")) {
	include($_SERVER['PWD']."/core/functions.php");
}
else {
	die("Missing Functions.php! PITC{$append} CANNOT Function without this.");
}

// Turn off echo's
//ob_start();

if (file_exists($_SERVER['PWD']."/core/config.php")) {
	include($_SERVER['PWD']."/core/config.php");
}
else {
	shutdown("ERROR Loading Config.php!\n");
}

if (file_exists($_SERVER['PWD']."/core/database.php")) {
	include($_SERVER['PWD']."/core/database.php");
}
else {
	shutdown("ERROR Loading Database.php!\n");
}

if ($cfg->exists("core")) {
	$loaded = $cfg->load("core");
	if (!$loaded) {
		die("Unable to load configuration!");
	}
	// Fill in missing values.
	$cfg->applydefault("core",$cfg->getdef("core"));
} else {
	$cfg->create("core",$cfg->getdef("core"));
	$cfg->save("core");
	$core->internal("PITC Configuration Missing!");
	$core->internal("Default configuration created please edit configs/core.cfg");
	die();
}

if ($cfg->get("core","lang") !== FALSE) {
	$language = $cfg->get("core","lang");
}
else {
	$language = "en";
}
$lng = array();
// Load English as a default language.
if (file_exists("langs/en.lng")) {
		eval(file_get_contents("langs/en.lng"));
}
// Load other languages over the top of it.
if (file_exists("langs/".$language.".lng")) {
	eval(file_get_contents("langs/".$language.".lng"));
}
else {
	if (file_exists("langs/en.lng")) {
		eval(file_get_contents("langs/en.lng"));
	}
	else {
		shutdown("Unable to load Specified Language or English Language!\n");
	}
}


// Variable Inits - LEAVE THEM ALONE!

// Windows and $scrollback are no longer used, Kept in for now.
$active = "strt"; // Current window being viewed.
$windows = array();
$scrollback['0'] = array(" = {$lng['STATUS']} {$lng['WINDOW']}. =");
$text = "";

// Channel Stuff.
$chan_modes = array();
$chan_topic = array();

if (file_exists($_SERVER['PWD']."/core/api.php")) {
	include($_SERVER['PWD']."/core/api.php");
}
else {
	shutdown("{$lng['MSNG_API']}\n");
}

// ASCI is no longer in PITCBots.


// Scripting interface/api
$api_commands = array();
$api_messages = array();
$api_actions = array();
$api_ctcps = array();
$api_joins = array();
$api_parts = array();
$api_connect = array();
$api_tick = array();
$api_raw = array();
$api_start = array();
$api_stop = array();

// PITC Variables
$_PITC['nick'] = $cfg->get("core","nick");
$_PITC['altnick'] = $cfg->get("core","altnick");
$_PITC['network'] = false;
$_PITC['server'] = false;
$_PITC['address'] = false;
$_PITC['hosts'] = array();

// Temp DB Data.
$channels = array(); // Channels im in.
$users = array(); // User info.

// START Handler/Hook
$x = 0;
while ($x != count($api_start)) {
	$args = array(); // Empty for now
	call_user_func($api_start[$x],$args);
	$x++;
}

// Init our API's
$api = new pitcapi();
$chan_api = new channel();
$timer = new timer();

// Load any core scripts.
include("colours.php");

// Load auto scripts.
if (file_exists($_SERVER['PWD']."/scripts/autoload")) {
	$scripts = explode("\n",file_get_contents($_SERVER['PWD']."/scripts/autoload"));
	for ($x=0;$x != count($scripts);$x++) {
		if ($scripts[$x] != "") {
			if ($scripts[$x][0] != ";") {
				$script = $_SERVER['PWD']."/scripts/".trim($scripts[$x]);
				if (file_exists($script)) {
					include_once($script);
					$loaded[] = $script;
				}
				else {
					$core->internal(" = {$lng['AUTO_ERROR']} '{$scripts[$x]}' {$lng['NOSUCHFILE']} =");
				}
			}
		}
	}
}
if ($_SERVER['TERM'] == "screen") {
	$core->internal(" = {$lng['SCREEN']} =");
}
$ann = data_get("http://announcements.pitc.x10.mx/?bots");
if ($ann != false && $ann->message != "none") { $core->internal(" [Announcement] ".$ann->message); }

// Connect!
$core->internal(" [INFO] {$lng['CONN_DEF']} (".$cfg->get("core","address").")");
$address = $cfg->get("core","address");
$_PITC['address'] = $address;

$address = explode(":",$address);
if (isset($address[1]) && is_numeric($address[1])) { $port = $address[1]; }
else { $port = 6667; }
if (isset($text[2])) { $password = $text[2]; } else { if ($cfg->get("core","password")) { $password = $cfg->get("core","password"); } else { $password = false; } }
$ssl = false;
if ($port[0] == "+") { $ssl = true; }

$sid = connect($cfg->get("core","nick"),$address[0],$port,$ssl,$password);
if (!$sid) {
	$core->internal(" [ERROR] {$lng['CONN_ERROR']}");
	unset($sid);
}
else {
	socket_set_nonblock($sid);
}


/* Handle being terminated */
if (function_exists('pcntl_signal')) {
	/*
	 * Mac OS X (darwin) doesn't be default come with the pcntl module bundled
	 * with it's PHP install.
	 * Load it to take advantage of Signal Features.
	*/
	///* Currently broken
	pcntl_signal(SIGTERM, "shutdown");
	pcntl_signal(SIGINT, "shutdown");
	pcntl_signal(SIGHUP, "shutdown");
	pcntl_signal(SIGUSR1, "shutdown");
	//*/
}
else {
	$core->internal(" [INFO] Your installation of PHP lacks the PCNTL Module! Load it for the Shutdown handler. =");
}

// Loaded and started
$core->internal(" [INFO] PITC{$append} v{$version} Started ".date('h:ia d-m-Y'));

while (1) {
	
	// There is NO Buffer anymore!
	// There are NO Commands anymore.
	// Handle Connection - It's all we care about now.
	if (isset($sid)) {
		$irc_dat = parse($sid);
		if ($irc_dat) {
			$data = explode("\n",$irc_dat);
			foreach ($data as $irc) {
				// Handle IRC.
				
				$irc_data = explode(" ",$irc);
				// Raw Handler.
				$x = 0;
				while ($x != count($api_raw)) {
					call_user_func($api_raw[$x],$irc_data);
					$x++;
				}
				if (isset($irc_data[1]) && $irc_data[1] == "001") {
					$cnick = $irc_data[2];
					$x = 0;
					while ($x != count($api_connect)) {
						$args = array(); // Empty for now
						call_user_func($api_connect[$x],$args);
						$x++;
					}
					$_PITC['network'] = $irc_data[6];
				}
				else if (isset($irc_data[1]) && ($irc_data[1] == "CAP" && $irc_data[4] == ":sasl")) {
					// SASL Time.
					if ($cfg->get("core","sasl") && strtolower($cfg->get("core","sasl")) == "y") {
						$core->internal(" = IRC Network supports SASL, Using SASL! =");
						pitc_raw("AUTHENTICATE PLAIN");
					}
				}
				else if ($irc_data[0] == "AUTHENTICATE" && $irc_data[1] == "+") {
					if ($cfg->get("core","sasl") && strtolower($cfg->get("core","sasl")) == "y") {
						$enc = base64_encode(chr(0).$cfg->get("core","sasluser").chr(0).$cfg->get("core","saslpass"));
						pitc_raw("AUTHENTICATE {$enc}");
					}
				}
				else if ($irc_data[0] == "AUTHENTICATE" && $irc_data[1] == "A") {
					// IRCD Aborted SASL.
					$core->internal(" = Server aborted SASL conversation! =");
					pitc_raw("CAP END");
				}
				else if ($irc_data[0] == "AUTHENTICATE" && $irc_data[1] == "F") {
					// Some form of Failiure, Not sure which. InspIRCD seems to send it.
					pitc_raw("CAP END");
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "900") {
					$core->internal(" = You are logged in via SASL! =");
					pitc_raw("CAP END");
				}
				else if (isset($irc_data[1]) && ($irc_data[1] == "904" || $irc_data[1] == "905")) {
					$core->internal(" = SASL Auth failed. Incorrect details =");
					pitc_raw("CAP END");
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "906") {
					// IRCD Aborted SASL.
					$core->internal(" = Server aborted SASL conversation! =");
					pitc_raw("CAP END");
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "903") {
					pitc_raw("CAP END");
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "353") {
					// TANGO9891  http://screenshotuploader.com/s/1307kevob
					// Userlist :3
					// 2013 - Fixed in regards to a bug causing issues. :D
					// [WIP] Userlist shows every time a mode changes etc, needs to be sorted.
					$users = array_slice($irc_data,5);
					
					$udata = array();
					foreach ($users as $usr) {
						if (trim($usr) != "") {
							$udata[] = trim($usr);
						}
					}
					$users = $udata;
					if (isset($irc_data[4])) {
						$chan = strtolower($irc_data[4]);
						$users[0] = substr($users[0],1);
						$core->internal($colors->getColoredString(" [{$chan}] [ ".implode(" ",uListSort($users))." ]","cyan"));
						$userlist[$chan] = array_merge($userlist[$chan],$users);
						$userlist[$chan] = uListSort($userlist[$chan]);
						array_values($userlist[$chan]);
					} else {
						$core->internal(" Error parsing userlist RAW. Got:");
						$core->internal(" ".trim(implode(chr(32),$irc_data)));
					}
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "324") {
					$mode = $irc_data[4];
					$chan = $irc_data[3];
					$id = $chan;
					$mode = str_split($mode);
					sort($mode);
					$mode = implode("",$mode);
					$chan_modes[$id] = $mode;
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "311") {
					// WHOIS.
					$core->internal(" [SELF] = WHOIS for {$irc_data[3]} =");
					$core->internal(" [SELF] * {$irc_data[3]} is ".implode(" ",array_slice($irc_data,4)));
				}
				else if (isset($irc_data[1]) && ($irc_data[1] == "379" || $irc_data[1] == "378")) {
					$core->internal(" [SELF] * Whois data");
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "PRIVMSG") {
					$ex = explode("!",$irc_data[0]);
					$source = substr($ex[0],1);
					$target = $irc_data[2];
					if ($target[0] == "#") {
						// Ulist Check!
						$target = strtolower($target);
						$source = get_prefix($source,$userlist[$target]);
					}
					$message = array_slice($irc_data, 3);
					$message = substr(implode(" ",$message),1);
					$isctcp = false;
					if ($target == $cnick) {
						// Check for CTCP!
						$msg_d = explode(" ",$message); // Reversing the previous, I know.
						$msg_d_lchar = strlen($msg_d[0][0])-1;
						$msg_d_lchar = $msg_d[0][$msg_d_lchar];
						if ($msg_d[0][0] == "" && $msg_d_lchar == "") {
							// CTCP!
							$ctcp = trim(trim($msg_d[0],""));
							$ctcp_data = getCtcp($ctcp);
							$core->internal($colors->getColoredString(" [".trim($source)." ".$ctcp."]","light_red"));
							if ($ctcp == "PING") {
								ctcpReply($source,$ctcp,trim($msg_d[1],""));
							}
							if ($ctcp_data) {
								ctcpReply($source,$ctcp,$ctcp_data);
							}
							$isctcp = true;
							// CTCP API
							$args = array();
							$args[] = strtolower($source);
							$args[] = $ctcp;
							$x = 0;
							while ($x != count($api_ctcps)) {
								call_user_func($api_ctcps[$x],$args);
								$x++;
							}
						}
						// Message to me.
						$wid = getWid($source);
						$win = $source;
					}
					else {
						// Message to a channel.
						$wid = getWid($target);
						$win = $target;
					}
					if (!$wid && !$isctcp) {
						// No such channel. Create it.
						$windows[] = $win;
						$wid = getWid($win);
						// Wat.
						$core->internal($colors->getColoredString(" [{$source}] = {$lng['MSG_IN']} [".$wid.":".$win."] {$lng['FROM']} ".$source." = ","cyan"));
						// Get the new id.
					}

					$words = explode(" ",$message);
					// Last Char
					$sc = implode(" ",$words);
					$length = strlen($sc);
					$lchar = $sc[$length-1];
					// Figure out if its an action or not. -.-
					if ($words[0] == "ACTION" && $lchar == "" && !$isctcp) {
						// ACTION!
						unset($words[0]);
						$words_string = trim(implode(" ",$words),"");
						// Check for Highlight!
						if (isHighlight($words_string,$cnick)) {
							// Highlight!
							$core->internal($colors->getColoredString(" [{$target}] * ".$source." ".$words_string,"yellow"));
						}
						else {
							$core->internal($colors->getColoredString(" [{$target}] * ".$source." ".$words_string,"purple"));
						}
						// API TIME!
						$args = array();
						$args['active'] = $active;
						$args['nick'] = str_replace(str_split('!~&@%+'),'',$source);
						$args['nick_mode'] = $source;
						$args['channel'] = strtolower($win);
						$args['text'] = $words_string;
						$args['text_array'] = explode(" ",$words_string);
						$x = 0;
						while ($x != count($api_actions)) {
							call_user_func($api_actions[$x],$args);
							$x++;
						}
					}
					else {
						if (!$isctcp) {
							// Message! - Check for highlight!
							//$scrollback[$wid][] = $cnick." ".$_CONFIG['nick']." ".stripos($message,$cnick)." ".stripos($message,$_CONFIG['nick']); // H/L Debug
							if (isHighlight($message,$cnick)) {
								// Highlight!
								$core->internal(" [{$target}] ".$colors->getColoredString(" <".$source."> ".$message,"yellow"));
							}
							else {
								$core->internal(" [{$target}] <".$source."> ".format_text($message));
							}
							// API TIME!
							$args = array();
							$args['nick'] = str_replace(str_split('!~&@%+'),'',$source);
							$args['nick_mode'] = $source;
							$args['channel'] = strtolower($win);
							$args['text'] = $message;
							$args['text_array'] = explode(" ",$message);
							$x = 0;
							while ($x != count($api_messages)) {
								call_user_func($api_messages[$x],$args);
								$x++;
							}
							// Done
						}
					}
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "NICK") {
					$ex = explode("!",$irc_data[0]);
					$nick = substr($ex[0],1);
					if ($irc_data[2][0] == ":") {
						$nnick = substr($irc_data[2],1);
					}
					else {
						$nnick = $irc_data[2];
					}
					if ($nick != $cnick) {
						// someone changed their nick, Lets return the shizzle for them.
						$string = $colors->getColoredString(" [{$nick}] * ".$nick." {$lng['NICK_OTHER']} ".$nnick, "green");
					}
					else {
						$string = $colors->getColoredString(" [{$nick}] * {$lng['NICK_SELF']} ".$nnick, "green");
						$cnick = $nnick;
					}
					// No more shiny code, We dont care where they're in.
					$core->internal($string);
				}
				else if (isset($irc_data[1]) && $irc_data[0] == "PING") {
					// Do nothing. We're already pinging
					// Actually why is this code even in here?
					// WELL?
					// Exactly. No one knows.
					// Yeah, welcome.
				}
				else if (isset($irc_data[1]) && $irc_data[0] == "ERROR") {
					// Lost connection!
					$message = array_slice($irc_data,1);
					$message = substr(implode(" ",$message),1);
					$core->internal(" [SELF] ".$colors->getColoredString(" = ".$message." =","blue"));
					$x = 0;
					while ($x != key($scrollback)) {
						if (isset($scrollback[$x])) {
							$core->internal($colors->getColoredString(" [SELF] = {$lng['DISCONNECTED']} ".$_PITC['address']." {$lng['RECONNECT']} =","blue"));
						}
						$x++;
					}
					unset($sid);
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "NOTICE") {
					// Got Notice!
					$dest = $irc_data[2];
					$ex = explode("!",$irc_data[0]);
					$nick = substr($ex[0],1);
					$message = array_slice($irc_data, 3);
					$message = substr(implode(" ",$message),1);
					
					// CTCP Stuff.
					$msg_d = explode(" ",$message); // Reversing the previous, I know.
					$msg_d_lchar = strlen($msg_d[count($msg_d)-1][0])-1;
					$ctcp = trim($msg_d[0],"");
					$ctcp_data = trim(implode(" ",array_slice($msg_d, 1)),"");
					$msg_d_lchar = $msg_d[0][$msg_d_lchar];
					
					if ($dest[0] == "#") {
						// Channel notice.
						$wid = getWid($dest);
						$core->internal(" [SELF] ".$colors->getColoredString(" -".$nick.":".$dest."- ".$message, "red"));
					}
					else {
						// Private notice. Forward to Status window
						if ($msg_d[0][0] == "" && $msg_d_lchar == "") {
							$core->internal(" [{$nick}] ".$colors->getColoredString(" <- [".$nick." ".$ctcp." reply]: ".$ctcp_data, "light_red"));
						}
						else {
							$core->internal(" [{$nick}] ".$colors->getColoredString(" -".$nick."- ".$message, "red"));
						}
					}
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "421") {
					// IRCD Threw an error regarding a command :o
					$message = array_slice($irc_data, 4);
					$message = substr(implode(" ",$message),1);
					$core->internal(" [SELF] ".strtoupper($irc_data[3])." ".$message);
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "404") {
					// 3 - chan
					$message = array_slice($irc_data, 4);
					$message = substr(implode(" ",$message),1);
					$scrollback[getWid($irc_data['3'])][] = $colors->getColoredString(" = ".$message." =","light_red");
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "005") {
					$core->internal(" [{$_PITC['network']}] Supressed IRCD supported commands and settings..");
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "366") {
					// Doing nothing suppresses this.
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "251") {
					$message = array_slice($irc_data, 3);
					$message = substr(implode(" ",$message),1);
					$core->internal(" [{$_PITC['network']}] ".$message);
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "422") {
					$message = array_slice($irc_data, 3);
					$message = substr(implode(" ",$message),1);
					$core->internal(" [MOTD] ".$message);
				}
				else if (isset($irc_data[1]) && ($irc_data[1] >= "252" && $irc_data[1] <= "266")) {
					// Doing nothing suppresses this.
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "TOPIC") {
					$chan = trim($irc_data[2]);
					$wid = getWid($chan);
					$ex = explode("!",$irc_data[0]);
					$nick = substr($ex[0],1);
					$message = array_slice($irc_data, 3);
					$message = substr(implode(" ",$message),1);
					$chan_topic[$wid] = $message;
					$core->internal($colors->getColoredString(" [{$chan}] * ".$nick." {$lng['TOPIC_CHANGE']} '".$message."'", "green"));
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "332") {
					// Topic.
					$chan = trim($irc_data[3]);
					$message = array_slice($irc_data, 4);
					$message = trim(substr(implode(" ",$message),1));
					$chan_topic[$chan] = $message;
					$str = " [{$chan}] * {$lng['TOPIC_IS']} '".format_text($message)."'";
					$shell_cols = exec('tput cols');
					if (!is_numeric($shell_cols)) {
						$shell_cols = 80;
					}
					if (strlen($str) > $shell_cols) {
						$str = substr($str,0,$shell_cols-13)."'...";
					}
					$core->internal($colors->getColoredString($str,"green"));
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "329") {
					// Channel creation date, No use to us.
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "333") {
					$chan = trim($irc_data[3]);
					$ex = explode("!",$irc_data[4]);
					$nick = $ex[0];
					$date = date(DATE_RFC822,trim($irc_data[5]));
					$core->internal($colors->getColoredString(" [{$chan}] * {$lng['TOPIC_BY']} ".$nick." ".$date,"green"));
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "MODE") {
					$chan = strtolower($irc_data[2]);

					$ex = explode("!",$irc_data[0]);
					$nick = substr($ex[0],1);
					$message = array_slice($irc_data,3);
					$message = implode(" ",$message);
					if ($message[0] == ":") { $message = substr($message,1); }
					if ($chan[0] == "#") {
						$nick = get_prefix($nick,$userlist[$chan]);
						// Recapture the userlist.
						$userlist[$chan] = array();
						pitc_raw("NAMES ".$chan);
						pitc_raw("MODE {$chan}");
					}
					$core->internal($colors->getColoredString(" [{$chan}] * ".$nick." {$lng['SETS_MODE']}: ".$message,"green"));
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "JOIN") {
					// Joined to a channel.
					// Add a new window.
					if ($irc_data[2][0] == ":") {
						$channel = trim(substr($irc_data[2],1));
					}
					else {
						$channel = trim($irc_data[2]);
					}
					$chan = $channel;
					$channel = strtolower($channel);
					$ex = explode("!",$irc_data[0]);
					$nick = substr($ex[0],1);
					
					// Did I join or did someone else?
					if ($nick == $cnick) {
						// I joined, Make a window.
						$windows[$channel] = $channel;
						pitc_raw("MODE {$channel}");
						$userlist[$channel] = array();
						$core->internal($colors->getColoredString(" [{$chan}] * {$lng['JOIN_SELF']} ".trim($channel),"green"));
					}
					else {
						// Someone else did.
						$core->internal($colors->getColoredString(" [{$chan}] * ".$nick." (".$ex[1].") {$lng['JOIN_OTHER']} ".$channel,"green"));
						// Recapture the userlist.
						$core->internal(" [{$chan}] Resetting the userlist in {$channel}");
						$userlist[$channel] = array();
						pitc_raw("NAMES ".$channel);
					}
					// API TIME!
					$args = array();
					$args['nick'] = $nick;
					$args['channel'] = strtolower($channel);
					$args['host'] = $ex[1];
					$x = 0;
					while ($x != count($api_joins)) {
						call_user_func($api_joins[$x],$args);
						$x++;
					}
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "PART") {
					$channel = trim($irc_data[2]);
					$ex = explode("!",$irc_data[0]);
					$nick = substr($ex[0],1);
					if ($nick != $cnick) {
						if (isset($irc_data[3])) {
							$message = array_slice($irc_data, 3);
							$message = substr(implode(" ",$message),1);
							$scrollback[$channel][] = $colors->getColoredString(" [{$channel}] * ".$nick." (".$ex[1].") {$lng['PARTED']} ".$channel." (".$message.")","green");
						}
						else {
							$scrollback[$channel][] = $colors->getColoredString(" [{$channel}] * ".$nick." (".$ex[1].") {$lng['PARTED']} ".$channel,"green");
						}
					}
					// Repopulate the Userlist.
					$userlist[$channel] = array();
					if ($nick != $cnick) {
						pitc_raw("NAMES ".$channel);
					}
					// API TIME!
					$args = array();
					$args['nick'] = $nick;
					$args['channel'] = strtolower($channel);
					$args['host'] = $ex[1];
					$args['text'] = $message;
					$args['text_array'] = explode(" ",$message);
					$x = 0;
					while ($x != count($api_joins)) {
						call_user_func($api_joins[$x],$args);
						$x++;
					}
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "KICK") {
					$chan = trim($irc_data[2]);
					$ex = explode("!",$irc_data[0]);
					$kicker = substr($ex[0],1);
					$kicked = $irc_data[3];
					if ($kicked != $cnick) {
						if (isset($irc_data[4])) {
							$message = array_slice($irc_data, 4);
							$message = substr(implode(" ",$message),1);
							$scrollback[$channel][] = $colors->getColoredString(" [{$chan}] * ".$kicked." {$lng['KICK_OTHER']} ".$kicker." (".$message.")","green");
						}
						else { // %5 chance of this ever been used. but hey still could be!
							$scrollback[$channel][] = $colors->getColoredString(" [{$chan}] * ".$kicked." {$lng['KICK_OTHER']} ".$kicker,"green");
						}
					}
					else {
						// I've been kicked.
						if (isset($irc_data[4])) {
							$message = array_slice($irc_data, 4);
							$message = substr(implode(" ",$message),1);
							$core->internal($colors->getColoredString(" [{$chan}] * ".$kicker." {$lng['KICK_SELF']} ".$chan." (".$message.")","green"));
						}
						else {
							$core->internal($colors->getColoredString(" [{$chan}] * ".$kicked." {$lng['KICK_SELF']} ".$chan,"green"));
						}
						$active = 0;
						unset($scrollback[$channel],$userlist[$channel]);
					}
				}
				else if (isset($irc_data[1]) && $irc_data[1] == "QUIT") {
					$ex = explode("!",$irc_data[0]);
					$nick = substr($ex[0],1);
					if ($nick != $cnick) {
						// Not me.
						$message = array_slice($irc_data, 2);
						$message = substr(implode(" ",$message),1);
						$string = $colors->getColoredString(" [SELF] * ".$nick." (".$ex[1].") {$lng['QUIT']} (".$message.")","blue");
						
						$matches = 0;
						foreach ($windows as $channel) {
							if ($channel[0] == "#" || $channel == $nick) {
								if ($channel[0] == "#") {
									$ison = $chan_api->ison($nick,$channel);
									if ($ison) { $userlist[$channel] = array(); pitc_raw("NAMES ".$channel); }
								}
								else {
									$ison = true;
								}
								if ($ison) {
									$wid = getwid($channel);
									$scrollback[$wid][] = $string;
									$matches++;
								}
							}
						}
						if ($matches == 0) { $scrollback[0] = $string; }
					}
				}
				else {
					$str = implode(chr(32),$irc_data);
					if (strpos($str,":")) {
						$core->internal(" [{$_PITC['network']}] ".substr($str,strpos($str,":")));
					} else {
						$message = array_slice($irc_data, 3);
						$message = implode(" ",$message);
						$core->internal(" [{$_PITC['network']}] ".$message);
					}
				}
			}
		}
	}
	// Check if any timers are being called.
	$timer->checktimers();
	//usleep($refresh);
}

function pitcError($errno, $errstr, $errfile, $errline) {
	global $active,$core;
	// Dirty fix to supress connection issues for now.
	if ($errline != 171) {
		if (!isset($core)) {
			echo "PITC PHP Error: (Line ".$errline.") [$errno] $errstr in $errfile\n";
		} else {
			$core->internal("PITC PHP Error: (Line ".$errline.") [$errno] $errstr in $errfile");
		}
	}
}
function pitcFatalError() {
	global $active,$core;
	// Dirty fix to supress connection issues for now.
	$error = error_get_last();
	if( $error !== NULL) {
		// Its a FATAL Error
		$errno   = $error["type"];
		$errfile = $error["file"];
		$errline = $error["line"];
		$errstr  = $error["message"];
		if ($errline != 171) {
			if (!isset($core)) {
				echo "PITC PHP FATAL Error: (Line ".$errline.") [$errno] $errstr in $errfile\n";
			} else {
				$core->internal("PITC PHP FATAL Error: (Line ".$errline.") [$errno] $errstr in $errfile");
			}
			shutdown("Fatal PITC Error! Please refer to your terminal.\n"); // Lets us perform vital stuff including API Calls before shutting down!
		}
	}
}
?>