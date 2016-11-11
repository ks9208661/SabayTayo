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
define ( 'QUERY_FILE', WORKING_DIR . 'queries.sql' );
define ( 'TIME_WINDOW', 1209600 ); // 2 weeks
define ( 'TRIPS_TABLE', 'st_trips1' );
define ( 'RESPONSE_SMS_PRE', 'TY from SABAYTAYO! ' );
define ( 'RESPONSE_SMS_POST', '' );
define ( 'LOCK_FILE', WORKING_DIR . APP_NAME . '.lock' );
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

/* add coomment here */
function validate_input($text) {
	global $filehandle, $subscriber_number;
	
	$sms_tokens = explode ( PARAM_SEPARATOR, $text );
	switch (strtoupper ( $sms_tokens [0] )) {
		case 'SABAYTAYO' :
			// count number of parameters; minimum 6
			// if minimum not met, reject input
			// check syntax, e.g. date and time formats, pax must be a number, no special characters in notes, etc.
			// check if ports exist
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
	}
}

/**
 * *************************************************************
 * FUNCTIONS - End
 * **************************************************************
 */

?>