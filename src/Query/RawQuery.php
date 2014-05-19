<?php
namespace Queasy\Query;

class RawQuery {
	private $sql = '';
	private $bindings = array();

	private $index = 0;

	public function __construct($sql = '', $bindings = array()) {
		$this->sql = $sql;
		$this->bindings = $bindings;
	}

	public function __toString() {
		return str_replace(array_keys($this->bindings), array_values($this->bindings), $this->sql);
	}

	public function getSql() {
		return $this->sql;
	}

	public function getBindings() {
		return $this->bindings;
	}

	public function append($sql) {
		$this->sql .= $sql;
	}

	public function bind($val) {
		while(isset($this->bindings[':b' . $this->index])) {
			++$this->index;
		}

		$this->bindings[':b' . $this->index] = $val;
		return ':b' . ($this->index++);
	}

	public function bindInt($val) {
		return intval($val);
	}
	
};
