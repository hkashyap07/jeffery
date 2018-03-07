/**
 * JS shown on all pages in the admin area.
 */
var $j = jQuery.noConflict();
$j(function()
{
	// ### Users section
	// Prompt for the bulk reset.
	$j('#wpcw_user_progress_reset_point_bulk_btn').click(function(e)
	{
		if (!confirm(wpcw_js_consts_usr.confirm_bulk_change)) {
			e.preventDefault();
			return false;
		}

		return true;
	});

	// Prompt for the single reset.
	$j('.wpcw_user_progress_reset_point_single').change(function(e)
	{
		if (!confirm(wpcw_js_consts_usr.confirm_single_change)) {
			e.preventDefault();
			return false;
		}

		$j(this).closest('form').submit();

		return true;
	});

});