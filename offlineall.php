<?php

include ("functions.php");

$result = mysql_query ("select sso_username from `concdb`.`conc_clients`" );

while ( $row = mysql_fetch_array ( $result ) ) {
	$clientSsoUser= $row ['sso_username'];
	echo "Marking offline " . $clientSsoUser . "...";
	if(offlineClient ( $clientSsoUser )) {
		echo " Done <br />";
	} else {
		echo " Error <br />";
	}
}

?>