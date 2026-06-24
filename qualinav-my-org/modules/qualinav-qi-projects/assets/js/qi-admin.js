/* Qualinav QI Projects — admin UX niceties */
( function () {
	'use strict';

	// Confirm before submitting if any "remove" box is checked, so a stray
	// click can't silently delete a tab or section.
	var form = document.querySelector( '.qi-editor-form' );
	if ( ! form ) {
		return;
	}

	form.addEventListener( 'submit', function ( e ) {
		var removing = form.querySelectorAll(
			'input[name^="tab_remove"]:checked, input[name^="step_remove"]:checked'
		).length;
		if ( removing > 0 ) {
			var msg = 'You are removing ' + removing +
				' tab/section item(s). This changes the template for every project using it. Continue?';
			if ( ! window.confirm( msg ) ) {
				e.preventDefault();
			}
		}
	} );
}() );
