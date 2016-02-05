var provider = require( './provider' )
var a = require('atom');
var request = require( './request' );

module.exports = {
	activate: function() {
		this.__dir = atom.packages.getPackageDirPaths()[0] + '/autocomplete-wordpress-hooks';

		provider.loadCompletions()
		atom.commands.add( 'atom-text-editor', 'php-complete:reindex-file', function( event ) {
			request.request( 'POST', '/reindex', { file: atom.workspace.getActiveTextEditor().getPath() }, function() {} );
		} );
		atom.commands.add( 'atom-text-editor', 'php-complete:reindex-project', function( event ) {
			request.request( 'POST', '/reindex', {}, function() {

			} );
		} );
		atom.commands.add( 'atom-text-editor', 'php-complete:respawn-server', function( event ) {
			this.serverProcess.kill();
			this.startServer();
		}.bind( this ) );

		this.subscriptions = new a.CompositeDisposable();
		this.subscriptions.add( atom.workspace.observeTextEditors( function(editor) {
			buffer = editor.getBuffer();
			buffer.onDidSave(function(event) {
				request.request( 'POST', '/reindex', { file: event.path }, function() {} );
			});
		} ) );

		request.port = Math.floor(Math.random() * 65535) + 1024;
		//setInterval( this.pollStatus.bind( this ), 1000 );
		this.startServer();
	},
	getProvider: function() {
		return provider
	},
	consumeStatusBar: function( statusBar ) {
		this.statusBarItem = statusBar.addLeftTile( {
			item: document.createElement("span"),
			priority: 100
		});
	},
	startServer: function() {
		console.log( 'Starting server from ' + atom.project.getPaths()[0] );
		this.serverProcess = new a.BufferedProcess( {
			command: '/usr/bin/php',
			args: [ this.__dir + '/server/server.php', request.port, atom.project.getPaths()[0] ],
			stdout: function( data ) {
				this.updateStatusBar( data );
			}.bind( this ),
			stderr: function( data ) {
				console.err( data );
			}
		} );
	},
	pollStatus: function() {
		request.request( 'GET', '/index', {}, function( data ) {
			if ( data.status === 'indexing' ) {
				this.updateStatusBar( 'Indexing ' + data.last_indexed_file );
			} else {
				this.updateStatusBar( data.status );
			}

		}.bind( this ) );
	},
	updateStatusBar: function( text ) {
		this.statusBarItem.item.innerHTML = 'Autocomplete PHP: ' + text;
	}
}
