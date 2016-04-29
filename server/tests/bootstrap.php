<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../inc/DB.php';
require __DIR__ . '/../inc/Indexer.php';
require __DIR__ . '/../inc/IndexerNodeTraverserVisitor.php';
require __DIR__ . '/../inc/Completions.php';

$php_db = new DB( __DIR__ . '/indexes/test.sqlite' );
$indexer_php = new Indexer( realpath( __DIR__ . '/code', $php_db ) );

echo "Created Index...\n";

$indexer_php->delete_index();
$indexer_php->index();

echo "Created Index...\n";
