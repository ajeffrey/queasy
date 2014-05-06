<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Queasy\Connection;
use Queasy\Sql\SqlBuilder;

$connection = new Connection(new SqlBuilder, 'mysql', array(
	'username' => 'root',
	'password' => '--removed--',
	'database' => 'test_basis_orm',
	'host' => 'localhost',
));

$query = $connection->query('projects')->withFields(array($connection->expr('*')));
$query->where(array('project_id' => 1));
//echo $query->toSql();die;
var_dump($query->all());