<?php
/*
	*	"This code is not a code of honour... no highly esteemed code is commemorated here... nothing valued is here."
	*	"What is here is dangerous and repulsive to us. This message is a warning about danger."
	*	This is a rudimentary, single-file, low complexity, minimum functionality, ActivityPub server.
	*	For educational purposes only.
	*	The Server produces an Actor who can be followed.
	*	The Actor can send messages to followers.
	*	The message can have linkable URls, hashtags, and mentions.
	*	An image and alt text can be attached to the message.
	*	The Server saves logs about requests it receives and sends.
	*	This code is NOT suitable for production use.
	*	SPDX-License-Identifier: AGPL-3.0-or-later
	*	This code is also "licenced" under CRAPL v0 - https://matt.might.net/articles/crapl/
	*	"Any appearance of design in the Program is purely coincidental and should not in any way be mistaken for evidence of thoughtful software construction."
	*	For more information, please re-read.
	*/

//	Preamble: Set your details here
//	This is where you set up your account's name and bio.
//	You also need to provide a public/private keypair.
//	The posting endpoint is protected with a password that also needs to be set here.

//	Set up the Actor's information here, or in the .env file
$env = parse_ini_file('.env');
//	Edit these:
$username = rawurlencode($env["USERNAME"]);	//	Type the @ username that you want. Do not include an "@". 
$realName = $env["REALNAME"];	//	This is the user's "real" name.
$summary  = $env["SUMMARY"];	//	This is the bio of your user.

//	Generate locally or from https://cryptotools.net/rsagen
//	Newlines must be replaced with "\n"
$key_private = str_replace('\n', "\n", $env["KEY_PRIVATE"]);
$key_public  = str_replace('\n', "\n", $env["KEY_PUBLIC"]);

//	Password for sending messages
$password = $env["PASSWORD"];

/** No need to edit anything below here. But please go exploring! **/

//	Internal data
$server   = $_SERVER["SERVER_NAME"];	//	Do not change this!

//	Some requests require a User-Agent string.
define("USERAGENT", "activitybot-single-php-file/0.0");

//	Set up where to save logs, posts, and images.
//	You can change these directories to something more suitable if you like.
$data = "data";
$directories = array(
	"inbox"      => "{$data}/inbox",
	"followers"  => "{$data}/followers",
	"following"  => "{$data}/following",
	"logs"       => "{$data}/logs",
	"posts"      => "posts",
	"images"     => "images",
);
//	Create the directories if they don't already exist.
foreach ($directories as $directory) {
	if (!is_dir($directory)) {
		mkdir($data);
		mkdir($directory);
	}
}

// Get the information sent to this server
$input       = file_get_contents("php://input");
$body        = json_decode($input, true);
$bodyData    = print_r($body,     true);

//	If the root has been requested, manually set the path to `/`
!empty($_GET["path"]) ? $path = $_GET["path"] : $path = "/";

//	Routing:
//	The .htaccess changes /whatever to /?path=whatever
//	This runs the function of the path requested.
switch ($path) {
	case "/.well-known/webfinger":
		webfinger();   //	Mandatory. Static.
	case "/.well-known/nodeinfo":
		wk_nodeinfo(); //	Optional. Static.	
	case "/nodeinfo/2.1":
		nodeinfo();    //	Optional. Static.
	case "/" . rawurldecode($username):
	case "/@" . rawurldecode($username):	//	Some software assumes usernames start with an `@`
		username();    //	Mandatory. Static
	case "/following":
		following();   //	Mandatory. Can be static or dynamic.
	case "/followers":
		followers();   //	Mandatory. Can be static or dynamic.
	case "/inbox":
		inbox();       //	Mandatory.
	case "/outbox":
		outbox();      //	Optional. Dynamic.
	case "/action/send":
		send();        //	API for posting content to the Fediverse.
	case "/action/follow":
		follow();             // API for following other accounts
	case "/action/unfollow":
		unfollow();          // API for unfollowing accounts
	case "/":
		view("home"); // User interface for seeing what the user has posted.
	default:
		echo ($path);
		header("HTTP/1.1 404 Not Found");
		die();
}

//	The WebFinger Protocol is used to identify accounts.
//	It is requested with `example.com/.well-known/webfinger?resource=acct:username@example.com`
//	This server only has one user, so it ignores the query string and always returns the same details.
function webfinger()
{
	global $username, $server;

	$webfinger = array(
		"subject" => "acct:{$username}@{$server}",
		"links" => array(
			array(
				"rel" => "self",
				"type" => "application/activity+json",
				"href" => "https://{$server}/{$username}"
			)
		)
	);
	header("Content-Type: application/json");
	echo json_encode($webfinger);
	die();
}

//	User:
//	Requesting `example.com/username` returns a JSON document with the user's information.
function username()
{
	global $username, $realName, $summary, $server, $key_public;

	//	Was HTML requested?
	//	If so, probably a browser. Redirect to homepage.
	foreach (getallheaders() as $name => $value) {
		if ("Accept" ==	$name) {
			$accepts = explode(",", $value);
			if ("text/html" == $accepts[0]) {
				header("Location: https://{$server}/");
				die();
			}
		}
	}

	$user = array(
		"@context" => [
			"https://www.w3.org/ns/activitystreams",
			"https://w3id.org/security/v1"
		],
		"id" => "https://{$server}/{$username}",
		"type" => "Application",
		"following" => "https://{$server}/following",
		"followers" => "https://{$server}/followers",
		"inbox" => "https://{$server}/inbox",
		"outbox" => "https://{$server}/outbox",
		"preferredUsername" =>  rawurldecode($username),
		"name" => "{$realName}",
		"summary" => "{$summary}",
		"url" => "https://{$server}/{$username}",
		"manuallyApprovesFollowers" =>  false,
		"discoverable" =>  true,
		"published" => "2024-02-29T12:34:56Z",
		"icon" => [
			"type" => "Image",
			"mediaType" => "image/png",
			"url" => "https://{$server}/icon.png"
		],
		"image" => [
			"type" => "Image",
			"mediaType" => "image/png",
			"url" => "https://{$server}/banner.png"
		],
		"publicKey" => [
			"id"           => "https://{$server}/{$username}#main-key",
			"owner"        => "https://{$server}/{$username}",
			"publicKeyPem" => $key_public
		]
	);
	header("Content-Type: application/activity+json");
	echo json_encode($user);
	die();
}

