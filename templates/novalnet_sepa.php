<div id="loading_sepaiframe_div" style="display:inline;">
	<img alt="<?php __( 'Loading...', 'edd-novalnet-gateway' ) ?>"src="<?php echo NOVALNET_PLUGIN_URL ?>assets/images/novalnet-loading-icon.gif" style="box-shadow:none;">
</div>
<input type="hidden" name="sepa_owner" id="sepa_owner" value="" />
<input type="hidden" name="panhash" id="panhash" value="" />
<input type="hidden" name="sepa_uniqueid" id="sepa_uniqueid" value="" />
<input type="hidden" name="sepa_confirm" id="sepa_confirm" value="" />
<input type="hidden" name="sepa_field_validator" id="sepa_field_validator" value="" />
<input type="hidden" name="sepa_vendor_id" id="sepa_vendor_id" value="<?php echo $vendor_id ?>" />
<input type="hidden" name="nn_pluing_url" id="nn_plugin_url" value="<?php echo site_url() ?>" />
<input type="hidden" name="nn_cust_data" id="nn_cust_data" value="<?php echo $customer_details ?>" />
<input type="hidden" name="nnurldata" id="nnurldata" value="<?php echo $config_details ?>" />
<input type="hidden" id="original_sepa_customstyle_css" value="<?php echo $original_sepa_css ?>" />
<input type="hidden" id="original_sepa_customstyle_cssval" value="<?php echo $original_sepa_cssval ?>" />
<iframe width="100%" scrolling="no" height="460px" frameborder="0" name="novalnet_sepa_iframe" id="novalnet_sepa_iframe" onload="getSEPAValues()">
</iframe>