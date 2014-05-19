<?php
namespace Queasy\Query;
use IteratorAggregate;

/*
 * WHERE condition mapping
	array(
 		'AND',
 		'news_id' => '123',
 		array(
 			'OR',
			array('datetime', '>', $date),
			'sticky' => '1',
 		)
 	)

 *	news_id = 123 AND (datetime > '$date' OR sticky = 1)
 */

class SelectQueryException extends \RuntimeException {};

class SelectQuery implements IteratorAggregate {

	/* SQL fields */
	protected $calc_rows = FALSE;
	protected $fields = array();

	protected $from = NULL;
	protected $derived = NULL;

	protected $joins = array();
	protected $where = array();

	protected $group_by = array();
	protected $order = NULL;
	protected $offset = 0;
	protected $limit = NULL;

	/**
	 * @var $class Class to generate instances of when running queries
	 */
	private $class = NULL;

	/**
	 * @var $cached_count Cached result of FOUND_ROWS() from last query
	 * Returned by count()
	 */
	private $cached_count = NULL;

	/**
	 * @var $cache Cache of query results, keyed by function name
	 */
	private $cache = array();

	public function __construct($connection, $from, $alias = NULL, $class = NULL) {
		$this->connection = $connection;

		$this->class = $class;

		$this->from = $alias ?: 'dt';
		$this->derived = $from;
	}

	public function __toString() {
		return $this->toSql();
	}

	public function getIterator() {
		return $this->each();
	}

	public function __get($name) {
		return $this->$name;
	}

	/*====================================
	 * Functions for adding to the query *
	 ====================================*/

	public function getFromAlias() {
		return $this->derived;
	}

	public function getFrom() {
		return $this->from;
	}

	 public function setFrom($from, $dt = NULL) {
	 	$this->derived = $from;
	 	if($dt) $this->from = $dt;
	 }

	 public function getFields() {
	 	return $this->fields;
	 }

	 public function setFields($fields) {
	 	$this->fields = array();
	 	return $this->withFields($fields);
	 }

	/**
	 * Addselected fields to the query
	 * @param array $fields array of fields to pull out from query
	 * @param string|null $table alias of join table to pull fields from, or NULL to pull from primary table
	 * @return Queasy\Query\SelectQuery $this
	 */
	public function withFields($fields, $table = NULL) {
		if(!is_array($fields)) {
			$fields = array($fields);
		}

		$prefix = $table ?: $this->from;

		foreach($fields as $key => $field) {
			if(is_string($key)) {
				$this->fields[$key] = array('table' => $prefix, 'field' => $field);

			} else {
				$this->fields[] = array('table' => $prefix, 'field' => $field);
			}
		}

		return $this;
	}

	/**
	 * Enable SQL_CALC_NUM_ROWS for this query, with the count() function providing the result.
	 * @return Queasy\Query\SelectQuery $this
	 */
	public function withCount() {
		$this->calc_rows = TRUE;
		return $this;
	}

	public function setJoins($joins) {
		$this->joins = $joins;
	}

	/**
	 * Left join $table onto query on $on conditions, and add $fields to selected fields
	 * @param string $table table name or derived table to query from
	 * @param string $alias table alias to use
	 * @param array $on conditions to join on
	 * @param array $fields fields from table to include in output
	 * @return Queasy\Query\SelectQuery $this
	 */
	public function leftJoin($table, $alias, $on, $fields = array()) {
		$this->joins[$alias] = array(
			'type' => 'LEFT',
			'alias' => $alias,
			'table' => $table,
			'on' => $on,
		);

		return $this->withFields($fields, $alias);
	}

	/**
	 * Join $table onto query on $on conditions, and add $fields to selected fields
	 * @param string $table table name or derived table to query from
	 * @param string $alias table alias to use
	 * @param array $on conditions to join on
	 * @param array $fields fields from table to include in output
	 * @return Queasy\Query\SelectQuery $this
	 */
	public function join($table, $alias, $on, $fields = array()) {
		$this->joins[$alias] = array(
			'type' => 'INNER',
			'alias' => $alias,
			'table' => $table,
			'on' => $on,
			'conditions' => array(),
		);

		return $this->withFields($fields, $alias);
	}

	public function setWhere($where) {
		$this->where = $where;
	}