//	Follower / Following:
// These JSON documents show how many users are following / followers-of this account.
// The information here is self-attested. So you can lie and use any number you want.
function following()
{
	global $server, $directories;

	//	Get all the files 
	$following_files = glob($directories["following"] . "/*.json");
	//	Number of users
	$totalItems = count($following_files);

	//	Sort users by most recent first
	usort($following_files, function ($a, $b) {
		return filemtime($b) - filemtime($a);
	});

	//	Create a list of all accounts being followed
	$items = array();
	foreach ($following_files as $following_file) {
		$following = json_decode(file_get_contents($following_file), true);
		$items[] = $following["id"];
	}

	$following = array(
		"@context" => "https://www.w3.org/ns/activitystreams",
		"id" => "https://{$server}/following",
		"type" => "Collection",
		"totalItems" => $totalItems,
		"items" => $items
	);
	header("Content-Type: application/activity+json");
	echo json_encode($following);
	die();
}
function followers()
{
	global $server, $directories;
	//	The number of followers is self-reported.
	//	You can set this to any number you like.

	//	Get all the files 
	$follower_files = glob($directories["followers"] . "/*.json");
	//	Number of users
	$totalItems = count($follower_files);

	//	Sort users by most recent first
	usort($follower_files, function ($a, $b) {
		return filemtime($b) - filemtime($a);
	});

	//	Create a list of everyone being followed
	$items = array();
	foreach ($follower_files as $follower_file) {
		$following = json_decode(file_get_contents($follower_file), true);
		$items[] = $following["id"];
	}

	$followers = array(
		"@context" => "https://www.w3.org/ns/activitystreams",
		"id" => "https://{$server}/followers",
		"type" => "Collection",
		"totalItems" => $totalItems,
		"items" => $items
	);
	header("Content-Type: application/activity+json");
	echo json_encode($followers);
	die();
}

//	Inbox:
//	The `/inbox` is the main server. It receives all requests. 
function inbox()
{
	global $body, $server, $username, $key_private, $directories;

	//	Get the message, type, and ID
	$inbox_message = $body;
	$inbox_type = $inbox_message["type"];

	//	This inbox only sends responses to follow requests.
	//	A remote server sends the inbox a follow request which is a JSON file saying who they are.
	//	The details of the remote user's server is saved to a file so that future messages can be delivered to the follower.
	//	An accept request is cryptographically signed and POST'd back to the remote server.
	if ("Follow" == $inbox_type) {
		//	Validate HTTP Message Signature
		if (!verifyHTTPSignature()) {
			header("HTTP/1.1 401 Unauthorized");
			die();
		}

		//	Get the parameters
		$follower_id    = $inbox_message["id"];    //	E.g. https://mastodon.social/(unique id)
		$follower_actor = $inbox_message["actor"]; //	E.g. https://mastodon.social/users/Edent

		//	Get the actor's profile as JSON
		$follower_actor_details = getDataFromURl($follower_actor);

		//	Save the actor's data in `/data/followers/`
		$follower_filename = urlencode($follower_actor);
		file_put_contents($directories["followers"] . "/{$follower_filename}.json", json_encode($follower_actor_details));

		//	Get the new follower's Inbox
		$follower_inbox = $follower_actor_details["inbox"];

		//	Response Message ID
		//	This isn't used for anything important so could just be a random number
		$guid = uuid();

		//	Create the Accept message to the new follower
		$message = [
			"@context" => "https://www.w3.org/ns/activitystreams",
			"id"       => "https://{$server}/{$guid}",
			"type"     => "Accept",
			"actor"    => "https://{$server}/{$username}",
			"object"   => [
				"@context" => "https://www.w3.org/ns/activitystreams",
				"id"       =>  $follower_id,
				"type"     =>  $inbox_type,
				"actor"    =>  $follower_actor,
				"object"   => "https://{$server}/{$username}",
			]
		];

		//	The Accept is POSTed to the inbox on the server of the user who requested the follow
		sendMessageToSingle($follower_inbox, $message);
	} else {
		//	Messages to ignore.
		//	Some servers are very chatty. They send lots of irrelevant messages.
		//	Before even bothering to validate them, we can delete them.

		//	This server doesn't handle Add, Remove, Reject, Favourite, Replies, Repost
		//	See https://www.w3.org/wiki/ActivityPub/Primer
		if (
			"Add"      == $inbox_type ||
			"Remove"   == $inbox_type ||
			"Reject"   == $inbox_type ||
			"Like"     == $inbox_type ||
			"Create"   == $inbox_type ||
			"Announce" == $inbox_type
		) {
			//	TODO: Better HTTP header
			die();
		}

		//	Get a list of every account following us
		//	Get all the files 
		$followers_files = glob($directories["followers"] . "/*.json");

		//	Create a list of all accounts being followed
		$followers_ids = array();
		foreach ($followers_files as $follower_file) {
			$follower = json_decode(file_get_contents($follower_file), true);
			$followers_ids[] = $follower["id"];
		}

		//	Is this from someone following us?
		in_array($inbox_message["actor"], $followers_ids) ? $from_follower = true : $from_follower = false;

		//	As long as one of these is true, the server will process it
		if (!$from_follower) {
			//	Don't bother processing it at all.
			die();
		}

		//	Validate HTTP Message Signature
		if (!verifyHTTPSignature()) {
			die();
		}

		//	If this is an Undo (Unfollow) try to process it
		if ("Undo" == $inbox_type) {
			undo($inbox_message);
		} elseif (in_array($inbox_type, ["Accept", "Reject"])) {
			processFollowResponse($inbox_message);
		} else {
			die();
		}
	}

	//	If the message is valid, save the message in `/data/inbox/`
	$uuid = uuid($inbox_message);
	$inbox_filename = $uuid . "." . urlencode($inbox_type) . ".json";
	file_put_contents($directories["inbox"] . "/{$inbox_filename}", json_encode($inbox_message));

	die();
}

//	Unique ID:
// Every message sent should have a unique ID. 
// This can be anything you like. Some servers use a random number.
// I prefer a date-sortable string.
function uuid($message = null)
{
	//	UUIDs that this server *sends* will be [timestamp]-[random]
	//	65e99ab4-5d43-f074-b43e-463f9c5cf05c
	if (is_null($message)) {
		return sprintf(
			"%08x-%04x-%04x-%04x-%012x",
			time(),
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffffffffffff)
		);
	} else {
		//	UUIDs that this server *saves* will be [timestamp]-[hash of message ID]
		//	65eadace-8f434346648f6b96df89dda901c5176b10a6d83961dd3c1ac88b59b2dc327aa4

		//	The message might have its own object
		if (isset($message["object"]["id"])) {
			$id = $message["object"]["id"];
		} else {
			$id = $message["id"];
		}

		return sprintf("%08x", time()) . "-" . hash("sha256", $id);
	}
}

//	Headers:
// Every message that your server sends needs to be cryptographically signed with your Private Key.
// This is a complicated process.
// Please read https://blog.joinmastodon.org/2018/07/how-to-make-friends-and-verify-requests/ for more information.
function generate_signed_headers($message, $host, $path, $method)
{
	global $server, $username, $key_private;

	//	Location of the Public Key
	$keyId  = "https://{$server}/{$username}#main-key";

	//	Get the Private Key
	$signer = openssl_get_privatekey($key_private);

	//	Timestamp this message was sent
	$date   = date("D, d M Y H:i:s \G\M\T");

	//	There are subtly different signing requirements for POST and GET.
	if ("POST" == $method) {
		//	Encode the message object to JSON
		$message_json = json_encode($message);
		//	Generate signing variables
		$hash   = hash("sha256", $message_json, true);
		$digest = base64_encode($hash);

		//	Sign the path, host, date, and digest
		$stringToSign = "(request-target): post $path\nhost: $host\ndate: $date\ndigest: SHA-256=$digest";

		//	The signing function returns the variable $signature
		//	https://www.php.net/manual/en/function.openssl-sign.php
		openssl_sign(
			$stringToSign,
			$signature,
			$signer,
			OPENSSL_ALGO_SHA256
		);
		//	Encode the signature
		$signature_b64 = base64_encode($signature);

		//	Full signature header
		$signature_header = 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="' . $signature_b64 . '"';

		//	Header for POST request
		$headers = array(
			"Host: {$host}",
			"Date: {$date}",
			"Digest: SHA-256={$digest}",
			"Signature: {$signature_header}",
			"Content-Type: application/activity+json",
			"Accept: application/activity+json",
		);
	} else if ("GET" == $method) {
		//	Sign the path, host, date - NO DIGEST because there's no message sent.
		$stringToSign = "(request-target): get $path\nhost: $host\ndate: $date";

		//	The signing function returns the variable $signature
		//	https://www.php.net/manual/en/function.openssl-sign.php
		openssl_sign(
			$stringToSign,
			$signature,
			$signer,
			OPENSSL_ALGO_SHA256
		);
		//	Encode the signature
		$signature_b64 = base64_encode($signature);

		//	Full signature header
		$signature_header = 'keyId="' . $keyId . '",algorithm="rsa-sha256",headers="(request-target) host date",signature="' . $signature_b64 . '"';

		//	Header for GET request
		$headers = array(
			"Host: {$host}",
			"Date: {$date}",
			"Signature: {$signature_header}",
			"Accept: application/activity+json, application/json",
		);
	}

	return $headers;
}

