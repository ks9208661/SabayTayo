<?php

/****************************************************************
 * Sabay Tayo (c)
 * tb-sabaytayo-consent.php
 * Created by: Kenneth See
 ****************************************************************/

/**
 * *************************************************************
 * MAIN PROGRAM
 * **************************************************************
 */
require_once ('tb-sabaytayo-requirements1.php');
require_once (WP_LOAD_FILE);
global $wpdb;

// set up log file
$filehandle = fopen ( LOG_FILE, 'a' ) or die ( 'Cannot open file:  ' . LOG_FILE );

// ##################################### Switch in Server
$access_token = $_GET ["access_token"];
$subscriber_number = "+63" . $_GET ["subscriber_number"];
// $access_token = "3djfkdjfkdjf";
// $subscriber_number = "+639293701284";

// save access token and subscriber number in database
$query = " SELECT * FROM " . SUBSCRIBER_TABLE . " WHERE subscriber_number = '$subscriber_number' ";
if (DEBUG) {
	fwrite ( $filehandle, "Query = $query\n" );
	echo "Query = $query\n";
}

$row = $wpdb->get_row ( $query );
if ($row == null) {
	$wpdb->insert ( SUBSCRIBER_TABLE, array (
			'subscriber_number' => $subscriber_number,
			'current' => 1,
			'access_token' => $access_token,
			'activation_timestamp' => time () 
	), array (
			'%s',
			'%d',
			'%s',
			'%d' 
	) );
} else {
	// update access_token of existing entry
	$wpdb->update ( SUBSCRIBER_TABLE, array (
			'access_token' => $access_token,
			'activation_timestamp' => time () 
	), array (
			'member_id' => $row->member_id 
	), array (
			'%s', 
			'%d' 
	), array (
			'%d' 
	) );
}

if (DEBUG) {
	echo "Member added or modified. Subscriber Number = $subscriber_number , Access Token = $access_token\n";
	fwrite ( $filehandle, "Member added or modified. Subscriber Number = $subscriber_number , Access Token = $access_token\n" );
}

fclose ( $filehandle );

?>
