( function () {
	'use strict';

	var cfg = window.orwatchData;
	if ( ! cfg ) {
		return;
	}

	var table = document.getElementById( 'orwatch-table' );
	var scanBtn = document.getElementById( 'orwatch-scan' );
	var progress = document.getElementById( 'orwatch-progress' );
	var busy = false;

	function post( action, plugin ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( '_wpnonce', cfg.nonce );
		body.append( 'plugin', plugin );

		return fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function replaceRow( row, html ) {
		var holder = document.createElement( 'tbody' );
		holder.innerHTML = html;
		var fresh = holder.firstElementChild;
		if ( fresh ) {
			row.replaceWith( fresh );
		}
	}

	function run( action, row ) {
		row.classList.add( 'orwatch-row--busy' );
		return post( action, row.getAttribute( 'data-plugin' ) )
			.then( function ( json ) {
				if ( json && json.success ) {
					replaceRow( row, json.data.row );
				} else {
					row.classList.remove( 'orwatch-row--busy' );
				}
			} )
			.catch( function () {
				row.classList.remove( 'orwatch-row--busy' );
				progress.textContent = cfg.i18n.failed;
			} );
	}

	table.addEventListener( 'click', function ( event ) {
		var target = event.target;
		var row = target.closest( 'tr[data-plugin]' );
		if ( ! row || busy ) {
			return;
		}
		if ( target.classList.contains( 'orwatch-rescan' ) ) {
			run( 'orwatch_scan_plugin', row );
		} else if ( target.classList.contains( 'orwatch-ignore' ) ) {
			run( 'orwatch_toggle_ignore', row );
		}
	} );

	scanBtn.addEventListener( 'click', function () {
		if ( busy ) {
			return;
		}
		busy = true;
		scanBtn.disabled = true;
		scanBtn.textContent = cfg.i18n.scanning;

		var rows = Array.prototype.slice.call( table.querySelectorAll( 'tbody tr.orwatch-row' ) ).filter( function ( row ) {
			return ! row.classList.contains( 'orwatch-row--ignored' );
		} );
		var total = rows.length;
		var index = 0;

		function next() {
			if ( index >= total ) {
				progress.textContent = cfg.i18n.done;
				// Reload so rows and counters are re-sorted server-side.
				window.setTimeout( function () {
					window.location.reload();
				}, 600 );
				return;
			}
			progress.textContent = cfg.i18n.progress.replace( '%1$s', index ).replace( '%2$s', total );
			var row = rows[ index ];
			index++;
			run( 'orwatch_scan_plugin', row ).then( next );
		}

		next();
	} );
}() );