// User Interface for Homepage.
// This creates a basic HTML page. This content appears when someone visits the root of your site.
function view($style)
{
	global $username, $server, $realName, $summary, $directories;
	$rawUsername = rawurldecode($username);

	$h1 = "HomePage";
	$directory = "posts";

	//	Counters for followers, following, and posts
	$follower_files  = glob($directories["followers"] . "/*.json");
	$totalFollowers  = count($follower_files);
	$following_files = glob($directories["following"] . "/*.json");
	$totalFollowing  = count($following_files);

	//	Show the HTML page
	echo <<< HTML
<!DOCTYPE html>
<html lang="en-GB">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta property="og:url" content="https://{$server}">
		<meta property="og:type" content="website">
		<meta property="og:title" content="{$realName}">
		<meta property="og:description" content="{$summary}">
		<meta property="og:image" content="https://{$server}/banner.png">
		<title>{$h1} {$realName}</title>
		<style>
			* { max-width: 100%; }
			body { margin:0; padding: 0; font-family:sans-serif; }
			@media screen and (max-width: 800px) { body { width: 100%; }}
			@media screen and (min-width: 799px) { body { width: 800px; margin: 0 auto; }}
			address { font-style: normal; }
			img  { max-width: 50%; }
			.h-feed { margin:auto; width: 100%; }
			.h-feed > header { text-align: center; margin: 0 auto; }
			.h-feed .banner { text-align: center; margin:0 auto; max-width: 650px; }
			.h-feed > h1, .h-feed > h2 { margin-top: 10px; margin-bottom: 0;  }
			.h-feed > header > h1:has(span.p-author), h2:has(a.p-nickname) { word-wrap: break-word; max-width: 90%; padding-left:20px; }
			.h-feed .u-feature:first-child { margin-top: 10px; margin-bottom: -150px; max-width: 100%;}
			.h-feed .u-photo { max-height: 8vw; max-width:100%; min-height: 120px;  }
			.h-feed .about { font-size: smaller; background-color: #F5F5F5; padding: 10px; border-top: dotted 1px #808080; border-bottom: dotted 1px #808080; }
			.h-feed > ul {  padding-left: 0; list-style-type: none; }
			.h-feed > ul > li { padding: 10px; border-bottom: dotted 1px #808080; }
			.h-entry { padding-right: 10px; }
			.h-entry time { font-weight: bold; }
			.h-entry .e-content a { word-wrap: break-word; }
		</style>
	</head>
	<body>
		<main class="h-feed">
			<header>
				<div class="banner">
					<img src="banner.png" alt="" class="u-feature"><br>
					<img src="icon.png" alt="icon" class="u-photo">
				</div>
				<address>
					<h1 class="p-name p-author">{$realName}</h1>
					<h2><a class="p-nickname u-url" rel="author" href="https://{$server}/{$username}">@{$rawUsername}@{$server}</a></h2>
				</address>
				<p class="p-summary">{$summary}</p>
				<p>Following: {$totalFollowing} | Followers: {$totalFollowers}</p>
				<div class="about">
					<p><a href="https://gitlab.com/edent/activity-bot/">This software is licenced under AGPL 3.0</a>.</p>
					<p>This site is a basic <a href="https://www.w3.org/TR/activitypub/">ActivityPub</a> server designed to be <a href="https://shkspr.mobi/blog/2024/02/activitypub-server-in-a-single-file/">a lightweight educational tool</a>.</p>
				</div>
			</header>
			<ul>
HTML;
	//	Get all the files in the directory
	$message_files = array_reverse(glob("posts" . "/*.json"));

	//	There are lots of messages. The UI will only show 200.
	$message_files = array_slice($message_files, 0, 1000);

	//	Loop through the messages, get their conent:
	//	Ensure messages are in the right order.
	$messages_ordered = [];
	foreach ($message_files as $message_file) {
		//	Split the filename
		$file_parts = explode(".", $message_file);
		$type = $file_parts[1];

		//	Get the contents of the JSON 
		$message = json_decode(file_get_contents($message_file), true);

		$published = $message["published"];

		//	Place in an array where the key is the timestamp
		$messages_ordered[$published] = $message;
	}

	//	HTML is *probably* sanitised by the sender. But let's not risk it, eh?
	//	Using the allow-list from https://docs.joinmastodon.org/spec/activitypub/#sanitization
	$allowed_elements = ["p", "span", "br", "a", "del", "pre", "code", "em", "strong", "b", "i", "u", "ul", "ol", "li", "blockquote"];
	//	Print the items in a list
	foreach ($messages_ordered as $message) {
		//	The object of this *is* the message
		$object = $message;

		//	Get basic details
		$id = $object["id"];
		$published = $object["published"];

		//	HTML for who wrote this
		$publishedHTML  = "<a href=\"{$id}\">{$published}</a>";

		//	For displaying the post's information
		$timeHTML  = "<time datetime=\"{$published}\" class=\"u-url\" rel=\"bookmark\">{$publishedHTML}</time>";

		//	Get the actor who authored the message
		$actor = $object["attributedTo"];

		//	Assume that what comes after the final `/` in the URl is the name
		$actorArray    = explode("/", $actor);
		$actorName     = end($actorArray);
		$actorServer   = parse_url($actor, PHP_URL_HOST);
		$actorUsername = "@{$actorName}@{$actorServer}";

		//	Make i18n usernames readable and safe.
		$actorName = htmlspecialchars(rawurldecode($actorName));
		$actorHTML = "<a href=\"$actor\">@{$actorName}</a>";

		//	What type of message is this?
		$type = $message["type"];

		//	Get the HTML content
		$content = $message["content"];

		//	Sanitise the HTML
		$content = strip_tags($content, $allowed_elements);

		//	Is there is a Content Warning?
		if (isset($object["summary"])) {
			$summary = $object["summary"];
			$summary = strip_tags($summary, $allowed_elements);
			//	Hide the content until the user interacts with it.
			$content = "<details><summary>{$summary}</summary>{$content}</details>";
		}

		//	Add any images
		if (isset($object["attachment"])) {
			foreach ($object["attachment"] as $attachment) {
				//	Only use things which have a MIME Type set
				if (isset($attachment["mediaType"])) {
					$mediaURl = $attachment["url"];
					$mime = $attachment["mediaType"];
					//	Use the first half of the MIME Type.
					//	For example `image/png` or `video/mp4`
					$mediaType = explode("/", $mime)[0];

					if ("image" == $mediaType) {
						//	Get the alt text
						isset($attachment["name"]) ? $alt = htmlspecialchars($attachment["name"]) : $alt = "";
						$content .= "<img src='{$mediaURl}' alt='{$alt}'>";
					} else if ("video" == $mediaType) {
						$content .= "<video controls><source src='{$mediaURl}' type='{$mime}'></video>";
					} else if ("audio" == $mediaType) {
						$content .= "<audio controls src='{$mediaURl}' type='{$mime}'></audio>";
					}
				}
			}
		}

		$verb = "posted";

		$messageHTML = "{$timeHTML} {$actorHTML} {$verb}: <blockquote class=\"e-content\">{$content}</blockquote>";
		//	Display the message
		echo "<li><article class=\"h-entry\">{$messageHTML}<br></article></li>";
	}
	echo <<< HTML
			</ul>
		</main>
	</body>
</html>
HTML;
	die();
}

//	Send Endpoint:
//	This takes the submitted message and checks the password is correct.
//	It reads all the followers' data in `data/followers`.
//	It constructs a list of shared inboxes and unique inboxes.
//	It sends the message to every server that is following this account.
function send()
{
	global $password, $server, $username, $key_private, $directories;

	//	Does the posted password match the stored password?
	if ($password != $_POST["password"]) {
		header("HTTP/1.1 401 Unauthorized");
		echo "Wrong password.";
		die();
	}

	//	Get the posted content
	$content = $_POST["content"];

	//	Is this a reply?
	if (isset($_POST["inReplyTo"]) && filter_var($_POST["inReplyTo"], FILTER_VALIDATE_URL)) {
		$inReplyTo = $_POST["inReplyTo"];
	} else {
		$inReplyTo = null;
	}

	//	Process the content into HTML to get hashtags etc
	list("HTML" => $content, "TagArray" => $tags) = process_content($content);

	//	Is there an image attached?
	if (isset($_FILES['image']['tmp_name']) && ("" != $_FILES['image']['tmp_name'])) {
		//	Get information about the image
		$image      = $_FILES['image']['tmp_name'];
		$image_info = getimagesize($image);
		$image_ext  = image_type_to_extension($image_info[2]);
		$image_mime = $image_info["mime"];

		//	Files are stored according to their hash
		//	A hash of "abc123" is stored in "/images/abc123.jpg"
		$sha1 = sha1_file($image);
		$image_full_path = $directories["images"] . "/{$sha1}.{$image_ext}";

		//	Move media to the correct location
		move_uploaded_file($image, $image_full_path);

		//	Get the alt text
		if (isset($_POST["alt"])) {
			$alt = $_POST["alt"];
		} else {
			$alt = "";
		}

		//	Construct the attachment value for the post
		$attachment = array([
			"type"      => "Image",
			"mediaType" => "{$image_mime}",
			"url"       => "https://{$server}/{$image_full_path}",
			"name"      => $alt
		]);
	} else {
		$attachment = [];
	}

	//	Current time - ISO8601
	$timestamp = date("c");

	//	Outgoing Message ID
	$guid = uuid();

	//	Construct the Note
	//	`contentMap` is used to prevent unnecessary "translate this post" pop ups
	// hardcoded to English
	$note = [
		"@context"     => array(
			"https://www.w3.org/ns/activitystreams"
		),
		"id"           => "https://{$server}/posts/{$guid}.json",
		"type"         => "Note",
		"published"    => $timestamp,
		"attributedTo" => "https://{$server}/{$username}",
		"inReplyTo"    => $inReplyTo,
		"content"      => $content,
		"contentMap"   => ["en" => $content],
		"to"           => ["https://www.w3.org/ns/activitystreams#Public"],
		"tag"          => $tags,
		"attachment"   => $attachment
	];

	//	Construct the Message
	//	The audience is public and it is sent to all followers
	$message = [
		"@context" => "https://www.w3.org/ns/activitystreams",
		"id"       => "https://{$server}/posts/{$guid}.json",
		"type"     => "Create",
		"actor"    => "https://{$server}/{$username}",
		"to"       => [
			"https://www.w3.org/ns/activitystreams#Public"
		],
		"cc"       => [
			"https://{$server}/followers"
		],
		"object"   => $note
	];


	//	Save the permalink
	$note_json = json_encode($note);
	file_put_contents($directories["posts"] . "/{$guid}.json", print_r($note_json, true));

	//	Send to all the user's followers
	$messageSent = sendMessageToFollowers($message);

	//	Return the JSON so the user can see the POST has worked
	if ($messageSent) {
		header("Location: https://{$server}/posts/{$guid}.json");
		die();
	} else {
		header("HTTP/1.1 500 Internal Server Error");
		echo "ERROR!";
		die();
	}
}

function follow()
{
	global $password, $server, $username, $directories;

	// Verify directories exist and are writable
	if (!is_dir($directories['following']) || !is_writable($directories['following'])) {
		header("HTTP/1.1 500 Internal Server Error");
		error_log("Following directory not writable");
		echo "Server configuration error";
		die();
	}

	// Check password
	if ($password != $_POST["password"]) {
		header("HTTP/1.1 401 Unauthorized");
		echo "Wrong password.";
		die();
	}

	// Get and sanitize the account
	if (!isset($_POST["account"])) {
		header("HTTP/1.1 400 Bad Request");
		echo "Missing account parameter";
		die();
	}

	// Limit input size
	if (strlen($_POST["account"]) > 255) {
		header("HTTP/1.1 400 Bad Request");
		echo "Account string too long";
		die();
	}

	$account = trim(filter_var($_POST["account"], FILTER_SANITIZE_STRING));

	// If it starts with @, remove it
	if (str_starts_with($account, '@')) {
		$account = substr($account, 1);
	}

	// Split into user@domain and validate parts
	$parts = explode("@", $account);
	if (
		count($parts) != 2 ||
		empty($parts[0]) ||
		empty($parts[1]) ||
		!preg_match('/^[a-zA-Z0-9_.-]+$/', $parts[0]) ||  // Validate username format
		!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $parts[1])
	) { // Basic domain format
		header("HTTP/1.1 400 Bad Request");
		echo "Invalid account format. Use user@domain";
		die();
	}

	$targetUser = $parts[0];
	$targetDomain = $parts[1];

	// Verify domain uses HTTPS
	if (!filter_var("https://{$targetDomain}", FILTER_VALIDATE_URL)) {
		header("HTTP/1.1 400 Bad Request");
		echo "Invalid domain";
		die();
	}

	// Get WebFinger data with timeout and error handling
	$webfinger_url = "https://{$targetDomain}/.well-known/webfinger?resource=acct:{$targetUser}@{$targetDomain}";
	$ch = curl_init($webfinger_url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT => USERAGENT,
		CURLOPT_TIMEOUT => 10,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 3,
		CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_SSL_VERIFYHOST => 2
	]);

	$response = curl_exec($ch);

	if (curl_errno($ch)) {
		header("HTTP/1.1 502 Bad Gateway");
		error_log("WebFinger fetch failed: " . curl_error($ch));
		echo "Failed to fetch WebFinger data";
		curl_close($ch);
		die();
	}

	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($status !== 200) {
		header("HTTP/1.1 404 Not Found");
		echo "Account not found";
		curl_close($ch);
		die();
	}

	curl_close($ch);

	$webfinger = json_decode($response, true);
	if (!$webfinger || !isset($webfinger['links'])) {
		header("HTTP/1.1 502 Bad Gateway");
		echo "Invalid WebFinger response";
		die();
	}

	// Find ActivityPub actor URL
	$actor_url = null;
	foreach ($webfinger['links'] as $link) {
		if (
			$link['rel'] === 'self' &&
			$link['type'] === 'application/activity+json' &&
			filter_var($link['href'], FILTER_VALIDATE_URL) &&
			parse_url($link['href'], PHP_URL_SCHEME) === 'https'
		) {
			$actor_url = $link['href'];
			break;
		}
	}

	if (!$actor_url) {
		header("HTTP/1.1 404 Not Found");
		echo "Could not find ActivityPub account";
		die();
	}

	// Get actor data
	try {
		$actor_data = getDataFromURl($actor_url);
	} catch (Exception $e) {
		header("HTTP/1.1 502 Bad Gateway");
		error_log("Actor fetch failed: " . $e->getMessage());
		echo "Failed to fetch account data";
		die();
	}

	// Verify required actor properties
	if (
		!isset($actor_data['inbox']) ||
		!filter_var($actor_data['inbox'], FILTER_VALIDATE_URL) ||
		!isset($actor_data['id']) ||
		$actor_data['id'] !== $actor_url
	) {  // Verify actor URL matches claimed ID
		header("HTTP/1.1 502 Bad Gateway");
		echo "Invalid actor data";
		die();
	}

	// Check follow state
	$following_file = "{$directories['following']}/" . urlencode($actor_url) . ".json";
	$pending_file = "{$directories['following']}/.pending/" . urlencode($actor_url) . ".json";

	if (file_exists($following_file)) {
		header("HTTP/1.1 409 Conflict");
		echo "Already following this account";
		die();
	}

	if (file_exists($pending_file)) {
		header("HTTP/1.1 409 Conflict");
		echo "Follow request already pending";
		die();
	}

	// Create follow activity with proper UUID
	$guid = uuid();
	$message = [
		"@context" => "https://www.w3.org/ns/activitystreams",
		"id" => "https://{$server}/follow/{$guid}",
		"type" => "Follow",
		"actor" => "https://{$server}/{$username}",
		"object" => $actor_url
	];

	// Ensure pending directory exists
	$pending_dir = "{$directories['following']}/.pending";
	if (!is_dir($pending_dir)) {
		if (!mkdir($pending_dir, 0755, true)) {
			header("HTTP/1.1 500 Internal Server Error");
			error_log("Could not create pending directory");
			echo "Server configuration error";
			die();
		}
	}

	// Save pending follow request first
	$pending_data = [
		'guid' => $guid,
		'timestamp' => time(),
		'actor_data' => $actor_data,
		'message' => $message
	];

	if (!file_put_contents($pending_file, json_encode($pending_data))) {
		header("HTTP/1.1 500 Internal Server Error");
		error_log("Failed to save pending follow");
		echo "Failed to save follow request";
		die();
	}

	// Send follow request
	$success = sendMessageToSingle($actor_data['inbox'], $message);

	if (!$success) {
		unlink($pending_file); // Clean up pending file
		header("HTTP/1.1 500 Internal Server Error");
		echo "Failed to send follow request";
		die();
	}

	// Return success
	header("Location: https://{$server}/following");
	die();
}

