<?php

class Completions {

	protected $tokens = array(
		'namespace'    => '[0-9A-Za-z_|\\\\]',
		'function'     => '[0-9A-Za-z_]',
		'method'       => '[0-9A-Za-z_]',
		'class'        => '[0-9A-Za-z_]',
		'scalar'       => 'float|int|string',
		'constant'     => '[0-9A-Za-z_]',
		'variable'     => '\$[0-9A-Za-z_]',
	);

	protected $completion_scenarios = array(
		'INSTANTIATE_CLASS'                           => 'new :class',
		'DECLARE_CLASS_EXTENDS_CLASS'                 => 'class :class extends :class',
		'DECLARE_FUNCTION_PARAM_TYPEHINT_CLASS'       => 'function :function( ...:class',
		'FUNCTION_CALL_PARAM_FUNCTION'                => ':function( ...:function',
		'STATIC_METHOD_CALL_PARAM_FUNCTION'           => ':class:::method( ...:function',
		// 'FUNCTION_CALL_PARAM_FUNCTION_CAST'           => ':function( ...(:scalar) :function',
		// 'FUNCTION_CALL_PARAM_CLASS'                   => ':function( :class',
		'FUNCTION_CALL_PARAM_STATIC_METHOD'            => ':function( :class:::method',
		// 'FUNCTION_CALL_PARAM_TYPEHINTED_FUNCTION'     => ':function( (:scalar) :function',
		// 'FUNCTION_CALL_PARAM_TYPEHINTED_CLASS_METHOD' => ':function( (:scalar) :class:::function',
		// 'CALL_USER_FUNC_CLASS'                        => 'call_user_func( array( ":class',
		// 'CALL_USER_FUNC_CLASS_METHOD'                 => 'call_user_func( array( ":class", ":function"',
		'FUNCTION_CALL'                               => ':function',
		'ACCESS_CLASS'                                => ':class',
		'ACCESS_CONSTANT'                             => ':constant',
		'ACCESS_CLASS_CONSTANT'                       => ':class:::constant',
		'CALL_CLASS_STATIC_METHOD'                    => ':class:::method',
		'CALL_CLASS_STATIC_METHOD'                    => ':class:::method',
		'ACCESS_VARIABLE'                             => ':variable',
	);

	public function __construct( DB $db, $file_contents, $line, $column ) {
		$this->db = $db;
		$this->line = $line;
		$this->column = $column;
		$this->file_contents = $file_contents;
		$lines = explode( "\n", $file_contents );
		$this->line = $lines[ $line - 1 ];
		$this->line_to_caret = substr( $this->line, 0, $column );
		$this->file_contents_to_caret = implode( "\n", array_slice( $lines, 0, $line - 1 ) ) . "\n" . $this->line_to_caret;
		$this->current_word = '';
		if ( preg_match( '/([\w]*)$/', $this->line_to_caret, $matches ) ) {
			$this->current_word = $matches[1];
		}
		$this->namespaces = $this->get_namespaces();
		$this->scope = $this->get_scope();
	}

