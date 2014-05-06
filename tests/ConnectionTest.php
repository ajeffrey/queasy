<?php
require_once __DIR__ . '/../src/Connection.php';

class ConnectionTest extends PHPUnit_Framework_TestCase {
	/**
	 * Tests that trying to connect to an invalid database type results in a
	 * ConnectionException being thrown.
	 */
	public function testInvalidConnectionType() {
		$this->setExpectedException('\Queasy\ConnectionException');
		$conn = new \Queasy\Connection(NULL, 'invalidtype');
	}

	/**
	 * Tests that when connecting to a MySQL database, the Connection object will
	 * ensure that all connection parameters are provided.
	 */
	public function testMysqlMissingOptions() {
		$this->setExpectedException('\Queasy\ConnectionException');
		$conn = new \Queasy\Connection(NULL, 'mysql', ['username' => 'test', 'host' => 'localhost']);
	}

	/**
	 * Tests that when connecting to a MySQL database, the Connection object will
	 * ensure that all connection parameters are provided.
	 */
	public function testValidMysqlConnection() {
		$conn = new \Queasy\Connection(NULL, 'mysql', [
			'username' => MYSQL_TEST_USERNAME,
			'password' => MYSQL_TEST_PASSWORD,
			'database' => MYSQL_TEST_DATABASE,
			'host' => MYSQL_TEST_HOST,
		]);
	}


};