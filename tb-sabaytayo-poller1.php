<?php

/***************************************************************
 Project: Sabay Tayo
 File: sabaytayo-process.php
 Created by: Kenneth See
 Description:
 When executed, checks a directory for files to process. When files are found, files are handled one by one by passing them to another php script that directly processes them and outputs a file in the end containing SQL queries. Processed files are moved to another directory afterwards. When all the files are processed and the SQL queries file finished, it starts sending the queries to the database. SMSes are sent to users based on query results. When all queries have been executed, the query file is removed.
 
 During execution, a temporary lock file is created in the default directory to prevent other instances of this program from running and causing collision. The temporary lock file is removed at the end of the execution.
 
 Format of file: timestamp|||subscriber number|||text message. 
 Assumption: file has already been checked for errors and possible cracking attempts. 
 ***************************************************************/

/**
 * *************************************************************
 * CONSTANTS - Begin
 * **************************************************************
 */

// define('APP_NAME', 'sabaytayo' );
// define('DEBUG', true );
// define('WORKING_DIR', '/kunden/homepages/41/d579830064/htdocs/clickandbuilds/SabayTayo/' );
// define('INCOMING_TEXTS_DIR', WORKING_DIR.'tb-in/' );
// define('PROCESSED_TEXTS_DIR', WORKING_DIR.'tb-proc/' );
// define('LOG_DIR', WORKING_DIR.'tb-logs/' );
// define('LOG_FILE', LOG_DIR.APP_NAME.'.log' );
// define('GLOBE_APP_NUMBER', '0465');
// define('LOCK_FILE', WORKING_DIR.APP_NAME.'.lock');
// define('QUERY_FILE', WORKING_DIR.'queries.sql' );
// define('RESPONSE_SMS_PRE', 'TY from SABAYTAYO! ' );
// define('RESPONSE_SMS_POST', '' );
// define('PHP_FULL_PATH', '/usr/bin/php5.5-cli' );
// define('ST_PROCESSOR_FILE', WORKING_DIR.'tb-sabaytayo-processor.php' );
// define('SUBSCRIBER_TABLE', 'st_member_mobiles' );

/**
 * *************************************************************
 * CONSTANTS - End
 * **************************************************************
 */

/**
 * *************************************************************
 * MAIN PROGRAM - Begin
 * **************************************************************
 */

// General prep work
$_SERVER ['HTTP_HOST'] = HTTP_HOST;
require_once ('tb-sabaytayo-requirements1.php');
require_once (WP_LOAD_FILE);
global $wpdb;
global $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes;

// Prep for sending SMS via Globe API
session_start ();
require_once ('api/PHP/src/GlobeApi.php');
$globe = new GlobeApi ( 'v1' );

// set up log file
$filehandle = fopen ( LOG_FILE, 'a' ) or die ( 'Cannot open file:  ' . LOG_FILE );
$timestamp = time ();

// check for presence of lock file; quit program if lock file exists
$running = file_exists ( LOCK_FILE );
if ($running) {
	if (DEBUG) {
		echo "$timestamp: Process already running.\n";
		fwrite ( $filehandle, "$timestamp: Process already running.\n" );
	}
	exit ();
}

// initialise lock file
file_put_contents ( LOCK_FILE, $timestamp );
if (DEBUG) {
	echo "Lock file ". LOCK_FILE . " created.\n";
}

// initialise query file
file_put_contents ( QUERY_FILE, '' );
if (DEBUG) {
	echo "Query file ". QUERY_FILE . " created.\n";
}

// the MEAT
$files_to_process = glob ( INCOMING_TEXTS_DIR . APP_NAME . ".*" );
while ( count ( $files_to_process ) > 0 ) {
	exec ( PHP_FULL_PATH . ' ' . ST_PROCESSOR_FILE . ' ' . $files_to_process [0] );
	exec ( "mv $files_to_process[0] " . PROCESSED_TEXTS_DIR );
	$files_to_process = glob ( INCOMING_TEXTS_DIR . APP_NAME . ".*" );
}

// read queries file into array
$queries = file ( QUERY_FILE );
$queries = array_unique ( $queries );

// query database and notify passengers with matching itineraries
$c = count ( $queries );
for($i = 0; $i < $c; $i ++) {
	$q = array_shift ( $queries );
	// run query
	$results = $wpdb->get_results ( $q );
	if (DEBUG) {
		fwrite ( $filehandle, "$timestamp: Query Results: " . print_r ( $results ) . "\n" );
	}
	// build response SMS
	$response_sms = RESPONSE_SMS_PRE;
	$response_sms .= "The ff people are travelling from {$results[0]->port_orig} to {$results[0]->port_dest}: ";
	$subscribers = array ();
	// reset($subscribers);
	foreach ( $results as $r ) {
		array_push ( $subscribers, $r->subscriber_number );
		// $response_sms .= "$r->subscriber_number (".date('G:i',strtotime($r->dept_time)).", $r->pax pax, $r->notes) ";
		$response_sms .= "$r->subscriber_number ($r->dept_date " . substr ( $r->dept_time, 0, 5 ) . ", $r->pax pax, $r->notes) ";
	}
	// send response SMS to subscribers
	for($i = 0; $i < sizeof ( $subscribers ); $i ++) {
		send_sms ( $subscribers [$i], $response_sms );
	}
}

// delete query file
unlink ( QUERY_FILE );

// delete lock file
unlink ( LOCK_FILE );

// properly close log file
fclose ( $filehandle );
?>
