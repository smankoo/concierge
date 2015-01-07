<?php
require_once 'dbconfig.php';

$result = mysql_query("SELECT * FROM conc_clients");

while($row = mysql_fetch_array($result)) {
  echo $row['sso_username'] . " " . $row['phone_number'];
  echo "<br>";
}

mysql_close();
?>