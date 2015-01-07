<?php

include ("functions.php");

$result = mysql_query ("select sso_username from `concdb`.`conc_clients`" );

while ( $row = mysql_fetch_array ( $result ) ) {
	$clientSsoUser= $row ['sso_username'];
	echo "Checking in " . $clientSsoUser . "...";
	if(checkinClient ( $clientSsoUser, NULL, NULL, "EXISTING_CLIENT" )) {
		echo " Done <br />";
	} else {
		echo " Error <br />";
	}
}

?>