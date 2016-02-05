var path = require( 'path' )
var http = require('http')
var querystring = require( 'querystring' )
var port = 1000;

function r( method, url, args, callback ) {

	if ( method === 'GET' ) {
		url += '?' + querystring.stringify( args )
	} else {
		args = querystring.stringify( args )
	}
	console.log( method + ' ' + url );
	var req = http.request({
		host: '127.0.0.1',
		port: module.exports.port,
		path: url,
		method: method,
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Content-Length': Buffer.byteLength( args )
		}
	}, function( response ) {
		var str = '';

		//another chunk of data has been recieved, so append it to `str`
		response.on('data', function (chunk) {
			str += chunk;
		});

		//the whole response has been recieved, so we just print it out here
		response.on('end', function () {
			try {
				str = JSON.parse( str )
			} catch (e ) {

			}
			console.log( str )
			callback( str );
		});
	});
	if ( method === "POST" ) {
		req.write(args);
	}
	req.end();
}

module.exports = {
	port: port,
	request: r
}
