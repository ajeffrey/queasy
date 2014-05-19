<?php
namespace Queasy\Query;
use Iterator;

class QueryIterator implements Iterator {
	private $query = NULL;
	private $rows = array();
	private $index = 0;

	private $class = NULL;

	public function __construct($query, $class = NULL) {
		$this->query = $query;
		$this->class = $class;
	}

	public function current() {
		return $this->at($this->index);
	}

	public function key() {
		return $this->index;
	}

	public function rewind() {
		$this->index = 0;
	}

	public function next() {
		++$this->index;
	}

	public function valid() {
		return $this->at($this->index) !== NULL;
	}

	public function fetchAll() {
		$rows = array();
		foreach($this as $row) {
			$rows[] = $row;
		}

		return $rows;
	}

	public function fetch() {
		$ret = $this->current();
		$this->next();
		return $ret;
	}

	private function at($index) {
		if($this->class) {
			$class = $this->class;

			while($index >= count($this->rows)) {
				$row = $this->query->fetchObject();
				if(!$row) {
					return NULL;
				}

				$this->rows[] = new $class($row);
			}

		} else {
			while($index >= count($this->rows)) {
				$row = $this->query->fetchObject();
				if(!$row) {
					return NULL;
				}

				$this->rows[] = $row;
			}
		}

		return $this->rows[$index];
	}

};
