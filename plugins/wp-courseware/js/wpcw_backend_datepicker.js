/**
 * JS shown on all pages in the admin area that need a translated date picker.
 */
var $j = jQuery.noConflict();
$j(function()
{
	// Dynamically, for each date picker on the page, set the alt datepicker
	// to be the one next to it so that we can have multiple pickers on the same
	// page.
	$j('.wpcw_datepicker_vis').each(function()
	{
		$j(this).datetimepicker(
		{
			dateFormat: 		'd M yy',
			timeFormat:			'HH:mm:ss',
		    showButtonPanel: 	true,

		    minute:				'30',

		    // Copy settings from localisation translation.
		    closeText: 			wpcw_dateL10n.closeText,
		    currentText: 		wpcw_dateL10n.currentText,

		    monthNames: 		wpcw_dateL10n.monthNames,
		    monthNamesShort: 	wpcw_dateL10n.monthNamesShort,

		    dayNames: 			wpcw_dateL10n.dayNames,
		    dayNamesShort: 		wpcw_dateL10n.dayNamesShort,
		    dayNamesMin: 		wpcw_dateL10n.dayNamesMin,

		    timeText:			wpcw_dateL10n.timeText,
		    hourText:			wpcw_dateL10n.hourText,
		    minuteText:			wpcw_dateL10n.minuteText,
		    secondText:			wpcw_dateL10n.secondText,

		    firstDay: 			wpcw_dateL10n.firstDay,

		    isRTL: 				('rtl' == wpcw_dateL10n.text_direction),

		    // Alternative field that has the full date
		    altFieldTimeOnly:	false,
		    altField: 			$j(this).closest('.wpcw_datepicker_wrapper').find(".wpcw_datepicker_nonvis"),
		    altFormat: 			"yy-mm-dd",
		    altTimeFormat:		"HH:mm:ss"
		});
	});

	/*
	// Add date picker for the specific date.
	$j('.wpcw_datepicker_vis').datepicker(
	{
		dateFormat : 'd M yy',
	    showButtonPanel: 	true,

	    // Copy settings from localisation translation.
	    closeText: 			wpcw_dateL10n.closeText,
	    currentText: 		wpcw_dateL10n.currentText,
	    monthNames: 		wpcw_dateL10n.monthNames,
	    monthNamesShort: 	wpcw_dateL10n.monthNamesShort,
	    dayNames: 			wpcw_dateL10n.dayNames,
	    dayNamesShort: 		wpcw_dateL10n.dayNamesShort,
	    dayNamesMin: 		wpcw_dateL10n.dayNamesMin,
	    firstDay: 			wpcw_dateL10n.firstDay,
	    isRTL: 				('rtl' == wpcw_dateL10n.text_direction),

	    // Alternative field that has the full date
	    altField: 			".wpcw_datepicker_nonvis",
	    altFormat: 			"yy-mm-dd"
	});*/

});