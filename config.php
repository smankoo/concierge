<?php

$hostname="localhost";
$username="root";
$password="root";
$dbname="concdb";

mysql_connect($hostname, $username, $password) OR DIE('Unable to connect to database! Please try again later.');
mysql_select_db( $dbname ) or die( 'Error'. mysql_error() );
?>