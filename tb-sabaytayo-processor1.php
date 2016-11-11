<?php

/***************************************************************
 * Project: Sabay Tayo
 * File: tb-sabaytayo-processor1.php
 * Created by: Kenneth See
 * Description:
 * Take in a formatted file as input. Data in the file is inserted into the database. Output is a file containing SQL queries to be executed by the next process in the chain.
 * 
 * Format of file: timestamp|||subscriber number|||text message. 
 * Assumption: file has already been checked for errors and possible cracking attempts. 
 ***************************************************************/

/**
 * *************************************************************
 * CONSTANTS - Begin
 * **************************************************************
 */
// define ( 'APP_NAME', 'sabaytayo' );
// define ( 'DEBUG', true );
// define ( 'TOKEN_SEPARATOR', "|||" );
// define ( 'PARAM_SEPARATOR', "/" );
// define ( 'WORKING_DIR', '/kunden/homepages/41/d579830064/htdocs/clickandbuilds/SabayTayo/' );
// define ( 'LOG_DIR', WORKING_DIR . 'tb-logs/' );
// define ( 'LOG_FILE', LOG_DIR . APP_NAME . '.log' );
// define ( 'QUERY_FILE', WORKING_DIR . 'queries.sql' );
// define ( 'DEFAULT_TIMEZONE', 'Asia/Manila' );
// define ( 'DEFAULT_TIMEZONE_OFFSET', '+08:00' );
// define ( 'TIME_WINDOW', 1209600 ); // 2 weeks
// define ( 'TRIPS_TABLE', 'st_trips' );
// define ( 'GLOBE_APP_NUMBER', '3363' );
// define ( 'SUBSCRIBER_TABLE', 'st_member_mobiles' );

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

/*
 * // load Wordpress environment to access mySQL underneath
 * function find_wordpress_base_path() {
 * $dir = dirname ( __FILE__ );
 * do {
 * // it is possible to check for other files here
 * if (file_exists ( $dir . "/wp-config.php" )) {
 * return $dir;
 * }
 * } while ( $dir = realpath ( "$dir/.." ) );
 * return null;
 * }
 *
 * // get access token of the subscriber number
 * function get_access_token($phone_number) {
 * global $handle, $wpdb;
 *
 * $query = "SELECT access_token FROM " . SUBSCRIBER_TABLE . " WHERE subscriber_number = '$phone_number'";
 * if (DEBUG) {
 * fwrite ( $handle, "SQL QUERY: $query\n" );
 * }
 * $results = $wpdb->get_results ( $query );
 * $tok = $results [0]->access_token;
 * if (DEBUG) {
 * fwrite ( $handle, "ACCESS TOKEN: $tok\n" );
 * }
 * return $tok;
 * }
 * function send_sms($phone_number, $message) {
 * global $handle, $globe, $timestamp;
 *
 * $sms = $globe->sms ( GLOBE_APP_NUMBER );
 * $acctok = get_access_token ( $phone_number );
 * $response = $sms->sendMessage ( $acctok, $phone_number, $message );
 * if (DEBUG) {
 * fwrite ( $handle, "$timestamp: SMS Response to $phone_number = $message\n" );
 * }
 * $logfilename = LOG_DIR . "$timestamp.$phone_number.response";
 * file_put_contents ( $logfilename, $message );
 * }
 */

/* put some comments here */
function split_parameters($t) {
	global $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes;
	global $filehandle, $timestamp;
	
	// parse parameters
	$parameters = explode ( PARAM_SEPARATOR, $t );
	
	$port_orig = strtoupper ( $parameters [1] );
	$port_dest = strtoupper ( $parameters [2] );
	$dept_date = $parameters [3];
	$dept_time = $parameters [4];
	$pax = $parameters [5];
	$notes = $parameters [6];
	
	if (DEBUG) {
		echo "$timestamp: Port of Origin = $port_orig, Port of Destination = $port_dest, Departure Date = $dept_date, Departure Time = $dept_time, Pax = $pax, Notes = $notes\n";
		fwrite ( $filehandle, "$timestamp: Port of Origin = $port_orig, Port of Destination = $port_dest, Departure Date = $dept_date, Departure Time = $dept_time, Pax = $pax, Notes = $notes\n" );
	}
}

