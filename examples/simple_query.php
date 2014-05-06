<?php
require __DIR__ . '/../src/Expression.php';
require __DIR__ . '/../src/Connection.php';
require __DIR__ . '/../src/Query/QueryIterator.php';
require __DIR__ . '/../src/Query/RawQuery.php';
require __DIR__ . '/../src/Query/SelectQuery.php';
require __DIR__ . '/../src/Sql/SqlBuilder.php';
use Queasy\Connection;
use Queasy\Query\SelectQuery;
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