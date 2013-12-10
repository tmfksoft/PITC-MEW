<?php
/* #########################################
 * #       PITC-Bots AI 'mew' v1.4         #
 * #      Created by Thomas Edwards        #
 * #            Copyright 2013             #
 * # http://github.com/TMFKSOFT/PITC-MEW/  #
 * #########################################
 */
 
$admin = "Fudgie"; // CHANGEMEEE to your nickname, Case matters!
date_default_timezone_set("Europe/London"); // Set it to your or comment out for the System Time
$debug = true; // Make mew spit out verbose data?
$smallurl_key = "Put yours here!"; // Leave blank or set to false if you dont have one.
$api->log(" [MEW] Loading mew ^~^");
$api->addTextHandler("my_text");
$api->addActionHandler("my_action");
$api->addShutdownHandler("mew_quit");
$api->addConnectHandler("mew_connect");
$api->log(" [MEW] Loading Twitter API");
require_once('mew/twitter/twitteroauth.php');
$api->log(" [MEW] Loading SmallURL API");
require_once('mew/smallurl.php');
if (isset($SmallURL)) {
	$api->log(" [SmallURL] Loaded SmallURL Api!");
	if ($smallurl_key != "" && $smallurl_key != false) {
		$smres = $SmallURL->init($smallurl_key);
		if (is_array($smres)) {
			$api->log($smres['msg']);
			unset($SmallURL); // Unload.
			$api->log(" [SmallURL] Unloaded SmallURL API!");
		} else {
			$api->log(" [SmallURL] Supplied key is correct!");
		}
	}
}

$happy_smile = array("^_^","^-^","^~^",":3",":)",":D","c:");
$confused_smile = array("o_o","._.","o.o","O_O","O_o","o_O");
$sad_smile = array(":(",":'(","D;","D:",":(",":c",":/");

$levels = "";
$scripting = false;
$script = "";
$scripter = "";
$triggers = array();

// Emotion
$mood = array();
$mood['happy'] = 10;
$mood['sad'] = 0;
$mood['horny'] = 0;
$mood['lonely'] = 0;
$mood['sleepy'] = 0;

$emotion = new emote();
$mew = new mew_core();
$jk = new joke_system();

// buried items.
$buried = array();

// User data
$u_data = array();
$u = new user;

$names = array();

// Extras
$w = new weather();
$locations = array();

// Self stuffs
$self = array();
$self['asleep'] = true;
$self['overlay'] = "0";

// some DB stuff
$jokes = array();
$_TWITTER = array();

// Load the databases
load_mew_db();
// We have to override.
$self['ver'] = "1.4";

$regexes = array();
mew_reindex();

// Twitter Stuff
$_TWITTER['consumerkey'] = "changeme";
$_TWITTER['consumersecret'] = "changeme";
// Twitter stuffs
if (!isset($_TWITTER['users'])) {
	$_TWITTER['users'] = array();
}

// Yourself
// Change TMFKSOFT to your Screenname
if (!isset($_TWITTER['users']['TMFKSOFT'])) {
	$_TWITTER['users']['TMFKSOFT'] = array();
}
$_TWITTER['users']['TMFKSOFT']['accesstoken'] = "changeme";
$_TWITTER['users']['TMFKSOFT']['accesstokensecret'] = "changeme";
$_TWITTER['users']['TMFKSOFT']['channels'] = array("#mychan"); // Channels to send tweets to
$_TWITTER['users']['TMFKSOFT']['nick'] = $admin; // Your Nick

