<?php
/**
 * phpAkismetContact
 * 
 * Secure PHP contact form with Akismet support
 *
 * Takes any variables posted to this script, and send an email to a specified recipient.
 *
 * REQUIRED PARAMETERS:
 * recipient - valid email address that email will go to
 * OPTIONAL PARAMETERS:
 * redirect - full URL (with http://) that the script will redirect to on success.
 * comments - the contents of this parameter will be sent to Akismet for spam checking
 * bcc - THIS FIELD MUST BE BLANK. This is designed to bait spammers. You may want to insert
 *       it into your form HTML, but comment it out or hide it via CSS.
 *       http://isc.sans.org/diary.php?storyid=1836 
 * All other parameters will be included in the email.
 *
 * Please note that in order to use this, you must have a vaild 
 * {@link http://wordpress.com/api-keys/ WordPress API key}.  They are free for non/small-profit 
 * types and getting one will only take a couple of minutes.
 *
 * For commercial use, please {@link http://akismet.com/commercial/ visit the Akismet commercial licensing page}.
 *
 * @package phpAkismetContact
 * @author Jesse Newland, {@link http://jnewland.com/ http://jnewland.com/}
 * @version 0.1
 * @copyright Jesse Newland, {@link http://jnewland.com/ http://jnewland.com/}
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 */


/**
 * Configuration Options
 */

//REQUIRED - Your Wordpress.com API Key
$wordpress_API_key = "XXXXXX";

//REQUIRED - website the contact form is being sumbitted from - if the form is located at http://www.test.com/contact, then
//           this is http://www.test.com
$website_URL = "http://www.test.com";				

//REQUIRED - allowed referrers. POSTs coming from any other domain will be disallowed 
$referrers = array("test.com");

//REQUIRED - email address this contact form will send email to. Must match 'recipient' value posted.
$recipient = "test@test.com";

//change this path if you need to
require('Akismet.class.php');

/**
 * Don't touch anything below. Unless, of course, you want to.
 * If you do, please contact me. I'm happy to take patches to make this better.
 */


/**
 * Functions
 */

// validate referrer
function valid_referer($referers) {	
	if(!empty($_SERVER['HTTP_REFERER'])) {
    $referer = end(array_slice(explode("/", $_SERVER['HTTP_REFERER']), 2, 1));
    foreach ($referers as $valid_referer) {
			if($valid_referer == $referer) {
		    return true;
			}
    }
	}
	return false;
}

//validate email
function valid_email($email){
	return preg_match("/^[A-Z0-9._%-\+]+@[A-Z0-9.-]+.[A-Z]{2,4}$/i", $email);
}

function error($error){
	$html = "<html><head><title>Error</title><body><h1 style='color:#FF0000;'>".$error."</h1><a href='javascript:history.go(-1);'>Back</a></body></html>";
	print $html;
}

/**
 * Here we go......
 */

//cleanup post parameters
$post = array();
$crack = false;
if ($_POST){
	foreach ($_POST as $key => $val) {
		//stripslashes
		$post[$key] = stripslashes($_POST[$key]);
		//if there is a newline followed by an email command, reject it.
		$crack = eregi("(%0A|%0D|\n+|\r+)(content-type:|to:|cc:|bcc:)",$post[$key]);
		if ($crack) {
			error("Invalid input detected. You've been logged.");
			return;
		}
	}
} else {
	error("No values submitted");
	return;
}

//verify nothing is in the bcc field
if (!empty($post["bcc"])) {
	error("Invalid input detected. You've been logged.");
	return;
}

//verify the recipient is correct
if ($post["recipient"] != $recipient) {
	error("Recipient is incorrect. You've been logged.");
	return;
}

//verify the sender as a valid email
if (!valid_email($post["email"])) {
	error("Sender is invalid. You've been logged.");
	return;
}

//verify the referer is allowed
if (!valid_referer($referrers)) {
	error("Referrer is incorrect. You've been logged.");
	return;
}

if (!empty($post["redirect"])) {
	$redirect = "Location: " . $post["redirect"];
} else {
	$redirect = "Location: " . $_SERVER['HTTP_REFERER'];
}

$addlHeaders = "From: " . $post["email"] . "\n" .
               "Reply-To: " . $post["email"] . "\n";

//unset a couple vars we don't want in the email
unset($post["redirect"]);
unset($post["recipient"]);
unset($post["bcc"]);

//generate the message
$message = "";
foreach ($post as $key => $value) {
	$message .= ucfirst($key) . ": " . $value . "\n\n";
}
$message = wordwrap($message, 70, "\n");

//setup akismet
$akismet = new Akismet($website_URL, $wordpress_API_key);
$akismet->setAuthor($post["name"]);
$akismet->setAuthorEmail($post["email"]);
$akismet->setContent($post["comments"]);
$akismet->setType("contact_form");

//submit to akismet for validation
if($akismet->isSpam()) {
	error("Your message was marked as spam by <a href='http://akismet.com'>Akismet</a>. Please try again.");
	return;
} else {
	//akismet passed, send the email
	if (mail($recipient,"Contact Form Submission",$message,$addlHeaders)) {
		header($redirect);
	} else {
		error("Error sending your message.");
		return;
	}
}
?>