<?php
header ( "Content-Type:application/json" );
include ("functions.php");

if (! empty ( $_GET ['clientSsoUser'] ) and ! empty ( $_GET ['event'] )) {
	$clientSsoUser = $_GET ['clientSsoUser'];
	$clientSsoUser = strtoupper ( $clientSsoUser );
	$event = $_GET ['event'];
	$event = strtoupper ( $event );
	
	if (empty ( $clientSsoUser ) or empty ( $event )) {
		deliver_response ( 100, "NULL client or event in request", NULL );
		exit ();
	}
	if ($event == "GETFAILOVER") {
		if (! empty ( $_GET ['appVer'] )) {
			$appVer = $_GET ['appVer'];
		}
		if (empty ( $appVer ))
			$appVer = "UNKNOWN";
			
		if (! validateClient ( $clientSsoUser )) {
			deliver_response ( 110, "Client not found in concierge database", NULL );
		} else {
			$failoverClient = getFailoverClient ( $clientSsoUser );
			$failoverClient = strtoupper ( $failoverClient );
			deliver_response ( 200, "Failover Client Found", $failoverClient);

			// Log stuff in event_log for stats
			$queryString="INSERT INTO `concdb`.`event_log` (`event_time`, `sso_user`, `event_name`, `event_info`, `app_ver`) VALUES (now(),'" . $clientSsoUser . "','" . $event . "','Failover Client returned : " . $failoverClient . "','" . $appVer . "')";
			mysql_query ( $queryString );
				
		}
	} elseif ($event == "CHECKIN") {
		if (! empty ( $_GET ['phoneNumber'] )) {
			$phoneNumber = $_GET ['phoneNumber'];
		}
		if (! empty ( $_GET ['concPool'] )) {
			$concPool = $_GET ['concPool'];
		}
		if (! empty ( $_GET ['appVer'] )) {
			$appVer = $_GET ['appVer'];
		}
		if (empty ( $phoneNumber ))
			$phoneNumber = NULL;
		if (empty ( $concPool ))
			$concPool = NULL;
		if (empty ( $appVer ))
			$appVer = "UNKNOWN";
		
		if (validateClient ( $clientSsoUser )) {
			$checkinOutput=checkinClient ( $clientSsoUser, $phoneNumber, $concPool, "EXISTING_CLIENT" , $appVer);
			
			$checkinResult=substr($checkinOutput, 0, (strpos($checkinOutput,":")));
			$lastCheckinTime=substr($checkinOutput, strpos($checkinOutput,":") + 1);
			
			if($checkinResult=="SUCCESS")
				$checkedIn=true;
			else
				$checkedIn=false;
				
			if ($checkedIn) {
				deliver_response ( 200, "Client checked in", "LAST_CHECKIN:" . $lastCheckinTime );
			} else {
				deliver_response ( 130, "Error occured while trying to check in client", $clientSsoUser );
			}
		} else {
			if ($phoneNumber == NULL) {
				deliver_response ( 120, "Client " . $clientSsoUser . "does not exist in Concierge database. Must specify Phone Number.", NULL );
			} else {
				if (checkinClient ( $clientSsoUser, $phoneNumber, $concPool, "NEW_CLIENT" , $appVer)) {
					deliver_response ( 200, "Client checked in", "LAST_CHECKIN:NEVER" );
				
				} else {
					deliver_response ( 130, "Error occured while trying to check in client", $clientSsoUser );
				}
			}
		}
	} elseif ($event == "MARKAWAY") {
		if (! validateClient ( $clientSsoUser )) {
			deliver_response ( 110, "Client not found in concierge database", NULL );
		} else {
			$failedOverSsoUser = NULL;
			$failedOverPhoneNumber = NULL;
			if (! empty ( $_GET ['failedOverSsoUser'] )) {
				$failedOverSsoUser = $_GET ['failedOverSsoUser'];
				$failedOverSsoUser = strtoupper ( $failedOverSsoUser );
			}
			if (! empty ( $_GET ['appVer'] )) {
				$appVer = $_GET ['appVer'];
			}
			if (empty ( $appVer ))
				$appVer = "UNKNOWN";
			
			if (empty ( $failedOverSsoUser ) or $failedOverSsoUser == NULL) {
				deliver_response ( 101, "NULL failedOverSsoUser in request", NULL );
			} else {
				if (! empty ( $_GET ['failedOverPhoneNumber']))
						$failedOverPhoneNumber=$_GET ['failedOverPhoneNumber'];
				
				if (markClientAway ( $clientSsoUser, $failedOverSsoUser, $failedOverPhoneNumber )) {
					deliver_response ( 200, "Client marked away", $clientSsoUser . " failed over to " . $failedOverSsoUser );

					// Log stuff in event_log for stats
					$queryString="INSERT INTO `concdb`.`event_log` (`event_time`, `sso_user`, `event_name`, `event_info`, `app_ver`) VALUES (now(),'" . $clientSsoUser . "','" . $event . "','Client went AWAY. Failed over to : " . $failedOverSsoUser . ":" . $failedOverPhoneNumber . "','" . $appVer . "')";
					mysql_query ( $queryString );
				} else {
					deliver_response ( 130, "Error occured while trying to mark client away", $clientSsoUser );
				}
			}
		} 
	} elseif ($event == "MARKOFFLINE") {
		$result = offlineClient($clientSsoUser);

		if($result = true){
			deliver_response ( 200, "User " . $clientSsoUser . " marked offline.", $result);
		} else {
			deliver_response ( 100, "User " . $clientSsoUser . " NOT marked offline.", $result);
		}
	} elseif ($event == "REFRESHFAILOVER") {
		$failedOverSsoUser = NULL;
		$failedOverPhoneNumber = NULL;
		if (! empty ( $_GET ['failedOverSsoUser'] )) {
			$failedOverSsoUser = $_GET ['failedOverSsoUser'];
			$failedOverSsoUser = strtoupper ( $failedOverSsoUser );
		}
		
		if (! empty ( $_GET ['failedOverPhoneNumber']))
			$failedOverPhoneNumber=$_GET ['failedOverPhoneNumber'];
		
		$refreshResult = refreshFailover($clientSsoUser, $failedOverSsoUser, $failedOverPhoneNumber);
		if($refreshResult <> null){
			deliver_response ( 200, "New configuration available. Client should refresh.", $refreshResult);
		} else {
			deliver_response ( 200, "No new configuration available", "NO_REFRESH");
		}
	} elseif ($event == "REFRESHCONSUMED") {
		if(refreshConsumed($clientSsoUser))
			deliver_response ( 200, "Concierge database updated", "OK" );
		else
			deliver_response (100, "Trouble updating Concierge database", "Not OK" );
	} else {
		deliver_response ( 180, "Invalid Event", $event );
	}
} else {
	deliver_response ( 100, "invalid request", NULL );
}
function deliver_response($status, $status_message, $data) {
	header ( "HTTP/1.1 $status $status_message" );
	
	$response ['status'] = $status;
	$response ['status_message'] = $status_message;
	$response ['data'] = $data;
	
	$json_response = json_encode ( $response );
	echo $json_response;
}
?>