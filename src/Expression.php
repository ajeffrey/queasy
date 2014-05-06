<?php
namespace Queasy;

class Expression {
	private $string = NULL;

	public function __construct($str) {
		$this->string = $str;
	}

	public function __toString() {
		return $this->string;
	}
	
};
