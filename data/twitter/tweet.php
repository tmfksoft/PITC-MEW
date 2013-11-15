<?php
date_default_timezone_set("Europe/London");
$r = file_get_contents("smallurl_tweetdate");
if(strtotime('+1 min', $r) < time()) { 

require_once('twitteroauth.php'); //Path to twitteroauth library
$consumerkey = "QIsKpADwjjPHC0jro0bYA";
$consumersecret = "hIFAWsDmmCDYtZ7XpPpySKu7jpHqBaMyHzQw7NFg7Y";
$accesstoken = "255093801-Pzygi658QOzXZEMic4XshkEio2WA5xZfRlILu7y9";
$accesstokensecret = "aMLHJ5eyQpXL09akq4oQDjEXl2zR8j6nLfVHe2362SpLI";

function getConnectionWithAccessToken($cons_key, $cons_secret, $oauth_token, $oauth_token_secret) {
  $connection = new TwitterOAuth($cons_key, $cons_secret, $oauth_token, $oauth_token_secret);
  return $connection;
}
$connection = getConnectionWithAccessToken($consumerkey, $consumersecret, $accesstoken, $accesstokensecret);
$tweets = $connection->get("https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=SmallURLService&count=3&include_rts=false");
file_put_contents("smallurl_tweetdate", time());
file_put_contents("smallurl_lasttweet", json_encode($tweets));
echo json_encode($tweets);
} else {
	echo file_get_contents("smallurl_lasttweet");
}
?>
