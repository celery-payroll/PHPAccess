<?php
require 'vendor/autoload.php';

$access = new PHPAccess\PHPAccess('example.mdb');

//Get tables in access database
$tables = $access->getTables();
echo "Tables:\n";
print_r($tables);

//Display contents of the tables
foreach($tables as $table) {
	echo "Table '$table' columns:\n";
	print_r($access->getColumns($table));
	
	echo "Table '$table' data:\n";
	print_r($access->getData($table));
}

//Display contents of the tables using a SQL query
echo "Table 'test' data using a query:\n";
print_r($access->getData($table, "SELECT * FROM test WHERE description = 'test123'"));

//Export table structures as SQL queries
echo "SQL-dump:\n";
echo $access->getDatabaseSql('mysql');


//etc...