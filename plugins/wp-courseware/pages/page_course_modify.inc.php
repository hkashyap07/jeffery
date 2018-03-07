<?php
/**
 * WP Courseware
 *
 * Functions relating to modifying a course.
 */

/**
 * Function that allows a course to be created or edited.
 */
function WPCW_showPage_ModifyCourse_load() {
	// Vars
	$page          = new PageBuilder( true );
	$courseDetails = false;
	$courseID      = false;
	$canAddCourse  = false;
	$canEditCourse = false;
	$current_user  = wp_get_current_user();

	// Admin Email
	$current_site_admin_email = get_bloginfo( 'admin_email' );

	// Editing a course, else adding one.
	if ( isset( $_GET[ 'course_id' ] ) ) {
		$courseID      = $_GET[ 'course_id' ] + 0;
		$courseDetails = WPCW_courses_getCourseDetails( $courseID );

		// Abort if course not found.
		if ( ! $courseDetails ) {
			$page->showPageHeader( __( 'Edit Course', 'wp_courseware' ), '75%', WPCW_icon_getPageIconURL() );
			$page->showMessage( __( 'Sorry, but that course could not be found.', 'wp_courseware' ), true );
			$page->showPageFooter();

			return;
		} else {
			// Editing a course, and it was found
			$page->showPageHeader( __( 'Edit Course', 'wp_courseware' ), '75%', WPCW_icon_getPageIconURL() );

			// Check permissions, this condition allows admins to view all modules even if they are not the author.
			if ( user_can( $current_user->ID, 'manage_wpcw_settings' ) ) {
				$canEditCourse = true;
			}

			// Check Author
			if ( $courseDetails->course_author == $current_user->ID ) {
				$canEditCourse = true;
			}

			// Back compat filter
			$canEditCourse = apply_filters( 'wpcw_back_permissions_user_can_edit_course', $canEditCourse, $current_user->ID, $courseDetails );

			// Add filters to override
			$canEditCourse     = apply_filters( 'wpcw_permissions_user_can_edit_course', $canEditCourse, $current_user, $courseDetails );
			$cantEditCourseMsg = apply_filters( 'wpcw_permissions_user_can_edit_course_msg', esc_attr__( 'You are not permitted to edit this course.', 'wp_courseware' ), $current_user, $courseDetails );

			// Display message if no access.
			if ( ! $canEditCourse ) {
				$page->showMessage( $cantEditCourseMsg, true );
				$page->showPageFooter();

				return;
			}
		}
	} else {
		// Adding course
		$page->showPageHeader( __( 'Add Course', 'wp_courseware' ), '75%', WPCW_icon_getPageIconURL() );

		// Check permissions
		if ( user_can( $current_user->ID, 'view_wpcw_courses' ) ) {
			$canAddCourse = true;
		}

		// Add filter to override
		$canAddCourse     = apply_filters( 'wpcw_permissions_user_can_add_course', $canAddCourse, $current_user );
		$cantAddCourseMsg = apply_filters( 'wpcw_permissions_user_can_add_course_msg', esc_attr__( 'You are not permitted to add a new course.', 'wp_courseware' ), $current_user );

		// Legacy Fitler
		$canAddCourse     = apply_filters( 'wpcw_back_permissions_user_can_add_course', $canAddCourse, $current_user->ID );
		$cantAddCourseMsg = apply_filters( 'wpcw_back_msg_permissions_user_can_add_course', esc_attr__( 'You are not permitted to add a new course.', 'wp_courseware' ), $current_user->ID );

		// Check
		if ( ! $canAddCourse ) {
			$page->showMessage( $cantAddCourseMsg, true );
			$page->showPageFooter();

			return;
		}
	}

	// We've requested a course tool. Do the checks here...
	if ( $courseDetails && $action = WPCW_arrays_getValue( $_GET, 'action' ) ) {
		switch ( $action ) {
			// Tool - reset progress for all users.
			case 'reset_course_progress':
				// Get a list of all users on this course.
				global $wpdb, $wpcwdb;
				$userList = $wpdb->get_col( $wpdb->prepare( "
						SELECT user_id
						FROM $wpcwdb->user_courses
						WHERE course_id = %d
					", $courseDetails->course_id ) );

				$unitList = false;

				// Get all units for a course
				$courseMap = new WPCW_CourseMap();
				$courseMap->loadDetails_byCourseID( $courseDetails->course_id );
				$unitList = $courseMap->getUnitIDList_forCourse();

				// Reset all users for this course.
				WPCW_users_resetProgress( $userList, $unitList, $courseDetails, $courseMap->getUnitCount() );

				// Confirm it's complete.
				$page->showMessage( __( 'User progress for this course has been reset.', 'wp_courseware' ) );
				break;

			// Access changes
			case 'grant_access_users_all':
			case 'grant_access_users_all_subscribers':
			case 'grant_access_users_admins':
				WPCW_showPage_ModifyCourse_courseAccess_runAccessChanges( $page, $action, $courseDetails );
				break;
		}

		// Add a link back to editing, as we've hidden that panel.
		printf( '<p><a href="%s?page=WPCW_showPage_ModifyCourse&course_id=%d" class="button button-secondary">%s</a></p>', admin_url( 'admin.php' ), $courseDetails->course_id, __( '&laquo; Go back to editing the course settings', 'wp_courseware' ) );
	} else {

		global $wpcwdb;

		// Formt Details
		$formDetails = array(
			'break_course_general' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab( false ),
			),

			'course_title' => array(
				'label'    => __( 'Course Title', 'wp_courseware' ),
				'type'     => 'text',
				'required' => true,
				'cssclass' => 'wpcw_course_title',
				'desc'     => __( 'The title of your course.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 150,
					'minlen' => 1,
					'regexp' => '/^[^<>]+$/',
					'error'  => __( 'Please specify a name for your course, up to a maximum of 150 characters, just no angled brackets (&lt; or &gt;). Your trainees will be able to see this course title.', 'wp_courseware' )
				)
			),

			'course_desc' => array(
				'label'    => __( 'Course Description', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => true,
				'cssclass' => 'wpcw_course_desc',
				'desc'     => __( 'The description of this course. Your trainees will be able to see this course description.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 5000,
					'minlen' => 1,
					'error'  => __( 'Please limit the description of your course to 5000 characters.', 'wp_courseware' )
				)
			),

			'course_author' => array(
				'label' => __( 'Course Author', 'wp_courseware' ),
				'type'  => 'hidden'
			),

			/* Maybe useful in future - descoped for now.
			'course_overview_page' => array(
					'label' 	=> __('Course Overview Page', 'wp_courseware'),
					'type'  	=> 'select',
					'required'  => false,
					'desc'  	=> __('The page that links to the list of all modules for the course.', 'wp_courseware'),
					'data'	 	=> WPCW_pages_getPageList()
				),*/

			'course_opt_completion_wall' => array(
				'label'    => __( 'When do users see the next unit on the course?', 'wp_courseware' ),
				'type'     => 'radio',
				'required' => true,
				'desc'     => __( 'Can a user see all possible course units? Or must they complete previous units before seeing the next unit?', 'wp_courseware' ),
				'data'     => array(
					'all_visible'     => __( '<b>All Units Visible</b> - All units are visible regardless of completion progress.', 'wp_courseware' ),
					'completion_wall' => __( '<b>Only Completed/Next Units Visible</b> - Only show units that have been completed, plus the next unit that the user can start.', 'wp_courseware' )
				)
			),

			// ### User Access - Courses
			'break_course_access' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab(),
			),

			'course_opt_user_access' => array(
				'label'    => __( 'Granting users access to this course', 'wp_courseware' ),
				'type'     => 'radio',
				'required' => true,
				'desc'     => __( 'This setting allows you to set how users can access this course. They can either be given access automatically as soon as the user is created, or you can manually give them access. You can always manually remove access if you wish.', 'wp_courseware' ),
				'data'     => array(
					'default_show' => __( '<b>Automatic</b> - All newly created users will be given access this course.', 'wp_courseware' ),
					'default_hide' => __( '<b>Manual</b> - Users can only access course if you grant them access.', 'wp_courseware' )
				)
			),

			// ### User Messages - Modules
			'break_course_messages' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab(),
			),

			'course_message_unit_complete' => array(
				'label'    => __( 'Message - Unit Complete', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => true,
				'cssclass' => 'wpcw_course_message',
				'desc'     => __( 'The message shown to a trainee once they\'ve <b>completed a unit</b>, which is displayed at the bottom of the unit page. HTML is OK.', 'wp_courseware' ),
				'rows'     => 2,
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 500,
					'minlen' => 1,
					'error'  => __( 'Please limit message to 500 characters.', 'wp_courseware' )
				)
			),

			'course_message_course_complete' => array(
				'label'    => __( 'Message - Course Complete', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => true,
				'cssclass' => 'wpcw_course_message',
				'desc'     => __( 'The message shown to a trainee once they\'ve <b>completed the whole course</b>, which is displayed at the bottom of the unit page. HTML is OK.', 'wp_courseware' ),
				'rows'     => 2,
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 500,
					'minlen' => 1,
					'error'  => __( 'Please limit message to 500 characters.', 'wp_courseware' )
				)
			),

			'course_message_unit_pending' => array(
				'label'    => __( 'Message - Unit Pending', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => true,
				'cssclass' => 'wpcw_course_message',
				'desc'     => __( 'The message shown to a trainee when they\'ve <b>yet to complete a unit</b>. This message is displayed at the bottom of the unit page, along with a button that says "<b>Mark as completed</b>". HTML is OK.', 'wp_courseware' ),
				'rows'     => 2,
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 500,
					'minlen' => 1,
					'error'  => __( 'Please limit message to 500 characters.', 'wp_courseware' )
				)
			),

			'course_message_prerequisite_not_met' => array(
				'label'    => __( 'Message - Prerequisite not met', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => true,
				'cssclass' => 'wpcw_course_message_prerequisite_not_met',
				'desc'     => __( 'The message shown to a student that attempts to access a course that has one or more prerequisites that have not been met.', 'wp_courseware' ),
				'rows'     => 2,
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 500,
					'minlen' => 1,
					'error'  => __( 'Please limit message to 500 characters.', 'wp_courseware' )
				)
			),

			'course_message_unit_no_access' => array(
				'label'    => __( 'Message - Access Denied', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => true,
				'cssclass' => 'wpcw_course_message',
				'desc'     => __( 'The message shown to a trainee they are <b>not allowed to access a unit</b>, because they are not allowed to <b>access the whole course</b>.', 'wp_courseware' ),
				'rows'     => 2,
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 500,
					'minlen' => 1,
					'error'  => __( 'Please limit message to 500 characters.', 'wp_courseware' )
				)
			),

			'course_message_unit_not_yet' => array(
				'label'    => __( 'Message - Not Yet Available', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => true,
				'cssclass' => 'wpcw_course_message',
				'desc'     => __( 'The message shown to a trainee they are <b>not allowed to access a unit yet</b>, because they need to complete a previous unit.', 'wp_courseware' ),
				'rows'     => 2,
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 500,
					'minlen' => 1,
					'error'  => __( 'Please limit message to 500 characters.', 'wp_courseware' )
				)
			),

			'course_message_unit_not_yet_dripfeed' => array(
				'label'    => __( 'Message - Not Unlocked Yet', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => true,
				'cssclass' => 'wpcw_course_message',
				'desc'     => __( 'The message shown to a trainee they are <b>not allowed to access a unit yet</b>, because the unit is locked due to a drip feed setting. Use the variable <code>{UNIT_UNLOCKED_TIME}</code> to insert the approximate days and hours when the unit will be unlocked.', 'wp_courseware' ),
				'rows'     => 2,
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 500,
					'minlen' => 1,
					'error'  => __( 'Please limit message to 500 characters.', 'wp_courseware' )
				)
			),

			'course_message_unit_not_logged_in' => array(
				'label'    => __( 'Message - Not Logged In', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => true,
				'cssclass' => 'wpcw_course_message',
				'desc'     => __( 'The message shown to a trainee they are <b>not logged in</b>, and therefore cannot access the unit.', 'wp_courseware' ),
				'rows'     => 2,
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 500,
					'minlen' => 1,
					'error'  => __( 'Please limit message to 500 characters.', 'wp_courseware' )
				)
			),

			'course_message_quiz_open_grading_blocking' => array(
				'label'    => __( 'Message - Open-Question Submitted - Blocking Mode', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => true,
				'cssclass' => 'wpcw_course_message',
				'desc'     => __( 'The message shown to a trainee they have submitted an answer to an <b>open-ended or upload question</b>, and you need to grade their answer <b>before they continue</b>.', 'wp_courseware' ),
				'rows'     => 2,
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 500,
					'minlen' => 1,
					'error'  => __( 'Please limit message to 500 characters.', 'wp_courseware' )
				)
			),

			'course_message_quiz_open_grading_non_blocking' => array(
				'label'    => __( 'Message - Open-Question Submitted - Non-Blocking Mode', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => true,
				'cssclass' => 'wpcw_course_message',
				'desc'     => __( 'The message shown to a trainee they have submitted an answer to an <b>open-ended or upload question</b>, and you need to grade their answer, but they can <b>continue anyway</b>.', 'wp_courseware' ),
				'rows'     => 2,
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 500,
					'minlen' => 1,
					'error'  => __( 'Please limit message to 500 characters.', 'wp_courseware' )
				)
			),

			// ### User Notifications - From Email Address details
			'break_course_notifications_from_details' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab(),
			),

			'course_from_email' => array(
				'label'    => __( 'Email From Address', 'wp_courseware' ),
				'type'     => 'text',
				'required' => true,
				'cssclass' => 'wpcw_course_email',
				'desc'     => __( 'The email address that the email notifications should be from.<br/>Depending on your server\'s spam-protection set up, this may not appear in the outgoing emails.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'email',
					'maxlen' => 150,
					'minlen' => 1,
					'error'  => __( 'Please enter a valid email address.', 'wp_courseware' )
				)
			),

			'course_from_name' => array(
				'label'    => __( 'Email From Name', 'wp_courseware' ),
				'type'     => 'text',
				'required' => true,
				'cssclass' => 'wpcw_course_email',
				'desc'     => __( 'The name used on the email notifications, which are sent to you and your trainees. <br/>Depending on your server\'s spam-protection set up, this may not appear in the outgoing emails.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 150,
					'minlen' => 1,
					'regexp' => '/^[^<>]+$/',
					'error'  => __( 'Please specify a from name, up to a maximum of 150 characters, just no angled brackets (&lt; or &gt;).', 'wp_courseware' )
				)
			),

			'course_to_email' => array(
				'label'    => __( 'Admin Notify Email Address', 'wp_courseware' ),
				'type'     => 'text',
				'required' => true,
				'cssclass' => 'wpcw_course_email',
				'desc'     => __( 'The email address to send admin notifications to.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'email',
					'maxlen' => 150,
					'minlen' => 1,
					'error'  => __( 'Please enter a valid email address.', 'wp_courseware' )
				)
			),

			// ### Email Notifications - Units
			'break_course_notifications_user_unit' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab(),
			),

			'email_unit_unlocked_subject' => array(
				'label'    => __( 'Unit Unlocked - Email Subject', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => false,
				'cssclass' => 'wpcw_course_email_template_subject',
				'rows'     => 2,
				'desc'     => __( 'The <b>subject line</b> for the email sent to a user when a unit that\'s being drip fed is <b>unlocked and available</b> for them to access.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 300,
					'minlen' => 1,
					'error'  => __( 'Please limit the email subject to 300 characters.', 'wp_courseware' )
				)
			),

			'email_unit_unlocked_body' => array(
				'label'    => __( 'Unit Unlocked - Email Body', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => false,
				'rows'     => 10,
				'cssclass' => 'wpcw_course_email_template',
				'desc'     => __( 'The <b>template body</b> for the email sent to a user when a unit that\'s being drip fed is <b>unlocked and available</b> for them to access.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 5000,
					'minlen' => 1,
					'error'  => __( 'Please limit the email body to 5000 characters.', 'wp_courseware' )
				)
			),

			// ### Email Notifications - Modules
			'break_course_notifications_user_module' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab(),
			),

			'email_complete_module_option_admin' => array(
				'label'    => __( 'Module Complete - Notify You?', 'wp_courseware' ),
				'type'     => 'radio',
				'required' => true,
				'cssclass' => 'wpcw_course_email_template_option',
				'data'     => array(
					'send_email' => __( '<b>Send me an email</b> - when one of your trainees has completed a module.', 'wp_courseware' ),
					'no_email'   => __( '<b>Don\'t send me an email</b> - when one of your trainees has completed a module.', 'wp_courseware' )
				)
			),

			'email_complete_module_option' => array(
				'label'    => __( 'Module Complete - Notify User?', 'wp_courseware' ),
				'type'     => 'radio',
				'required' => true,
				'cssclass' => 'wpcw_course_email_template_option',
				'data'     => array(
					'send_email' => __( '<b>Send Email</b> - to user when module has been completed.', 'wp_courseware' ),
					'no_email'   => __( '<b>Don\'t Send Email</b> - to user when module has been completed.', 'wp_courseware' )
				)
			),

			'email_complete_module_subject' => array(
				'label'    => __( 'Module Complete - Email Subject', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => false,
				'cssclass' => 'wpcw_course_email_template_subject',
				'rows'     => 2,
				'desc'     => __( 'The <b>subject line</b> for the email sent to a user when they complete a <b>module</b>.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 300,
					'minlen' => 1,
					'error'  => __( 'Please limit the email subject to 300 characters.', 'wp_courseware' )
				)
			),

			'email_complete_module_body' => array(
				'label'    => __( 'Module Complete - Email Body', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => false,
				'rows'     => 10,
				'cssclass' => 'wpcw_course_email_template',
				'desc'     => __( 'The <b>template body</b> for the email sent to a user when they complete a <b>module</b>.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 5000,
					'minlen' => 1,
					'error'  => __( 'Please limit the email body to 5000 characters.', 'wp_courseware' )
				)
			),

			// ### Email Notifications - Courses
			'break_course_notifications_user_course' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab(),
			),

			'email_complete_course_option_admin' => array(
				'label'    => __( 'Course Complete - Notify You?', 'wp_courseware' ),
				'type'     => 'radio',
				'required' => true,
				'cssclass' => 'wpcw_course_email_template_option',
				'data'     => array(
					'send_email' => __( '<b>Send me an email</b> - when one of your trainees has completed the whole course.', 'wp_courseware' ),
					'no_email'   => __( '<b>Don\'t send me an email</b> - when one of your trainees has completed the whole course.', 'wp_courseware' )
				)
			),

			'email_complete_course_option' => array(
				'label'    => __( 'Course Complete - Notify User?', 'wp_courseware' ),
				'type'     => 'radio',
				'required' => true,
				'cssclass' => 'wpcw_course_email_template_option',
				'data'     => array(
					'send_email' => __( '<b>Send Email</b> - to user when the whole course has been completed.', 'wp_courseware' ),
					'no_email'   => __( '<b>Don\'t Send Email</b> - to user when the whole course has been completed.', 'wp_courseware' )
				)
			),

			'email_complete_course_subject' => array(
				'label'    => __( 'Course Complete - Email Subject', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => false,
				'cssclass' => 'wpcw_course_email_template_subject',
				'rows'     => 2,
				'desc'     => __( 'The <b>subject line</b> for the email sent to a user when they complete <b>the whole course</b>.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 300,
					'minlen' => 1,
					'error'  => __( 'Please limit the email subject to 300 characters.', 'wp_courseware' )
				)
			),

			'email_complete_course_body' => array(
				'label'    => __( 'Course Complete - Email Body', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => false,
				'rows'     => 10,
				'cssclass' => 'wpcw_course_email_template',
				'desc'     => __( 'The <b>template body</b> for the email sent to a user when they complete <b>the whole course</b>.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 5000,
					'minlen' => 1,
					'error'  => __( 'Please limit the email body to 5000 characters.', 'wp_courseware' )
				)
			),

			// ### User Notifications - Quiz Grades
			'break_course_notifications_user_grades' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab(),
			),

			'email_quiz_grade_option' => array(
				'label'    => __( 'Quiz Grade - Notify User?', 'wp_courseware' ),
				'type'     => 'radio',
				'required' => true,
				'cssclass' => 'wpcw_course_email_template_option',
				'data'     => array(
					'send_email' => __( '<b>Send Email</b> - to user after a quiz is graded (automatically or by the instructor).', 'wp_courseware' ),
					'no_email'   => __( '<b>Don\'t Send Email</b> - to user when a quiz is graded.', 'wp_courseware' )
				),
			),

			'email_quiz_grade_subject' => array(
				'label'    => __( 'Quiz Graded - Email Subject', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => false,
				'cssclass' => 'wpcw_course_email_template_subject',
				'rows'     => 2,
				'desc'     => __( 'The <b>subject line</b> for the email sent to a user when they receive a <b>grade for a quiz</b>.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 300,
					'minlen' => 1,
					'error'  => __( 'Please limit the email subject to 300 characters.', 'wp_courseware' )
				)
			),

			'email_quiz_grade_body' => array(
				'label'    => __( 'Quiz Graded - Email Body', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => false,
				'cssclass' => 'wpcw_course_email_template',
				'desc'     => __( 'The <b>template body</b> for the email sent to a user when they receive a <b>grade for a quiz</b>.', 'wp_courseware' ),
				'rows'     => 10,
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 5000,
					'minlen' => 1,
					'error'  => __( 'Please limit the email body to 5000 characters.', 'wp_courseware' )
				)
			),

			// ### User Notifications - Final Summary Email
			'break_course_notifications_user_final' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab(),
			),

			'email_complete_course_grade_summary_subject' => array(
				'label'    => __( 'Final Summary - Email Subject', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => false,
				'cssclass' => 'wpcw_course_email_template_subject',
				'rows'     => 2,
				'desc'     => __( 'The <b>subject line</b> for the email sent to a user when they receive their <b>grade summary at the end of the course</b>.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 300,
					'minlen' => 1,
					'error'  => __( 'Please limit the email subject to 300 characters.', 'wp_courseware' )
				)
			),

			'email_complete_course_grade_summary_body' => array(
				'label'    => __( 'Final Summary - Email Body', 'wp_courseware' ),
				'type'     => 'textarea',
				'required' => false,
				'rows'     => 20,
				'cssclass' => 'wpcw_course_email_template',
				'desc'     => __( 'The <b>template body</b> for the email sent to a user when they receive their <b>grade summary at the end of the course</b>.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 5000,
					'minlen' => 1,
					'error'  => __( 'Please limit the email body to 5000 characters.', 'wp_courseware' )
				)
			),

			// ### Certificates - Courses
			'break_course_certificates_user_course' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab(),
			),

			'course_opt_use_certificate' => array(
				'label'    => __( 'Enable certificates?', 'wp_courseware' ),
				'type'     => 'radio',
				'cssclass' => 'wpcw_course_opt_use_certificate',
				'required' => true,
				'data'     => array(
					'use_certs' => __( '<b>Yes</b> - generate a PDF certificate when user completes this course.', 'wp_courseware' ),
					'no_certs'  => __( '<b>No</b> - don\'t generate a PDF certificate when user completes this course.', 'wp_courseware' )
				)
			),

			'cert_signature_type' => array(
				'label'    => __( 'Signature Type', 'wp_courseware' ),
				'type'     => 'radio',
				'cssclass' => 'wpcw_cert_signature_type',
				'required' => 'true',
				'data'     => array(
					'text'  => sprintf( '<b>%s</b> - %s', __( 'Text', 'wp_courseware' ), __( 'Just use text for the signature.', 'wp_courseware' ) ),
					'image' => sprintf( '<b>%s</b> - %s', __( 'Image File', 'wp_courseware' ), __( 'Use an image for the signature.', 'wp_courseware' ) ),
				),
			),

			'cert_sig_text' => array(
				'label'    => __( 'Name to use for signature', 'wp_courseware' ),
				'type'     => 'text',
				'cssclass' => 'wpcw_cert_signature_type_text',
				'desc'     => __( 'The name to use for the signature area.', 'wp_courseware' ),
				'validate' => array(
					'type'   => 'string',
					'maxlen' => 150,
					'minlen' => 1,
					'regexp' => '/^[^<>]+$/',
					'error'  => __( 'Please enter the name to use for the signature area.', 'wp_courseware' ),
				)
			),

			'cert_sig_image_url' => array(
				'label'     => __( 'Your Signature Image', 'wp_courseware' ),
				'cssclass'  => 'wpcw_image_upload_field wpcw_cert_signature_type_image',
				'type'      => 'text',
				'desc'      => '&bull;&nbsp;' . __( 'The URL of your signature image. Using a transparent image is recommended.', 'wp_courseware' ) . '<br/>&bull;&nbsp;' . sprintf( __( 'The image must be <b>%d pixels wide, and %d pixels high</b> to render correctly. ', 'wp_courseware' ), WPCW_CERTIFICATE_SIGNATURE_WIDTH_PX * 2, WPCW_CERTIFICATE_SIGNATURE_HEIGHT_PX * 2 ),
				'validate'  => array(
					'type'   => 'url',
					'maxlen' => 300,
					'minlen' => 1,
					'error'  => __( 'Please enter the URL of your signature image.', 'wp_courseware' ),
				),
				'extrahtml' => sprintf( '<a id="cert_sig_image_url_btn" href="#" class="wpcw_insert_image button-secondary" data-uploader_title="%s" data-uploader_btn_text="%s" data-target="cert_sig_image_url"><span class="wpcw_insert_image_img"></span> %s</a>', __( 'Choose an image to use for the signature image...', 'wp_courseware' ), __( 'Select Image', 'wp_courseware' ), __( 'Select Image', 'wp_courseware' ) ),
			),

			'cert_logo_enabled' => array(
				'label'    => __( 'Show your logo?', 'wp_courseware' ),
				'cssclass' => 'wpcw_cert_logo_enabled',
				'type'     => 'radio',
				'required' => 'true',
				'data'     => array(
					'cert_logo'    => sprintf( '<b>%s</b> - %s', __( 'Yes', 'wp_courseware' ), __( 'Use your logo on the certificate.', 'wp_courseware' ) ),
					'no_cert_logo' => sprintf( '<b>%s</b> - %s', __( 'No', 'wp_courseware' ), __( 'Don\'t show a logo on the certificate.', 'wp_courseware' ) ),
				),
			),

			'cert_logo_url' => array(
				'label'     => __( 'Your Logo Image', 'wp_courseware' ),
				'type'      => 'text',
				'cssclass'  => 'wpcw_cert_logo_url wpcw_image_upload_field',
				'desc'      => '&bull;&nbsp;' . __( 'The URL of your logo image. Using a transparent image is recommended.', 'wp_courseware' ) . '<br/>&bull;&nbsp;' . sprintf( __( 'The image must be <b>%d pixels wide, and %d pixels</b> high to render correctly. ', 'wp_courseware' ), WPCW_CERTIFICATE_LOGO_WIDTH_PX * 2, WPCW_CERTIFICATE_LOGO_HEIGHT_PX * 2 ),
				'validate'  => array(
					'type'   => 'url',
					'maxlen' => 300,
					'minlen' => 1,
					'error'  => __( 'Please enter the URL of your logo image.', 'wp_courseware' ),
				),
				'extrahtml' => sprintf( '<a id="cert_logo_url_btn" href="#" class="wpcw_insert_image button-secondary" data-uploader_title="%s" data-uploader_btn_text="%s" data-target="cert_logo_url"><span class="wpcw_insert_image_img"></span> %s</a>', __( 'Choose an image to use for your logo on the certificate...', 'wp_courseware' ), __( 'Select Image', 'wp_courseware' ), __( 'Select Image', 'wp_courseware' ) ),
			),

			'cert_background_type' => array(
				'label'    => __( 'Certificate Background', 'wp_courseware' ),
				'cssclass' => 'wpcw_cert_background_type',
				'type'     => 'radio',
				'required' => 'true',
				'data'     => array(
					'use_default' => sprintf( '<b>%s</b> - %s', __( 'Built-in', 'wp_courseware' ), __( 'Use the built-in certificate background.', 'wp_courseware' ) ),
					'use_custom'  => sprintf( '<b>%s</b> - %s', __( 'Custom', 'wp_courseware' ), __( 'Use your own certificate background.', 'wp_courseware' ) ),
				),
			),

			'cert_background_custom_url' => array(
				'label'     => __( 'Custom Background Image', 'wp_courseware' ),
				'type'      => 'text',
				'cssclass'  => 'wpcw_cert_background_custom_url wpcw_image_upload_field',
				'desc'      => '&bull;&nbsp;' . __( 'The URL of your background image.', 'wp_courseware' ) . '<br/>&bull;&nbsp;' . sprintf( __( 'The background image must be <b>%d pixels wide, and %d pixels</b> high at <b>72 dpi</b> to render correctly. ', 'wp_courseware' ), WPCW_CERTIFICATE_BG_WIDTH_PX, WPCW_CERTIFICATE_BG_HEIGHT_PX ),
				'validate'  => array(
					'type'   => 'url',
					'maxlen' => 300,
					'minlen' => 1,
					'error'  => __( 'Please enter the URL of your certificate background image.', 'wp_courseware' ),
				),
				'extrahtml' => sprintf( '<a id="cert_background_custom_url_btn" href="#" class="wpcw_insert_image button-secondary" data-uploader_title="%s" data-uploader_btn_text="%s" data-target="cert_background_custom_url"><span class="wpcw_insert_image_img"></span> %s</a>', __( 'Choose an image to use for the certificate background...', 'wp_courseware' ), __( 'Select Image', 'wp_courseware' ), __( 'Select Image', 'wp_courseware' ) ),
			),

			'cert_tools_preview' => array(
				'label'    => __( 'Preview Certificate', 'wp_courseware' ),
				'type'     => 'custom',
				'cssclass' => 'wpcw_cert_tool_preview_cert',
				'html'     => sprintf( '<div class="certifcate-preview-button">%s</div>', __( 'Please save the course details to preview your certificate.', 'wp_courseware' ) )
			),

			// ### Course Tools
			'break_course_certificates_user_tools' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab(),
			),

			'course_tools_reset_all_users' => array(
				'label' => __( 'Reset User Progess for this Course?', 'wp_courseware' ),
				'type'  => 'custom',
				'html'  => sprintf( '<a href="%s?page=WPCW_showPage_ModifyCourse&course_id=%d&action=reset_course_progress" class="button-primary" id="wpcw_course_btn_progress_reset_whole_course">%s</a><p>%s</p>', admin_url( 'admin.php' ), $courseID, __( 'Reset All Users on this Course to the start', 'wp_courseware' ), __( 'This button will reset all users who can access this course back to the beginning of the course. This deletes all grade data too.', 'wp_courseware' ) )
			),

			'course_tools_user_access' => array(
				'label' => __( 'Bulk-grant access to this course?', 'wp_courseware' ),
				'type'  => 'custom',
				'html'  => sprintf( '<a href="%s?page=WPCW_showPage_ModifyCourse&course_id=%d&action=grant_access_users_all" class="button-primary" id="wpcw_course_btn_access_all_existing_users">%s</a>&nbsp;&nbsp;
										    <a href="%s?page=WPCW_showPage_ModifyCourse&course_id=%d&action=grant_access_users_admins" class="button-primary" id="wpcw_course_btn_access_all_existing_admins">%s</a>
										    <p>%s</p>', admin_url( 'admin.php' ), $courseID, __( 'All Existing Users (including Administrators)', 'wp_courseware' ),

					admin_url( 'admin.php' ), $courseID, __( 'Only All Existing Administrators', 'wp_courseware' ),

					__( 'You can use the buttons above to grant all users access to this course. Depending on how many users you have, this may be a slow process.', 'wp_courseware' ) )
			),

			// ### Course Prerequisites
			'break_course_prerequisites' => array(
				'type' => 'break',
				'html' => WPCW_forms_createBreakHTML_tab(),
			)
		);

		// Get Courses
		$coursesSelect = WPCW_courses_getCourseList( false, $courseID );

		// Course Prerequisites
		if ( ! empty( $coursesSelect ) ) {
			$formDetails[ 'course_opt_prerequisites' ] = array(
				'label'         => __( 'Select the courses that must be completed in order to access this course.', 'wp_courseware' ),
				'labelposition' => 'top',
				'type'          => 'checkboxlist',
				'required'      => false,
				'data'          => $coursesSelect
			);
		} else {
			$formDetails[ 'course_prerequisites_none_message' ] = array(
				'label'         => __( 'Select the courses that must be completed in order to access this course.', 'wp_courseware' ),
				'labelposition' => 'top',
				'type'          => 'custom',
				'html'          => sprintf( '<em>%s</em><br /><br /><a class="button-primary" href="%s">%s</a>', __( 'There are currently no other courses available. Please add more courses to add course prerequisites to this course.', 'wp_courseware' ), add_query_arg( array( 'page' => 'WPCW_showPage_ModifyCourse' ), esc_url( admin_url( 'admin.php' ) ) ), __( 'Add Course', 'wp_courseware' ) )
			);
		}

		// Generate the tabs.
		$tabList = array(
			'break_course_general'                    => array( 'label' => __( 'General Course Details', 'wp_courseware' ) ),
			'break_course_access'                     => array( 'label' => __( 'User Access', 'wp_courseware' ) ),
			'break_course_messages'                   => array( 'label' => __( 'User Messages', 'wp_courseware' ) ),
			'break_course_notifications_from_details' => array( 'label' => __( 'Email Address Details', 'wp_courseware' ) ),
			'break_course_notifications_user_unit'    => array( 'label' => __( 'Email Notifications - Units', 'wp_courseware' ) ),
			'break_course_notifications_user_module'  => array( 'label' => __( 'Email Notifications - Module', 'wp_courseware' ) ),
			'break_course_notifications_user_course'  => array( 'label' => __( 'Email Notifications - Course', 'wp_courseware' ) ),
			'break_course_notifications_user_grades'  => array( 'label' => __( 'Email Notifications - Quiz Grades', 'wp_courseware' ) ),
			'break_course_notifications_user_final'   => array( 'label' => __( 'Email Notifications - Final Summary', 'wp_courseware' ) ),
			'break_course_certificates_user_course'   => array( 'label' => __( 'Certificates', 'wp_courseware' ) ),
			'break_course_certificates_user_tools'    => array( 'label' => __( 'Course Access Tools', 'wp_courseware' ) ),
			'break_course_prerequisites'              => array( 'label' => __( 'Course Prerequisites', 'wp_courseware' ) )
		);

		// Check permissions for teachers
		if ( ! user_can( $current_user, 'manage_wpcw_settings' ) ) {
			$formDetails[ 'course_tools_user_access'] = array(
				'label' => __( 'Bulk-grant access to this course?', 'wp_courseware' ),
				'type'  => 'custom',
				'html'  => sprintf(
					'<a href="%s?page=WPCW_showPage_ModifyCourse&course_id=%d&action=grant_access_users_all_subscribers" class="button-primary" id="wpcw_course_btn_access_all_existing_users">%s</a><p>%s</p>', admin_url( 'admin.php' ),
					$courseID,
					__( 'All Existing Users', 'wp_courseware' ),
					__( 'You can use the buttons above to grant all users access to this course. Depending on how many users you have, this may be a slow process.', 'wp_courseware' ) )
			);
		}

		// Remove reset fields if not appropriate.
		if ( ! $courseDetails ) {
			// The tab
			unset( $tabList[ 'break_course_certificates_user_tools' ] );

			// The tool
			unset( $formDetails[ 'break_course_certificates_user_tools' ] );
			unset( $formDetails[ 'course_tools_reset_all_users' ] );
			unset( $formDetails[ 'course_tools_user_access' ] );
		}

		$form = new RecordsForm(
			$formDetails,       // List of form elements
			$wpcwdb->courses,   // Table for main details
			'course_id',        // Primary key column name
			false,
			'wpcw_course_settings'
		);

		$form->customFormErrorMsg = __( 'Sorry, but unfortunately there were some errors saving the course details. Please fix the errors and try again.', 'wp_courseware' );
		$form->setAllTranslationStrings( WPCW_forms_getTranslationStrings() );

		// Set defaults if adding a new course
		if ( ! $courseDetails ) {
			$form->loadDefaults( array(

				// Add basic Email Template to defaults when creating a new course.
				'email_complete_module_subject'                 => EMAIL_TEMPLATE_COMPLETE_MODULE_SUBJECT,
				'email_complete_course_subject'                 => EMAIL_TEMPLATE_COMPLETE_COURSE_SUBJECT,
				'email_quiz_grade_subject'                      => EMAIL_TEMPLATE_QUIZ_GRADE_SUBJECT,
				'email_complete_course_grade_summary_subject'   => EMAIL_TEMPLATE_COURSE_SUMMARY_WITH_GRADE_SUBJECT,
				'email_unit_unlocked_subject'                   => EMAIL_TEMPLATE_UNIT_UNLOCKED_SUBJECT,

				// Email bodies
				'email_complete_module_body'                    => EMAIL_TEMPLATE_COMPLETE_MODULE_BODY,
				'email_complete_course_body'                    => EMAIL_TEMPLATE_COMPLETE_COURSE_BODY,
				'email_quiz_grade_body'                         => EMAIL_TEMPLATE_QUIZ_GRADE_BODY,
				'email_complete_course_grade_summary_body'      => EMAIL_TEMPLATE_COURSE_SUMMARY_WITH_GRADE_BODY,
				'email_unit_unlocked_body'                      => EMAIL_TEMPLATE_UNIT_UNLOCKED_BODY,

				// Email address details
				'course_from_name'                              => get_bloginfo( 'name' ),
				'course_from_email'                             => ( $current_user->user_email != $current_site_admin_email ) ? $current_user->user_email : $current_site_admin_email,
				'course_to_email'                               => ( $current_user->user_email != $current_site_admin_email ) ? $current_user->user_email : $current_site_admin_email,

				// Course Author
				'course_author'                                 => $current_user->ID,

				// Completion wall default (blocking mode)
				'course_opt_completion_wall'                    => 'completion_wall',
				'course_opt_user_access'                        => 'default_show',

				// Email notification defaults (yes to send email)
				'email_complete_course_option_admin'            => 'send_email',
				'email_complete_course_option'                  => 'send_email',
				'email_complete_module_option_admin'            => 'send_email',
				'email_complete_module_option'                  => 'send_email',
				'email_quiz_grade_option'                       => 'send_email',

				// Certificate defaults
				'course_opt_use_certificate'                    => 'no_certs',
				'cert_signature_type'                           => 'text',
				'cert_sig_text'                                 => get_bloginfo( 'name' ),
				'cert_logo_enabled'                             => 'no_cert_logo',
				'cert_background_type'                          => 'use_default',

				// User Messages
				'course_message_unit_not_yet'                   => __( "You need to complete the previous unit first.", 'wp_courseware' ),
				'course_message_unit_pending'                   => __( "Have you completed this unit? Then mark this unit as completed.", 'wp_courseware' ),
				'course_message_unit_complete'                  => __( "You have now completed this unit.", 'wp_courseware' ),
				'course_message_course_complete'                => __( "You have now completed the whole course. Congratulations!", 'wp_courseware' ),
				'course_message_unit_no_access'                 => __( "Sorry, but you're not allowed to access this course.", 'wp_courseware' ),
				'course_message_prerequisite_not_met'           => __( "This course can not be accessed until the prerequisites for this course are complete.", 'wp_courseware' ),
				'course_message_unit_not_logged_in'             => __( 'You cannot view this unit as you\'re not logged in yet.', 'wp_courseware' ),
				'course_message_unit_not_yet_dripfeed'          => __( "This unit isn't available just yet. Please check back in about {UNIT_UNLOCKED_TIME}. ", 'wp_courseware' ),

				// User Messages - quizzes
				'course_message_quiz_open_grading_blocking'     => __( 'Your quiz has been submitted for grading by the course instructor. Once your grade has been entered, you will be able access the next unit.', 'wp_courseware' ),
				'course_message_quiz_open_grading_non_blocking' => __( 'Your quiz has been submitted for grading by the course instructor. You have now completed this unit.', 'wp_courseware' ),
			) );
		}

		// Useful place to go
		$directionMsg = '<br/></br>' . sprintf( __( 'Do you want to return to the <a href="%s">course summary page</a>?', 'wp_courseware' ), admin_url( 'admin.php?page=WPCW_wp_courseware' ) );

		// Override success messages
		$form->msg_record_created = __( 'Course details successfully created. ', 'wp_courseware' ) . $directionMsg;
		$form->msg_record_updated = __( 'Course details successfully updated. ', 'wp_courseware' ) . $directionMsg;

		$form->setPrimaryKeyValue( $courseID );
		$form->setSaveButtonLabel( __( 'Save ALL Details', 'wp_courseware' ) );

		// Process form
		$formHTML = $form->getHTML();

		// Certificate Details
		$preview_certificate_url = add_query_arg( array( 'page' => 'wpcw_pdf_create_certificate', 'certificate' => 'preview', 'course_id' => $form->primaryKeyValue ), esc_url( site_url( '/' ) ) );
		if ( $form->primaryKeyValue ) {
			$formHTML = str_replace(
				sprintf( '<div class="certifcate-preview-button">%s</div>', __( 'Please save the course details to preview your certificate.', 'wp_courseware' ) ),
				sprintf( '<a href="%s" target="_blank" class="button-primary">%s</a><p>%s</p>', $preview_certificate_url, __('Preview Certificate', 'wp_courseware'), __( 'After saving the course settings, you can preview the certificate using the button above. The preview opens in a new window.', 'wp_courseware' ) ),
				$formHTML
			);
		}

		// Show message about this course having quizzes that require a pass mark.
		// Need updated details for this.
		$courseDetails = WPCW_courses_getCourseDetails( $courseID );
		if ( $courseDetails && $courseDetails->course_opt_completion_wall == 'all_visible' ) {
			$quizzes = WPCW_quizzes_getAllBlockingQuizzesForCourse( $courseDetails->course_id );

			// Count how many blocking quizzes there are.
			if ( $quizzes && count( $quizzes ) > 0 ) {
				$quizCountMessage = sprintf( __( 'Currently <b>%d of your quizzes</b> are blocking process based on a percentage score <b>in this course</b>.', 'wp_courseware' ), count( $quizzes ) );
			} else {
				$quizCountMessage = __( 'You do not currently have any blocking quizzes for this course.', 'wp_courseware' );
			}

			printf( '<div id="message" class="wpcw_msg_info wpcw_msg"><b>%s</b> - %s<br/><br/>
					%s
					</div>', __( 'Important Note', 'wp_courseware' ), __( 'You have selected <b>All Units Visible</b>. If you create a quiz blocking progress based on a percentage score, students will have access to the entire course regardless of quiz score.', 'wp_courseware' ), $quizCountMessage );

		}

		// Generate the tabs
		echo WPCW_tabs_generateTabHeader( $tabList, 'wpcw_courses_tabs', false );

		// Show the form
		echo $formHTML;
		echo '</div>'; // .wpcw_tab_wrapper

	} // end if not doing a tool manipulation.

	$page->showPageMiddle( '20%' );

	// Include a link to delete the course
	if ( $courseDetails ) {
		$page->openPane( 'wpcw-deletion-course', __( 'Delete Course?', 'wp_courseware' ) );
		WPCW_showPage_ModifyCourse_deleteCourseButton( $courseDetails );
		$page->closePane();
	}

	// Email template tags here...
	$page->openPane( 'wpcw_docs_email_tags', __( 'Email Template Tags', 'wp_courseware' ) );

	printf( '<h4 class="wpcw_docs_side_mini_hdr">%s</h4>', __( 'All Email Notifications', 'wp_courseware' ) );
	printf( '<dl class="wpcw_email_tags">' );

	printf( '<dt>{USER_NAME}</dt><dd>%s</dd>', __( 'The display name of the user.', 'wp_courseware' ) );
	printf( '<dt>{FIRST_NAME}</dt><dd>%s</dd>', __( 'The first name of the user.', 'wp_courseware' ) );
	printf( '<dt>{LAST_NAME}</dt><dd>%s</dd>', __( 'The last name of the user.', 'wp_courseware' ) );

	printf( '<dt>{SITE_NAME}</dt><dd>%s</dd>', __( 'The name of the website.', 'wp_courseware' ) );
	printf( '<dt>{SITE_URL}</dt><dd>%s</dd>', __( 'The URL of the website.', 'wp_courseware' ) );

	printf( '<dt>{COURSE_TITLE}</dt><dd>%s</dd>', __( 'The title of the course for the unit that\'s just been completed.', 'wp_courseware' ) );
	printf( '<dt>{MODULE_TITLE}</dt><dd>%s</dd>', __( 'The title of the module for the unit that\'s just been completed.', 'wp_courseware' ) );
	printf( '<dt>{MODULE_NUMBER}</dt><dd>%s</dd>', __( 'The number of the module for the unit that\'s just been completed.', 'wp_courseware' ) );

	printf( '<dt>{UNIT_TITLE}</dt><dd>%s</dd>', __( 'The title of the unit that is associated with the quiz.', 'wp_courseware' ) );
	printf( '<dt>{UNIT_URL}</dt><dd>%s</dd>', __( 'The URL of the unit that is associated with the quiz.', 'wp_courseware' ) );

	printf( '<dt>{CERTIFICATE_LINK}</dt><dd>%s</dd>', __( 'If the course has PDF certificates enabled, this is the link of the PDF certficate. (If there is no certificate or certificates are not enabled, this is simply blank)', 'wp_courseware' ) );

	printf( '</dl>' );

	printf( '<h4 class="wpcw_docs_side_mini_hdr">%s</h4>', __( 'Quiz Email Notifications Only', 'wp_courseware' ) );
	printf( '<dl class="wpcw_email_tags">' );
	printf( '<dt>{QUIZ_TITLE}</dt><dd>%s</dd>', __( 'The title of the quiz that has been graded.', 'wp_courseware' ) );
	printf( '<dt>{QUIZ_GRADE}</dt><dd>%s</dd>', __( 'The overall percentage grade for a quiz.', 'wp_courseware' ) );
	printf( '<dt>{QUIZ_GRADES_BY_TAG}</dt><dd>%s</dd>', __( 'Includes a breakdown of scores by tag if available.', 'wp_courseware' ) );
	printf( '<dt>{QUIZ_TIME}</dt><dd>%s</dd>', __( 'If the quiz was timed, displays the time used to complete the quiz.', 'wp_courseware' ) );
	printf( '<dt>{QUIZ_ATTEMPTS}</dt><dd>%s</dd>', __( 'Indicates the number of attempts for the quiz.', 'wp_courseware' ) );
	printf( '<dt>{CUSTOM_FEEDBACK}</dt><dd>%s</dd>', __( 'Includes any custom feedback messages that have been triggered based on the user\'s specific results in the quiz.', 'wp_courseware' ) );
	printf( '<dt>{QUIZ_RESULT_DETAIL}</dt><dd>%s</dd>', __( 'Any optional information relating to the result of the quiz, e.g. information about retaking the quiz.', 'wp_courseware' ) );
	printf( '</dl>' );

	printf( '<h4 class="wpcw_docs_side_mini_hdr">%s</h4>', __( 'Final Summary Notifications Only', 'wp_courseware' ) );
	printf( '<p>%s</p>', __( 'In the final summary notification, only the following tags are supported:', 'wp_courseware' ) );
	printf( '<dl class="wpcw_email_tags">' );
	printf( '<dt>{CUMULATIVE_GRADE}</dt><dd>%s</dd>', __( 'The overall cumulative grade that the user has scored from completing all quizzes on the course.', 'wp_courseware' ) );
	printf( '<dt>{QUIZ_SUMMARY}</dt><dd>%s</dd>', __( 'The summary of each quiz, and what the user scored on each.', 'wp_courseware' ) );

	printf( '<dt>{USER_NAME}</dt><dd>%s</dd>', __( 'The display name of the user.', 'wp_courseware' ) );
	printf( '<dt>{FIRST_NAME}</dt><dd>%s</dd>', __( 'The first name of the user.', 'wp_courseware' ) );
	printf( '<dt>{LAST_NAME}</dt><dd>%s</dd>', __( 'The last name of the user.', 'wp_courseware' ) );

	printf( '<dt>{SITE_NAME}</dt><dd>%s</dd>', __( 'The name of the website.', 'wp_courseware' ) );
	printf( '<dt>{SITE_URL}</dt><dd>%s</dd>', __( 'The URL of the website.', 'wp_courseware' ) );

	printf( '<dt>{COURSE_TITLE}</dt><dd>%s</dd>', __( 'The title of the course for the unit that\'s just been completed.', 'wp_courseware' ) );

	printf( '<dt>{CERTIFICATE_LINK}</dt><dd>%s</dd>', __( 'If the course has PDF certificates enabled, this is the link of the PDF certficate. (If there is no certificate or certificates are not enabled, this is simply blank)', 'wp_courseware' ) );
	printf( '</dl>' );

	$page->showPageFooter();
}

/**
 * Handles showing the delete course button on the course modification page.
 */
function WPCW_showPage_ModifyCourse_deleteCourseButton( $courseDetails ) {
	$html = false;

	// Generate the URL that will handle the deletion for this course. Using the ID in the GET URL just in case the deletion fails.
	$html .= sprintf( '<form method="POST" action="%s&action=delete_course&course_id=%d" id="wpcw_course_settings_delete_course">', admin_url( 'admin.php?page=WPCW_wp_courseware' ), $courseDetails->course_id );

	// Radio option selection
	$html .= '<div class="wpcw_form_delete_options">';
	$html .= sprintf( '<label><input type="radio" name="delete_course_type" value="course_and_module"/> %s <div class="wpcw_form_delete_options_desc">%s</div></label>', __( 'Course and module settings only', 'wp_courseware' ), __( 'Units and quizzes will not be deleted, but simply disassociated from the course.', 'wp_courseware' ) );

	$html .= sprintf( '<label><input type="radio" name="delete_course_type" value="complete" checked/> %s <div class="wpcw_form_delete_options_desc">%s</div></label>', __( 'Delete everything', 'wp_courseware' ), __( 'This option will delete the course, the modules, all units and all quizzes.', 'wp_courseware' ) );
	$html .= '</div>';

	// Submit
	$html .= sprintf( '<input type="submit" value="%s" class="button-primary wpcw_delete_item" title="%s" />', __( 'Delete this Course', 'wp_courseware' ), __( "Are you sure you want to delete the this course?\n\nThis CANNOT be undone!", 'wp_courseware' ) );
	$html .= '</form>';

	echo $html;
}

/**
 * Run the changes for the course access change.
 *
 * @param Object $page        The current page object for messages.
 * @param String $action      The action that's been requested.
 * @param Object $userDetails The details of this course.
 */
function WPCW_showPage_ModifyCourse_courseAccess_runAccessChanges( $page, $action, $courseDetails ) {
	$args = array(// No defaults actually, get_users() gets all users by default.
	);

	switch ( $action ) {
		case 'grant_access_users_all':
			$userType = false;
			break;

		case 'grant_access_users_all_subscribers':
			$args[ 'role' ] = 'subscriber';
			$userType       = __( 'subscriber', 'wp_courseware' );
			break;

		case 'grant_access_users_admins':
			$args[ 'role' ] = 'administrator';
			$userType       = __( 'admin', 'wp_courseware' );
			break;

		default:
			$page->showMessage( __( 'Unknown access change was requested.', 'wp_courseware' ), true );
			return;
			break;
	}

	// Kick of message to show we've started.
	WPCW_messages_showProgress( sprintf( __( 'Requesting a list of <b>all %s users</b> to update... (this make take a while)...', 'wp_courseware' ), $userType ), 0 );
	$userList = get_users( $args );

	// Report how many users we have to process.
	if ( ! empty( $userList ) ) {
		$userCount = count( $userList );
		WPCW_messages_showProgress( sprintf( __( 'Found %d user(s), so now starting to add them to this course...', 'wp_courseware' ), $userCount ), 1 );

		global $wpdb, $wpcwdb;
		$count = 0;

		// Each user has 2 DB accesses to update, so this may take a while.
		foreach ( $userList as $userDetails ) {

			WPCW_messages_showProgress( sprintf( __( 'Processing <b>%s</b>... ', 'wp_courseware' ), $userDetails->data->user_login, $userDetails->data->display_name ), 2 );

			// See if the user already exists for this course.
			$entryExists = $wpdb->get_row( $wpdb->prepare( "
				SELECT *
				 FROM $wpcwdb->user_courses
				WHERE user_id = %d
				  AND course_id = %d
				 ", $userDetails->ID, $courseDetails->course_id ) );

			// They already exist, nothing to do.
			if ( $entryExists ) {
				WPCW_messages_showProgress( __( 'User can already access this course. Skipping.', 'wp_courseware' ), 3 );
			} // Adding the user
			else {
				$wpdb->query( $wpdb->prepare( "
				INSERT INTO $wpcwdb->user_courses
				(user_id, course_id, course_progress, course_final_grade_sent)
				VALUES(%d, %d, 0, '')
				 ", $userDetails->ID, $courseDetails->course_id ) );

				WPCW_messages_showProgress( __( 'Added.', 'wp_courseware' ), 3 );
			}

			$count ++;
			WPCW_messages_showProgress( sprintf( __( 'Done. %.1f%% complete.', 'wp_courseware' ), ( $count / $userCount ) * 100 ), 3 );
		}
	} else {
		WPCW_messages_showProgress( __( 'No users found. Nothing to do.', 'wp_courseware' ), 1 );
	}

	WPCW_messages_showProgress( '<b>' . __( 'All done.', 'wp_courseware' ) . '</b>', 0 );
}

/**
 * Show user progress.
 *
 * @param String  $message     The message to show.
 * @param Integer $indentLevel A number representing how many indent levels to add.
 */
function WPCW_messages_showProgress( $message, $indentLevel ) {
	printf( '<div class="wpcw_msg_progress wpcw_msg_progress_indent_%d">%s</div>', $indentLevel, $message );
	flush();
}

/**
 * Get a list of pages, with heirarchy, set as ID => Page Title in an array.
 *
 * @return Array The page list as an array.
 */
function WPCW_pages_getPageList() {
	$args = array(
		'echo' => 0
	);

	// Find all values and options, and return as an array of IDs to Page Title with indents.
	if ( preg_match_all( '/<option(.+?)value="(.+?)">(.+?)<\/option>/i', wp_dropdown_pages( $args ), $matches ) ) {
		$blank = array( '' => __( '---- No Page Selected ----', 'wp_courseware' ) );

		// Fix for page selection to key page keys.
		return $blank + array_combine( $matches[ 2 ], $matches[ 3 ] );
	}

	return false;
}

/**
 * Gets a list of all blocking courses for the specified course ID.
 *
 * @param Integer $courseID The ID of the course to search.
 *
 * @return Array A list of blocking quizzes for the specified course ID (or false if there are none).
 */
function WPCW_quizzes_getAllBlockingQuizzesForCourse( $courseID ) {
	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	$SQL = $wpdb->prepare( "
    	SELECT *
    	FROM $wpcwdb->quiz
    	WHERE parent_course_id = %d
    	  AND quiz_type = 'quiz_block'
   	", $courseID );

	return $wpdb->get_results( $SQL );
}

?>