	public function get_completions() {

		$completions = array();
		$used_end_tokens_in_scenarios = array();
		foreach ( $this->completion_scenarios as $name => $pattern ) {
			// replace tokems with their regexes
			// whitespaces are optional, replace a space with regex
			// quotes can either be " or '
			$regex = $pattern;
			// if it's just a call match, only auto complete if we are searching
			if ( strpos( $pattern, ':' ) === 0 && in_array( substr( $pattern, 1 ), array_keys( $this->tokens ) ) && ! $this->current_word ) {
				continue;
			}

			$regex = preg_replace( '/(\{|\(|\)|\;) /', '$1 ?', $regex );
			// escape parans
			$regex = str_replace( array( '(', ')', '$' ), array( '\\(', '\\)', '\\$' ), $regex );
			$regex = '(^|{|;|\(|\s)' . $regex;
			foreach ( $this->tokens as $token => $token_regex ) {
				// replace the token with it's regex. we have to loop and change the name each time
				// as you can't have more than one named param have the same name
				$replaced = 1;
				$token_regex = str_replace( array( '$'), array( '\$' ), $token_regex );
				while( strpos( $regex, ':' . $token ) !== false ) {
					$last_token_regex = '(?P<' . ( $replaced == 1 ? $token : ( $token . $replaced ) ) . '>' . $token_regex . '+)';
					$last_token = $token;
					$regex = preg_replace( '/\:' . $token . '/', $last_token_regex, $regex, 1 );
					$replaced++;
				}
				// replace the last regex with a wildcard * rather than +
				$regex = str_replace( $last_token_regex, str_replace( '+', '*', $last_token_regex ), $regex );
			}
			// support the stread syntax that means a list of arguments / vars
			$regex = str_replace( '...', '(?P<args>.*?)?', $regex );

			if ( in_array( $last_token, $used_end_tokens_in_scenarios ) ) {
				continue;
			}
			var_dump($regex);

			if ( preg_match( '!' . $regex . '$!', $this->line_to_caret, $matches ) ) {
				$method = 'get_completions_for_' . strtolower( $name );
				if ( method_exists( $this, $method ) ) {
					if ( isset( $matches['args'] ) ) {
						$matches['args'] = array_filter( array_map( 'trim', explode( ',', $matches['args'] ) ) );
					}
					$method_completions = call_user_func( array( $this, $method ), $matches );
					if ( $method_completions === null ) {
						continue;
					}
					$used_end_tokens_in_scenarios[] = $last_token;
					$completions += $method_completions;
				} else {
					var_dump( "method doesn't exist " . $method );
				}
			}
		}
		return $completions;
		$context = null;
		$completions = array();
		foreach ( $this->context_regexes as $c => $regex ) {
			if ( preg_match( $regex, $this->line_to_caret, $matches ) ) {
				$context = $c;
				break;
			}
		}
		return $completions;
	}

	protected function get_completions_for_instantiate_class( $vars ) {
		return $this->complete_class( $vars['class'] );
	}

	protected function get_completions_for_class_extends_class( $vars ) {
		return $this->complete_class( $vars['class2'] );
	}

	protected function get_completions_for_function_call( $vars ) {
		return $this->complete_function( $vars['function'] );
	}

	protected function get_completions_for_access_class_constant( $vars ) {
		return $this->complete_class_constants( $vars['class'], $vars['constant'] );
	}

	protected function get_completions_for_call_class_static_method( $vars ) {
		return $this->complete_static_method( $vars['class'], $vars['method'] );
	}

	protected function get_completions_for_declare_class_extends_class( $vars ) {
		return $this->complete_class( $vars['class2'] );
	}

	protected function get_completions_for_access_constant( $vars ) {
		return $this->complete_constant( $vars['constant'] );
	}

	protected function get_completions_for_access_class( $vars ) {
		return $this->complete_class( $vars['class'] );
	}

	protected function get_completions_for_declare_function_param_typehint_class( $vars ) {
		return $this->complete_class( $vars['class'] );
	}

	protected function get_completions_for_function_call_param_function( $vars ) {
		$functions = $this->db->get_functions( array(
			'namespace' => '',
			'function' => $vars['function']
		) );
		if ( ! $functions ) {
			return null;
		}
		$function = $functions[0];
		// if the function has less args than what we are looking for, return early
		if ( count( $function['parameters'] ) < count( $vars['args'] ) + 1 ) {
			return null;
		}

		return $this->db->get_functions( array(
			'namespace'    => '',
			'function'     => $vars['function2'] ? $vars['function2'] . '%' : null,
			'return_types' => json_encode( array( $function['parameters'][ count( $vars['args'] ) ]->type ) )
		));
	}

	protected function get_completions_for_function_call_param_function_cast( $vars ) {
		return $this->complete_fuction( $vars['function2'] );
	}

	protected function get_completions_for_static_method_call_param_function( $vars ) {

		$methods = $this->db->get_methods( array(
			'is_static' => true,
			'namespace' => $this->namespaces,
			'class'     => $vars['class'],
			'method'    => $vars['method'],
			'access'    => 'public',
		) );

		if ( ! $methods ) {
			return null;
		}
		$method = $methods[0];
		// if the function has less args than what we are looking for, return early
		if ( count( $method['parameters'] ) < count( $vars['args'] ) + 1 ) {
			return null;
		}

		return $this->db->get_functions( array(
			'namespace'    => '',
			'function'     => $vars['function'] ? $vars['function'] . '%' : null,
			'return_types' => json_encode( array( $method['parameters'][ count( $vars['args'] ) ]->type ) )
		));
	}