// Handle when remote server accepts/rejects the follow
function processFollowResponse($activity)
{
	global $directories;

	if (!isset($activity['object']['id'])) {
		return false;
	}

	// Extract original follow request ID
	$follow_id = $activity['object']['id'];
	$actor_url = $activity['actor'];
	$pending_file = "{$directories['following']}/.pending/" . urlencode($actor_url) . ".json";
	$following_file = "{$directories['following']}/" . urlencode($actor_url) . ".json";

	// Verify pending follow exists
	if (!file_exists($pending_file)) {
		return false;
	}

	$pending_data = json_decode(file_get_contents($pending_file), true);
	if (!$pending_data || $pending_data['message']['id'] !== $follow_id) {
		return false;
	}

	// Handle Accept/Reject
	if ($activity['type'] === 'Accept') {
		// Move from pending to following
		if (file_put_contents($following_file, json_encode($pending_data['actor_data']))) {
			unlink($pending_file);
			return true;
		}
	} else if ($activity['type'] === 'Reject') {
		// Just remove pending
		unlink($pending_file);
		return true;
	}

	return false;
}

function unfollow()
{
	global $password, $server, $username, $directories;

	// Check password
	if ($password != $_POST["password"]) {
		header("HTTP/1.1 401 Unauthorized");
		echo "Wrong password.";
		die();
	}

	// Get account URL
	if (!isset($_POST["account"]) || !filter_var($_POST["account"], FILTER_VALIDATE_URL)) {
		header("HTTP/1.1 400 Bad Request");
		echo "Invalid account URL";
		die();
	}

	$actor_url = $_POST["account"];
	$following_file = "{$directories['following']}/" . urlencode($actor_url) . ".json";

	if (!file_exists($following_file)) {
		header("HTTP/1.1 404 Not Found");
		echo "Not following this account";
		die();
	}

	// Get actor data to find inbox
	$actor_data = json_decode(file_get_contents($following_file), true);
	if (!$actor_data || !isset($actor_data['inbox'])) {
		header("HTTP/1.1 500 Internal Server Error");
		echo "Invalid following data";
		die();
	}

	// Create unfollow activity
	$guid = uuid();
	$message = [
		"@context" => "https://www.w3.org/ns/activitystreams",
		"id" => "https://{$server}/unfollow/{$guid}",
		"type" => "Undo",
		"actor" => "https://{$server}/{$username}",
		"object" => [
			"type" => "Follow",
			"actor" => "https://{$server}/{$username}",
			"object" => $actor_url
		]
	];

	// Send unfollow request
	$success = sendMessageToSingle($actor_data['inbox'], $message);

	if ($success) {
		unlink($following_file);
		header("Location: https://{$server}/following");
	} else {
		header("HTTP/1.1 500 Internal Server Error");
		echo "Failed to send unfollow request";
	}
	die();
}


