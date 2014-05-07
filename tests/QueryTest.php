<?php

class QueryTest extends PHPUnit_Framework_TestCase {

	public function setUp() {
		$this->conn = new \Queasy\Connection(new \Queasy\Sql\SqlBuilder, 'test');
	}

	public function testBasicWhere() {
		$q = $this->conn->query('projects')->withFields($this->conn->expr('*'))->where(array(
			'project_id' => 1
		));

		$this->assertEquals(
			$this->simplify($q->toSql()),
			$this->simplify(file_get_contents(__DIR__ . '/fixtures/basic_where.sql'))
		);
	}

	public function testWhereOr() {
		$q = $this->conn->query('projects')->withFields($this->conn->expr('*'))->where(array(
			'OR',
			'a' => 1,
			'b' => 2,
		));

		$this->assertEquals(
			$this->simplify($q->toSql()),
			$this->simplify(file_get_contents(__DIR__ . '/fixtures/where_or.sql'))
		);
	}

	public function testBasicJoin() {
		$q = $this->conn->query('tasks')->join(
			'projects',
			'p',
			array('p.project_id' => 'dt.project_id'),
			array('project' => 'name')
		)->withFields(array('task_id'));

		$this->assertEquals(
			$this->simplify($q->toSql()),
			$this->simplify(file_get_contents(__DIR__ . '/fixtures/basic_join.sql'))
		);
	}

	private function simplify($sql) {
		return trim(preg_replace('/\s+/', ' ', $sql));
	}


};