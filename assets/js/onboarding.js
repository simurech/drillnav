/* global drillnavOnboarding */
( function () {
	var notice = document.getElementById( 'drillnav-onboarding-notice' );
	if ( ! notice || ! window.drillnavOnboarding ) {
		return;
	}
	notice.addEventListener( 'click', function ( e ) {
		if ( e.target.classList.contains( 'notice-dismiss' ) ) {
			fetch( window.drillnavOnboarding.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=drillnav_dismiss_onboard&nonce=' + window.drillnavOnboarding.nonce
			} );
		}
	} );
} )();