//	POST a signed message to a single inbox
function sendMessageToSingle($inbox, $message)
{
	global $directories;

	$inbox_host  = parse_url($inbox, PHP_URL_HOST);
	$inbox_path  = parse_url($inbox, PHP_URL_PATH);

	//	Generate the signed headers
	$headers = generate_signed_headers($message, $inbox_host, $inbox_path, "POST");

	//	POST the message and header to the requester's inbox
	$ch = curl_init($inbox);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($message));
	curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers);
	curl_setopt($ch, CURLOPT_USERAGENT,      USERAGENT);
	curl_exec($ch);

	//	Check for errors
	if (curl_errno($ch)) {
		$timestamp = (new DateTime())->format(DATE_RFC3339_EXTENDED);
		$error_message = curl_error($ch) . "\ninbox: {$inbox}\nmessage: " . json_encode($message);
		file_put_contents($directories["logs"] . "/{$timestamp}.Error.txt", $error_message);
		return false;
	}
	curl_close($ch);
	return true;
}

//	POST a signed message to the inboxes of all followers
function sendMessageToFollowers($message)
{
	global $directories;
	//	Read existing followers
	$followers = glob($directories["followers"] . "/*.json");

	//	Get all the inboxes
	$inboxes = [];
	foreach ($followers as $follower) {
		//	Get the data about the follower
		$follower_info = json_decode(file_get_contents($follower), true);

		//	Some servers have "Shared inboxes"
		//	If you have lots of followers on a single server, you only need to send the message once.
		if (isset($follower_info["endpoints"]["sharedInbox"])) {
			$sharedInbox = $follower_info["endpoints"]["sharedInbox"];
			if (!in_array($sharedInbox, $inboxes)) {
				$inboxes[] = $sharedInbox;
			}
		} else {
			//	If not, use the individual inbox
			$inbox = $follower_info["inbox"];
			if (!in_array($inbox, $inboxes)) {
				$inboxes[] = $inbox;
			}
		}
	}

	//	Prepare to use the multiple cURL handle
	//	This makes it more efficient to send many simultaneous messages
	$mh = curl_multi_init();

	//	Loop through all the inboxes of the followers
	//	Each server needs its own cURL handle
	//	Each POST to an inbox needs to be signed separately
	foreach ($inboxes as $inbox) {

		$inbox_host  = parse_url($inbox, PHP_URL_HOST);
		$inbox_path  = parse_url($inbox, PHP_URL_PATH);

		//	Generate the signed headers
		$headers = generate_signed_headers($message, $inbox_host, $inbox_path, "POST");

		//	POST the message and header to the requester's inbox
		$ch = curl_init($inbox);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($message));
		curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers);
		curl_setopt($ch, CURLOPT_USERAGENT,      USERAGENT);

		//	Add the handle to the multi-handle
		curl_multi_add_handle($mh, $ch);
	}

	//	Execute the multi-handle
	do {
		$status = curl_multi_exec($mh, $active);
		if ($active) {
			curl_multi_select($mh);
		}
	} while ($active && $status == CURLM_OK);

	//	Close the multi-handle
	curl_multi_close($mh);

	return true;
}

