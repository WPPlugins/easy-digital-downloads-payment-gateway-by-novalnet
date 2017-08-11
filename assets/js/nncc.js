(function( $ ) {
	$( document ).ready( function() {
		displayiframe();
		$( document ).ajaxComplete( function( event, xhr, settings ) {
			currentURL = settings.url;
			var params = currentURL.split('?');
			params = params[1].split('=');
			if(params[1] == 'novalnet_cc') {
				displayiframe();
			}
		});
	});

	function displayiframe() {
		$( '#loading_cc_iframe_div' ).css( 'display', 'block' );

		var nnrequest = $('#nnccrequest').val();

		$( '#novalnet_cc_iframe' ).attr( 'src', $( '#nn_plugin_url' ).val() + '?novalnet_iframe=1' + nnrequest );
		$( '#loading_cc_iframe_div' ).css( 'display', 'inline' );
		$( document ).on( 'click', '#edd-purchase-button', function() {
			loadiframe()
		});
	}
})( jQuery );



function loadiframe() {
	(function( $ ) {
		$( '#loading_cc_iframe_div' ).css( 'display', 'none' );

		var cc_iframe = $( '#novalnet_cc_iframe' ).contents();

		$( '#cc_type' ).val( cc_iframe.find( '#novalnetCc_cc_type' ).val() );
		$( '#cc_holder' ).val( cc_iframe.find( '#novalnetCc_cc_owner' ).val() );
		$( '#cc_exp_month' ).val( cc_iframe.find( '#novalnetCc_expiration' ).val() );
		$( '#cc_exp_year' ).val( cc_iframe.find( '#novalnetCc_expiration_yr' ).val() );
		$( '#cc_cvv_cvc' ).val( cc_iframe.find( '#novalnetCc_cc_cid' ).val() );
		
		var cc_type = 0;
		var cc_holder = 0;
		var cc_no = 0;
		var nncc_hash = 0;
		var cc_exp_month = 0;
		var cc_exp_year = 0;
		var cc_cvv_cvc = 0;

		if ( cc_iframe.find( '#novalnetCc_cc_type' ).val() != '' ) {
			cc_type = 1;
		}
		if ( cc_iframe.find( '#novalnetCc_cc_owner' ).val() != '' ) {
			cc_holder = 1;
		}
		if ( cc_iframe.find('#novalnetCc_cc_number').val() != '' ) {
			cc_no = 1;
		}
		if ( cc_iframe.find( '#novalnetCc_expiration' ).val() != '' ) {
			cc_exp_month = 1;
		}
		if ( cc_iframe.find( '#novalnetCc_expiration_yr' ).val() != '' ) {
			cc_exp_year = 1;
		}
		if ( cc_iframe.find( '#novalnetCc_cc_cid' ).val() != '' ) {
			cc_cvv_cvc = 1;
		}

		$( '#cc_field_validator' ).val( cc_type + ',' + cc_holder + ',' + cc_no + ',' + cc_exp_month + ',' + cc_exp_year + ',' + cc_cvv_cvc );

		if ( cc_iframe.find( '#nncc_cardno_id' ).val() != '' ) {
			$( '#nn_unique' ).val( cc_iframe.find( '#nncc_unique_id' ).val() );
			$( '#nncc_hash' ).val( cc_iframe.find( '#nncc_cardno_id' ).val() );
		}
	})( jQuery );
}