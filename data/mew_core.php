:regex weather \bweather (?:(?:in|at|for) )?(\S[^?!.]+)[?.!]*$
:regex gender my gender is (male|female)
:regex gender_check (what is|what's|whats) (my|your) gender[?.]*
:regex hello (hi|hai|hey|sup|gday)(?:\\?)
:regex eval eval
:regex self (im|i am|i'm) (male|female|[^.*]+)

:snippet self {
	if ($matches[2] == "male" || $matches[2] == "female") {
		$u->set($nick,"gender",$matches[2]);
		$api->msg($chan,"gotcha {$u->name($nick)}! ill remember that for later on ".smile());
	}
	else {
		$u->set($nick,"name",$matches[2]);
		$api->msg($chan,"okay {$u->name($nick)}! ".smile());
	}
	count($matches);
	var_dump($matches);
	foreach ($lol as $lol) {
	
	}
	if ($you <= 5) {
	
	}
:snippet self }

:snippet eval {
	if ($nick == "Fudgie") {
		$api->msg($chan,"Okay ".smile());
		$code = implode(" ",array_slice($message,2));
		$api->log("EVALUATING: ".$code);
		eval($code);
	}
:snippet eval }

:snippet hello {
	$api->msg($chan,$matches[1].chr(32).$u->name($nick).chr(32).smile());
:snippet hello }


:snippet gender {
	$api->msg($chan,"oh okay {$u->name($nick)} ".smile());
	$u->set($nick,"gender",$message[4]);
:snippet gender }

:snippet gender_check {
	if ($matches[2] == "your") {
		$api->msg($chan,$u->name($nick)." im female".chr(32).smile());
	}
	else {
		$api->msg($chan,$u->name($nick)." you're ".$u->get($nick,"gender").chr(32).smile());
	}
:snippet gender_check }

:snippet weather {
	if ($matches[1] == "me") {
		$loc = $u->get($nick,"location");
	}
	else {
		$loc = $matches[1];
	}
	$api->msg($chan,"one second {$u->name($nick)}! im looking for the weather in {$loc} now ".smile());
	$weather = $w->get($loc);
	if (isset($weather['data']['error'])) {
		$api->msg($chan,"there was an error checking the weather for {$loc}! ".smile('sad'));
	}
	else {
		$api->msg($chan,"the weather in {$weather['data']['nearest_area'][0]['areaName'][0]['value']}, {$weather['data']['nearest_area'][0]['country'][0]['value']} is currently {$weather['data']['current_condition'][0]['weatherDesc'][0]['value']} ".smile());
	}
:snippet weather }