<?php
require_once __DIR__ . '/../vendor/autoload.php';

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
	public function testValidConnection() {
		$conn = new \Queasy\Connection(NULL, 'test');
	}


};