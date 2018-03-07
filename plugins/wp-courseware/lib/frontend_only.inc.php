<?php
/**
 * Only show code on the frontend of the website.
 */

/**
 * Check to see if there is a quiz completed sitting on top of an uncompleted unit
 **/
function WPCW_quiz_complete_unit_incomplete_fix()
{

		global $post;
		$user_id = get_current_user_id();
		$parentData = WPCW_units_getAssociatedParentData($post->ID);
		$userProgress = new UserProgress($parentData->course_id,$user_id);
		$unitQuizDetails = WPCW_quizzes_getAssociatedQuizForUnit($post->ID, false, $user_id);

		if ($unitQuizDetails && !$userProgress->isUnitCompleted($post->ID))
		{
			$unitQuizProgress = WPCW_quizzes_getUserResultsForQuiz($user_id, $post->ID, $unitQuizDetails->quiz_id);

			if (!$unitQuizProgress)
			{
				return;
			}

			if ( 'quiz_block' == $unitQuizDetails->quiz_type && ($unitQuizProgress->quiz_grade >= $unitQuizDetails->quiz_pass_mark))
			{
				WPCW_units_saveUserProgress_Complete($user_id, $post->ID);
			}

			if ( 'quiz_noblock' == $unitQuizDetails->quiz_type && $unitQuizProgress->quiz_paging_status == 'complete')
			{
				WPCW_units_saveUserProgress_Complete($user_id, $post->ID);
			}

			if ( 'survey' ==  $unitQuizDetails->quiz_type && $unitQuizProgress->quiz_paging_status == 'complete')
			{
				WPCW_units_saveUserProgress_Complete($user_id, $post->ID);
			}
		}
		return;
}

/**
 * Handle showing the box that allows a user to mark a unit as completed.
 */
function WPCW_units_processUnitContent($content)
{
	// #### Ensure we're only showing a course unit, a single item
	if (!is_single() || 'course_unit' !=  get_post_type()) {
		return $content;
	}

	// Run completed quiz/incomplete unit check
	WPCW_quiz_complete_unit_incomplete_fix();

	// Use object to handle the rendering of the unit on the frontend.
	include_once WPCW_plugin_getPluginDirPath() . 'classes/class_frontend_unit.inc.php';

	global $post;
	$fe = new WPCW_UnitFrontend($post);


	// #### Get associated data for this unit. No course/module data, then it's not a unit
	if (!$fe->check_unit_doesUnitHaveParentData()) {
		return $content;
	}

	// Don't show any course content if password protected.
	if ( post_password_required() ) {
    return $content;
	}

	// #### Ensure we're logged in
	if (!$fe->check_user_isUserLoggedIn()) {
		return $fe->message_user_notLoggedIn();
	}

	// #### User not allowed access to content, so certainly can't say they've done this unit.
	if (!$fe->check_user_canUserAccessCourse()) {
		return $fe->message_user_cannotAccessCourse();
	}

	// #### Is user allowed to access this unit yet?
	if (!$fe->check_user_canUserAccessUnit())
	{
		// DJH 2015-08-18 - Added capability for a previous button if we've stumbled
		// on a unit that we're not able to complete just yet.
		$navigationBox = $fe->render_navigation_getNavigationBox();

		// Show the navigation box AFTErR the cannot progress message.
		return $fe->message_user_cannotAccessUnit() . $navigationBox;
	}

	// #### Has user completed course prerequisites
	if (!$fe->check_user_hasCompletedCoursePrerequisites())
	{
		// on a unit that we're not able to complete just yet.
		$navigationBox = $fe->render_navigation_getNavigationBox();

		// Show navigation box after the cannot process message.
		return $fe->message_user_hasNotCompletedCoursePrerequisites() . $navigationBox;
	}

	// ### Do the remaining rendering...
	return $fe->render_detailsForUnit($content);
}



/**
 * If the settings permit, generate the powered by link for WP Courseware.
 * @param Array $settings The list of settings from the database.
 * @return String The HTML for rendering the powered by link.
 */
function WPCW_generatedPoweredByLink($settings)
{
	// Show the credit link by default.
	if (isset($settings['show_powered_by']) && $settings['show_powered_by'] == 'hide_link') {
		return false;
	}

	$url = 'https://flyplugins.com/?ref=1/';
	$nofollow = false;

	// Have we got a clickbank ID? If so, create an affiliate link
	if (isset($settings['affiliate_id']) && $settings['affiliate_id'])
	{
		$url = str_replace('XXX', $settings['affiliate_id'], 'https://flyplugins.com/?ref=XXX');
		$nofollow = 'rel="nofollow"';
	}

	return sprintf('<div class="wpcw_powered_by">%s <a href="%s" %s target="_blank">%s</a></div>',
		__('Powered By', 'wp_courseware'),
		$url, $nofollow,
		__('WP Courseware', 'wp_courseware')
	);
}


/**
 * Get the time difference using days, hours and minutes.
 * @param Integer $futureTime The timestamp of a time in the future.
 * @return String The human time in days, hours and minutes.
 */
function WPCW_date_getHumanTimeDiff($futureTime)
{
	$humanTime = false;

	// Work out seconds between now and future time.
	$secondsLeft = $futureTime - current_time('timestamp');

	$days = floor($secondsLeft / 86400);
	$secondsLeft = $secondsLeft - ($days * 86400);

	$hours = floor($secondsLeft / 3600);
	$secondsLeft = $secondsLeft - ($hours * 3600);

	$minutes = floor($secondsLeft / 60);
	$secondsLeft = $secondsLeft - ($minutes * 60);

	if ($minutes > 0)
	{
		// Now create a time based on what we've got.
		$humanTime = sprintf(_n('%d minute', '%d minutes', $minutes, 'wp_courseware'), $minutes);

		if ($days > 0 || $hours > 0)
		{
			// Must add hours, because we've got days
			$humanTime = sprintf(_n('%d hour', '%d hours', $hours, 'wp_courseware'), $hours)
				. ' ' . _x('and', 'Used in context of 4 days 7 hours and 14 minutes', 'wp_courseware') . ' ' . $humanTime;

			// Now add days
			if ($days > 0) {
				$humanTime = sprintf(_n('%d day', '%d days', $days, 'wp_courseware'), $days)  . ' ' . $humanTime;
			}
		}
	}

	// Less than 1 minute remaining...
	else
	{
		$humanTime = sprintf(_n('%d second', '%d seconds', $secondsLeft, 'wp_courseware'), $secondsLeft);
	}

	return $humanTime;
}

?>