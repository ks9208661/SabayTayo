<?php

/***************************************************************
 *
 * Sabay Tayo (c)
 * tb-sabaytayo-requirements1.php
 * Created by: Kenneth See
 *
 * Application-specific constants, functions, etc.
 *
 ***************************************************************/

/**
 * *************************************************************
 * CONSTANTS - Begin
 * **************************************************************
 */
define ( 'APP_NAME', 'sabaytayo' );
define ( 'DEBUG', true );
// ##################################### Switch in Server
define ( 'WORKING_DIR', '/kunden/homepages/41/d579830064/htdocs/clickandbuilds/SabayTayo/' );
define ( 'PHP_FULL_PATH', '/usr/bin/php5.5-cli' );
// define ( 'WORKING_DIR', './' );
define ( 'WP_LOAD_FILE', WORKING_DIR . 'wp-load.php' );
define ( 'LOG_DIR', WORKING_DIR . 'tb-logs1/' );
// define ( 'LOG_FILE', LOG_DIR . APP_NAME . '-subscriber-consent.log' );
define ( 'LOG_FILE', LOG_DIR . APP_NAME . '.log' );
define ( 'SUBSCRIBER_TABLE', 'st_member_mobiles1' );
define ( 'DEFAULT_TIMEZONE', 'Asia/Manila' );
define ( 'DEFAULT_TIMEZONE_OFFSET', '+08:00' );
define ( 'INCOMING_TEXTS_DIR', WORKING_DIR . 'tb-in1/' );
define ( 'PROCESSED_TEXTS_DIR', WORKING_DIR . 'tb-proc1/' );
define ( 'GLOBE_APP_NUMBER', '9015' );
define ( 'TOKEN_SEPARATOR', "|||" );
define ( 'PARAM_SEPARATOR', "/" );
define ( 'ST_POLLER', WORKING_DIR . 'tb-sabaytayo-poller1.php' );
define ( 'QUERY_FILE', WORKING_DIR . 'queries1.sql' );
define ( 'TIME_WINDOW', 1209600 ); // 2 weeks
define ( 'TRIPS_TABLE', 'st_trips1' );
define ( 'RESPONSE_SMS_PRE', 'TY from SABAYTAYO! ' );
define ( 'RESPONSE_SMS_POST', '' );
define ( 'LOCK_FILE', WORKING_DIR . APP_NAME . '1.lock' );
define ( 'ST_PROCESSOR_FILE', WORKING_DIR . 'tb-sabaytayo-processor1.php' );
define ( 'HTTP_HOST', 'sabaytayo.inourshoes.info' );

/**
 * *************************************************************
 * CONSTANTS - End
 * **************************************************************
 */

/**
 * *************************************************************
 * FUNCTIONS - Begin
 * **************************************************************
 */

// load Wordpress environment
function find_wordpress_base_path() {
	$dir = dirname ( __FILE__ );
	do {
		// it is possible to check for other files here
		if (file_exists ( $dir . "/wp-config.php" )) {
			return $dir;
		}
	} while ( $dir = realpath ( "$dir/.." ) );
	return null;
}

// get access token of the subscriber number
function get_access_token($phone_number) {
	global $filehandle, $wpdb;
	
	$query = "SELECT access_token FROM " . SUBSCRIBER_TABLE . " WHERE subscriber_number = '$phone_number'";
	if (DEBUG) {
		echo "SQL QUERY: $query\n";
		fwrite ( $filehandle, "SQL QUERY: $query\n" );
	}
	$results = $wpdb->get_results ( $query );
	$tok = $results [0]->access_token;
	if (DEBUG) {
		echo "ACCESS TOKEN: $tok\n";
		fwrite ( $filehandle, "ACCESS TOKEN: $tok\n" );
	}
	return $tok;
}

// put comment here
function send_sms($phone_number, $message) {
	global $filehandle, $globe, $timestamp;
	
	$sms = $globe->sms ( GLOBE_APP_NUMBER );
	$acctok = get_access_token ( $phone_number );
	$response = $sms->sendMessage ( $acctok, $phone_number, $message );
	if (DEBUG) {
		echo "$timestamp: SMS Response to $phone_number = $message\n";
		fwrite ( $filehandle, "$timestamp: SMS Response to $phone_number = $message\n" );
	}
	$logfilename = LOG_DIR . APP_NAME . ".$timestamp.$phone_number.response";
	file_put_contents ( $logfilename, $message );
}