/* put some comments here */
function process_boatservice($text) {
	global $timestamp, $filehandle, $subscriber_number;
	
	$sms_tokens = explode ( PARAM_SEPARATOR, $text );
	
	// $sms_tokens[0] is BOATSERVICE
	$port = $sms_tokens [1];
	$command = $sms_tokens [2];
	switch (strtoupper ( $command )) {
		case 'LIST' :
			$query = " SELECT * FROM " . BOATSERVICE_TABLE . " WHERE port = '$port' ";
			if (DEBUG) {
				echo "$timestamp: SQL Query: $query\n";
				fwrite ( $filehandle, "$timestamp: SQL Query: $query\n" );
			}
			$results = $wpdb->get_results ( $query );
			if (DEBUG) {
				echo "$timestamp: Query Results: " . print_r ( $results ) . "\n";
				fwrite ( $filehandle, "$timestamp: Query Results: " . print_r ( $results ) . "\n" );
			}
			
			// build response SMS
			$response_sms = RESPONSE_SMS_PRE . "The ff provide boat transfer service from $port:\n";
			foreach ( $results as $r ) {
				$response_sms .= "Cpt: $r->captain_name $r->captain_number (Vessel Name: $r->vessel_name, Max Cap: $r->vessel_capacity pax)\n";
			}
			send_sms ( $subscriber_number, $response_sms );
			break;
		case 'ADD' :
			// INSERT INTO BOATSERVICE_TABLE VALUES port, captain_name, captain_number, vessel_name, vessel_capacity, timestamp
			$response_sms = RESPONSE_SMS_PRE . "You have been removed from the list of boat service providers in $port.";
				break;
		case 'REMOVE' :
			// DELETE FROM BOATSERVICE_TABLE WHERE port = $port AND captain_number = $subscriber_number
			$response_sms = RESPONSE_SMS_PRE . "You have been removed from the list of boat service providers in $port.";
			break;
	}
}

/* put some comments here */
function process_trips($text) {
	global $timestamp, $filehandle, $subscriber_number;
	
	$sms_tokens = explode ( PARAM_SEPARATOR, $text );
	
	// $sms_tokens[0] is TRIPS
	// at least one of the two ports must be filled (ie non-blank)
	$port_orig = $sms_tokens [1];
	$port_dest = $sms_tokens [2];
	
	if ($port_orig == '') { // find any trip going to a port
		$query .= " SELECT * FROM " . TRIPS_TABLE;
		$query .= " WHERE port_dest = '$port_dest' ";
		$query .= " AND   dept_date <> '0000-00-00' ";
		$query .= " AND   dept_timestamp - unix_timestamp() < " . TIME_WINDOW;
		$query .= " UNION ";
		$query .= " SELECT * FROM " . TRIPS_TABLE;
		$query .= " WHERE port_dest = '$port_dest' ";
		$query .= " AND   dept_date = '0000-00-00' ";
		$query .= " AND   timestamp - unix_timestamp() < " . TIME_WINDOW;
	} elseif ($port_dest == '') { // find any trip leaving from a port
		$query .= " SELECT * FROM " . TRIPS_TABLE;
		$query .= " WHERE port_orig = '$port_orig' ";
		$query .= " AND   dept_date <> '0000-00-00' ";
		$query .= " AND   dept_timestamp - unix_timestamp() < " . TIME_WINDOW;
		$query .= " UNION ";
		$query .= " SELECT * FROM " . TRIPS_TABLE;
		$query .= " WHERE port_orig = '$port_orig' ";
		$query .= " AND   dept_date = '0000-00-00' ";
		$query .= " AND   timestamp - unix_timestamp() < " . TIME_WINDOW;
	} else { // find trips going from and to specific ports
		$query .= " SELECT * FROM " . TRIPS_TABLE;
		$query .= " WHERE port_orig = '$port_orig' ";
		$query .= " AND   port_dest = '$port_dest' ";
		$query .= " AND   dept_date <> '0000-00-00' ";
		$query .= " AND   dept_timestamp - unix_timestamp() < " . TIME_WINDOW;
		$query .= " UNION ";
		$query .= " SELECT * FROM " . TRIPS_TABLE;
		$query .= " WHERE port_orig = '$port_orig' ";
		$query .= " AND   port_dest = '$port_dest' ";
		$query .= " AND   dept_date = '0000-00-00' ";
		$query .= " AND   timestamp - unix_timestamp() < " . TIME_WINDOW;
	}
	if (DEBUG) {
		echo "$timestamp: SQL Query: $query\n";
		fwrite ( $filehandle, "$timestamp: SQL Query: $query\n" );
	}
	
	$results = $wpdb->get_results ( $query );
	if (DEBUG) {
		echo "$timestamp: Query Results: " . print_r ( $results ) . "\n";
		fwrite ( $filehandle, "$timestamp: Query Results: " . print_r ( $results ) . "\n" );
	}
	
	// build response SMS
	$response_sms = RESPONSE_SMS_PRE . "In the next " . TIME_WINDOW / 60 / 60 / 24 . " days, the ff people are travelling ";
	if ($port_orig == '') {
		$response_sms .= "to $port_dest: ";
	} elseif ($port_dest == '') {
		$response_sms .= "from $port_orig: ";
	} else {
		$response_sms .= "from $port_orig to $port_dest: ";
	}
	
	// $subscribers = array();
	// reset($subscribers);
	foreach ( $results as $r ) {
		// array_push($subscribers, $r->subscriber_number);
		// $response_sms .= "$r->subscriber_number (".date('G:i',strtotime($r->dept_time)).", $r->pax pax, $r->notes) ";
		$response_sms .= "$r->subscriber_number ($r->dept_date " . substr ( $r->dept_time, 0, 5 ) . ", $r->pax pax, $r->notes) ";
	}
	send_sms ( $subscriber_number, $response_sms );
}