//	Content can be plain text. But to add clickable links and hashtags, it needs to be turned into HTML.
//	Tags are also included separately in the note
function process_content($content)
{
	global $server;

	//	Convert any URls into hyperlinks
	$link_pattern = '/\bhttps?:\/\/\S+/iu';	//	Sloppy regex
	$replacement = function ($match) {
		$url = htmlspecialchars($match[0], ENT_QUOTES, "UTF-8");
		return "<a href=\"$url\">$url</a>";
	};
	$content = preg_replace_callback($link_pattern, $replacement, $content);

	//	Get any hashtags
	$hashtags = [];
	$hashtag_pattern = '/(?:^|\s)\#(\w+)/';	//	Beginning of string, or whitespace, followed by #
	preg_match_all($hashtag_pattern, $content, $hashtag_matches);
	foreach ($hashtag_matches[1] as $match) {
		$hashtags[] = $match;
	}

	//	Construct the tag value for the note object
	$tags = [];
	foreach ($hashtags as $hashtag) {
		$tags[] = array(
			"type" => "Hashtag",
			"name" => "#{$hashtag}",
		);
	}

	//	Add HTML links for hashtags into the text
	//	Todo: Make these links do something.
	$content = preg_replace(
		$hashtag_pattern,
		" <a href='https://{$server}/tag/$1'>#$1</a>",
		$content
	);

	//	Detect user mentions
	$usernames = [];
	$usernames_pattern = '/@(\S+)@(\S+)/'; //	This is a *very* sloppy regex
	preg_match_all($usernames_pattern, $content, $usernames_matches);
	foreach ($usernames_matches[0] as $match) {
		$usernames[] = $match;
	}

	//	Construct the mentions value for the note object
	//	This goes in the generic "tag" property
	//	TODO: Add this to the CC field & appropriate inbox
	foreach ($usernames as $username) {
		list(, $user, $domain) = explode("@", $username);
		$tags[] = array(
			"type" => "Mention",
			"href" => "https://{$domain}/@{$user}",
			"name" => "{$username}"
		);

		//	Add HTML links to usernames
		$username_link = "<a href=\"https://{$domain}/@{$user}\">$username</a>";
		$content = str_replace($username, $username_link, $content);
	}

	// Construct HTML breaks from carriage returns and line breaks
	$linebreak_patterns = array("\r\n", "\r", "\n"); // Variations of line breaks found in raw text
	$content = str_replace($linebreak_patterns, "<br/>", $content);

	//	Construct the content
	$content = "<p>{$content}</p>";

	return [
		"HTML"     => $content,
		"TagArray" => $tags
	];
}

//	When given the URl of a post, this looks up the post, finds the user, then returns their inbox or shared inbox
function getInboxFromMessageURl($url)
{

	//	Get details about the message
	$messageData = getDataFromURl($url);

	//	The author is the user who the message is attributed to
	if (isset($messageData["attributedTo"]) && filter_var($messageData["attributedTo"], FILTER_VALIDATE_URL)) {
		$profileData = getDataFromURl($messageData["attributedTo"]);
	} else {
		return null;
	}

	//	Get the shared inbox or personal inbox
	if (isset($profileData["endpoints"]["sharedInbox"])) {
		$inbox = $profileData["endpoints"]["sharedInbox"];
	} else {
		//	If not, use the individual inbox
		$inbox = $profileData["inbox"];
	}

	//	Return the destination inbox if it is valid
	if (filter_var($inbox, FILTER_VALIDATE_URL)) {
		return $inbox;
	} else {
		return null;
	}
}

//	GET a request to a URl and returns structured data
function getDataFromURl($url)
{
	//	Check this is a valid https address
	if (
		(filter_var($url, FILTER_VALIDATE_URL) != true) ||
		(parse_url($url, PHP_URL_SCHEME) != "https")
	) {
		die();
	}

	//	Split the URL
	$url_host  = parse_url($url, PHP_URL_HOST);
	$url_path  = parse_url($url, PHP_URL_PATH);

	//	Generate signed headers for this request
	$headers  = generate_signed_headers(null, $url_host, $url_path, "GET");

	// Set cURL options
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers);
	curl_setopt($ch, CURLOPT_USERAGENT,      USERAGENT);

	// Execute the cURL session
	$urlJSON = curl_exec($ch);

	$status_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

	// Check for errors
	if (curl_errno($ch) || $status_code == 404) {
		// Handle cURL error
		$timestamp = (new DateTime())->format(DATE_RFC3339_EXTENDED);
		$error_message = curl_error($ch) . "\nURl: {$url}\nHeaders: " . json_encode($headers);
		file_put_contents($directories["logs"] . "/{$timestamp}.Error.txt", $error_message);
		die();
	}

	// Close cURL session
	curl_close($ch);

	return json_decode($urlJSON, true);
}