function mew_connect() {
	global $api,$timer,$self;
	if ($self['asleep']) {
		$api->raw("AWAY :Sleeping ".smile());
	}
	$timer->addTimer("60",true,"mew_sleep_check");
	//$timer->addTimer("300",true,"twitter_check"); // Every 5mins
	$timer->addTimer("120",true,"twitter_check"); // Every 2mins
}
function mew_ver() {
	global $append,$version,$self;
	return "PITC{$append} v{$version} running MEW v{$self['ver']}";
}
function mew_sleep_check() {
	global $api,$self,$emotion,$mew;
	$api->log(" [MEW] Firing mew's sleep check.");
	$chan = "lobby";
	if ($self['asleep'] == "true" && $emotion->get('sleepy') == 0) {
		$api->log(" [MEW] Wakey time!");
		$api->action("#lobby","awakens");
		$api->msg("#lobby",smile('happy',false));
		$self['asleep'] = false;
		$self['overlay'] = 0;
		$api->raw("AWAY");
	}
	else if (!$self['asleep']) {
		$chance = rand(1,100);
		if ($chance <= 25) {
			$emotion->inc('sleepy');
		}
		$chance = rand(1,100);
		if ($chance <= 25) {
			if ($emotion->get('sleepy') >= 400 && $emotion->get('sleepy') <= 420) {
				$api->action($chan,"is getting tired ".smile());
			}
		}
	}
	else {
		$chance = rand(1,100);
		if ($chance <= 25) {
			$emotion->dec('sleepy');
		}
	}
	if ($emotion->get('sleepy') >= 500) {
		if ($self['asleep'] == false) {
			$self['asleep'] = true;
			$api->action("#lobby","crawls to a corner of the channel to sleep ".smile('happy'));
		}
	}
	// Piggyback
	
	$chance = rand(1,100);
	if ($chance <= 50) {
		if ($emotion->get('horny') > 0) {
			$emotion->dec('horny');
		}
	}
	
	if ($mew->get('attention')) {
		if ($mew->get('attention_time') <= time()) {
			// Lose interest from the user.
			$api->log(" [MEW] {$mew->get('attention_nick')} hasn't spoken to me for a while..");
			$mew->set('attention',false);
			$mew->del('attention_nick');
			$mew->del('attention_time');
		}
	}
}
function my_action_old($args) {
	global $api,$emotion,$cnick,$self,$u,$regexes;
	
	$message = explode(" ",$args['text']);
	$chan = $args['channel'];
	$nick = $args['nick'];
	if (array_search($cnick,$message) && $self['asleep'] == false) {
		$act = $message[0];
		if ($act == "licks") {
			$api->action($chan,"licked ".smile('confused'));
		}
		else if ($act == "slaps") {
			$api->msg($chan,"wat no ".smile('sad'));
		}
		else if ($act == "sits" || $act == "perches") {
			if (isset($message[1]) && $message[1] == "on") {
				$api->action($chan,"sat on ".smile('confused'));
			}
		}
		else if ($act == "cuddles") {
			if ($message[1] == $cnick) {
				$api->action($chan,"cuddles ".$u->name($nick)." back ".smile());
				if (isset($message[2]) && $message[2] == "lots") {
					$emotion->inc('happy',3);
				}
			}
		}
		else if ($act == "humps") {
			if ($emotion->get('horny') >= 15) {
				$api->action($chan,"gasms ".smile('confused'));
				$emotion->inc('happy',5);
				$emotion->dec('horny',10);
			}
			else {
				$api->msg($chan,smile('happy',false)." ".smile('happy'));
				$emotion->inc('horny');
			}
		}
		else if ($act == "pokes") {
			if (isset($message[2]) && ($message[2] == "in" || $message[2] == "on")) {
				if (isset($message[3]) && ($message[3] == "the" || $message[3] == "her" || $message[3] == "a")) {
					if (isset($message[4])) {
						$bodypart = trim(strtolower($message[4]));
						// Our list of body parts
						$part_wat = array('arm','leg','foot','hair','finger','toe','thumb','hip','head','forehead','eyebrow');
						$part_tickle = array('tummy','belly','belleh','neck','ear');
						$part_ouch = array('tooth','teeth','nose','nostril','nostrils','eye','eyes');
						$part_noway = array('vagina','pussy','ass','anus','arsehole','boob','tit','boobs','tits','titties');
						
						if (array_search($bodypart,$part_wat) !== false) {
							$api->msg($chan,"what the? Stahp. ".smile('confused'));
						}
						else if (array_search($bodypart,$part_tickle) !== false) {
							$api->msg($chan,":o");
							$api->action($chan,"falls to the floor and squirms lawts");
							sleep(1);
							$api->msg($chan,"stahp ".$u->name($nick)." that tickles! ".smile());
						}
						else if (array_search($bodypart,$part_ouch) !== false) {
							$api->msg($chan,"ouch! ".smile('sad'));
							$api->msg($chan,"that hurt lots ".smile('sad',false));
						}
						else if (array_search($bodypart,$part_noway) !== false) {
							if ($emotion->get('horny') >= 13) {
								$api->msg($chan,":o!");
								$api->msg($chan,"norty ".$u->name($nick)." ".smile('happy'));
								$emotion->inc('horny',2);
							}
							else {
								$api->action($chan,"slaps ".$u->name($nick)." ".smile('sad'));
								$api->msg($chan,"norty ".$u->name($nick)." you're not allowed to do that ".smile('sad',false));
								$emotion->dec('horny',3);
							}
						}
						else {
							$api->action($chan,"doesn't know how to react to being poked in the '{$bodypart}' place ".smile('confused'));
						}
					}
					else {
						$api->msg($chan,"uhmm..? ".smile('confused'));
					}
				}
				else {
					$api->msg($chan,"uh? ".smile('confused'));
				}
			}
			else {
				echo "POKED, Running random comamnd!\n";
				$actions = array('slap','lick','bury');
				$action = $actions[array_rand($actions)];
				echo "Im going to {$action} {$nick}\n";
				$argus = $args;
				$argus['text'] = "mew ".$action." ".$nick;
				my_text($argus);
			}
		}
	}
	else if (array_search($cnick,$message) && $self['asleep'] == true) {
		$api->action($chan,"rolls over in her sleep ".smile('sad'));
	}
	else {
		echo "I werent found in that action\n";
	}
}
function my_action($args) {
	global $api,$chan_api,$scripting,$script,$scripter,$cfg,$cnick,$admin;
	$chan = $args['channel'];
	$nick = $args['nick'];
	$me = $cnick;
	$message = explode(" ", $args['text']);
	if (isset($message[1])) {
		$cmd = strtolower($message[1]);
	}
	else {
		$cmd = "";
	}
	if ($scripting && $cmd != $me && $nick == $scripter) {
		$script .= $args['text'];
	}

	$data = array();
	$data['cmd'] = $cmd;
	$data['nick'] = $nick;
	$data['chan'] = $chan;
	$data['msg'] = $message;
	$data['me'] = $cnick;
	$data['admin'] = $admin;
	$data['type'] = "action";
	mew_command($data);

}
function my_text($args) {
	global $api,$chan_api,$scripting,$script,$scripter,$cfg,$cnick,$admin;
	$chan = $args['channel'];
	$nick = $args['nick'];
	$me = "mew";
	$message = explode(" ", $args['text']);
	if (isset($message[1])) {
		$cmd = strtolower($message[1]);
	}
	else {
		$cmd = "";
	}
	if ($scripting && $cmd != $me && $nick == $scripter) {
		$script .= $args['text'];
	}

	$data = array();
	$data['cmd'] = $cmd;
	$data['nick'] = $nick;
	$data['chan'] = $chan;
	$data['msg'] = $message;
	$data['me'] = $cnick;
	$data['admin'] = $admin;
	$data['type'] = "message";
	mew_command($data);

}
function mew_command($data) {
	global $api,$chan_api,$mood,$emotion,$userlist,$buried,$names,$w,$locations,$u,$regexes,$mew,$admin,$debug;
	$cmd = $data['cmd'];
	$nick = $data['nick'];
	$chan = $data['chan'];
	$ulist = $userlist[$chan]; // o.o
	$message = $data['msg'];
	$me = $data['me'];
	$type = $data['type'];

	// Check if someone has my attention or has said my name
	$mel = strtolower($me); // Lowercase version of $me
	$first = strtolower($message[0]); // Lowercase first word.
	$last = strtolower($message[count($message)-1]);

	if ($mew->get('attention') || ($mel == $first || $mel == $last) && isset($message[1]) ) {
		if ($mew->get('attention_nick') == $nick && ($first != $mel || $last !=  $mel || !array_search($nick,$message))) {
			// First word isnt my name but the person who said it has my attention.
			$api->log(" [MEW] {$nick} has my attention and said something.");
			$scentence = implode(chr(32),$message);
		}
		else {
			$api->log(" [MEW] {$nick} doesnt have my attention but said my name.");
			if ($type == "message") {
				$scentence = implode(chr(32),array_slice($message,1));
			} else {
				$scentence = implode(chr(32),array_slice($message,0,-1));
			}
		}
		// Cycle my snippets for the trigger
		$triggered = false;
		$reg = $regexes[$type];
		foreach ($reg as $snip) {
			if ($debug) {
				$api->log(" [MEW] Checking '{$type}' regex: {$snip['regex']}");
			}
			if (preg_match("/{$snip['regex']}/i",$scentence,$matches) && !$triggered) {
			
				if ($mew->get('attention') && $mew->get('attention_nick') == $nick) {
					$mew->set('attention_time',time()+60);
				}
				$api->log(" [MEW] Found ".count($matches)." matches: '".implode(":",$matches)."'");
				eval(base64_decode($snip['code']));
				$triggered = true;
			}
		}
	}
	else if ($mel == $first && !isset($message[1])) {
		// Some only said my name
		$api->log(" [MEW] {$nick} got my devoted attention!");
		$ack = array();
		$ack[] = "mhm";
		$ack[] = "yup";
		$ack[] = "yeah";
		$ack[] = "yus";
		$ack[] = "yes";
		$ack = $ack[array_rand($ack)];
		$api->msg($chan,$ack." ".smile()."?");
		$mew->set('attention',true);
		$mew->set('attention_nick',$nick);
		$mew->set('attention_time',time()+60);
	} 
	// End of mew_command
}
function smile($emote = "happy",$update = true) {
	global $happy_smile,$sad_smile,$confused_smile,$emotion;
	$smile = array("happy"=>$happy_smile,"sad"=>$sad_smile,"confused"=>$confused_smile);
	if (($emote == "happy" || $emote == "sad") && $update) {
		if ($emote == "happy") {
			$emotion->inc('happy');
			$emotion->dec('sad',2);
		}
		else {
			$emotion->inc('sad');
			$emotion->dec('happy',2);
		}
	}
	$smile = $smile[$emote];
	$key = array_rand($smile);
	return $smile[$key];
}
class emote {
	public function inc($emot,$amount = 1) {
		global $mood;
		$mood[$emot] += $amount;
	}
	public function dec($emot,$amount = 1) {
		global $mood;
		$mood[$emot] -= $amount;
	}
	public function get($emot) {
		global $mood;
		return $mood[$emot];
	}
}
function load_mew_db() {
	global $mood,$buried,$u_data,$self,$jokes,$_TWITTER;
	$db = load_database("mood");
	if ($db) { $mood = $db; }
	$db = load_database("buried");
	if ($db) { $buried = $db; }
	
	$db = load_database("users");
	if ($db) { $u_data = $db; }
	$db = load_database("self");
	if ($db) { $self = $db; }
	
	$db = load_database("jokes");
	if ($db) { $jokes = $db; }
	
	$db = load_database("twitter");
	if ($db) { $_TWITTER = $db; }
	global $api;
	$api->log(" [MEW] Loaded Mew's databases");
}
function load_database($name) {
	global $db,$api;
	$result = $db->load("mew_{$name}");
	if ($result) {
		return $db->select("mew_{$name}");
	}
	else {
		$api->log(" [MEW] Error loading mew_{$name}.db! See console.\n");
		return false;
	}
}
function save_mew_db() {
	global $mood,$buried,$u_data,$self,$jokes,$_TWITTER,$api;
	// Save mew's databases.
	$res = array();
	
	$res[] = save_database($mood,"mood");
	$res[] = save_database($buried,"buried");
	$res[] = save_database($u_data,"users");
	$res[] = save_database($self,"self");
	$res[] = save_database($jokes,"jokes");
	$res[] = save_database($_TWITTER,"twitter");
	
	$good = 0;
	foreach ($res as $a) {
		if ($a) {
			$good++;
		}
	}
	if ($good == count($res)) {
		$api->log(" [MEW] Saved Databases.");
		return true;
	} else {
		$api->log(" [MEW] Error saving databases. Please check the console.");
		return false;
	}
}
function save_database($data,$name) {
	global $db;
	$r = $db->replace("mew_{$name}",$data);
	$r = $db->save("mew_{$name}");
	return $r;
}
class weather {
	public function get($postcode) {
		$dat = $this->data($postcode);
		return $dat;
	}
	private function data($postcode) {
		$key = "6qbf8x36kntkh3bqfyagbn9c";
		$postcode = urlencode($postcode);
		return json_decode(file_get_contents("http://api.worldweatheronline.com/free/v1/weather.ashx?key={$key}&q={$postcode}&num_of_days=1&format=json&includeLocation=yes"),true);
	}
}
function mew_quit() {
	global $api;
	$api->log(" [MEW] PITC is closing. Saving mew's data!");
	save_mew_db();
}

