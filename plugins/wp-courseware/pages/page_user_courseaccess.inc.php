<?php
/**
 * WP Courseware
 *
 * Functions relating to changing the access for a specific user.
 */


/**
 * Page where the site owner can choose which courses a user is allowed to access.
 */
function WPCW_showPage_UserCourseAccess_load()
{
	// Globals
	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	// Vars
	$page = new PageBuilder(false);
	$current_user = wp_get_current_user();

	// Show header
	$page->showPageHeader(__('Update User Course Access Permissions', 'wp_courseware'), '75%', WPCW_icon_getPageIconURL());

	// Check permisssions
	if ( ! user_can( $current_user, 'view_wpcw_courses' ) ) {
		$page->showMessage(esc_attr__('sorry, but you do not have access to modify course permissions.', 'wp_courseware' ), true );
		$page->showPageFooter();
		return false;
	}

	// Check passed user ID is valid
	$userID = WPCW_arrays_getValue($_GET, 'user_id');
	$userDetails = get_userdata($userID);
	if (!$userDetails)
	{
		$page->showMessage(__('Sorry, but that user could not be found.', 'wp_courseware'), true);
		$page->showPageFooter();
		return false;
	}

	printf(__('<p>Here you can change which courses the user <b>%s</b> (Username: <b>%s</b>) can access.</p>', 'wp_courseware'), $userDetails->data->display_name, $userDetails->data->user_login);


	// Check to see if anything has been submitted?
	if (isset($_POST['wpcw_course_user_access']))
	{
		$subUserID = WPCW_arrays_getValue($_POST, 'user_id')+0;
		$userSubDetails = get_userdata($subUserID);

		// Check that user ID is valid, and that it matches user we're editing.
		if (!$userSubDetails || $subUserID != $userID) {
			$page->showMessage(__('Sorry, but that user could not be found. The changes were not saved.', 'wp_courseware'), true);
		}

		// Continue, as things appear to be fine
		else
		{
			// List of course IDs to add
			$course_OnesTheyCanAccess = array();

			// List of enrollment dates to use for course IDs
			$course_enrolmentDates = array();

			// Get list of courses that user is allowed to access from the submitted values.
			foreach ($_POST as $key => $value)
			{
				// Check for course ID selection
				if (preg_match('/^wpcw_course_(\d+)$/', $key, $matches))
				{
					$foundCourseID = $matches[1];
					$course_OnesTheyCanAccess[] = $foundCourseID;

					// See if we have an enrollment date to update
					if (isset($_POST['wpcw_enrolment_date_nonvis_' . $foundCourseID]))
					{
						$foundDate_ts = strtotime($_POST['wpcw_enrolment_date_nonvis_' . $foundCourseID]);
						if ($foundDate_ts > 0)
						{
							$course_enrolmentDates[$foundCourseID] = $foundDate_ts;
						}
					} // check for date
				} // check for course ID
			}

			// Sync courses that the user is allowed to access
			WPCW_courses_syncUserAccess($subUserID, $course_OnesTheyCanAccess, 'sync', $course_enrolmentDates, $current_user->ID);

			// Final success message
			$message = sprintf(__('The courses for user <em>%s</em> have now been updated.', 'wp_courseware'), $userDetails->data->display_name);
			$page->showMessage($message, false);
		}
	}

	// Add sql conditions
	$SQL_WHERE = false;
	if ( ! user_can( $current_user, 'manage_wpcw_settings' ) ) {
		$SQL_WHERE = $wpdb->prepare( ' WHERE course_author = %d', $current_user->ID );
	}

	$SQL = "SELECT *
			FROM $wpcwdb->courses
			$SQL_WHERE
			ORDER BY course_title ASC
			";

	$courses = $wpdb->get_results($SQL);
	if ($courses)
	{
		$tbl = new TableBuilder();
		$tbl->attributes = array(
			'id' 	=> 'wpcw_tbl_course_access_summary',
			'class'	=> 'widefat wpcw_tbl'
		);

		$tblCol = new TableColumn(__('Allowed Access', 'wp_courseware'), 'allowed_access');
		$tblCol->cellClass = "allowed_access";
		$tbl->addColumn($tblCol);

		$tblCol = new TableColumn(__('Course Title', 'wp_courseware'), 'course_title');
		$tblCol->cellClass = "course_title";
		$tbl->addColumn($tblCol);

		$tblCol = new TableColumn(__('Description', 'wp_courseware'), 'course_desc');
		$tblCol->cellClass = "course_desc";
		$tbl->addColumn($tblCol);

		$tblCol = new TableColumn(__('Enrollment Date', 'wp_courseware'), 'enrolment_date');
		$tblCol->cellClass = "course_desc";
		$tbl->addColumn($tblCol);


		// Format row data and show it.
		$odd = false;
		foreach ($courses as $course)
		{
			$data = array();

			// Basic details of this course.
			$data['course_desc']  	= $course->course_desc;

			$editURL = admin_url('admin.php?page=WPCW_showPage_ModifyCourse&course_id=' . $course->course_id);
			$data['course_title']  	= sprintf('<a href="%s">%s</a>', $editURL, $course->course_title);

			// Get the details for this user with what they've accessed
			$accessDetails = $wpdb->get_row($wpdb->prepare("
				SELECT *
				FROM $wpcwdb->user_courses
				WHERE user_id = %d AND course_id = %d
			", $userID, $course->course_id));


			// If the user has access, then we have access details.
			$checkedHTML = (!empty($accessDetails) ? 'checked="checked"' : '');
			$data['allowed_access'] = sprintf('<input type="checkbox" name="wpcw_course_%d" %s/>', $course->course_id, $checkedHTML);

			// Use css for the row if the user has access or not.
			$accessCSS   = (!empty($accessDetails) ? 'wpcw_user_has_access' : 'wpcw_user_has_no_access');

			// Show the enrolement date
			$convertedDate_Visible 	= false;
			$convertedDate_Hidden 	= false;

			// Manually convert the release date into a timestamp to keep timezone data.
			$enrolmentDate = 0;
			if ($accessDetails) {
				$enrolmentDate = strtotime($accessDetails->course_enrolment_date);
			}

			// Got a valid enrollment date, so extract it for the update screen.
			if ($enrolmentDate > 0)
			{
				$convertedDate_Visible = date_i18n('j M Y H:i:s', $enrolmentDate);
				$convertedDate_Hidden  = date_i18n('Y-m-d H:i:s', $enrolmentDate);
			}

			// Show today's date
			else
			{
				$convertedDate_Visible = date_i18n('j M Y  H:i:s', current_time('timestamp'));
				$convertedDate_Hidden  = date_i18n('Y-m-d  H:i:s', current_time('timestamp'));
			}

			// Create the fields for picking the enrollment date manually.
			$data['enrolment_date'] =
				'<span class="wpcw_datepicker_wrapper">' .
					sprintf('<input type="text" name="wpcw_enrolment_date_vis_%d" class="wpcw_datepicker_vis" value="%s" />', 		$course->course_id, $convertedDate_Visible) .
					sprintf('<input type="hidden" name="wpcw_enrolment_date_nonvis_%s" class="wpcw_datepicker_nonvis" value="%s" />', $course->course_id, $convertedDate_Hidden) .
				'</span>';

			// Odd/Even row colouring.
			$odd = !$odd;
			$tbl->addRow($data, ($odd ? 'alternate' : '') . ' ' . $accessCSS);
		}

		// Create a form so user can update access.
		?>
		<form action="<?php str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post">
			<?php

			// Finally show table
			echo $tbl->toString();

			?>
			<input type="hidden" name="user_id" value="<?php echo $userID; ?>">
			<input type="submit" class="button-primary" name="wpcw_course_user_access" value="<?php _e('Save Changes', 'wp_courseware'); ?>" />
		</form>
		<?php
	}

	else {
		printf('<p>%s</p>', __('There are currently no courses to show. Why not create one?', 'wp_courseware'));
	}



	$page->showPageFooter();
}

?>