/* put some comments here */
function process_sms($text) {
	global $filehandle, $subscriber_number;
	
	$sms_tokens = explode ( PARAM_SEPARATOR, $text );
	
	switch (strtoupper ( $sms_tokens [0] )) {
		case 'SABAYTAYO' :
			sabaytayo ( $text );
			break;
		case 'WEATHER' :
			$forecast = strtoupper ( $sms_tokens [1] );
			$chk_weather = strtoupper ( $sms_tokens [2] );
			if (($forecast) == 'FORECAST') {
				$response_sms = get_forecast_weather ( $chk_weather );
				send_sms ( $subscriber_number, $response_sms );
			} else {
				$response_sms = get_current_weather ( $chk_weather );
				send_sms ( $subscriber_number, $response_sms );
			}
			break;
		case 'BOATSERVICE' :
			process_boatservice ( $text );
			break;
		
		case 'TRIPS' :
			process_trips ( $text );
			break;
	}
}

/* put some comments here */
function sabaytayo($text) {
	global $subscriber_number, $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes;
	global $filehandle, $timestamp;
	global $wpdb;
	
	split_parameters ( $text );
	if ($dept_date == '')
		$dept_date = '0000-00-00';
		
		// insert entry into sabaytayo table in database
	$wpdb->replace ( TRIPS_TABLE, array (
			'subscriber_number' => $subscriber_number,
			'port_orig' => $port_orig,
			'port_dest' => $port_dest,
			'dept_date' => $dept_date,
			'dept_time' => $dept_time,
			'pax' => $pax,
			'notes' => $notes,
			'timezone' => DEFAULT_TIMEZONE_OFFSET,
			'dept_timestamp' => strtotime ( "$dept_date $dept_time" ),
			'timestamp' => $timestamp 
	), array (
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
			'%d',
			'%d' 
	) );
	if (DEBUG) {
		echo "$timestamp: Inserting into database: $subscriber_number, $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes, " . DEFAULT_TIMEZONE_OFFSET . ", " . strtotime ( "$dept_date $dept_time" ) . ", $timestamp\n";
		fwrite ( $filehandle, "$timestamp: Inserting into database: $subscriber_number, $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes, " . DEFAULT_TIMEZONE_OFFSET . ", " . strtotime ( "$dept_date $dept_time" ) . ", $timestamp\n" );
	}
	
	// prepare query
	if ($dept_date == '0000-00-00') {
		$query .= " SELECT * FROM " . TRIPS_TABLE;
		$query .= " WHERE port_orig = '$port_orig' ";
		$query .= " AND   port_dest = '$port_dest' ";
		$query .= " AND   dept_date <> '0000-00-00' ";
		$query .= " AND   dept_timestamp - unix_timestamp() < " . TIME_WINDOW;
		$query .= " UNION ";
		$query .= " SELECT * FROM " . TRIPS_TABLE;
		$query .= " WHERE port_orig = '$port_orig' ";
		$query .= " AND   port_dest = '$port_dest' ";
		$query .= " AND   dept_date = '0000-00-00' ";
		$query .= " AND   timestamp - unix_timestamp() < " . TIME_WINDOW;
	} else {
		$query = " SELECT * FROM " . TRIPS_TABLE;
		$query .= " WHERE port_orig = '$port_orig' ";
		$query .= " AND   port_dest = '$port_dest' ";
		$query .= " AND   dept_date = '$dept_date' ";
		$query .= " AND   STR_TO_DATE(CONCAT(dept_date, ' ', dept_time), '%Y-%m-%d %H:%i:%s')  >= convert_tz(NOW(),'-4:00','+8:00') "; // *** this is hardcoded to Eastern DAYLIGHT Time - find a way to remove dependency!!!
		$query .= " UNION ";
		$query .= " SELECT * FROM " . TRIPS_TABLE;
		$query .= " WHERE port_orig = '$port_orig' ";
		$query .= " AND   port_dest = '$port_dest' ";
		$query .= " AND   dept_date = '0000-00-00' ";
		$query .= " AND   unix_timestamp() - timestamp < " . TIME_WINDOW;
	}
	if (DEBUG) {
		echo "$timestamp: SQL Query: $query\n";
		fwrite ( $filehandle, "$timestamp: SQL Query: $query\n" );
	}
	
	// add query statement into query file
	file_put_contents ( QUERY_FILE, "$query\n", FILE_APPEND | LOCK_EX );
}

