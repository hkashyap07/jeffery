/**
 * JS shown on the page relating to unit editing in the admin area.
 */
var $j = jQuery.noConflict();
$j(function()
{
	// Controls the visibility of which boxes are visible when loading
	// the page.
	function wpcw_units_show_drip_type()
	{
		// Hide everything when we change.
		$j('#wpcw_units_drip_type-drip_interval').hide();
		$j('#wpcw_units_drip_type-drip_specific').hide();

		// Show the one that's right for our current selection.
		$j('#wpcw_units_drip_type-' + $j('#wpcw_units_drip_type').val()).show();
	}

	// Drip-type box has changed, so change what boxes are available.
	$j('#wpcw_units_drip_type').change(function(e)
	{
		wpcw_units_show_drip_type();
	});


	// ### Posts
	// AJAX to duplicate a post
	$j('.wpcw_units_admin_duplicate').click(function(e)
	{
		e.preventDefault();;

		// Create the data to pass
		var data = {
			action: 	'wpcw_handle_unit_duplication',
			source_id: 		$j(this).attr('data-postid'),
			security_id: 	$j(this).attr('data-nonce')
		};

		// Change message to show something is happening.
		var originalLinkText = $j(this).text();
		var originalLinkItem = $j(this);
		$j(this).text(wpcw_js_consts_units.status_copying);

		// Do the AJAX request
		$j.post(ajaxurl, data, function(response)
		{
			// Reload the page if successful, show error if not.
			if (response.success) {
				location.reload();
			} else {
				alert(response.errormsg);
				originalLinkItem.text(originalLinkText);
			}
		});
	});

	// Check visibility on load.
	wpcw_units_show_drip_type();
});