	/**
	 * Set initial query conditions
	 * @param array $conditions conditions to constrain query by
	 * @return Queasy\Query\SelectQuery $this
	 */
	public function where($type, $conditions = NULL) {
		if(!$conditions) {
			$conditions = $type;
			$type = 'AND';
		}

		if(isset($conditions[0]) && in_array($conditions[0], array('AND', 'OR'))) {
			$conditions = array($conditions);
		}
		
		array_unshift($conditions, $type);
		$this->setWhere($conditions);
		return $this;
	}

	/**
	 * OR the internally stored conditions with $conditions
	 * @param array $conditions conditions to constrain query by
	 * @return Queasy\Query\SelectQuery $this
	 */
	public function orWhere($conditions) {
		if(isset($conditions[0]) && in_array($conditions[0], array('AND', 'OR'))) {
			$conditions = array($conditions);
		}

		array_unshift($conditions, 'OR');
		array_push($conditions, $this->where);
		$this->setWhere($conditions);
		return $this;
	}

	/**
	 * AND the internally stored conditions with $conditions
	 * @param array $conditions conditions to constrain query by
	 * @return Queasy\Query\SelectQuery $this
	 */
	public function andWhere($conditions) {
		if(isset($conditions[0]) && in_array($conditions[0], array('AND', 'OR'))) {
			$conditions = array($conditions);
		}

		array_unshift($conditions, 'AND');
		array_push($conditions, $this->where);
		$this->setWhere($conditions);
		return $this;
	}

	/**
	 * Apply ordering to the results
	 * @param array $fields a key => value array where key is the field name and value is either 'ASC' or 'DESC'
	 * @return Queasy\Query\SelectQuery $this
	 */
	public function orderBy($fields = array()) {
		$this->order = $fields;
		return $this;
	}

	/**
	 * Offset the result set by $offs records
	 * @param integer $offs integer of number of records to offset by
	 * @return Queasy\Query\SelectQuery $this
	 */
	public function offset($offs) {
		$this->offset = $offs;
		return $this;
	}

	/**
	 * Limit the result set to $limit records
	 * @param integer $limit the number of records to return
	 * @return Queasy\Query\SelectQuery $this
	 */
	public function limit($limit) {
		$this->limit = $limit;
		return $this;
	}

	/**
	 * Group result records by $fields
	 * @param array $fields fields to uniquely group records by
	 * @return Queasy\Query\SelectQuery $this
	 */
	public function groupBy($fields = array()) {
		if(!is_array($fields)) {
			$fields = array($fields);
		}

		$this->group_by = $fields;
		return $this;
	}

	/*====================================================================
	 * Functions related to to executing the query and returning results *
	 ====================================================================*/

	 /**
	  * Clear the cached results from either a specific function or all results functions
	  * @param string|null $fn Function to clear the cache for, or NULL to clear all function caches
	  */
	 public function clearCache($fn = NULL) {
	 	if($fn) {
	 		unset($this->cache[$fn]);

	 	} else {
		 	$this->cache = array();
		 }

	 	return $this;
	 }
	 /**
	  * Return an iterator for looping through rows
	  * @return SelectQueryIterator
	  */
	 public function each() {
	 	$this->clearCache('run');
		return $this->connection->getIterator($this, $this->class);
	 }

	/**
	 * Return all records from the query
	 * @return array
	 */
	public function all($class = NULL) {
		$class = $class ?: $this->class;

		if(isset($this->cache[__FUNCTION__])) {
			return $this->cache[__FUNCTION__];
			
		} else {
			$result = $this->connection->fetchAll($this, $class);
			$this->storeResult($result, __FUNCTION__);
			return $result;
		}
	}

	/**
	 * Return the first record from the query
	 * @return object
	 */
	public function first($class = NULL) {
		$class = $class ?: $this->class;

		if(isset($this->cache[__FUNCTION__])) {
			return $this->cache[__FUNCTION__];
			
		} else {
			$this->limit(1);
			$result = $this->connection->fetchOne($this, $class);
			$this->storeResult($result, __FUNCTION__);
			return $result;
		}
	}

	/**
	 * Return the stored number of rows for the last execution of this query
	 * @return int the number of rows the last execution returned, or the FOUND_ROWS of calc_rows was used
	 */
	public function count() {
		if($this->cached_count === NULL) {
			throw new SelectQueryException('You must run this query before retrieving its row count');
		}

		return $this->cached_count;
	}

	private function storeResult($result, $fn) {
		if($fn == 'all' || $fn == 'run') {
			$this->cached_count = $this->connection->getRowCount();
		}

		$this->cache[$fn] = $result;
	}

	/**
	 * Construct the SQL string for the query
	 * @return string
	 */
	public function toSql() {
		return $this->connection->getSql($this);
	}
	
};
