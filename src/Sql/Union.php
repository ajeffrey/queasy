<?php
namespace Queasy\Sql;

class Union {
	private $queries = array();
	private $class = NULL;

	public function __construct($connection, $queries, $class = NULL) {
		$this->connection = $connection;
		$this->queries = $queries;
		$this->class = $class;
	}

	public function __get($name) {
		return $this->$name;
	}

	public function each() {
		$args = func_get_args();
		$fn = array_shift($args);
		
		foreach($this->queries as $query) {
			call_user_func_array(array($query, $fn), $args);
		}
	}

	public function toSql() {
		return $this->connection->getSql($this);
	}

	/**
	 * Return all records from the query
	 */
	public function all() {
		return $this->connection->fetchAll($this, $this->class);
	}
};