// parameter checking of text message
function isvalid($item, $type) {
	$isvalid = 0;

	// $type can be port, date, time, pax, and notes
	switch ($type) {
		case 'port' :
			$isvalid = preg_match ( "#^[0-9a-zA-Z]+$#", $item );
			break;
		case 'date' :
			if ($item == '') {
				$isvalid = 1;
			} else {
				$isvalid = preg_match ( "#^[0-9]{4}\-((0?[1-9])|(10)|(11)|(12))\-([0-3]?[0-9])$#", $item );
			}
			break;
		case 'time' :
			if ($item == '') {
				$isvalid = 1;
			} else {
				$isvalid = preg_match ( "#^(([0-1]?[0-9])|(20)|(21)|(22)|(23)):([0-5][0-9])$#", $item );
			}
			break;
		case 'pax' :
			$isvalid = preg_match ( "#^[0-9]+$#", $item );
			// if (! preg_match("#^[0-9]+$#", $item))
			// $isvalid = 0;
			break;
	}
	;

	// echo $isvalid;
	return ($isvalid);
}




/* add coomment here */
function validate_text_input($text) {
	global $filehandle, $subscriber_number;
	
	$em = '';
	$sms_tokens = explode ( PARAM_SEPARATOR, $text );
	switch (strtoupper ( $sms_tokens [0] )) {
		case 'SABAYTAYO' :
			// filter 1: num of parameters must be 6-7. The 'notes' field is optional.
			if (count ( $parameters ) < 6) {
				$em = "Message must follow the following pattern (case insensitive): SABAYTAYO/origin/destination/departure date in YYYY-MM-DD format/latest departure time in HH:mm format, military time/number of passengers/notes. Ex 1: SABAYTAYO/PHMDRPIN/PHRMBSBL/2017-01-31/16:00/3/can leave as early as 14:00";
				break;
			}
				
			// filter 2: parameters must be in the right format
			$port_orig = strtoupper ( $parameters [1] );
			$port_dest = strtoupper ( $parameters [2] );
			$dept_date = $parameters [3];
			$dept_time = $parameters [4];
			$pax = $parameters [5];
			$notes = $parameters [6];
			
			if (! isvalid ( $port_orig, 'port' )) {
				$em .= "Origin not in list. Pls refer to list of valid ports. ";
			}
			
			if (! isvalid ( $port_dest, 'port' )) {
				$em .= "Destination not in list. Pls refer to list of valid ports. ";
			}
			
			if (! isvalid ( $dept_date, 'date' )) {
				$em .= "Date format must be YYYY-MM-DD, ex. 2016-01-13 for 13 January 2016. ";
			}
			
			if (! isvalid ( $dept_time, 'time' )) {
				$em .= "Time format must be HH:mm, military time, ex. 13:45 for 1:45 PM. ";
			}
			
			if (! isvalid ( $pax, 'pax' )) {
				$em .= "Number of passengers must be a whole number. ";
			}
			
			// do something for the optional notes field to prevent SQL injection!!!
			break;
		case 'WEATHER' :
			// check if second token either FORECAST or CURRENT
			// check if third token is an existing port
			break;
		case 'BOATDRIVER' :
			// check if second token is an existing Port
			// check if third token is a command (list, add, remove)
			// BOATDRIVER/PHPLWCRN/list
			// BOATDRIVER/PHPLWCRN/add/Juan dela Cruz/Calachuchi/10 (phone number automatically associated with boat)
			// BOATDRIVER/PHPLWCRN/remove (since there can only be 1 boat per phone number, automatically remove boat associated with current number)
			break;
		case 'TRIPS' :
			// check if second token is an existing port
			break;
		case 'EVERYTHING ELSE' :
			// reject input
			break;
		default:
			$em = "Invalid text input."
			break;
	}
	return $em;
}

/**
 * *************************************************************
 * FUNCTIONS - End
 * **************************************************************
 */

?>