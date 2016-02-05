<?php

require 'vendor/autoload.php';
require 'inc/DB.php';
require 'inc/Indexer.php';
require 'inc/IndexerNodeTraverserVisitor.php';
require 'inc/Completions.php';

date_default_timezone_set( 'America/Montreal' );

$db = new DB( __DIR__ . '/indexes/' . md5( realpath( $argv[2] ) ) . '.sqlite' );
$indexer = new Indexer( realpath( $argv[2] ), $db );

$server = new CapMousse\ReactRestify\Server( "PHP Autocomplete", "0.0.1" );

$server->get( '/index', function( $request, $response, $next ) use ( $indexer ) {
	$response->write( json_encode( array(
		'status'            => $indexer->status,
		'last_indexed_file' => $indexer->last_indexed_file
	) ) );
	$next();
});

$server->post( '/index', function( $request, $response, $next ) use ( $indexer ) {
	echo "Starting index...\n";
	$next();
	$indexer->index();
	echo "Index complete.\n";
});

$server->post( '/reindex', function( $request, $response, $next ) use ( $indexer ) {
	echo "Starting reindex...\n";
	if ( $request->file ) {
		$file = realpath( $request->file );
		$indexer->delete_file_index( $file );
		$indexer->index_file( $file );
	} else {
		$indexer->delete_index();
		$indexer->index();
	}
	echo "Indexing completed.\n";
	$next();

});

$server->get( '/search', function( $request, $response, $next ) use ( $db ) {
	$params = $request->httpRequest->getQuery();
	$query = array();
	if ( isset( $params['namespace'] ) ) {
		$query['namespace'] = $params['namespace'];
	}

	$response->write( json_encode( $db->get_functions( $params ) ) );
	$next();
});

$server->get( '/completions', function( $request, $response, $next ) use ( $db ) {
	$params = $request->httpRequest->getQuery();
	$completions = new Completions( $db, file_get_contents( $params['file'] ), $params['line'], $params['column'] );
	$response->write( json_encode( $completions->get_completions() ) );
	$next();
} );

$server->post( '/completions', function( $request, $response, $next ) use ( $db ) {
	$completions = new Completions( $db, $request->file_contents, $request->line, $request->column );
	$response->write( json_encode( $completions->get_completions() ) );
	$next();
});

$runner = new CapMousse\ReactRestify\Runner( $server );
$runner->listen( $argv[1], '0.0.0.0');