class user {
	public function set($user,$item,$value) {
		global $u_data;
		$u = strtolower($user);
		if (isset($u_data[$u])) {
			if (isset($u_data[$u][$item])) {
				$u_data[$u][$item] = $value;
			}
			else {
				$u_data[$u][$item] = $value;
			}
		}
		else {
			$u_data[$u] = array($item=>$value);
		}
	}
	public function get($user,$item = false) {
		global $u_data;
		$u = strtolower($user);
		if ($item) {
			if (isset($u_data[$u][$item])) {
				return $u_data[$u][$item];
			}
			else {
				return false;
			}
		}
		else {
			if (isset($u_data[$u])) {
				return $u_data[$u];
			}
			else {
				return false;
			}
		}
	
	}
	public function name($user) {
		if ($this->get($user,"name")) {
			return $this->get($user,"name");
		}
		else {
			return $user;
		}
	}
	public function find_name($user) {
		// Try and find the name for someone.
	}
}
function mew_reindex($loud = false) {
	$act = mew_index("action",$loud);
	$msg = mew_index("message",$loud);
	return array("action"=>$act,"message"=>$msg);
}
function mew_index($type,$loud = false) {
	global $regexes,$api;
	if (!isset($regexes[$type])) {
		$regexes[$type] = array();
	}
	$current = count($regexes[$type]);
	
	$api->log(" [MEW] (Re)loading mew {$type} core.");
	$file = "mew/{$type}.core.mew";
	if (file_exists($file)) {
		$core = file_get_contents($file);
	} else {
		$api->log(" [MEW] The file '{$file}' does not exist!");
		return false;
	}
	$err = array();
	libxml_use_internal_errors(true);
	$xml = @simplexml_load_string($core);
	if ($xml) {
		if ($loud) {
			$api->log(" [MEW] To turn off loud output start with false param.");
		}
		$regexes[$type] = array();
		foreach ($xml->snippet as $dat) {
			$code = array();
			$name = md5(trim($dat->func));
			
			$code['regex'] = $dat->regex;
			
			// Turn errors off for a min
			$old_error = error_reporting(0);
			$result_preg = @preg_match("/".$code['regex']."/i","Bleh!");
			error_reporting($old_error);
			if ($result_preg === FALSE) {
				$err[] = "Invalid regex for code section '".trim($dat->func)."':'{$name}'! ".preg_last_error();
			}
			$code['code'] = base64_encode($dat->code);
			
			if ($loud) {
				$api->log(" [MEW] Code name is {$name}");
			}
			$regexes[$type][$name] = $code;
		}
		
		$api->log(" [MEW] {$type} core (re)loaded with: ".count($err)." errors and ".count($regexes[$type])." snippets!");
	}
	else {
		foreach (libxml_get_errors() as $errstr) {
			$err[] = $errstr;
		}
		$api->log(" [MEW] Encountered ".count($err)." errors while (re)loading {$type} core. Aborted reload.");
		if (!$loud) {
			$mew->log(" [MEW] Run with true parameter for verbose output.");
		}
	}
	if (count($err) <= 5 && $loud) {
		foreach ($err as $num => $error) {
			$mew->log(" [MEW] ERROR #{$num}: ".$error);
		}
	}
	
	$c = $current - count($regexes[$type]);
	if ($c > 0) {
		$api->log(" [MEW] {$c} snippets were unloaded.");
	}
	else if ($c < 0) {
		$api->log(" [MEW] ".abs($c)." snippets were loaded.");
	}
	if (count($err) > 0) {
		global $mew;
		$mew->set('errors',$err);
		$api->log(" [MEW] All ".count($err)." errors have been stored is self variable 'errors'!");
	}
	return $err;
}
function dateify($stamp = null) {
	$current = time();
	$differ = $current-$stamp;
	if ($differ < 86400 && $differ > 30) {
		// Below 24hrs.
		$mins = round($differ/60);
		if ($mins > 60) { $hours = round($mins/60); } else { $hours = "0"; }
		if ($hours > 1) { $word_h = "hours"; } else { $word_h = "hour"; }
		if ($mins > 1) { $word_m = "mins"; } else { $word_m = "min"; }
		if ($hours > 0) { $mins = $mins - ($hours * 60); }

		if ($hours <= 0) {
			$scentence = "{$mins} {$word_m} ago.";
		}
		else if ($mins <= 0) {
			$scentence = "{$hours} {$word_h} ago.";		
		}
		else {
			$scentence = "{$hours} {$word_h} {$mins} {$word_m} ago.";
		}
		return $scentence;
	}
	else if ($differ <= 30) {
		// 30 Seconds ago.
		return $differ." seconds ago.";
	}
	else {
		return substr(date('F',$stamp),0,3).chr(32).date('j, Y',$stamp);
	}
}
class mew_core {
	public function debug($res = 4) {
		global $debug;
		if ($res === 4) {
			// Toggle
			if ($debug) {
				$debug = false;
			}
			else {
				$debug = true;
			}
		}
		else {
			$debug = $res;
		}
	}
	public function set($name,$val) {
		global $self;
		$self[$name] = $val;
	}
	public function get($name) {
		global $self;
		if (isset($self[$name])) {
			return $self[$name];
		}
		else {
			return false;
		}
	}
	public function del($name) {
		global $self;
		unset($self[$name]);
	}
	public function save_all() {
		save_mew_db();
	}
}
function yt_get($id) {
	global $api;
	$feed = file_get_contents("http://gdata.youtube.com/feeds/api/videos/{$id}?alt=rss&v=1");
	$dom = new DOMDocument;
	$dom->loadXML($feed);
	if (!$dom) {
		$api->log(" [MEW] Error while parsing the document");
		exit;
	}
	$xml = simplexml_import_dom($dom);

	$rdata = array();

	$rdata['title'] = $xml->title;
	$rdata['id'] = $id;
	$rdata['link'] = $xml->link;
	$rdata['author'] = $xml->author;
	$rdata['desc'] = $xml->description;
	$rdata['thumb'] = "http://i.ytimg.com/vi/{$id}/0.jpg";
	return $rdata;
}
class joke_system {
	function get($id = false) {
		global $jokes;
		if ($id) {
			if (isset($jokes[$id])) {
				$joke = $jokes[$id];
				$joke['id'] = $joke;
				return $joke;
			}
			else {
				return false;
			}
		} else {
			$id = array_rand($jokes);
			$joke = $jokes[$id];
			$joke['id'] = $id;
			return $joke;
		}
	}
	function vote_up($id) {
		global $jokes;
		if (isset($jokes[$id])) {
			$rating = $jokes[$id]['rating'];
			$rating += 1;
			$jokes[$id]['rating'] = $rating;
			return true;
		}
		else {
			return false;
		}
	}
	function vote_down($id) {
		global $jokes;
		if (isset($jokes[$id])) {
			$rating = $jokes[$id]['rating'];
			$rating -= 1;
			$jokes[$id]['rating'] = $rating;
			return true;
		}
		else {
			return false;
		}
	}
	function edit($id,$text) {
		global $jokes;
		if (isset($jokes[$id])) {
			$jokes[$id]['text'] = $text;
			return true;
		}
		else {
			return false;
		}
	}
	function add($text) {
		global $jokes;
		$jokes[] = array("rating"=>"0","text"=>$text);
		return true;
	}
}
//Twitter Stuff
function get_tweets($username) {
	global $_TWITTER;
	$connection = getConnectionWithAccessToken($_TWITTER['consumerkey'], $_TWITTER['consumersecret'], $_TWITTER['users'][$username]['accesstoken'], $_TWITTER['users'][$username]['accesstokensecret']);
	$tweets = $connection->get("https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name={$username}&count=1&include_rts=false");
	return $tweets;
}
function getConnectionWithAccessToken($cons_key, $cons_secret, $oauth_token, $oauth_token_secret) {
  $connection = new TwitterOAuth($cons_key, $cons_secret, $oauth_token, $oauth_token_secret);
  return $connection;
}
function twitter_check() {
	global $_TWITTER,$api,$SmallURL;
	$api->log(" [MEW] Checking Twitter!");
	foreach ($_TWITTER['users'] as $uname => $data) {
		$tweets = get_tweets($uname);
		if (!isset($data['lasttweet'])) {
			$last = array("id"=>false);
		} else {
			$last = $data['lasttweet'];
		}
		if ($tweets[0]->id_str != $last['id']) {
			$last['id'] = $tweets[0]->id_str;
			$last['text'] = $tweets[0]->text;
			foreach ($data['channels'] as $channel) {
				$turl = "http://twitter.com/{$uname}/status/{$last['id']}";
				if (isset($SmallURL)) {
					$url = $SmallURL->shorten($turl);
					if (!$url['result']) {
						$url = $turl;
					} else {
						$url = $url['full'];
					}
				} else { $url = $turl; }
				$api->msg($channel,"@{$uname}: ".html_entity_decode($last['text'])." {$url}");
			}
			$_TWITTER['users'][$uname]['lasttweet'] = $last;
		} else {
			$api->log(" [MEW] ".$uname.":{$data['nick']} has no new Tweets.");
		}
	}
}
