<?php
require_once 'config.php';
$table = 'conc_clients';

// sending query
$result = mysql_query("select * from (SELECT * FROM conc_clients where status = 'AVAILABLE' order by conc_pool_name, last_check_in desc) cc1
union
select * from (SELECT * FROM conc_clients where status <> 'AVAILABLE' order by status, last_check_in desc) cc2");
if (!$result) {
    die("Query to show fields from table failed");
}

$fields_num = mysql_num_fields($result);

echo "<html><style>
#conc_clients {
    font-family: \"Trebuchet MS\", Arial, Helvetica, sans-serif;
	border-collapse: collapse;
	table-layout: fixed;
}
#conc_clients th{
	white-space: nowrap;
	font-size: 0.9em;
	text-align: center;
	padding: 5px 5px 5px 5px;
	color: #000000;
	width: 13px;
    color: #FFFFFF;
    border-collapse: collapse;
    background-color: #303030;
}

#conc_clients td{
	font-size: 0.8em;
	overflow: hidden;
    padding: 5px 5px 5px 5px;
	color: #FFFFFF;
	background-color: #007A00;
	text-align: center;
}

#conc_clients tr.alt td {
	background-color: #339533;
}

#conc_clients tr > td:first-child {
	padding: 2px 10px 2px 10px;
	text-align: left;
}
#conc_clients td.in_shift td{
	background-color: #FFFFFF;
	color: 000000;
}

h1{
		font-family: \"Trebuchet MS\", Arial, Helvetica, sans-serif;
		font-size: 1.1em;
}

</style>
		<head><title>Concierge Clients</title></head>";
echo "<h1>Table: {$table}</h1>";
echo "<table id=\"conc_clients\"><tr>";
// printing table headers
for($i=0; $i<$fields_num; $i++)
{
    $field = mysql_fetch_field($result);
    echo "<th>{$field->name}</th>";
}
echo "</tr>\n";

$alt=false;
// printing table rows
while($row = mysql_fetch_row($result))
{
	if ($alt) { 
		echo "<tr class=\"alt\">";
		$alt = false;
	} else {
		echo "<tr>";
		$alt = true;
	}

    // $row is array... foreach( .. ) puts every element
    // of $row to $cell variable
    foreach($row as $cell)
        echo "<td>$cell</td>";

    echo "</tr>\n";
}
mysql_free_result($result);
echo "</table></body></html>"

?>