	protected function get_completions_for_function_call_param_static_method( $vars ) {
		$functions = $this->db->get_functions( array(
			'namespace' => '',
			'function' => $vars['function']
		) );
		if ( ! $functions ) {
			return null;
		}
		$function = $functions[0];
		// if the function has less args than what we are looking for, return early
		if ( count( $function['parameters'] ) < count( $vars['args'] ) + 1 ) {
			return null;
		}
		return $this->db->get_methods( array(
			'namespace'    => '',
			'method'       => $vars['method'] ? $vars['method'] . '%' : null,
			'class'        => $vars['class'],
			'return_types' => json_encode( array( $function['parameters'][ count( $vars['args'] ) ]->type ) )
		));
	}

	protected function get_completions_for_access_variable( $vars ) {
		var_dump( $vars);

		if ( strpos( $vars['variable'], '$' ) !== 0 || $vars['variable'] === '$this' ) {
			return null;
		}

		return array_values( array_map( function( $variable ) {
			return array(
				'type' => 'variable',
				'name' => ltrim( $variable, '$' ),
			);
		}, array_filter( $this->scope['variables'], function( $variable ) use ( $vars ) {
			return strpos( $variable, $vars['variable'] ) === 0;
		} ) ) );

	}

	protected function complete_function( $search ) {
		// to do functions in namespaces
		return $this->db->get_functions( array(
			'namespace' => '',
			'function'  => $search . '%'
		));
	}

	protected function complete_constant( $search ) {
		return $this->db->get_constants( array(
			'namespace' => $this->namespaces,
			'constant'  => $search ? $search . '%' : null
		));
	}

	protected function complete_class( $search ) {
		return $this->db->get_classes( array(
			'namespace' => $this->namespaces,
			'class'     => $search ? $search . '%' : null
		));
	}

	protected function complete_static_method( $class, $method ) {
		$args = array(
			'namespace' => $this->namespaces,
			'class'     => $class,
			'access'    => 'public',
			'is_static' => true,
		);
		if ( $method ) {
			$args['method'] = $method . '%';
		}

		return $this->db->get_methods( $args );
	}

	protected function complete_class_constants( $class, $constant ) {
		$args = array(
			'namespace' => $this->namespaces,
			'class'     => $class,
		);
		if ( $constant ) {
			$args['constant'] = $constant . '%';
		}
		return $this->db->get_class_constants( $args );
	}

	protected function get_namespaces() {
		$namespaces = array();
		$match = $this->match( '^namespace :namespace', $this->file_contents );
		if ( $match ) {
			$namespaces[] = $match['namespace'];
			$match = $this->match_all( '^use :namespace', $this->file_contents );
			if ( $match ) {
				$namespaces = array_merge( $namespaces, $match['namespace'] );
			}
		}
		if ( ! $namespaces ) {
			$namespaces = array( '' );
		}
		return $namespaces;
	}

	protected function match( $match, $string ) {
		preg_match( '/' . $this->get_regex_for_token_string( $match ) . '/m', $string, $matches );
		return $matches;
	}

	protected function match_all( $match, $string ) {
		preg_match_all( '/' . $this->get_regex_for_token_string( $match ) . '/m', $string, $matches );
		return $matches;
	}

	protected function get_regex_for_token_string( $token_string ) {
		$regex = preg_replace_callback( '/\:([0-9A-Za-z_]+)/', function( $matches ) {
			return '(?P<' . $matches[1] . '>' . $this->tokens[ $matches[1] ] . '+)';
		}, $token_string );

		return $regex;
	}

	protected function get_scope() {
		$scope = array();
		if ( $vars = $this->match( 'class :class', $this->file_contents_to_caret ) ) {
			$scope['class'] = $vars['class'];

			if ( $vars = $this->match( 'function :method', $this->file_contents_to_caret ) ) {
				$scope['method'] = $vars['method'];
			}

		} else if ( $vars = $this->match( 'function :function', $this->file_contents_to_caret ) ) {
			$scope['function'] = $vars['function'];
		}

		$scope['variables'] = $this->match_all( ':variable =', $this->file_contents )['variable'];

		return array_unique( $scope );
	}
}
