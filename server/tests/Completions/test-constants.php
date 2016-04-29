<?php

class Test_Completetions_Constants extends PHPUnit_Test_Case {

	function setUp() {
		$this->db = new DB( __DIR__ . '/../indexes/test.sqlite' );
	}

	function test_constant_is_completed() {
		$code = <<<EOT
		<?php

		INTEGER_CON
		EOT;

		$completions = $this->get_completions( $code );

		var_dump( $completions );
	}

	function test_constant_for_param_type_is_completed() {
		$code = <<<EOT
		<?php

		accepts_integer( INTEGER_CON
		EOT;

		$completions = $this->get_completions( $code );

		var_dump( $completions );
	}

	protected function get_completions( $code ) {
		$lines = explode( "\n", $code );
		//$completions = new Completions( $this->db, $code, count( $lines ), strlen( $lines[ count( $lines ) ] ) );
		return $completions->get_completions();
	}
}
