<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/inc/DB.php';
require __DIR__ . '/inc/Indexer.php';
require __DIR__ . '/inc/IndexerNodeTraverserVisitor.php';

$indexer = new Indexer( realpath( $argv[1] ) );

$indexer->index();