//	The Outbox contains a date-ordered list (newest first) of all the user's posts
//	This is optional.
function outbox()
{
	global $server, $username, $directories;

	//	Get all posts
	$posts = array_reverse(glob($directories["posts"] . "/*.json"));
	//	Number of posts
	$totalItems = count($posts);
	//	Create an ordered list
	$orderedItems = [];
	foreach ($posts as $post) {
		$postData = json_decode(file_get_contents($post), true);
		$orderedItems[] = array(
			"type"   => $postData["type"],
			"actor"  => "https://{$server}/{$username}",
			"object" => "https://{$server}/{$post}"
		);
	}

	//	Create User's outbox
	$outbox = array(
		"@context"     => "https://www.w3.org/ns/activitystreams",
		"id"           => "https://{$server}/outbox",
		"type"         => "OrderedCollection",
		"totalItems"   =>  $totalItems,
		"summary"      => "All the user's posts",
		"orderedItems" =>  $orderedItems
	);

	//	Render the page
	header("Content-Type: application/activity+json");
	echo json_encode($outbox);
	die();
}

//	Verify the signature sent with the message.
//	This is optional
//	It is very confusing
function verifyHTTPSignature()
{
	global $input, $body, $server, $directories;

	//	What type of message is this? What's the time now?
	//	Used in the log filename.
	$type = urlencode($body["type"]);
	$timestamp = (new DateTime())->format(DATE_RFC3339_EXTENDED);

	//	Get the headers send with the request
	$headers = getallheaders();
	//	Ensure the header keys match the format expected by the signature 
	$headers = array_change_key_case($headers, CASE_LOWER);

	//	Validate the timestamp
	//	7.2.4 of https://datatracker.ietf.org/doc/rfc9421/ 
	if (!isset($headers["date"])) {
		//	No date set
		//	Filename for the log
		$filename  = "{$timestamp}.{$type}.Signature.Date_Failure.txt";

		//	Save headers and request data to the timestamped file in the logs directory
		file_put_contents(
			$directories["logs"] . "/{$filename}",
			"Original Body:\n"    . print_r($body, true)       . "\n\n" .
				"Original Headers:\n" . print_r($headers, true)    . "\n\n"
		);
		return null;
	}
	$dateHeader = $headers["date"];
	$headerDatetime  = DateTime::createFromFormat('D, d M Y H:i:s T', $dateHeader);
	$currentDatetime = new DateTime();

	//	First, check if the message was sent no more than ± 1 hour
	//	https://github.com/mastodon/mastodon/blob/82c2af0356ff888e9665b5b08fda58c7722be637/app/controllers/concerns/signature_verification.rb#L11
	// Calculate the time difference in seconds
	$timeDifference = abs($currentDatetime->getTimestamp() - $headerDatetime->getTimestamp());
	if ($timeDifference > 3600) {
		//	Write a log detailing the error
		//	Filename for the log
		$filename  = "{$timestamp}.{$type}.Signature.Delay_Failure.txt";

		//	Save headers and request data to the timestamped file in the logs directory
		file_put_contents(
			$directories["logs"] . "/{$filename}",
			"Header Date:\n"      . print_r($dateHeader, true) . "\n" .
				"Server Date:\n"      . print_r($currentDatetime->format('D, d M Y H:i:s T'), true) . "\n" .
				"Original Body:\n"    . print_r($body, true)       . "\n\n" .
				"Original Headers:\n" . print_r($headers, true)    . "\n\n"
		);
		return false;
	}

	//	Is there a significant difference between the Date header and the published timestamp?
	//	Two minutes chosen because Friendica is frequently more than a minute skewed
	$published = $body["published"];
	$publishedDatetime = new DateTime($published);
	// Calculate the time difference in seconds
	$timeDifference = abs($publishedDatetime->getTimestamp() - $headerDatetime->getTimestamp());
	if ($timeDifference > 120) {
		//	Write a log detailing the error
		//	Filename for the log
		$filename  = "{$timestamp}.{$type}.Signature.Time_Failure.txt";

		//	Save headers and request data to the timestamped file in the logs directory
		file_put_contents(
			$directories["logs"] . "/{$filename}",
			"Header Date:\n"      . print_r($dateHeader, true) . "\n" .
				"Published Date:\n"   . print_r($publishedDatetime->format('D, d M Y H:i:s T'), true) . "\n" .
				"Original Body:\n"    . print_r($body, true)       . "\n\n" .
				"Original Headers:\n" . print_r($headers, true)    . "\n\n"
		);
		return false;
	}

	//	Validate the Digest
	//	It is the hash of the raw input string, in binary, encoded as base64.
	$digestString = $headers["digest"];

	//	Usually in the form `SHA-256=Ofv56Jm9rlowLR9zTkfeMGLUG1JYQZj0up3aRPZgT0c=`
	//	The Base64 encoding may have multiple `=` at the end. So split this at the first `=`
	$digestData = explode("=", $digestString, 2);
	$digestAlgorithm = $digestData[0];
	$digestHash = $digestData[1];

	//	There might be many different hashing algorithms
	//	TODO: Find a way to transform these automatically
	//	See https://github.com/superseriousbusiness/gotosocial/issues/1186#issuecomment-1976166659 and https://github.com/snarfed/bridgy-fed/issues/430 for hs2019
	if ("SHA-256" == $digestAlgorithm || "hs2019" == $digestAlgorithm) {
		$digestAlgorithm = "sha256";
	} else if ("SHA-512" == $digestAlgorithm) {
		$digestAlgorithm = "sha512";
	}

	//	Manually calculate the digest based on the data sent
	$digestCalculated = base64_encode(hash($digestAlgorithm, $input, true));

	//	Does our calculation match what was sent?
	if (!($digestCalculated == $digestHash)) {
		//	Write a log detailing the error
		$filename  = "{$timestamp}.{$type}.Signature.Digest_Failure.txt";

		//	Save headers and request data to the timestamped file in the logs directory
		file_put_contents(
			$directories["logs"] . "/{$filename}",
			"Original Input:\n"    . print_r($input, true)    . "\n" .
				"Original Digest:\n"   . print_r($digestString, true) . "\n" .
				"Calculated Digest:\n" . print_r($digestCalculated, true) . "\n"
		);
		return false;
	}

	//	Examine the signature
	$signatureHeader = $headers["signature"];

	// Extract key information from the Signature header
	$signatureParts = [];
	//	Converts 'a=b,c=d e f' into ["a"=>"b", "c"=>"d e f"]
	// word="text"
	preg_match_all('/(\w+)="([^"]+)"/', $signatureHeader, $matches);
	foreach ($matches[1] as $index => $key) {
		$signatureParts[$key] = $matches[2][$index];
	}

	//	Manually reconstruct the header string
	$signatureHeaders = explode(" ", $signatureParts["headers"]);
	$signatureString = "";
	foreach ($signatureHeaders as $signatureHeader) {
		if ("(request-target)" == $signatureHeader) {
			$method = strtolower($_SERVER["REQUEST_METHOD"]);
			$target =             $_SERVER["REQUEST_URI"];
			$signatureString .= "(request-target): {$method} {$target}\n";
		} else if ("host" == $signatureHeader) {
			$host = strtolower($_SERVER["HTTP_HOST"]);
			$signatureString .= "host: {$host}\n";
		} else {
			$signatureString .= "{$signatureHeader}: " . $headers[$signatureHeader] . "\n";
		}
	}

	//	Remove trailing newline
	$signatureString = trim($signatureString);

	//	Get the Public Key
	//	The link to the key might be sent with the body, but is always sent in the Signature header.
	$publicKeyURL = $signatureParts["keyId"];

	//	This is usually in the form `https://example.com/user/username#main-key`
	//	This is to differentiate if the user has multiple keys
	//	TODO: Check the actual key
	$userData  = getDataFromURl($publicKeyURL);
	$publicKey = $userData["publicKey"]["publicKeyPem"];

	//	Check that the actor's key is the same as the key used to sign the message
	//	Get the actor's public key
	$actorData = getDataFromURl($body["actor"]);
	$actorPublicKey = $actorData["publicKey"]["publicKeyPem"];

	if ($publicKey != $actorPublicKey) {
		//	Filename for the log
		$filename  = "{$timestamp}.{$type}.Signature.Mismatch_Failure.txt";

		//	Save headers and request data to the timestamped file in the logs directory
		file_put_contents(
			$directories["logs"] . "/{$filename}",
			"Original Body:\n"              . print_r($body, true)             . "\n\n" .
				"Original Headers:\n"           . print_r($headers, true)          . "\n\n" .
				"Signature Headers:\n"          . print_r($signatureHeaders, true) . "\n\n" .
				"publicKeyURL:\n"               . print_r($publicKeyURL, true)     . "\n\n" .
				"publicKey:\n"                  . print_r($publicKey, true)        . "\n\n" .
				"actorPublicKey:\n"             . print_r($actorPublicKey, true)   . "\n"
		);
		return false;
	}

	//	Get the remaining parts
	$signature = base64_decode($signatureParts["signature"]);
	$algorithm = $signatureParts["algorithm"];

	//	There might be many different signing algorithms
	//	TODO: Find a way to transform these automatically
	//	See https://github.com/superseriousbusiness/gotosocial/issues/1186#issuecomment-1976166659 and https://github.com/snarfed/bridgy-fed/issues/430 for hs2019
	if ("hs2019" == $algorithm) {
		$algorithm = "sha256";
	}

	//	Finally! Calculate whether the signature is valid
	//	Returns 1 if verified, 0 if not, false or -1 if an error occurred
	$verified = openssl_verify(
		$signatureString,
		$signature,
		$publicKey,
		$algorithm
	);

	//	Convert to boolean
	if ($verified === 1) {
		$verified = true;
	} elseif ($verified === 0) {
		$verified = false;
	} else {
		$verified = null;
	}

	//	Filename for the log
	$filename  = "{$timestamp}.{$type}.Signature." . json_encode($verified) . ".txt";

	//	Save headers and request data to the timestamped file in the logs directory
	file_put_contents(
		$directories["logs"] . "/{$filename}",
		"Original Body:\n"              . print_r($body, true)             . "\n\n" .
			"Original Headers:\n"           . print_r($headers, true)          . "\n\n" .
			"Signature Headers:\n"          . print_r($signatureHeaders, true) . "\n\n" .
			"Calculated signatureString:\n" . print_r($signatureString, true)  . "\n\n" .
			"Calculated algorithm:\n"       . print_r($algorithm, true)        . "\n\n" .
			"publicKeyURL:\n"               . print_r($publicKeyURL, true)     . "\n\n" .
			"publicKey:\n"                  . print_r($publicKey, true)        . "\n\n" .
			"actorPublicKey:\n"             . print_r($actorPublicKey, true)   . "\n"
	);

	return $verified;
}

