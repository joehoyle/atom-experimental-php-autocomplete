var fs = require( 'fs' )
var request = require( './request' )
module.exports = {
	executablePath: 'php',

	// This will work on JavaScript and CoffeeScript files, but not in js comments.
	selector: '.source.php',
	disableForSelector: '.source.php .comment',

	// This will take priority over the default provider, which has a priority of 0.
	// `excludeLowerPriority` will suppress any providers with a lower priority
	// i.e. The default provider will be suppressed
	inclusionPriority: 1,
	excludeLowerPriority: true,
	loadCompletions: function() {

	},

	getSuggestions: function( completion ) {
		var m = this.responseToAutocomplete;
		var r = request.request;

		return new Promise( function(resolve) {
			var line = completion.editor.buffer.lines[ completion.bufferPosition.row ].substr( 0, completion.bufferPosition.column );
			r( 'POST', '/completions', {
				file_contents: completion.editor.getText(),
				line: completion.bufferPosition.row + 1,
				column: completion.bufferPosition.column
			}, function( completions ) {
				resolve( completions.map( m))
			});
		});
	},
	responseToAutocomplete: function( r ) {
		switch( r.type ) {
			case 'function':
			case 'method':
				if ( r.parameters.length ) {
					return {
						type: r.type,
						description: r.description,
						leftLabel: r.return_types.join( ' ' ),
						text: r.name + '( ',
						displayText: r.name + '( ' + r.parameters.map( function( param ) {
							if ( param.default ) {
								return '[$' + param.name + ']';
							}
							return '$' + param.name;
						}).join(', ') + ' )'
					}
				}
				return {
					type: r.type,
					description: r.description,
					leftLabel: r.return_types.join( ' ' ),
					text: r.name + '()',
				}
			case 'constant':
				return {
					type: r.type,
					text: r.name,
					description: r.description,
					leftLabel: r.value_type,
					rightLabel: r.value
				}
			case 'class-constant':
				return {
					type: r.type,
					text: r.name,
					description: r.description,
					leftLabel: r.value_type
				}
			default:
				return {
					type: r.type,
					text: r.name,
					description: r.description
				}
		}

	}
}
