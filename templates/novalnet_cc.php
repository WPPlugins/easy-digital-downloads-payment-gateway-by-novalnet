<div id="loading_cc_iframe_div" style="display:block;">
	<img alt="<?php echo  __( 'Loading...', 'edd-novalnet-gateway' ) ?>"src="<?php echo NOVALNET_PLUGIN_URL ?>assets/images/novalnet-loading-icon.gif" style="box-shadow:none;">
</div>
<input type="hidden" name="cc_type" id="cc_type" value="" />
<input type="hidden" name="cc_holder" id="cc_holder" value="" />
<input type="hidden" name="cc_exp_month" id="cc_exp_month" value="" />
<input type="hidden" name="cc_exp_year" id="cc_exp_year" value="" />
<input type="hidden" name="cc_cvv_cvc" id="cc_cvv_cvc" value="" />
<input type="hidden" name="original_vendor_id" id="original_vendor_id" value="<?php echo $vendor_id ?>" />
<input type="hidden" name="original_vendor_authcode" id="original_vendor_authcode" value="<?php echo $auth_code ?>" />
<input type="hidden" id="original_customstyle_css" value="<?php echo $original_cc_css ?>" />
<input type="hidden" id="original_customstyle_cssval" value="<?php echo $original_cc_cssval ?>" />
<input type="hidden" name="nn_plugin_url" id="nn_plugin_url" value="<?php echo site_url() ?>" />
<input type="hidden" name="nn_unique" id="nn_unique" value="" />
<input type="hidden" name="nncc_hash" id="nncc_hash" value="" />
<input type="hidden" name="cc_field_validator" id="cc_field_validator" value="" />
<input type="hidden" name="nnccrequest" id="nnccrequest" value="&lang=<?php echo $config_details ?>" />
<iframe width="100%" scrolling="no" height="280px" frameborder="0" name="novalnet_cc_iframe" id="novalnet_cc_iframe" onload = "loadiframe();" >
</iframe>