//	The NodeInfo Protocol is used to identify servers.
//	It is looked up with `example.com/.well-known/nodeinfo`
//	See https://nodeinfo.diaspora.software/
function wk_nodeinfo()
{
	global $server;

	$nodeinfo = array(
		"links" => array(
			array(
				"rel" => "self",
				"type" => "http://nodeinfo.diaspora.software/ns/schema/2.1",
				"href" => "https://{$server}/nodeinfo/2.1"
			)
		)
	);
	header("Content-Type: application/json");
	echo json_encode($nodeinfo);
	die();
}

//	The NodeInfo Protocol is used to identify servers.
//	It is looked up with `example.com/.well-known/nodeinfo` which points to this resource
//	See http://nodeinfo.diaspora.software/docson/index.html#/ns/schema/2.0#$$expand
function nodeinfo()
{
	global $server, $directories;

	//	Get all posts
	$posts =  glob($directories["posts"] . "/*.json");
	//	Number of posts
	$totalItems = count($posts);

	$nodeinfo = array(
		"version" => "2.1",	//	Version of the schema, not the software
		"software" => array(
			"name"       => "Single File ActivityPub Server in PHP",
			"version"    => "0.000000001",
			"repository" => "https://gitlab.com/edent/activitypub-single-php-file/"
		),
		"protocols" => array("activitypub"),
		"services" => array(
			"inbound"  => array(),
			"outbound" => array()
		),
		"openRegistrations" => false,
		"usage" => array(
			"users" => array(
				"total" => 1
			),
			"localPosts" => $totalItems
		),
		"metadata" => array(
			"nodeName" => "activitypub-single-php-file",
			"nodeDescription" => "This is a single PHP file which acts as an extremely basic ActivityPub server.",
			"spdx" => "AGPL-3.0-or-later"
		)
	);
	header("Content-Type: application/json");
	echo json_encode($nodeinfo);
	die();
}

//	Perform the Undo action requested
function undo($message)
{
	global $server, $directories;

	//	Get some basic data
	$type = $message["type"];
	$id   = $message["id"];
	//	The thing being undone
	$object      = $message["object"];

	//	Does the thing being undone have its own ID or Type?
	if (isset($object["id"])) {
		$object_id   = $object["id"];
	} else {
		$object_id = $id;
	}

	if (isset($object["type"])) {
		$object_type   = $object["type"];
	} else {
		$object_type = $type;
	}

	//	Inbox items are stored as the hash of the original ID
	$object_id_hash = hash("sha256", $object_id);

	//	Find all the inbox messages which have that ID
	$inbox_files = glob($directories["inbox"] . "/*.json");
	foreach ($inbox_files as $inbox_file) {
		//	Filenames are `data/inbox/[date]-[SHA256 hash0].[Type].json
		// Find the position of the first hyphen and the first dot
		$hyphenPosition = strpos($inbox_file, '-');
		$dotPosition    = strpos($inbox_file, '.');

		if ($hyphenPosition !== false && $dotPosition !== false) {
			// Extract the text between the hyphen and the first dot
			$file_id_hash = substr($inbox_file, $hyphenPosition + 1, $dotPosition - $hyphenPosition - 1);
		} else {
			//	Ignore the file and move to the next.
			continue;
		}

		//	If this has the same hash as the item being undone
		if ($object_id_hash == $file_id_hash) {
			//	Delete the file
			unlink($inbox_file);

			//	If this was the undoing of a follow request, remove the external user from followers 😢
			if ("Follow" == $object_type) {
				$actor = $object["actor"];
				$follower_filename = urlencode($actor);
				unlink($directories["followers"] . "/{$follower_filename}.json");
			}
			//	Stop looping
			break;
		}
	}
}

//	"One to stun, two to kill, three to make sure"
die();
die();
die();
