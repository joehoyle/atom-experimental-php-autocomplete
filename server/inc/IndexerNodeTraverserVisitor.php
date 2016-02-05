<?php

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use phpDocumentor\Reflection\DocBlock;

class IndexerNodeTraverserVisitor extends NodeVisitorAbstract {

	public function __construct( DB $db ) {
		$this->db = $db;
	}

	public function leaveNode( Node $node ) {

		if ( $node instanceof Node\Stmt\Function_ ) {
			$this->db->store_function( $node );
		}

		if ( $node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && (string) $node->name === 'define' ) {
			$this->db->store_constant( $node );
		}

		if ( $node instanceof Node\Stmt\Class_ ) {
			$this->db->store_class( $node );
		}
	}

}
