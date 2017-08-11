(function( $ ) {
	$( document ).ready( function() {
		novalnet_sepa_displayiframe();
        $( document ).ajaxComplete( function( event, xhr, settings ) {
            currentURL = settings.url;
			var params = currentURL.split('?');
			params = params[1].split('=');
			if ( params[1] == 'novalnet_sepa' ) {
				novalnet_sepa_displayiframe();
			}
            $( '#edd-email, #edd-first, #edd-last, #card_address, #card_address_2, #card_city, input[name=card_zip], select[name=billing_country]' ).on( 'change', function() {
                novalnet_sepa_displayiframe();
            });

        });
    });

	function novalnet_sepa_displayiframe() {
		$( '#loading_sepaiframe_div' ).css( 'display', 'inline' );
		var email = $( '#edd-email' ).val();
		var fname = $( '#edd-first' ).val();
		var lname = $( '#edd-last' ).val();
		var country = $( 'select[name=billing_country]' ).val();
		var address = $( '#card_address' ).val();
		var zip = $( 'input[name=card_zip]' ).val();
		var city = $( '#card_city' ).val();
		var nncust = '&first_name=' + encodeURIComponent( fname ) + '&last_name=' + encodeURIComponent( lname ) + '&email=' + encodeURIComponent( email );
		var nncustaddr = '&country=' + encodeURIComponent( country ) + '&postcode=' + encodeURIComponent( zip ) + '&city=' + encodeURIComponent( city ) + '&address=' + encodeURIComponent( address );
		var nnconfig = $( '#nnurldata' ).val();
		var nn_cust_data = $( '#nn_cust_data' ).val();
		var novalnet_data = nncust + nncustaddr + nn_cust_data + nnconfig;
		$( '#novalnet_sepa_iframe' ).attr( 'src', jQuery( '#nn_plugin_url' ).val() + '?novalnet_iframe=1' + novalnet_data );
		$( document ).on( 'click', '#edd-purchase-button', function() {
			getSEPAValues();
		});
	}
})( jQuery );

function getSEPAValues() {
	(function( $ ) {
		$( '#loading_sepaiframe_div' ).css( 'display', 'none' );
		var novalnet_sepa_iframe = $( '#novalnet_sepa_iframe' ).contents();
		if ( novalnet_sepa_iframe.find( '#nnsepa_unique_id' ).val() != null ) {
			$( '#sepa_owner' ).val( novalnet_sepa_iframe.find( '#novalnet_sepa_owner' ).val() );
			$( '#sepa_uniqueid' ).val( novalnet_sepa_iframe.find( '#nnsepa_unique_id' ).val() );
			$( '#sepa_confirm' ).val( novalnet_sepa_iframe.find( '#nnsepa_iban_confirmed' ).val() );
			$( '#panhash' ).val( novalnet_sepa_iframe.find( '#nnsepa_hash' ).val() );

			var sepa_owner = 0;
			var sepa_accountno = 0;
			var sepa_bankcode = 0;
			var sepa_iban = 0;
			var sepa_swiftbic = 0;
			var sepa_hash = 0;
			var sepa_country = 0;
		
			if ( novalnet_sepa_iframe.find( '#novalnet_sepa_owner' ).val() != '' ) {
				sepa_owner = 1;
			}
			if ( novalnet_sepa_iframe.find( '#novalnet_sepa_accountno' ).val() != '' ) {
				sepa_accountno = 1;
			}
			if ( novalnet_sepa_iframe.find( '#novalnet_sepa_bankcode' ).val() != '' ) {
				sepa_bankcode = 1;
			}
			if ( novalnet_sepa_iframe.find( '#novalnet_sepa_iban' ).val() != '' ) {
				sepa_iban = 1;
			}
			if ( novalnet_sepa_iframe.find( '#novalnet_sepa_swiftbic' ).val() != '' ) {
				sepa_swiftbic = 1;
			}
			if ( novalnet_sepa_iframe.find( '#nnsepa_hash' ).val() != '' ) {
				sepa_hash = 1;
			}
			if ( novalnet_sepa_iframe.find( '#novalnet_sepa_country' ).val() != '' ) {
				sepa_country = 1 + '-' + novalnet_sepa_iframe.find( '#novalnet_sepa_country' ).val();
			}
			var fldvdr = sepa_owner + ',' + sepa_accountno + ',' + sepa_bankcode + ',' + sepa_iban + ',' + sepa_swiftbic + ',' + sepa_hash + ',' + sepa_country;
			$( '#sepa_field_validator' ).val( fldvdr );
		}
	})( jQuery );
}