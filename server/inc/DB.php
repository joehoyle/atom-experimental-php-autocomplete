<?php

use PhpParser\Node;
use phpDocumentor\Reflection\DocBlock;

class DB {
	public function __construct( $file ) {
		$this->file = $file;
		$this->create();
	}

	protected $models = array(
		'classes' => array(
			'class'            => PDO::PARAM_STR,
			'namespace'        => PDO::PARAM_STR,
			'file'             => PDO::PARAM_STR,
			'line'             => PDO::PARAM_INT,
			'parent'           => PDO::PARAM_STR,
			'parent_namespace' => PDO::PARAM_STR,
			'description'      => PDO::PARAM_STR,
		),
		'constants' => array(
			'constant'         => PDO::PARAM_STR,
			'namespace'        => PDO::PARAM_STR,
			'file'             => PDO::PARAM_STR,
			'line'             => PDO::PARAM_INT,
			'description'      => PDO::PARAM_STR,
			'type'             => PDO::PARAM_STR,
			'value'            => PDO::PARAM_STR,
		),
		'class_constants' => array(
			'constant'         => PDO::PARAM_STR,
			'class'            => PDO::PARAM_STR,
			'namespace'        => PDO::PARAM_STR,
			'file'             => PDO::PARAM_STR,
			'line'             => PDO::PARAM_INT,
			'type'             => PDO::PARAM_STR,
			'description'      => PDO::PARAM_STR,
		),
		'functions' => array(
			'function'         => PDO::PARAM_STR,
			'namespace'        => PDO::PARAM_STR,
			'file'             => PDO::PARAM_STR,
			'line'             => PDO::PARAM_INT,
			'description'      => PDO::PARAM_STR,
			'return_types'     => PDO::PARAM_STR,
			'parameters'       => PDO::PARAM_STR,
		),
		'methods' => array(
			'method'           => PDO::PARAM_STR,
			'class'            => PDO::PARAM_STR,
			'namespace'        => PDO::PARAM_STR,
			'file'             => PDO::PARAM_STR,
			'line'             => PDO::PARAM_INT,
			'description'      => PDO::PARAM_STR,
			'return_types'     => PDO::PARAM_STR,
			'parameters'       => PDO::PARAM_STR,
			'access'           => PDO::PARAM_STR,
			'is_static'        => PDO::PARAM_INT,
		),
		'files' => array(
			'file'             => PDO::PARAM_STR,
			'hash'             => PDO::PARAM_STR,
		),
	);

	public function store_function( Node\Stmt\Function_ $function ) {

		$parameters = array_map( function( $param ) {
			return array(
				'name' => $param->name,
				'default' => ! empty( $param->default ),
				'type' => ltrim( (string) $param->type, '\\' ),
			);
		}, $function->getParams() );

		$description = '';
		$return_types = array();
		if ( $comments = $function->getAttribute( 'comments' ) ) {
			$phpdoc = new DocBlock( $comments[0]->getText() );
			$description = $phpdoc->getShortDescription();

			if ( $return = $phpdoc->getTagsByName( 'return' ) ) {
				$return_types = array_map( 'ltrim', explode( '|', $return[0]->getType() ), array( '\\' ) );
			}

			// short circuit @ignore functions
			if ( $phpdoc->hasTag( 'ignore' ) ) {
				return;
			}
		}
		$this->store_model( 'functions', array(
			'function'         => $function->name,
			'namespace'        => ! empty( $function->namespacedName ) ? implode( '\\', array_slice( $function->namespacedName->parts, 0, -1 ) ) : '',
			'file'             => $this->_current_file,
			'line'             => $function->getLine(),
			'description'      => $description,
			'return_types'     => json_encode( $return_types ),
			'parameters'       => json_encode( $parameters ),
		) );
	}

