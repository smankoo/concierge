<?php
require_once 'config.php';
function getFailoverClient($awayClient) {
	$result = mysql_query ( "select sso_username,phone_number, (select count(*) from conc_clients where failedover_sso_username = cc.sso_username) failover_count
								from conc_clients cc
								where conc_pool_name = (select conc_pool_name from conc_clients where sso_username = '" . $awayClient . "')
								and sso_username <> '" . $awayClient . "'
								and status = 'AVAILABLE'
								order by failover_count asc
								limit 1" );
	
	if (! $result) {
		die ( 'Invalid query: ' . mysql_error () );
	}
	
	if(mysql_num_rows($result) == 0) {
		// This means there's nobody AVAILABLE from that user's segment. So try any other segment.

		$result = mysql_query ( "select sso_username,phone_number, (select count(*) from conc_clients where failedover_sso_username = cc.sso_username) failover_count
									from conc_clients cc
									where sso_username <> '" . $awayClient . "'
									and status = 'AVAILABLE'
									order by failover_count asc
									limit 1" );
		if(mysql_num_rows($result) == 0) {
			// If still no rows selected, then NOBODY is AVAILABLE in the entire team. In that case, return NULL
			
			$result1 = mysql_query ( "select phone_number from conc_clients where sso_username = '" . $awayClient . "'" );
			$row1 = mysql_fetch_array ( $result1 );
			
			// Everybody is AWAY. So return self.
			$failoverClient = $awayClient . ":" . $row1 ['phone_number'];
			
			return $failoverClient;
		}
	}

	while ( $row = mysql_fetch_array ( $result ) ) {
		$failoverClient = $row ['sso_username'] . ":" . $row ['phone_number'];
	}
	if($failoverClient != $awayClient){
		return $failoverClient;		
	} else {
		return null;
	}
}
function validateClient($clientSsoUser) {
	$result = mysql_query ( "select count(*) from conc_clients where sso_username = '" . $clientSsoUser . "'" );
	$row = mysql_fetch_row ( $result );
	
	$clientCount = $row [0];
	
	if ($clientCount == 1) {
		return true;
	} else {
		return false;
	}
}

function checkinClient($clientSsoUsername, $phoneNumber, $concPool, $existentialStatus, $appVer) {
	heartBeat($clientSsoUsername);
	$defaultConcPool = "COMMON_POOL";
	if ($existentialStatus == "EXISTING_CLIENT") {
 		
 		// lastCheckinTime functionality was disabled because 1. it didn't serve any purpose and 2. it would make checkins needlessly heavy and timeconsuming
 		$lastCheckinTime="NEVER";
		
		$queryString = "UPDATE `concdb`.`conc_clients` SET `status`='AVAILABLE', `last_check_in`= now(), `last_heartbeat`= now(), `failedover_sso_username`= NULL, `failedover_phone_number`= NULL, `app_ver`= '" . $appVer . "'";
		
		if (! empty ( $concPool ) and $concPool != NULL)
			$queryString = $queryString . ", `conc_pool_name`='" . $concPool . "'";
		if (! empty ( $phoneNumber ) and $phoneNumber != NULL)
			$queryString = $queryString . ",`phone_number`='" . $phoneNumber . "'";
		
		$queryString = $queryString . " WHERE `sso_username`='" . $clientSsoUsername . "'";
		
		$result = mysql_query ( $queryString );
		
		/*
		 * Disabling logging of CHECKINs. I don't see them serving any purpose other than bloating up the database.
		 * 
		 * 
		 * 
		$queryString="INSERT INTO `concdb`.`event_log` (`event_time`, `sso_user`, `event_name`, `event_info`) VALUES (now(),'" . $clientSsoUsername . "','" . 'CHECKIN' . "','Check In')";
		$result = mysql_query ( $queryString );
			
		if (! $result) {
			die ( 'Invalid query: ' . mysql_error () );
		}
		*/
		
	} else {
		$lastCheckinTime="NEVER";
		if (empty ( $concPool ) or $concPool == NULL)
			$concPool = $defaultConcPool;
		
		$result = mysql_query ( "INSERT INTO `concdb`.`conc_clients` (`sso_username`, `phone_number`, `conc_pool_name`, `status`, `last_check_in`, `last_heartbeat`, `app_ver`)
					VALUES ('" . $clientSsoUsername . "', '" . $phoneNumber . "', '" . $concPool . "', 'AVAILABLE', now(), now(), '" . $appVer . "')" );
		
		if (! $result) {
			die ( 'Invalid query: ' . mysql_error () );
		}
	}
	
	$queryString = "SELECT sso_username FROM conc_clients where status = 'AWAY' and failedover_sso_username=sso_username";
	$result = mysql_query ($queryString);
	
	if (! $result) {
		die ( 'Invalid query: ' . mysql_error () );
	}
		
	while ( $row = mysql_fetch_array ( $result ) ) {
		$allAwayClient = $row ['sso_username'];
		$str=getFailoverClient($allAwayClient);
		$failoverClientUsername=substr($str, 0,strpos($str, ":"));
		
		markClientAway($allAwayClient,$failoverClientUsername,NULL,true);
		
	}
	
	
	If($result=TRUE)
		return "SUCCESS:" . $lastCheckinTime;
	else
		return "ERROR:" . $lastCheckinTime;
			
}

function markClientAway($clientSsoUsername, $failedOverSsoUser, $failedOverPhoneNumber,$setRefresh = FALSE) {
	if (empty($failedOverPhoneNumber) or $failedOverPhoneNumber == NULL) {
		$queryString="select phone_number from conc_clients WHERE `sso_username`='" . $failedOverSsoUser . "'";
		$result = mysql_query ( $queryString );
		$row = mysql_fetch_row ( $result );
		if (! $result) {
			echo "Query Is:";
			echo $queryString;
			die ( 'Invalid query: ' . mysql_error () );
		}
		
		
		$failedOverPhoneNumber = $row [0];
	}

	// Update the primary client	

	$queryString="UPDATE `concdb`.`conc_clients` SET `status`='AWAY', `failedover_sso_username`='" . $failedOverSsoUser . "', `failedover_phone_number`='" . $failedOverPhoneNumber . "'";
	if($setRefresh) {
		$queryString=$queryString . ", `refresh_failover`='YES' ";
	}
	$queryString=$queryString . " WHERE `sso_username`='" . $clientSsoUsername . "'";
	
	$result = mysql_query ( $queryString );
	
	if (! $result) {
		echo "Query Is:";
		echo $queryString;
		die ( 'Invalid query: ' . mysql_error () );
	}
	
	/*
	 * I have no idea why I thought it would be a good idea to quit the function here if there were no AVAILABLE clients
	 * The code that follows this block has nothing to do with any AVAILABLE clients.
	 * 
	 * Commenting out for now. We'll see if it's required for some reason.
	 * 
	$queryString="select count(*) from conc_clients where status = 'AVAILABLE'";
	$result = mysql_query ( $queryString );
	$row = mysql_fetch_row ( $result );
	
	$availableCount = $row [0];
	
	if ($availableCount == 0) {
		return true;
	}
	*/
		
	// Update the dependent clients
	$result = mysql_query ("select sso_username from `concdb`.`conc_clients` WHERE `failedover_sso_username`='" . $clientSsoUsername . "' and `failedover_sso_username` <> sso_username" );
	// echo "running query : " . "select sso_username from `concdb`.`conc_clients` WHERE `failedover_sso_username`='" . $clientSsoUsername . "'"; 
	
	while ( $row = mysql_fetch_array ( $result ) ) {
		$childClientSsoUsername= $row ['sso_username'];
		$str=getFailoverClient($childClientSsoUsername);
		$childFailoverClientSsoUsername=substr($str, 0,strpos($str, ":"));
		markClientAway($childClientSsoUsername,$childFailoverClientSsoUsername,NULL,true);
	}
	return $result;
}

function heartBeat($clientSsoUsername){
	$queryString = "UPDATE `concdb`.`conc_clients` SET `last_heartbeat` = now() WHERE `sso_username`='" . $clientSsoUsername . "'";
	mysql_query ( $queryString );
}


function refreshFailover($clientSsoUsername, $failedOverSsoUser = NULL, $failedOverPhoneNumber = NULL) {
	heartBeat($clientSsoUsername);
	
	$query_string = "select * from conc_clients where sso_username = '" . $clientSsoUsername . "'";
	$result = mysql_query ( $query_string );
	if (! $result) {
		echo "Query Is:";
		echo $queryString;
		die ( 'Invalid query: ' . mysql_error () );
	}
	
	if(mysql_num_rows($result) <> 0) {
		while ( $row = mysql_fetch_array ( $result ) ) {
			if ($failedOverSsoUser <> NULL and $row['status'] <> 'AWAY') {
				markClientAway($clientSsoUsername, $failedOverSsoUser, $failedOverPhoneNumber);
			}
			
			//In case the client we've currently failed over to isn't ONLINE anymore
			$query_string = "select * from conc_clients where sso_username = '" . $row ['failedover_sso_username'] . "'";
			$result1 = mysql_query ( $query_string );
			if (! $result) {
				echo "Query Is:";
				echo $queryString;
				die ( 'Invalid query: ' . mysql_error () );
			}
			
			$failoverClient = null;
			//$row1 = mysql_fetch_row ( $result1 );
			while ( $row1 = mysql_fetch_array ( $result1 ) ) {
				if ($row ['refresh_failover'] == 'YES') {
					$failoverClient = $row ['failedover_sso_username'] . ":" . $row ['failedover_phone_number'];
				} elseif ($row1['failedover_sso_username'] != $clientSsoUsername and $row1['status'] !='AVAILABLE') {
					$str=getFailoverClient($clientSsoUsername);
					$childFailoverClientSsoUsername=substr($str, 0,strpos($str, ":"));
					$childFailoverClientPhoneNumber=substr($str, strpos($str, ":"));
					if($childFailoverClientSsoUsername != null and $childFailoverClientSsoUsername != "") {
						// If a another client is found to be ONLINE, failover to it
						markClientAway($clientSsoUsername,$childFailoverClientSsoUsername,$childFailoverClientPhoneNumber,true);
						$failoverClient =  $childFailoverClientSsoUsername . ":" . $childFailoverClientPhoneNumber;
					} {
						//If no other client is ONLINE, failover to self
						markClientAway($clientSsoUsername,$clientSsoUsername,NULL,true);
						$failoverClient = $clientSsoUsername . ":" . $row['phone_number'];
					}
				}
			}
			return $failoverClient;
		}
	} 
}

function refreshConsumed($clientSsoUsername) {
	$queryString = "UPDATE `concdb`.`conc_clients` SET `refresh_failover`='NO' WHERE `sso_username`='" . $clientSsoUsername . "'";
	
	$result = mysql_query ( $queryString );
	if (! $result) {
		echo "Query Is:";
		echo $queryString;
		die ( 'Invalid query: ' . mysql_error () );
	}
	return $result;
}

function offlineClient($clientSsoUser){
	$queryString = "UPDATE `concdb`.`conc_clients` SET `status`='OFFLINE', `failedover_sso_username`='', `failedover_phone_number`='', `refresh_failover`='NO'  WHERE `sso_username`='" . $clientSsoUser . "'";
	
	$result = mysql_query ( $queryString );
	if (! $result) {
		echo "Query Is:";
		echo $queryString;
		die ( 'Invalid query: ' . mysql_error () );
	}

	$queryString="INSERT INTO `concdb`.`event_log` (`event_time`, `sso_user`, `event_name`, `event_info`) VALUES (now(),'" . $clientSsoUser . "','OFFLINE','Client marked OFFLINE')";
	$result = mysql_query ( $queryString );
	if (! $result) {
		echo "Query Is:";
		echo $queryString;
		die ( 'Invalid query: ' . mysql_error () );
	}
	return $result;
}
?>