/* put some comments here */
function get_current_weather($port) {
	global $wpdb;
	
	$query = " SELECT last_update, temp_current, windspeed_current, direction_current, chance_rain_current, gale_warning_current, port_desc
        FROM st_weather 
        WHERE port='" . $port . "' ";
	echo "Query = $query\n";
	
	$return = $wpdb->get_row ( $query );
	
	var_dump ( $return );
	
	$date1 = date ( 'Y-m-d', ($return->last_update + 12 * 60 * 60) );
	
	$gale_warning = $return->gale_warning_current;
	$warn = '';
	if (($gale_warning) == 1) {
		$warn = 'GALE WARNING! ';
	}
	
	$return_text = $warn . 'Current Weather for ' . $return->port_desc . ' on ' . $date1 . ' Temp: ' . $return->temp_current . ' Windspeed:' . $return->windspeed_current . 'kph ' . $return->direction_current . ' with ' . $return->chance_rain_current . '% chance of rain';
	return $return_text;
}

/* put some comments here */
function get_forecast_weather($port) {
	global $wpdb;
	
	$query = " SELECT last_update, temp_forecast, windspeed_forecast, direction_forecast, chance_rain_forecast, gale_warning_forecast, port_desc
	        FROM st_weather 
	        WHERE port='" . $port . "' ";
	echo "Query = $query\n";
	
	$return = $wpdb->get_row ( $query );
	
	var_dump ( $return );
	
	$date1 = date ( 'Y-m-d', ($return->last_update + 36 * 60 * 60) );
	
	$gale_warning = $return->gale_warning_forecast;
	$warn = '';
	if (($gale_warning) == 1) {
		$warn = 'GALE WARNING! ';
	}
	
	$return_text = $warn . 'Weather forecast for ' . $return->port_desc . ' on ' . $date1 . ' Temp: ' . $return->temp_forecast . ' Windspeed: ' . $return->windspeed_forecast . 'kph ' . $return->direction_forecast . ' with ' . $return->chance_rain_forecast . '% chance of rain';
	return $return_text;
}

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

// make sure filename is passed to PHP as parameter
if (count ( $argv ) < 2) {
	die ( "Usage: $argv[0] <filename>\n" );
}

// General prep work
require_once ('tb-sabaytayo-requirements1.php');
require_once (WP_LOAD_FILE);
global $wpdb;
global $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes;
global $filehandle, $globe, $timestamp, $subscriber_number;
$_SERVER ['HTTP_HOST'] = HTTP_HOST;

// Prep for sending SMS via Globe API
session_start ();
require_once ('api/PHP/src/GlobeApi.php');
$globe = new GlobeApi ( 'v1' );

// set up log file
$filehandle = fopen ( LOG_FILE, 'a' ) or die ( 'Cannot open file:  ' . LOG_FILE );

date_default_timezone_set ( DEFAULT_TIMEZONE );
if (DEBUG) {
	echo "Timezone = " . DEFAULT_TIMEZONE . "\n";
	fwrite ( $filehandle, "Timezone = " . DEFAULT_TIMEZONE . "\n" );
}

// ASSUMPTION: file contents follow the pattern: timestamp|||subscriber_number|||text_message
// read file and split into various components
$input_filename = $argv [1]; // add error checking here to make sure filename is legit
$contents = file_get_contents ( $input_filename, true ); // assumption: there's only 1 line of SMS in the file
$inputline = explode ( TOKEN_SEPARATOR, $contents );
$timestamp = $inputline [0];
$subscriber_number = $inputline [1];
$text = $inputline [2];

if (DEBUG) {
	echo "$timestamp: Subscriber Number = $subscriber_number, Text Message = $text\n";
	fwrite ( $filehandle, "$timestamp: Subscriber Number = $subscriber_number, Text Message = $text\n" );
}

process_sms ( $text );

fclose ( $filehandle );

/**
 * *************************************************************
 * MAIN PROGRAM - End
 * **************************************************************
 */

?>