	public function store_constant( Node\Expr\FuncCall $function_call ) {
		$name = $function_call->args[0]->value->value;
		$value = $function_call->args[1]->value->value;

		if ( $comments = $function_call->getAttribute( 'comments' ) ) {
			$phpdoc = new DocBlock( $comments[0]->getText() );
			$description = $phpdoc->getShortDescription();
			// short circuit @ignore functions
			if ( $phpdoc->hasTag( 'ignore' ) ) {
				return;
			}
		} else {
			$description = '';
		}

		$this->store_model( 'constants', array(
			'constant'         => $name,
			'namespace'        => ! empty( $function_call->namespacedName ) ? implode( '\\', array_slice( $function_call->namespacedName->parts, 0, -1 ) ) : '',
			'file'             => $this->_current_file,
			'line'             => $function_call->getLine(),
			'value'            => $value,
			'type'             => $this->get_type_for_node( $function_call->args[1]->value ),
			'description'      => $description,
		) );
	}

	public function store_file( $file, $hash ) {
		var_dump($file);
		var_dump($hash);
		$this->store_model( 'files', array(
			'file'             => $file,
			'hash'             => $hash,
		) );
	}

	public function store_class( Node\Stmt\Class_ $class ) {

		if ( $class->extends ) {
			$parent = end( $class->extends->parts );
			$parent_namespace = implode( '\\', array_slice( $class->extends->parts, 0, -1 ) );
		} else {
			$parent = '';
			$parent_namespace = '';
		}
		$description = '';

		if ( $comments = $class->getAttribute( 'comments' ) ) {
			$phpdoc = new DocBlock( $comments[0]->getText() );
			$description = $phpdoc->getShortDescription();
			// short circuit @ignore functions
			if ( $phpdoc->hasTag( 'ignore' ) ) {
				return;
			}
		}

		$this->store_model( 'classes', array(
			'class'            => $class->name,
			'namespace'        => ! empty( $class->namespacedName ) ? implode( '\\', array_slice( $class->namespacedName->parts, 0, -1 ) ) : '',
			'file'             => $this->_current_file,
			'line'             => $class->getLine(),
			'parent'           => $parent,
			'parent_namespace' => $parent_namespace,
			'description'      => $description,
		) );

		$methods = array_filter( $class->stmts, function( $node ) {
			return $node instanceof Node\Stmt\ClassMethod;
		});
		foreach( $methods as $method ) {
			$this->store_class_method( $class, $method );
		}

		$constants = array_filter( $class->stmts, function( $node ) {
			return $node instanceof Node\Stmt\ClassConst;
		});

		foreach ( $constants as $constant ) {
			$this->store_class_constant( $class, $constant );
		}
	}

	protected function get_type_for_node( Node $node ) {
		if ( $node instanceof Node\Expr\ConstFetch && in_array( (string) $node->name, array( 'true', 'false' ) ) ) {
			return 'bool';
		}

		if ( $node instanceof Node\Scalar\String_ ) {
			return 'string';
		}

		return '';
	}

	protected function store_model( $model, $data ) {
		$model_data = $this->models[ $model ];
		$statement = $this->pdo->prepare( $query = "INSERT INTO " . $model . " ( " . implode( ', ', array_keys( $model_data ) ) . " ) VALUES ( " . implode( ', ', array_map( function($m){return ':'.$m;}, array_keys( $model_data ) ) ) . " )" );
		foreach ( $data as $key => &$value ) {
			$statement->bindParam( ':' . $key, $value, $model_data[ $key ] );
		}
		$statement->execute();
	}

