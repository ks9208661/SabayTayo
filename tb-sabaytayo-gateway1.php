<?php

/***************************************************************
 *
 * Sabay Tayo (c)
 * tb-sabaytayo-gateway1.php
 * Created by: Kenneth See
 *
 * Receives SMS from the users, processes the contents, then
 * replies back to the users also via SMS.
 *
 ***************************************************************/

/**
 * *************************************************************
 * FUNCTIONS - Begin
 * **************************************************************
 */

// put comment here
// function syntax_error($t) {
// 	global $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes;
// 	global $filehandle;
// 	$em = '';
	
// 	// parse parameters
// 	$parameters = explode ( PARAM_SEPARATOR, $t );
	
// 	if (APP_NAME == strtolower ( $parameters [0] )) {
// 		// filter 1: num of parameters must be 6-7. The 'notes' field is optional.
// 		if (count ( $parameters ) < 6) {
// 			$em = "Message must follow the following pattern (case insensitive): SABAYTAYO/origin/destination/departure date in YYYY-MM-DD format/latest departure time in HH:mm format, military time/number of passengers/notes. Ex 1: SABAYTAYO/PHPIN/PHSBL/2017-01-31/16:00/3/can leave as early as 14:00";
// 			return $em;
// 		}
		
// 		// filter 2: parameters must be in the right format
// 		$port_orig = strtoupper ( $parameters [1] );
// 		$port_dest = strtoupper ( $parameters [2] );
// 		$dept_date = $parameters [3];
// 		$dept_time = $parameters [4];
// 		$pax = $parameters [5];
// 		$notes = $parameters [6];
		
// 		if (! isvalid ( $port_orig, 'port' )) {
// 			$em .= "Origin not in list. Pls refer to list of valid ports. ";
// 		}
		
// 		if (! isvalid ( $port_dest, 'port' )) {
// 			$em .= "Destination not in list. Pls refer to list of valid ports. ";
// 		}
		
// 		if (! isvalid ( $dept_date, 'date' )) {
// 			$em .= "Date format must be YYYY-MM-DD, ex. 2016-01-13 for 13 January 2016. ";
// 		}
		
// 		if (! isvalid ( $dept_time, 'time' )) {
// 			$em .= "Time format must be HH:mm, military time, ex. 13:45 for 1:45 PM. ";
// 		}
		
// 		if (! isvalid ( $pax, 'pax' )) {
// 			$em .= "Number of passengers must be a whole number. ";
// 		}
		
// 		// do something for the optional notes field to prevent SQL injection!!!
// 	}
	
// 	return $em;
// }

/**
 * *************************************************************
 * FUNCTIONS - End
 * **************************************************************
 */

/**
 * *************************************************************
 * MAIN PROGRAM - Begin
 * **************************************************************
 */

// General prep work
require_once ('tb-sabaytayo-requirements1.php');
require_once (WP_LOAD_FILE);
global $wpdb;
global $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes;

// set up log file
$filehandle = fopen ( LOG_FILE, 'a' ) or die ( 'Cannot open file:  ' . LOG_FILE );

date_default_timezone_set ( DEFAULT_TIMEZONE );
if (DEBUG) {
	echo "Timezone = " . DEFAULT_TIMEZONE . "\n";
	fwrite ( $filehandle, "Timezone = " . DEFAULT_TIMEZONE . "\n" );
}

$timestamp = time ();
if (DEBUG) {
	echo "Timestamp = $timestamp\n";
	fwrite ( $filehandle, "Timestamp = $timestamp\n" );
}

// Prep for sending SMS via Globe API
session_start ();
require_once ('api/PHP/src/GlobeApi.php');
$globe = new GlobeApi ( 'v1' );

// get json object which contains the text message and metadata; 1 object per SMS batch (can be 1-4 individual SMS depending on length of message
$json = file_get_contents ( 'php://input' );
$json = stripslashes ( $json );
$jsonvalues = json_decode ( $json, true );

// get mobile number. NOTE: senderAddr ADDS A "TEL:" STRING TO THE PHONE NUMBER
$subscriber_number = $jsonvalues [inboundSMSMessageList] [inboundSMSMessage] [0] [senderAddress];
$subscriber_number = substr ( $subscriber_number, 4 ); // remove the "tel:" prefix from the string
if (DEBUG) {
	echo "Subscriber Number = $subscriber_number\n";
	fwrite ( $filehandle, "Subscriber Number = $subscriber_number\n" );
}

// get text message. Rebuild if entire message is broken into 2-4 SMSes
$c = $jsonvalues [inboundSMSMessageList] [numberOfMessagesInThisBatch];
echo "Number of messages in batch = $c\n";
if (DEBUG) {
	echo "$timestamp: Number of messages in batch = $c\n";
	fwrite ( $filehandle, "$timestamp: Number of messages in batch = $c\n" );
}
$text = '';
for($i = 0; $i < $c; $i ++) {
	// get text message
	$text .= $jsonvalues [inboundSMSMessageList] [inboundSMSMessage] [$i] [message];
}
if (DEBUG) {
	echo "$timestamp: Text message = $text\n";
	fwrite ( $filehandle, "$timestamp: Text message = $text\n" );
}

// ########################## Change in Server
// $subscriber_number = $argv[1];
// $text = $argv[2];

// validate text message; if wrongly formatted, text user then quit program
$se = validate_text_input ( $text );
if (! ($se === '')) {
	send_sms ( $subscriber_number, $se );
} else {
	// create file with text message + other data
	$textfilename = INCOMING_TEXTS_DIR . APP_NAME . ".$timestamp";
	file_put_contents ( $textfilename, $timestamp . TOKEN_SEPARATOR . $subscriber_number . TOKEN_SEPARATOR . $text );
	if (DEBUG) {
		echo "$timestamp: Text file $textfilename created.\n";
		fwrite ( $filehandle, "$timestamp: Text file $textfilename created.\n" );
	}
}

// exec(PHP_FULL_PATH.' '.ST_POLLER);
// if (DEBUG) {
// echo "$timestamp: ".ST_POLLER." executed.\n";
// fwrite($handle, "$timestamp: ".ST_POLLER." executed.\n");
// }

// properly close log file
fclose ( $filehandle );
?>
