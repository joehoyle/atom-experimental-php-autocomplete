<?php

use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class Indexer {

	public function __construct( $path, DB $db ) {
		$this->db = $db;
		$this->path = $path;
		$this->parser = $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		$this->traverser = new NodeTraverser;
		$this->traverser->addVisitor(new NameResolver);
		$this->traverser->addVisitor( new IndexerNodeTraverserVisitor( $this->db ) );
		$this->status = 'in-sync';
		$this->last_indexed_file = '';
	}

	public function index() {
		$this->status = 'indexing';
		$path = realpath( $this->path );
		var_dump($this->path);
		var_dump( $path );

		$objects = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ), RecursiveIteratorIterator::SELF_FIRST );
		$objects = new RegexIterator( $objects, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH );
		foreach( $objects as $name => $object ) {
			$this->index_file( $name );
			echo "Indexing file " . str_replace( $path, '', $name ) . "\n";
		}
		$this->status = 'in-sync';
	}

	public function index_file( $file_path ) {
		$this->last_indexed_file = $file_path;
		$md5 = md5_file( $file_path );

		$previous_index = $this->db->get_file( $file_path );

		if ( $previous_index && $previous_index['hash'] === $hash ) {
			echo "Already up to date\n";
			return;
		}

		try {
			$stmts = $this->parser->parse( file_get_contents( $file_path ) );
		} catch ( \Exception $e ) {
			echo 'parse error for file ' . $file_path;
			return;
		}

		$this->db->_current_file = $file_path;
		$this->traverser->traverse( $stmts );
		$this->db->store_file( $file_path, $md5 );
	}

	public function delete_file_index( $file_path ) {
		$this->db->delete_file( $file_path );
	}

	public function delete_index() {
		$this->db->delete();
	}
}