	public function store_class_method( Node\Stmt\Class_ $class, Node\Stmt\ClassMethod $method ) {

		$parameters = array_map( function( $param ) {
			return array(
				'name'    => $param->name,
				'default' => ! empty( $param->default ),
				'type'    => ltrim( (string) $param->type, '\\' ),
			);
		}, $method->getParams() );

		$access = array_keys( array_filter( array(
			'public'    => $method->isPublic(),
			'protected' => $method->isProtected(),
			'private'   => $method->isPrivate()
		) ) )[0];

		$return_types = array();
		$description = '';
		if ( $comments = $method->getAttribute( 'comments' ) ) {
			$phpdoc = new DocBlock( $comments[0]->getText() );
			$description = $phpdoc->getShortDescription();

			if ( $return = $phpdoc->getTagsByName( 'return' ) ) {
				$return_types = array_map( 'ltrim', explode( '|', $return[0]->getType() ), array( '\\' ) );
			}
			// short circuit @ignore functions
			if ( $phpdoc->hasTag( 'ignore' ) ) {
				return;
			}
		}

		$this->store_model( 'methods', array(
			'method'           => $method->name,
			'namespace'        => ! empty( $method->namespacedName ) ? implode( '\\', array_slice( $method->namespacedName->parts, 0, -1 ) ) : '',
			'file'             => $this->_current_file,
			'line'             => $method->getLine(),
			'description'      => $description,
			'return_types'     => json_encode( $return_types ),
			'parameters'       => json_encode( $parameters ),
			'access'           => $access,
			'is_static'        => $method->isStatic(),
			'class'            => $class->name,
		) );
	}

	public function store_class_constant( Node\Stmt\Class_ $class, Node\Stmt\ClassConst $constant ) {

		if ( $comments = $constant->getAttribute( 'comments' ) ) {
			$phpdoc = new DocBlock( $comments[0]->getText() );
			$description = $phpdoc->getShortDescription();
			// short circuit @ignore functions
			if ( $phpdoc->hasTag( 'ignore' ) ) {
				return;
			}
		} else {
			$description = '';
		}

		$this->store_model( 'class_constants', array(
			'constant'    => $constant->consts[0]->name,
			'class'       => $class->name,
			'namespace'   => ! empty( $class->namespacedName ) ? implode( '\\', array_slice( $class->namespacedName->parts, 0, -1 ) ) : '',
			'file'        => $this->_current_file,
			'line'        => $constant->getLine(),
			'type'        => $this->get_type_for_node( $constant->consts[0]->value ),
			'description' => $description,
		) );
	}

	public function get_functions( $args ) {
		return array_map( function( $row ) {
			return array(
				'name'         => $row['function'],
				'namespace'    => $row['namespace'],
				'file'         => $row['file'],
				'line'         => (int) $row['line'],
				'parameters'   => json_decode( $row['parameters'] ),
				'return_types' => json_decode( $row['return_types'] ),
				'type'         => 'function',
				'description'  => $row['description'],
			);
		}, $this->get_rows( 'functions', $args ) );

		return $results;
	}

	public function get_constants( $args ) {
		return array_map( function( $row ) {
			return array(
				'name'         => $row['constant'],
				'namespace'    => $row['namespace'],
				'file'         => $row['file'],
				'line'         => (int) $row['line'],
				'value'        => $row['value'],
				'value_type'   => $row['type'],
				'description'  => $row['description'],
				'type'         => 'constant',
			);
		}, $this->get_rows( 'constants', $args ) );
	}

	public function get_classes( $args ) {
		return array_map( function( $row ) {
			return array(
				'name'         => $row['class'],
				'namespace'    => $row['namespace'],
				'file'         => $row['file'],
				'line'         => (int) $row['line'],
				'type'         => 'class',
				'parent'       => $row['parent'],
				'parent_namespace' => $row['parent_namespace'],
				'description'  => $row['description'],
			);
		}, $this->get_rows( 'classes', $args ) );
	}

