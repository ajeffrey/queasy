<?php
namespace Queasy;
use Queasy\Query\QueryIterator;
use Queasy\Query\SelectQuery;
use Queasy\Expression;
use PDO;

class ConnectionException extends \RuntimeException {};

class Connection {
	static $MYSQL_PARAMS = array('username', 'password', 'database', 'host');

	private $builder;
	private $connection;

	public function __construct($builder, $type, $params = array()) {
		$this->builder = $builder;

		switch((string)$type) {
			case 'mysql':

				// Ensure we have all the required connection params
				$missing_params = array_diff(self::$MYSQL_PARAMS, array_keys($params));
				if($missing_params) {
					throw new ConnectionException('Missing connection parameters: ' . implode(', ', $missing_params));
				}

				// Attempt to connect to the database
				try {
					$dsn = 'mysql:dbname=' . $params['database'] . ';host=' . $params['host'];
					$this->connection = new PDO($dsn, $params['username'], $params['password']);
				    $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


				} catch(Exception $e) {
					throw new ConnectionException('An error occurred while trying to connect to the database', 0, $e);
				}

				break;

			case 'test':
				$this->connection = NULL;
				break;

			default:
				throw new ConnectionException('Connection type "' . $type . '" not supported');
		}
	}

	public function getRowCount() {
		return $this->getColumn('SELECT FOUND_ROWS()');
	}

	public function getColumn($sql, $bindings = array()) {
		$query = $this->executeSql($sql, $bindings);
		return $query->fetchColumn(0);
	}

	public function getSql($query) {
		return $this->builder->build($query);
	}

	public function expr($expr) {
		return new Expression($expr);
	}

	/**
	 * Execute the query $query, returning a collection of objects
	 * @param Queasy\SelectQuery $query
	 * @return array
	 */
	public function fetchAll($query) {
		$rowset = $this->execute($query);
		return $rowset->fetchAll();
	}

	public function fetch($query) {
		$rowset = $this->execute($query);
		return $rowset->fetch();
	}

	public function getIterator($query) {
		$rowset = $this->execute($query);
		return $rowset;
	}

	public function execute($query) {
		$sql = $this->getSql($query);
		$rowset = $this->executeSql($sql->getSql(), $sql->getBindings());
		return new QueryIterator($rowset, $query->class);
	}

	public function query($from, $alias = NULL, $class = NULL) {
		return new SelectQuery($this, $from, $alias, $class);
	}

	private function executeSql($sql, $bindings = array()) {
		$stmt = $this->connection->prepare($sql);
		$stmt->execute($bindings);
		return $stmt;
	}
};
