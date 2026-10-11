/**
 * Checks admin/js/peep-jalali.js against a fixture generated from the PHP
 * classes by tests/run.php.
 *
 * Usage: node tests/jalali-parity.js <fixture.json>
 */
'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const fixture = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const sandbox = { window: {} };

vm.runInNewContext( fs.readFileSync( path.join( __dirname, '..', 'admin', 'js', 'peep-jalali.js' ), 'utf8' ), sandbox );

const J = sandbox.window.PeepJalali;
const failures = [];

function same( actual, expected, label ) {
	if ( JSON.stringify( actual ) !== JSON.stringify( expected ) ) {
		failures.push( `${ label }: expected ${ JSON.stringify( expected ) }, got ${ JSON.stringify( actual ) }` );
	}
}

for ( const [ iso, jalali, readable ] of fixture.days ) {
	const parts = iso.split( '-' ).map( Number );

	same( J.toJalali( ...parts ), jalali, `toJalali(${ iso })` );
	same( J.toGregorian( ...jalali ), parts, `toGregorian(${ jalali.join( '-' ) })` );
	same( J.format( iso, 'j F Y' ), readable, `format(${ iso })` );
}

for ( const [ year, length ] of fixture.esfand ) {
	same( J.monthLength( year, 12 ), length, `monthLength(${ year }, 12)` );
}

for ( const [ input, expected ] of fixture.inputs ) {
	const parsed = J.parseInput( input );
	const iso = Array.isArray( parsed ) ? parsed.map( ( value, index ) => String( value ).padStart( index ? 2 : 4, '0' ) ).join( '-' ) : '';

	same( iso, expected, `parseInput(${ input })` );
}

if ( failures.length ) {
	console.error( failures.slice( 0, 20 ).join( '\n' ) );
	process.exit( 1 );
}

console.log( `jalali-parity=passed (${ fixture.days.length } days, ${ fixture.esfand.length } Esfand lengths, ${ fixture.inputs.length } inputs)` );