	public function get_methods( $args ) {
		$classes = $this->get_class_with_parents( $args['class'], $args['namespace'] );

		return array_reduce( $classes, function( $methods, $class ) use ( $args ) {
			$class = array_merge( $args, $class );
			return array_merge( array_map( function( $row ) {
				return array(
					'name'         => $row['method'],
					'class'        => $row['class'],
					'namespace'    => $row['namespace'],
					'file'         => $row['file'],
					'line'         => (int) $row['line'],
					'type'         => 'method',
					'is_static'    => (bool) $row['is_static'],
					'access'       => $row['access'],
					'return_types' => json_decode( $row['return_types'] ),
					'parameters'   => json_decode( $row['parameters'] ),
				);
			}, $this->get_rows( 'methods', $class ) ), $methods );
		}, array() );
	}

	public function get_class_constants( $args ) {
		$classes = $this->get_class_with_parents( $args['class'], $args['namespace'] );

		return array_reduce( $classes, function( $constants, $class ) use ( $args ) {
			$class = array_merge( $args, $class );
			return array_merge( array_map( function( $row ) {
				return array(
					'name'         => $row['constant'],
					'class'        => $row['class'],
					'namespace'    => $row['namespace'],
					'file'         => $row['file'],
					'line'         => (int) $row['line'],
					'type'         => 'class-constant',
					'value_type'   => $row['type'],
				);
			}, $this->get_rows( 'class_constants', $class ) ), $constants );
		}, array() );

	}

	public function get_class_with_parents( $class, $namespace ) {
		$all = array();
		$class = $this->get_classes( array( 'class' => $class, 'namespace' => $namespace ) );
		if ( ! $class ) {
			return array();
		}
		$class = $class[0];

		while ( $class ) {
			$all[] = array( 'class' => $class['name'], 'namespace' => $class['namespace'] );
			$class = $this->get_classes( array( 'class' => $class['parent'], 'namespace' => $class['parent_namespace'] ) );
			if ( $class ) {
				$class = $class[0];
			}
		}
		return $all;
	}

	public function get_file( $file ) {
		$this->get_rows( 'files', array( 'file' => $file ) );
	}

	public function delete_file( $file_path ) {
		$this->pdo->exec( "DELETE FROM functions WHERE file = '" . $file_path . "'" );
		$this->pdo->exec( "DELETE FROM methods WHERE file = '" . $file_path . "'" );
		foreach ( $this->models as $model => $values ) {
			$this->pdo->exec( "DELETE FROM $model WHERE file = '" . $file_path . "'" );
		}
	}

	public function delete() {
		if ( file_exists( $this->file ) ) {
			unlink( $this->file );
			$this->create();
		}
	}

	protected function get_rows( $table, $where ) {
		$where_sql = '1 = 1';
		foreach ( $where as $key => $value ) {
			if ( is_string( $value ) && strpos( $value, '%' ) ) {
				$operator = 'LIKE';
				$where_sql .= " AND `$key` $operator '$value'";
			} else if ( is_array( $value ) ) {
				$operator = 'IN';
				$value = array_map( function( $v ) {
					return "'" . $v . "'";
				}, $value);

				$where_sql .= " AND `$key` $operator ( " . implode( ', ', $value ) . " )";
			} else if ( is_bool( $value ) ) {
				$operator = '=';
				$value = (string) intval( $value );
				$where_sql .= " AND `$key` $operator '$value'";
			} else if ( is_null( $value ) ) { //skip null values

			} else {
				$operator = '=';
				$where_sql .= " AND `$key` $operator '$value'";
			}
		}

		$results = array();
		return $this->pdo->query( "SELECT * FROM $table WHERE " . $where_sql )->fetchAll();
	}

	protected function create() {
		$this->pdo = new PDO( 'sqlite:' . $this->file );
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		foreach ( $this->models as $table => $model ) {
			$query = "CREATE TABLE IF NOT EXISTS " . $table . " (";
			$query .= implode( ', ', array_map( function( $key, $value ) {
				return $key . ' ' . array( PDO::PARAM_STR => 'TEXT', PDO::PARAM_INT => 'INTEGER' )[ $value ];
			}, array_keys( $model ), array_values( $model ) ) );

			$query .= " )";

			$this->pdo->exec( $query );
		}
	}
}
