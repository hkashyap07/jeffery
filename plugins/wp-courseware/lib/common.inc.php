<?php


/**
 * Update the quiz results in the database. Assume that the data exists when doing this update.
 * @param Object $quizResultsSoFar The updated list of results data.
 */
function WPCW_quizzes_updateQuizResults($quizResultsSoFar)
{
	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$markinglistCount = 0;
	$markinglist = false;

	// Relabel variables for clarity.
	$needsMarkingList = $quizResultsSoFar->quiz_needs_marking_list;
	$newQuizData 	  = $quizResultsSoFar->quiz_data;

	// Got items that need marking.
	if (!empty($needsMarkingList))
	{
		$markinglist = serialize($needsMarkingList);
		$markinglistCount = count($needsMarkingList);
	}

	$dataToUpdate = array(
		'user_id' 					=> $quizResultsSoFar->user_id,
		'unit_id' 					=> $quizResultsSoFar->unit_id,
		'quiz_id' 					=> $quizResultsSoFar->quiz_id,
		'quiz_needs_marking_list' 	=> $markinglist,
		'quiz_needs_marking' 		=> $markinglistCount,
		'quiz_data' 				=> serialize($newQuizData),
		'quiz_grade'				=> -1,
		'quiz_attempt_id'			=> $quizResultsSoFar->quiz_attempt_id
	);

	// Update with the quiz grade
	$dataToUpdate['quiz_grade'] = WPCW_quizzes_calculateGradeForQuiz($newQuizData, $markinglistCount);

	$SQL = arrayToSQLUpdate($wpcwdb->user_progress_quiz, $dataToUpdate, array('user_id', 'unit_id', 'quiz_id', 'quiz_attempt_id'));
	$wpdb->query($SQL);
}


/**
 * Simple debug function to echo a variable to the page.
 * @param Array $showvar The variable to echo.
 * @param Boolean $return If true, then return the information rather than echo it.
 * @return String The HTML to render the array as debug output.
 */
function WPCW_debug_showArray($showvar, $return = false)
{
	$html = "<pre style=\"background: #FFF; margin: 10px; padding: 10px; border: 2px solid grey; clear: both; display: block;\">";
	$html .= print_r($showvar, true);
	$html .= "</pre>";

	if (!$return) {
		echo $html;
	}
	return $html;
}


/**
 * Simple debug function to echo a variable to the debug.log
 * @param $message Message that you would like to pass to the debug.log file.
 * @return void
 */
function WPCW_debug( $message ) {
	if ( WP_DEBUG === true ) {
		if ( is_array( $message ) || is_object( $message ) ) {
			error_log( print_r( $message, true ) );
		} else {
			error_log( $message );
		}
	}
}


/**
 * Safe method to get the value from an array using the specified key.
 * @param Array $array The array to search.
 * @param String $key The key to use to index the array.
 * @param Mixed $returnDefault Return this value if the value is not found.
 * @return String The array value.
 */
function WPCW_arrays_getValue($array, $key, $returnDefault = false)
{
	if ($array && isset($array[$key])) {
		return $array[$key];
	}

	return $returnDefault;
}


/**
 * Shuffles an array, maintaining the keys.
 * @param Array $list The array to sort.
 * @return Array The shuffled list.
 */
function WPCW_arrays_shuffle_assoc($list)
{
	if (!is_array($list)) return $list;

	$keys = array_keys($list);
	shuffle($keys);
	$random = array();
	foreach ($keys as $key) {
		$random[$key] = $list[$key];
	}
	return $random;
}


/**
 * Function to get all of the course details.
 * @param Integer $courseID The ID of the course for which we want to get details.
 * @return Object The details of the course as an object.
 */
function WPCW_courses_getCourseDetails($courseID)
{
	if (!$courseID) {
		return false;
	}

	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$SQL = $wpdb->prepare("SELECT *
			FROM $wpcwdb->courses
			WHERE course_id = %d
			", $courseID);

	return $wpdb->get_row($SQL);
}


/**
 * Function to get all of the module details.
 * @param Integer $moduleID The ID of the module for which we want to get details.
 * @return Object The details of the module as an object.
 */
function WPCW_modules_getModuleDetails($moduleID)
{
	if (!$moduleID) {
		return false;
	}

	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$SQL = $wpdb->prepare("SELECT *
			FROM $wpcwdb->modules
			WHERE module_id = %d
			", $moduleID);

	return $wpdb->get_row($SQL);
}


/**
 * Function to get all of the quiz details.
 *
 * @param Integer $quizID The ID of the quiz for which we want to get details.
 * @param Boolean $getQuestionsToo If true, get the questions for the quiz too.
 * @param Boolean $resolveRandomQuestions If true, convert any randomised questions to live questions.
 * @param Integer $userID The ID of the user to resolve the questions to.
 *
 * @return Object The details of the quiz as an object.
 */
function WPCW_quizzes_getQuizDetails($quizID, $getQuestionsToo = false, $resolveRandomQuestions = false, $userID)
{
	if (!$quizID) {
		return false;
	}

	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$SQL = $wpdb->prepare("SELECT *
			FROM $wpcwdb->quiz
			WHERE quiz_id = %d
			", $quizID);

	$quizObj = $wpdb->get_row($SQL);

	// Nothing found
	if (!$quizObj) {
		return false;
	}

	// Add flag indicating if random questions are resolved or not.
	$quizObj->resolved_random_questions = $resolveRandomQuestions;

	if ($getQuestionsToo)
	{
		// Something found, so return the questions for this quiz too.
		$quizObj->questions = WPCW_quizzes_getListOfQuestions($quizObj->quiz_id);

		// Check if we need to expand any random questions
		if ($resolveRandomQuestions && $userID > 0 && !empty($quizObj->questions))
		{
			$questionListToRender = array();

			foreach ($quizObj->questions as $question)
			{
				switch ($question->question_type)
				{
					// Got a random selection - extract these questions
					case 'random_selection':
							$quObj = new WPCW_quiz_RandomSelection($question);
							$randList = $quObj->questionSelection_getLockedQuestionSelection($userID, $quizObj->parent_unit_id);

							// Append the random questions.
							if (!empty($randList)) {
								$questionListToRender += $randList;
							}
						break;

					// Got a standard question
					case 'multi':
					case 'open':
					case 'upload':
					case 'truefalse':
							$questionListToRender[$question->question_id] = $question;
						break;

					// Not expecting anything here... so not handling the error case.
					default:
						die(__('Unexpected question type, aborting.', 'wp_courseware'));
						break;
				} // end switch
			} // end foreach

			// Overwrite existing questions
			$quizObj->questions = $questionListToRender;

		} // end if we want to expand random questions
	}

	// Simple flag to see if we have open questions or not.
	$quizObj->has_open_questions = false;

	// Are we expecting any uploads? If so, set a flag to make answer processing faster.
	$quizObj->want_uploads = false;
	if (!empty($quizObj->questions))
	{
		foreach ($quizObj->questions as $quizID => $quizItem)
		{
			// We're searching for an upload anyway, so check for an open question
			if ('upload' == $quizItem->question_type) {
				$quizObj->want_uploads = true;
				$quizObj->has_open_questions = true;
				break;
			}
		}

		// Not found an open question yet even though we checked for uploads.
		if (!$quizObj->has_open_questions)
		{
			foreach ($quizObj->questions as $quizID => $quizItem)
			{
				// Look for an open question (already checked uploads). This s
				// saves some computation time.
				if ('open' == $quizItem->question_type) {
					$quizObj->has_open_questions = true;
					break;
				}
			}
		}
	}

	return $quizObj;
}

/**
 * Get the associated quiz for a unit.
 *
 * @param Integer $unitID The ID of the unit to get the associated quiz for.
 * @param Boolean $resolveRandomQuestions If true, convert any randomised questions to live questions.
 * @param Integer $userID The ID of the user to resolve the questions to.
 *
 * @return Object The Object of the associated quiz, or false if no quiz found.
 */
function WPCW_quizzes_getAssociatedQuizForUnit($unitID, $resolveRandomQuestions = false, $userID)
{
	if (!$unitID) {
		return false;
	}

	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$SQL = $wpdb->prepare("
			SELECT quiz_id
			FROM $wpcwdb->quiz
			WHERE parent_unit_id = %d
			", $unitID);

	$quizObj = $wpdb->get_row($SQL);

	// Nothing found
	if (!$quizObj) {
		return false;
	}

	// Return full details for this quiz
	return WPCW_quizzes_getQuizDetails($quizObj->quiz_id, true, $resolveRandomQuestions, $userID);
}


/**
 * Get a list of quiz post objects that match the specified parent unit ID.
 * @param Integer $unitID The ID of the unit to get the quizzes for (0 = unassigned units).
 * @return Array A list of quiz objects in the order that they appear.
 */
function WPCW_quizzes_getListOfQuizzes($unitID)
{
	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	// Current user
	$current_user = wp_get_current_user();

	// check permissions
	$SQL_AND = false;
	if ( ! user_can( $current_user, 'manage_wpcw_settings' ) ) {
		$SQL_AND = $wpdb->prepare( ' AND quiz_author = %d', $current_user->ID );
	}

	$SQL = $wpdb->prepare("
		SELECT *
		FROM $wpcwdb->quiz
		WHERE parent_unit_id = %d
		$SQL_AND
	", $unitID);

	// No list of associated IDs? Abort, and return false, as no quizzes objects.
	$rawQuizzes = $wpdb->get_results($SQL);
	if (!$rawQuizzes) {
		return false;
	}

	// Re-order post objects so that they are ID => Object details, rather than 0 => Object, 1 => Object
	$quizObjList = array();
	foreach ($rawQuizzes as $obj)
	{
		$quizObjList[$obj->quiz_id] = $obj;
	}

	return $quizObjList;
}


/**
 * Get a list of questions that match the specified quiz ID.
 *
 * @param Integer $quizID The ID of the quiz to get the questions for.
 * @param Boolean $getTagsToo If true, then get the list of these tags too.
 *
 * @return Array A list of questions objects in the order that they appear within the quiz.
 */
function WPCW_quizzes_getListOfQuestions($quizID, $getTagsToo = true)
{
	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$SQL = $wpdb->prepare("
			SELECT *
			FROM $wpcwdb->quiz_qs_mapping qqm
				LEFT JOIN $wpcwdb->quiz_qs qq ON qq.question_id = qqm.question_id
			WHERE qqm.parent_quiz_id = %d
			ORDER BY qqm.question_order ASC
		", $quizID);

	// No list of associated IDs? Abort, and return false, as no question objects.
	$rawQuestions = $wpdb->get_results($SQL);
	if (!$rawQuestions) {
		return false;
	}

	// Re-order post objects so that they are ID => Object details, rather than 0 => Object, 1 => Object
	$questionObjList = array();
	foreach ($rawQuestions as $obj)
	{
		$obj->tags = false;

		// Also grab any tags that this question has.
		if ($getTagsToo)
		{
			$obj->tags = $wpdb->get_results($wpdb->prepare("
				SELECT qt.*
				FROM $wpcwdb->question_tag_mapping qtm
					LEFT JOIN $wpcwdb->question_tags qt ON qtm.tag_id = qt.question_tag_id
				WHERE question_id = %d
				ORDER BY question_tag_name ASC
			", $obj->question_id));
		}

		$questionObjList[$obj->question_id] = $obj;
	}

	return $questionObjList;
}


/**
 * Converts the encoded database answers into an array of answers.
 *
 * @param String $rawDatabaseAnswers The raw answers that need to be decoded.
 * @return Array A list of the decoded answers.
 */
function WPCW_quizzes_decodeAnswers($rawDatabaseAnswers)
{
	if (!$rawDatabaseAnswers) {
		return false;
	}

	// Unserialize the array to a PHP array
	$answerData = unserialize($rawDatabaseAnswers);

	// Decode each answer in the array
	if (!empty($answerData))
	{
		foreach ($answerData as $idx => $innerData)
		{
			// Decode to exactly the same space in the array.
			$answerData[$idx]['answer'] = base64_decode($answerData[$idx]['answer']);
		}

		return $answerData;
	}
	else {
		return array ();
	}
}


/**
 * Get the name for the type of quiz being shown.
 * @param String $quizType The type of the quiz.
 * @return String The actual name of the quiz type.
 */
function WPCW_quizzes_getQuizTypeName($quizType)
{
	switch ($quizType)
	{
		case 'survey':
				return __('Survey', 'wp_courseware');
			break;

		case 'quiz_block':
				return __('Quiz - Blocking', 'wp_courseware');
			break;

		case 'quiz_noblock':
				return __('Quiz - Non-Blocking', 'wp_courseware');
			break;
	}
	return false;
}


/**
 * Get all of the quiz details for the specified unit and quiz ID.
 *
 * @param Integer $userID The ID of the user.
 * @param Integer $unitID The ID of the unit.
 * @param Integer $quizID The ID of the quiz.
 *
 * @return Object The quiz results as an object.
 */
function WPCW_quizzes_getUserResultsForQuiz($userID, $unitID, $quizID)
{
	global $wpdb, $wpcwdb;
    $wpdb->show_errors();

    // Get the latest version of the quiz results, as there may be other versions to check for.
    $SQL = $wpdb->prepare("
    	SELECT *, UNIX_TIMESTAMP(quiz_completed_date) AS quiz_completed_date_ts
    	FROM $wpcwdb->user_progress_quiz
    	WHERE user_id = %d
    	  AND unit_id = %d
    	  AND quiz_id = %d
    	ORDER BY quiz_attempt_id DESC
    	LIMIT 1
   	", $userID, $unitID, $quizID);

    $quizObj = $wpdb->get_row($SQL);

    // Sort out the array of quiz data.
    if ($quizObj && $quizObj->quiz_data) {
    	$quizObj->quiz_data = maybe_unserialize($quizObj->quiz_data);
    }

    // Unserialize the quiz marking list.
	if ($quizObj && $quizObj->quiz_needs_marking_list) {
    	$quizObj->quiz_needs_marking_list = unserialize($quizObj->quiz_needs_marking_list);
    }

    // Count how many attempts there were.
    if ($quizObj)
    {
    	$quizObj->attempt_count = $wpdb->get_var($wpdb->prepare("
	    	SELECT COUNT(*) AS attempt_count
	    	FROM $wpcwdb->user_progress_quiz
	    	WHERE user_id = %d
	    	  AND unit_id = %d
	    	  AND quiz_id = %d
	   	", $userID, $unitID, $quizID));
    }

    return $quizObj;
}


/**
 * Calculates the grade for a set of results, taking into account the
 * different types of questions.
 *
 * @param Array $quizData The list of quiz results data.
 * @param Integer $questionsThatNeedMarking How many questions need marking.
 *
 * @return Integer The overall grade for the results.
 */
function WPCW_quizzes_calculateGradeForQuiz($quizData, $questionsThatNeedMarking = 0)
{
	if ($questionsThatNeedMarking > 0) {
		return '-1';
	}

	$questionTotal = 0;
	$gradeTotal = 0;
	foreach ($quizData as $questionID => $questionResults)
	{
		// It's a truefalse/multi question
		if ($questionResults['got_right'])
		{
			// Got it right, so add 100%.
			if ($questionResults['got_right'] == 'yes') {
				$gradeTotal += 100;
			}
		}

		// It's a graded question.
		else
		{
			// Making assumption that the grade number exists
			// Otherwise we'd never get this far as the question still needs marking.
			$gradeTotal += WPCW_arrays_getValue($questionResults, 'their_grade');
		}

		$questionTotal++;
	}

	// Simple calculation that averages the grade.
	$grade = 0;
	if ($questionTotal) {
		$grade = number_format($gradeTotal / $questionTotal, 1);
	}

	return $grade;
}


/**
 * Function to get all of the module details.
 * @param Integer $courseID The ID of the course for which we want to get details.
 * @param Integer $moduleNumber The module number for the module in this course
 * @return Object The details of the module as an object.
 */
function WPCW_modules_getModuleDetails_byModuleNumber($courseID, $moduleNumber)
{
	if (!$courseID || !$moduleNumber) {
		return false;
	}

	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$SQL = $wpdb->prepare("SELECT *
			FROM $wpcwdb->modules
			WHERE module_number = %d
			  AND parent_course_id = %d
			", $moduleNumber, $courseID);

	return $wpdb->get_row($SQL);
}


/**
 * Function to get a list of the courses for use in a select list.
 * @param String $addBlank If set, use this string as the blank option at the top of the list.
 * @param Int $excludeCourseId If set, it will exclude the course id from the list.
 * @return Array The list of courses as an array of (courseID => Course name).
 */
function WPCW_courses_getCourseList($addBlank = false, $excludeCourseId = false)
{
	$list = array();
	if ($addBlank) {
		$list[] = $addBlank;
	}

	global $wpcwdb, $wpdb;
	$current_user_id = get_current_user_id();
	$wpdb->show_errors();

	$SQL_WHERE = false;
	if ( ! user_can( $current_user_id, 'manage_wpcw_settings' ) ) {
		$SQL_WHERE = $wpdb->prepare( 'WHERE course_author = %d', $current_user_id );
	}

	$SQL = "SELECT *
			FROM $wpcwdb->courses
			$SQL_WHERE
			ORDER BY course_title
			";

	$items = $wpdb->get_results($SQL);
	if (count($items) < 1) {
		return $list;
	}

	foreach ($items as $item) {
		$list[$item->course_id] = $item->course_title;
	}

	if ( $excludeCourseId !== false ) {
		unset( $list[$excludeCourseId] );
	}

	return $list;
}


/**
 * Get a list of the modules for a training course, in the order required for training.
 *
 * @param Integer $courseID The ID of the course to get the modules for.
 */
function WPCW_courses_getModuleDetailsList($courseID)
{
	$list = array();

	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$SQL = $wpdb->prepare("SELECT *
			FROM $wpcwdb->modules
			WHERE parent_course_id = %d
			ORDER BY module_order, module_title ASC
			", $courseID);

	$items = $wpdb->get_results($SQL);
	if (count($items) < 1) {
		return false;
	}

	// List modules in array using module ID
	foreach ($items as $item)  {
		$list[$item->module_id] = $item;
	}

	return $list;
}


/**
 * Get a list of all quizzes for a training course, in the order that they are used.
 *
 * @param Integer $courseID The ID of the course to get the quizzes for.
 *
 * @return Array A list of the quizzes in order.
 */
function WPCW_quizzes_getAllQuizzesForCourse($courseID)
{
	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	return $wpdb->get_results($wpdb->prepare("
    	SELECT *
    	FROM $wpcwdb->quiz q
    		LEFT JOIN $wpcwdb->units_meta um ON um.unit_id = q.parent_unit_id
    	WHERE q.parent_course_id = %d
    	  AND quiz_type != 'survey'
    	ORDER BY unit_order
   	", $courseID));
}


/**
 * Get the quiz results data for the specified user and list of quizzes.
 *
 * @param Integer $userID The ID of the user to get the progress data for.
 * @param String $quizIDListForSQL The SQL that contains an SQL list of quiz IDs.
 * @return Array A list of the quiz progress for the specified user.
 */
function WPCW_quizzes_getQuizResultsForUser($userID, $quizIDListForSQL)
{
	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	$SQL = $wpdb->prepare("
		SELECT * FROM $wpcwdb->user_progress_quiz
		WHERE quiz_id IN $quizIDListForSQL
		  AND user_id = %d
		  AND quiz_is_latest = 'latest'
	", $userID);

	$quizResults = $wpdb->get_results($SQL);
	$quizData = array();

	if ($quizResults)
	{
		// Convert list into quid_id => object
		foreach ($quizResults as $aResult) {
			$quizData[$aResult->quiz_id] = $aResult;
		}
	}

	return $quizData;
}


/**
 * Function called to check if there are any ungraded quizzes left for a specific user now they've completed the unit.
 *
 * @param Integer $userID The ID of the user that's completed the unit.
 * @param Integer $courseID The ID of the course that the user appears to have completed.
 *
 * @return Boolean True if they have any ungraded quizzes, false otherwise.
 */
function WPCW_quizzes_doesUserHaveAnyUngradedQuizzes($userID, $courseID)
{
	$quizzesForCourse = WPCW_quizzes_getAllQuizzesForCourse($courseID);

	// No quizzes for this course.
	if (empty($quizzesForCourse)) {
		return false;
	}

	// Create a simple list of quiz IDs to use in SQL queries
	$quizIDList = array();
	foreach ($quizzesForCourse as $singleQuiz)  {
		$quizIDList[] = $singleQuiz->quiz_id;
	}

	// Convert list of IDs into an SQL list
	$quizIDListForSQL = '(' . implode(',', $quizIDList) . ')';

	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	// Find out how many quizzes in this course have questions
	// that need marking.
	$SQL = $wpdb->prepare("
		SELECT COUNT(*) AS needs_grading
		  FROM $wpcwdb->user_progress_quiz
		WHERE quiz_id IN $quizIDListForSQL
		  AND user_id = %d
		  AND quiz_is_latest = 'latest'
		  AND quiz_needs_marking > 0
	", $userID);

	$quizzesThatNeedMarking = $wpdb->get_var($SQL);

	return $quizzesThatNeedMarking > 0;
}


/**
 * Attempt to get the unit meta data for a single unit.
 *
 * @param Integer $unit_id The ID of the unit to get the meta details for.
 * @return Object The details of the unit if found, false otherwise.
 */
function WPCW_units_getUnitMetaData($unit_id)
{
	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	// See if there's an entry in the courseware table
	$unitMeta = $wpdb->get_row($wpdb->prepare("
		SELECT *
		FROM $wpcwdb->units_meta
		WHERE unit_id = %d
	", $unit_id));

	// Manually convert the release date into a timestamp.
	if ($unitMeta && $unitMeta->unit_drip_date) {
		$unitMeta->unit_drip_date_ts = strtotime($unitMeta->unit_drip_date);
	}

	return $unitMeta;
}


/**
 * Marks a unit as complete for the specified user. No error checking is made to check that the user
 * is allowed to update the record, it's assumed that the permission checking has been done before this step.
 *
 * @param Integer $userID The ID of the user that's completed the unit.
 * @param Integer $unitID The ID of the unit that's been completed.
 */
function WPCW_units_saveUserProgress_Complete($userID, $unitID)
{
	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	$keyColumns = array('user_id', 'unit_id');

	$data = array();
	$data['unit_completed_status'] 	= 'complete';
	$data['unit_completed_date']  	= current_time('mysql');
	$data['user_id'] 				= $userID;
	$data['unit_id'] 				= $unitID;

	$progress = doesRecordExistAlready($wpcwdb->user_progress, $keyColumns, array($userID, $unitID));
	if ($progress)
	{
		// Has it been marked as complete? If so, we don't want to do that again to preserve the date.
		// We generally shouldn't get here, but protect anyway.
		if ($progress->unit_completed_status == 'complete') {
			return false;
		}

		$SQL = arrayToSQLUpdate($wpcwdb->user_progress, $data, $keyColumns);
	}

	// Insert
	else {
		$SQL = arrayToSQLInsert($wpcwdb->user_progress, $data);
	}

	$wpdb->query($SQL);
}


/**
 * Calculates the cumulative grade for a course and user.
 * @param Integer $courseID The ID of the course.
 * @param Integer $userID The ID of the user.
 *
 * @return String The progress for the course/user, or n/a if there's nothing to report.
 */
function WPCW_courses_getCourseCumulativeGrade($courseID, $userID)
{
	// Get all the quizzes for this course
	$quizIDList 		= array();
	$quizIDListForSQL 	= false;
	$quizzesForCourse 	= WPCW_quizzes_getAllQuizzesForCourse($courseID);

	// Create a simple list of IDs to use in SQL queries
	if ($quizzesForCourse)
	{
		foreach ($quizzesForCourse as $singleQuiz)  {
			$quizIDList[$singleQuiz->quiz_id] = $singleQuiz;
		}

		// Convert list of IDs into an SQL list
		$quizIDListForSQL = '(' . implode(',', array_keys($quizIDList)) . ')';
	}

	// (!$quizzesForCourse) Break here if there are no quizzes for this course.
	else  {
		return __('n/a', 'wp_courseware');
	}


	// Get quiz results for this user
	$quizResults = WPCW_quizzes_getQuizResultsForUser($userID, $quizIDListForSQL);

	// Track cumulative data
	$quizScoresSoFar = 0;
	$quizScoresSoFar_count = 0;

	// ### Now render results for each quiz
	foreach ($quizIDList as $aQuizID => $singleQuiz)
	{
		// Got progress data, process the result
		if (isset($quizResults[$aQuizID]))
		{
			// Extract results and unserialise the data array.
			$theResults = $quizResults[$aQuizID];
			$theResults->quiz_data = maybe_unserialize($theResults->quiz_data);

			// We've got something that needs grading.
			if ($theResults->quiz_needs_marking == 0)
			{
				// Calculate score, and use for cumulative.
				$score = number_format($theResults->quiz_grade);
				$quizScoresSoFar += $score;
				$quizScoresSoFar_count++;
			}
		} // end of quiz result check.
	}// end foreach ($quizIDList as $aQuizID => $singleQuiz)

	// Calculate the cumulative grade
	return ($quizScoresSoFar_count > 0 ? number_format(($quizScoresSoFar / $quizScoresSoFar_count), 1) . '%' : __('n/a', 'wp_courseware'));
}


/**
 * Get a list of unit post objects that match the specified module ID.
 * @param Integer $moduleID The ID of the module to get the units for (0 = unassigned units).
 * @return Array A list of unit objects in the order that they appear.
 */
function WPCW_units_getListOfUnits($moduleID)
{
	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	// Current user
	$current_user = wp_get_current_user();

	$SQL_USER_CONDITION = false;
	if ( is_admin() && ! user_can( $current_user, 'manage_wpcw_settings' ) ) {
		$SQL_USER_CONDITION = $wpdb->prepare( ' AND unit_author = %d', $current_user->ID );
	}

	$SQL = $wpdb->prepare("
		SELECT *
		FROM $wpcwdb->units_meta
		WHERE parent_module_id = %d
		$SQL_USER_CONDITION
		ORDER BY unit_order ASC, unit_id ASC
	", $moduleID);

	// No list of associated IDs? Abort, and return false, as no units or unit objects.
	$rawUnits = $wpdb->get_results($SQL);
	if (!$rawUnits) {
		return false;
	}

	// Turn list into ID => meta list
	$unitIDList = array();
	foreach ($rawUnits as $rawUnit)
	{
		$unitIDList[$rawUnit->unit_id] = $rawUnit;
	}

	// Get list of IDs, and use this for WordPress query to get the full objects
	$uniPostObjsArgs = array(
		'post_type' => 'course_unit', 				// Just course units
		'number'	=> -1,							// No limit, i.e. all
		'orderby'	=> 'none',						//
		'include'	=> array_keys($unitIDList)		// List of posts to get.
	);

	// Check permissions
	if ( is_admin() && ! user_can( $current_user, 'manage_wpcw_settings' ) ) {
		$uniPostObjsArgs[ 'post_author' ] = $current_user->ID;
	}

	// Get Units
	$unitPostObjsRaw = get_posts($uniPostObjsArgs);

	if (!$unitPostObjsRaw) {
		return false;
	}

	// Re-order post objects so that they are ID => Object details, rather than 0 => Object, 1 => Object
	$unitPostObjs = array();
	foreach ($unitPostObjsRaw as $obj)
	{
		// Add our metadata
		$obj->unit_meta = $unitIDList[$obj->ID];

		$unitPostObjs[$obj->ID] = $obj;
	}

	// Use unit ordering from table to return actual ordering list.
	$unitDataRet = array();
	foreach ($unitIDList as $unitID => $unitObj)
	{
		if (isset($unitPostObjs[$unitID])) {
			$unitDataRet[$unitID] = $unitPostObjs[$unitID];
		}
	}

	return $unitDataRet;
}


/**
 * Get all of the associated parent data for the specified course unit.
 * @param Integer $post_id The ID of the course unit
 * @return Object The details of the parent objects, or false if there is no parent.
 */
function WPCW_units_getAssociatedParentData($post_id)
{
	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$SQL = $wpdb->prepare("
		SELECT *
		FROM $wpcwdb->units_meta um
		LEFT JOIN $wpcwdb->modules m ON m.module_id = um.parent_module_id
		LEFT JOIN $wpcwdb->courses c ON c.course_id = m.parent_course_id
		WHERE um.unit_id = %d AND course_title IS NOT NULL
	", $post_id);

	return $wpdb->get_row($SQL);
}


/**
 * Convert a percentage to a percentage bar
 * @param String $percent The number to show in the progress bar.
 * @param String $title The optional title of the course.
 *
 * @return String The HTML to render the percentage bar.
 */
function WPCW_stats_convertPercentageToBar($percent, $title = false)
{
	if ($title) {
		$title = sprintf('<span class="wpcw_progress_bar_title">%s</span>', $title);
	}

	return WPCW_content_progressBar($percent, false, $title);
}


/**
 * Check if a user can access the specified training course.
 *
 * @param Integer $courseID The ID of the course to check.
 * @param Integer $userID The ID of the user to check.
 * @return Boolean True if the user can access this course, false otherwise.
 */
function WPCW_courses_canUserAccessCourse($courseID, $userID)
{
	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$SQL = $wpdb->prepare("
		SELECT *
		FROM $wpcwdb->user_courses
		WHERE user_id = %d AND course_id = %d
	", $userID, $courseID);

	//return ($wpdb->get_row($SQL) != false);
	return apply_filters('wpcw_courses_canuseraccesscourse', ($wpdb->get_row($SQL) != false), $courseID, $userID);
}


/**
 * Get the enrollment date for a user from the courses database.
 *
 * @param Integer $userID The ID of the user to get the details for.
 * @param Integer $courseID The ID of the course to get the enrollment date for.
 *
 * @return Integer The course enrolement date as a timestamp, or false if the user wasn't found.
 */
function WPCW_users_getCourseEnrolmentDate($userID, $courseID)
{
	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$thedate = $wpdb->get_var($wpdb->prepare("
		SELECT course_enrolment_date
		FROM $wpcwdb->user_courses
		WHERE user_id = %d AND course_id = %d
	", $userID, $courseID));

	// Convert into a timestamp with no timezone conversion.
	if ($thedate) {
		return strtotime($thedate);
	}

	return false;
}

/**
 * Get the course prerequisites.
 *
 * @param Integer $courseID The ID of the course to get the enrollment date for.
 *
 * @return Integer The course enrolement date as a timestamp, or false if the user wasn't found.
 */
function WPCW_users_getCoursePrerequisites($courseID = 0)
{
	if ( $courseID == 0 ) {
		return;
	}

	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	$prerequisites = $wpdb->get_var($wpdb->prepare("
		SELECT course_opt_prerequisites
		FROM $wpcwdb->courses
		WHERE course_id = %d
	", $courseID));

	if ($prerequisites) {
		return maybe_unserialize($prerequisites);
	}

	return false;
}


/**
 * Get the access date for a unit based on the enrollment date and the details of the unit meta.
 *
 * @param Integer $userID The ID of the user to get the details for.
 * @param Object $unitMeta An object with the meta data for this unit.
 *
 * @return Integer The date that this unit should unlock if it's drip-fed.
 */
function WPCW_users_getUnitUnlockDate_forUser($userID, $unitMeta)
{
	// Need unit meta for this to work.
	if (!$unitMeta) {
		return false;
	}

	// Calculate the future date based on the type.
	switch ($unitMeta->unit_drip_type)
	{
		case 'drip_specific':
				return $unitMeta->unit_drip_date_ts;
			break;

		case 'drip_interval':
				// Fetch the enrolement date for this user
				$enrolmentDate = WPCW_users_getCourseEnrolmentDate($userID, $unitMeta->parent_course_id);

				// Add the interval type, which is stored in seconds.
				return $unitMeta->unit_drip_interval + $enrolmentDate;
			break;

		// If unknown, just return the date the user registered.
		default:
			return $enrolmentDate;
			break;
	}

	return false;
}


/**
 * Update the user progress count based on units completed.
 * @param Integer $courseID ID of course.
 * @param Integer $userID ID of user.
 * @param Integer $totalUnitCount The total number of units for this course.
 */
function WPCW_users_updateUserUnitProgress($courseID, $userID, $totalUnitCount)
{
	global $wpdb, $wpcwdb;
    $wpdb->show_errors();


    // Get number of completed units
    $completed = $wpdb->get_var($wpdb->prepare("
    	SELECT COUNT(*)
    	FROM $wpcwdb->user_progress up
    		LEFT JOIN $wpcwdb->units_meta um ON up.unit_id = um.unit_id
    	WHERE user_id = %d
    	  AND parent_course_id = %d
    	  AND unit_completed_status = 'complete'
    ",
    	$userID,
    	$courseID
    ));

    // Calculate progress as a percentage
    $progress = 0;
    if ($totalUnitCount > 0) {
    	$progress = floor(($completed / $totalUnitCount) *100);
    }

    // Update database with the completed progress
    $wpdb->query($wpdb->prepare("
    	UPDATE $wpcwdb->user_courses
    	   SET course_progress = %d
    	WHERE user_id = %d
    	  AND course_id = %d
    ", $progress, $userID, $courseID));
}


/**
 * Function called when courses are updated. This will update the metrics associated with the course
 * such as the total number of units.
 *
 * @param Array $courseDetails The course details that have just been updated.
 */
function WPCW_actions_courses_courseDetailsUpdated($courseDetails)
{
	if (!$courseDetails) {
		return;
	}

	global $wpdb, $wpcwdb;
    $wpdb->show_errors();

    // Get a total count of units in this course
    $SQL = $wpdb->prepare("
    	SELECT COUNT(*)
    	FROM $wpcwdb->units_meta
    	WHERE parent_course_id = %d
    ", $courseDetails->course_id);

    $totalUnitCount = $wpdb->get_var($SQL);

    // Update database with actual count
    $wpdb->query($wpdb->prepare("
    	UPDATE $wpcwdb->courses
    	   SET course_unit_count = %d
    	 WHERE course_id = %d
    ", $totalUnitCount, $courseDetails->course_id));


	// User progress counts will now be out of sync too, particularly with new or deleted units.
	$SQL = $wpdb->prepare("
		SELECT *
		FROM $wpcwdb->user_courses
		WHERE course_id = %d
	", $courseDetails->course_id);

	$users = $wpdb->get_results($SQL);
	if ($users)
	{
		foreach ($users as $userCourseDetails) {
			WPCW_users_updateUserUnitProgress($userCourseDetails->course_id, $userCourseDetails->user_id, $totalUnitCount);
		}
	}
}


/**
 * Function called when a module in the specified course has been created or editied. Used to
 * ensure that all modules have a valid module number.
 *
 * @param Integer $courseID The ID of the course that looks after the module that's been created or edited.
 */
function WPCW_actions_modules_modulesModified($courseID)
{
	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	$modules = $wpdb->get_results($wpdb->prepare("
		SELECT *
		FROM $wpcwdb->modules
		WHERE parent_course_id = %d
		ORDER BY module_order ASC
	", $courseID));

	// Nothing to do
	if (!$modules) {
		return;
	}

	$moduleOrderCount = 0;
	foreach ($modules as $module)
	{
		$moduleOrderCount++;

		// Update module list with new ordering
		$SQL = $wpdb->prepare("
			UPDATE $wpcwdb->modules
			   SET module_order = %d, module_number = %d
			WHERE module_id = %d
		", $moduleOrderCount, $moduleOrderCount, $module->module_id);

		$wpdb->query($SQL);
	}
}


/**
 * Function called after a module has been created with the modify module form.
 *
 * @param Array $formValues The processed form values.
 * @param Array $originalFormValues The raw form values.
 * @param Object $thisObject The reference to the form object doing the saving.
 */
function WPCW_actions_modules_afterModuleSaved_formHook($formValues, $originalFormValues, $thisObject)
{
	// Modules have been modified. Call action to update module numbers.
	do_action('wpcw_modules_modified', $formValues['parent_course_id']);
}


/**
 * Function called when the user completes a unit
 * @param Integer $userID The ID of the user that's completed the unit.
 * @param Integer $unitID The ID of the unit that's been completed.
 * @param Object $unitParentData The object of parent data associated with the unit, such as module and course.
 */
function WPCW_actions_users_unitCompleted($userID, $unitID, $unitParentData)
{
	if (!$userID || !$unitID || !$unitParentData) {
		return;
	}

	global $wpdb, $wpcwdb;
    $wpdb->show_errors();

    // Update the user progress count.
	WPCW_users_updateUserUnitProgress($unitParentData->course_id, $userID, $unitParentData->course_unit_count);

    // Work out if module/course completed.
    $userProgress = new UserProgress($unitParentData->course_id, $userID);

    // Cache result of course completion
    $hasCourseCompleted = $userProgress->isCourseCompleted();

    // Module is complete, but course isn't yet complete
    if ($userProgress->isModuleCompleted($unitID) && !$hasCourseCompleted) {
    	do_action('wpcw_user_completed_module', $userID, $unitID, $unitParentData);
    }

    // Course has also completed
	if ($hasCourseCompleted) {
    	do_action('wpcw_user_completed_course', $userID, $unitID, $unitParentData);
    }

    //error_log('');
    //error_log('Module Completed?: ' . $unitID . ' - ' . ($userProgress->isModuleCompleted($unitID) ? 'yes' : 'no'));
    //error_log('Course Completed?: ' . $unitID . ' - ' . ($userProgress->isCourseCompleted() ? 'yes' : 'no'));
}


/**
 * Function called when the user completes a module.
 * @param Integer $userID The ID of the user that's completed the unit.
 * @param Integer $unitID The ID of the unit that's just been completed.
 * @param Object $unitParentData The object of parent data associated with the unit, such as module and course.
 */
function WPCW_actions_users_moduleCompleted($userID, $unitID, $unitParentData)
{
	if (!$userID || !$unitID || !$unitParentData) {
		return;
	}

	$userDetails = get_userdata($userID);

	// Admin wants an email notification, and email address exists. Assumption is that it's valid.
	if ($unitParentData->email_complete_module_option_admin == 'send_email' && $unitParentData->course_to_email)
	{
		$adminSubject = __("Module Complete Notification - {USER_NAME} - Module {MODULE_NUMBER}", 'wp_courseware');
		$adminBody    = __("Hi Trainer!

Just to let you know, {USER_NAME} has just completed 'Module {MODULE_NUMBER} - {MODULE_TITLE}'.

{SITE_NAME}
{SITE_URL}
", 'wp_courseware');

		// Do email sending now
		WPCW_email_sendEmail($unitParentData,
							$userDetails, 									// User who's done the completion
							$unitParentData->course_to_email,
							$adminSubject, $adminBody);
	}

	// Check if admin wants user to have an email.
	if ($unitParentData->email_complete_module_option == 'send_email')
	{
		WPCW_email_sendEmail($unitParentData,
							$userDetails,									// User who's done the completion
							$userDetails->user_email,
							$unitParentData->email_complete_module_subject, // Use subject template in the settings
							$unitParentData->email_complete_module_body		// Use body template in the settings
						);
	}

	// Any additional admin-level notifications?
	do_action("wpcw_user_completed_module_notification", $unitParentData, $userDetails, $adminSubject, $adminBody);
}


/**
 * Function called when the user completes a module.
 * @param Integer $userID The ID of the user that's completed the unit.
 * @param Integer $unitID The ID of the unit that's just been completed.
 * @param Object $unitParentData The object of parent data associated with the unit, such as module and course.
 */
function WPCW_actions_users_courseCompleted($userID, $unitID, $unitParentData)
{
	if (!$userID || !$unitID || !$unitParentData) {
		return;
	}

	// Certificates have been requested, so generate one for this user and course.
	if ($unitParentData->course_opt_use_certificate == 'use_certs')
	{
		// Add a certificate entry to the database for the user.
		WPCW_certificate_generateCertificateEntry($userID, $unitParentData->course_id);
	}

	$userHasUngradedQuestions = WPCW_quizzes_doesUserHaveAnyUngradedQuizzes($userID, $unitParentData->course_id);

	$userDetails = get_userdata($userID);

	$adminSubject = __("Course Complete Notification - {USER_NAME} - '{COURSE_TITLE}'", 'wp_courseware');
	$adminBody    = __("Hi Trainer!

Just to let you know, {USER_NAME} has just completed the '{COURSE_TITLE}' course.

{SITE_NAME}
{SITE_URL}
", 'wp_courseware');

	// Admin wants an email notification, and email address exists. Assumption is that it's valid.
	if ($unitParentData->email_complete_course_option_admin == 'send_email' && $unitParentData->course_to_email  && !$userHasUngradedQuestions)
	{
		WPCW_email_sendEmail($unitParentData,
							$userDetails, 									// User who's done the completion
							$unitParentData->course_to_email,
							$adminSubject, $adminBody);
	}

	// Check if admin wants user to have an email.
	if ($unitParentData->email_complete_course_option == 'send_email' && !$userHasUngradedQuestions)
	{
		do_action('wpcw_user_completed_module', $userID, $unitID, $unitParentData);
		WPCW_email_sendEmail($unitParentData,
							$userDetails,									// User who's done the completion
							$userDetails->user_email,
							$unitParentData->email_complete_course_subject, // Use subject template in the settings
							$unitParentData->email_complete_course_body		// Use body template in the settings
						);
	}

	// Any additional admin-level notifications?
	do_action("wpcw_user_completed_course_notification", $unitParentData, $userDetails, $adminSubject, $adminBody);
}


/**
 * The function called when a user receives a grade for a quiz, either when marked manually, or
 * when the question it automatically graded.
 *
 * Triggered by wpcw_quiz_graded.
 *
 * @param Integer $userID The ID of the user to notify.
 * @param Object $quizDetails The details of the quiz.
 * @param Integer $grade The grade that they've been given
 * @param String $additionalDetail Any additional data to add to the message.
 */
function WPCW_actions_userQuizGraded_notifyUser($userID, $quizDetails, $grade, $additionalDetail)
{
	if (!$userID || !$quizDetails) {
		return;
	}

	// Need parent details to determine if we can send an email or not.
	$unitParentData	= WPCW_units_getAssociatedParentData($quizDetails->parent_unit_id);

	// Check if admin wants user to have an email.
	if ($unitParentData->email_quiz_grade_option == 'send_email')
	{
		// User details - for sending the email.
		$userDetails = get_userdata($userID);

		// Need post object to create the notification.
		$post = get_post($quizDetails->parent_unit_id);
		if (!$post) {
			return;
		}

		// Set current user before calling WPCW_UnitFrontend class
		wp_set_current_user($userID);

		// Initalise the unit details. Check we have access.
		$fe = new WPCW_UnitFrontend($post);
		if (!$fe->check_unit_doesUnitHaveParentData() || !$fe->check_user_canUserAccessCourse()) {
			return;
		}

		// Do email body first
		$emailBody    = $unitParentData->email_quiz_grade_body;
		$tagList_Body = WPCW_email_getTagList($emailBody);
		$emailBody    = WPCW_email_replaceTags_quizData($fe, $tagList_Body, $emailBody, $additionalDetail);


		// Then do subject line
		$emailSubject    	= $unitParentData->email_quiz_grade_subject;
		$tagList_Subject 	= WPCW_email_getTagList($emailSubject);
		$emailSubject    	= WPCW_email_replaceTags_quizData($fe, $tagList_Subject, $emailSubject, $additionalDetail);


		// Now send email
		WPCW_email_sendEmail($unitParentData,
							$userDetails,									// User who's done the completion
							$userDetails->user_email,
							$emailSubject,
							$emailBody
						);
	}
}


/**
 * Function called when a user quiz needs grading by the admin.
 *
 * @param Integer $userID The ID of the user who's quiz needs grading
 * @param Object $quizDetails The details of the quiz that needs grading.
 */
function WPCW_actions_userQuizNeedsGrading_notifyAdmin($userID, $quizDetails)
{
	if (!$userID || !$quizDetails) {
		return;
	}

	$adminSubject = __("Quiz Needs Grading Notification - {USER_NAME} - '{COURSE_TITLE}'", 'wp_courseware');
	$adminBody    = __("Hi Trainer!

Just to let you know, {USER_NAME} has just completed a quiz ({QUIZ_TITLE}), which requires grading. You can grade the question here:
{QUIZ_GRADE_URL}

{SITE_NAME}
{SITE_URL}
", 'wp_courseware');


	// Generate the quiz name and URL to mark the quiz before passing for email to be sent.
	$quizGradeURL = sprintf('%s&user_id=%d&quiz_id=%d&unit_id=%d',
		admin_url('users.php?page=WPCW_showPage_UserProgess_quizAnswers'),
		$userID, $quizDetails->quiz_id, $quizDetails->parent_unit_id
	);

	$adminBody = str_ireplace('{QUIZ_TITLE}', $quizDetails->quiz_title, $adminBody);
	$adminBody = str_ireplace('{QUIZ_GRADE_URL}', $quizGradeURL, $adminBody);

	// User and Unit details
	$userDetails = get_userdata($userID);
	$unitParentData	= WPCW_units_getAssociatedParentData($quizDetails->parent_unit_id);

	// Check admin email address exists before sending.
	if ($unitParentData->course_to_email)
	{
		WPCW_email_sendEmail($unitParentData,
							$userDetails, 									// User who's done the completion
							$unitParentData->course_to_email,
							$adminSubject, $adminBody);
	}

	// Any additional admin-level notifications?
	do_action("wpcw_user_quiz_needs_marking_notification", $unitParentData, $userDetails, $adminSubject, $adminBody);
}


/**
 * Function called when a user is blocked on a quiz due to running out of attempts.
 *
 * @param Integer $userID The ID of the user who has run out of attempts.
 * @param Object $quizDetails The details of the quiz that needs grading.
 */
function WPCW_actions_userQuizUserNeedsUnblocking_notifyAdmin($userID, $quizDetails)
{
	if (!$userID || !$quizDetails) {
		return;
	}

	$adminSubject = __("User Needs Unblocking Notification - {USER_NAME} - '{COURSE_TITLE}'", 'wp_courseware');
	$adminBody    = __("Hi Trainer!

Just to let you know, {USER_NAME} has just completed a quiz ({QUIZ_TITLE}), but they have failed and run out of attempts. You can unblock their progress here:
{QUIZ_GRADE_URL}

{SITE_NAME}
{SITE_URL}
", 'wp_courseware');


	// Generate the quiz name and URL to mark the quiz before passing for email to be sent.
	$quizGradeURL = sprintf('%s&user_id=%d&quiz_id=%d&unit_id=%d',
		admin_url('users.php?page=WPCW_showPage_UserProgess_quizAnswers'),
		$userID, $quizDetails->quiz_id, $quizDetails->parent_unit_id
	);

	$adminBody = str_ireplace('{QUIZ_TITLE}', $quizDetails->quiz_title, $adminBody);
	$adminBody = str_ireplace('{QUIZ_GRADE_URL}', $quizGradeURL, $adminBody);

	// User and Unit details
	$userDetails = get_userdata($userID);
	$unitParentData	= WPCW_units_getAssociatedParentData($quizDetails->parent_unit_id);

	// Check admin email address exists before sending.
	if ($unitParentData->course_to_email)
	{
		WPCW_email_sendEmail($unitParentData,
							$userDetails, 									// User who's done the completion
							$unitParentData->course_to_email,
							$adminSubject, $adminBody);
	}

	// Any additional admin-level notifications?
	do_action("wpcw_user_quiz_user_needs_blocking_notification", $unitParentData, $userDetails, $adminSubject, $adminBody);
}


/**
 * Send an email out using a template.
 *
 * @param Object $unitParentData The parent data for a unit.
 * @param Object $userDetails The details of the user who's done the completing.
 * @param String $targetEmail The email address of the recipient.
 * @param String $subjectTemplate The content of the subject template, before substitutions.
 * @param String $bodyTemplate The content of the email body template, before substitutions.
 */
function WPCW_email_sendEmail($unitParentData, $userDetails, $targetEmail, $subjectTemplate, $bodyTemplate)
{
	// TODO ZZZ - DEBUG Tool - Email logging
	//error_log(sprintf('Sending Email - WPCW_email_sendEmail(): %s (%s)',  $targetEmail, $subjectTemplate));

	// Replace content in email body first
	$tagList_Body = WPCW_email_getTagList($bodyTemplate);
	$bodyTemplate = WPCW_email_replaceTags_generic($unitParentData, $userDetails, $tagList_Body, $bodyTemplate);

	// Then do subject line
	$tagList_Subject = WPCW_email_getTagList($subjectTemplate);
	$subjectTemplate = WPCW_email_replaceTags_generic($unitParentData, $userDetails, $tagList_Subject, $subjectTemplate);

	//error_log(sprintf('Sending Email - WPCW_email_sendEmail(): %s (%s)',  $messageSubject, $messageBody));

	// Construct the from part of the email
	$headers = false;
	if ($unitParentData->course_from_email) {
		$headers = sprintf('From: %s <%s>' . "\r\n", $unitParentData->course_from_name, $unitParentData->course_from_email);
	}

	// Actually send the email
	if (!wp_mail($targetEmail, $subjectTemplate, $bodyTemplate, $headers)) {
		error_log('WPCW_email_sendEmail() - email did not send.');
	}
}


/**
 * Used to uppercase each item in the array.
 */
function WPCW_email_getTagList_upper(&$item, $key) {
    $item = strtoupper($item);
}


/**
 * Given a template, return a list of all of the tags being used in the body.
 * @param String $templateBody The template body to check for tags.
 * @return Array A list if template tags being used in the template.
 */
function WPCW_email_getTagList($templateBody)
{
	preg_match_all("%{[A-Za-z_]+}%", $templateBody, $matches);

	// This returns Array ( [0] => Array() ) by default.
	if (!empty($matches))
	{
		// Ensure all items are uppercase.
		array_walk($matches[0], 'WPCW_email_getTagList_upper');

		return $matches[0];
	}

	return array();
}


/**
 * Given a frontend object with user progress data, replace the tags with quiz-related information
 * based on the tags that have been found in the email.
 *
 * @param WPCW_UnitFrontend $feObj The frontend object with details.
 * @param Array $tagList The list of tags found in the template.
 * @param String $emailData The data to replace the strings with.
 * @param String $additionalData Any additional data triggered from the trainer.
 *
 * @return The modified email data ready for sending.
 */
function WPCW_email_replaceTags_quizData($feObj, $tagList, $emailData, $additionalData = false)
{
	if (!$feObj || empty($tagList)) {
		return $emailData;
	}

	$quizDetails  	= $feObj->fetch_getUnitQuizDetails();
	$progressData	= $feObj->fetch_getQuizProgressDetails();

	// Replace each tag for quiz-related data.
	foreach ($tagList as $tagToReplace)
	{
		switch ($tagToReplace)
		{
			case '{QUIZ_TITLE}':
				$emailData = str_replace('{QUIZ_TITLE}', $quizDetails->quiz_title, $emailData);
				break;

			case '{QUIZ_GRADE}':
				$emailData = str_replace('{QUIZ_GRADE}', $progressData->quiz_grade . '%', $emailData);
				break;

			case '{QUIZ_ATTEMPTS}':
				$emailData = str_replace('{QUIZ_ATTEMPTS}', $progressData->attempt_count, $emailData);
				break;

			case '{QUIZ_TIME}':
				$timeToShare = __('n/a', 'wp_courseware');

				if ($progressData->quiz_completion_time_seconds > 0) {
					$timeToShare = WPCW_time_convertSecondsToHumanLabel($progressData->quiz_completion_time_seconds);
				}

				$emailData = str_replace('{QUIZ_TIME}', $timeToShare, $emailData);
				break;

			case '{QUIZ_RESULT_DETAIL}':
				$emailData = str_replace('{QUIZ_RESULT_DETAIL}', $additionalData, $emailData);
			break;

			case '{QUIZ_GRADES_BY_TAG}':
				// Use existing frontend code to get the list of messages relating to tags.
				$msgList = $feObj->fetch_quizzes_questionResultsByTag();
				$msgSummary = false;

				if (!empty($msgList))
				{
					foreach ($msgList as $tagDetails)
					{
						// Got open questions
						if ($tagDetails['question_open_count'] > 0)
						{
							$msgSummary .= sprintf("%s: %s\n", $tagDetails['tag_details']->question_tag_name,
								sprintf(__('Your grade is %d%%', 'wp_courseware'), $tagDetails['score_total'])
							);
						}

						// Just closed questions, show out of message.
						else
						{
							$msgSummary .= sprintf("%s: %s\n", $tagDetails['tag_details']->question_tag_name,
								sprintf(__('%d out of %d correct (%d%%)', 'wp_courseware'),
									$tagDetails['score_correct_questions'],
									$tagDetails['question_count'],
									$tagDetails['score_total'])
							);
						} // end of question type check


					}
				}

				$emailData = str_replace('{QUIZ_GRADES_BY_TAG}', $msgSummary, $emailData);
			break;

			case '{CUSTOM_FEEDBACK}':
				// Use existing frontend code to get the list of custom feedback messages.
				$customFeedback = false;
				$msgList = $feObj->fetch_customFeedbackMessage_calculateMessages();

				if (!empty($msgList))
				{
					$customFeedback = apply_filters('wpcw_email_feedback_separator_top', "\n\n------\n\n");
					foreach ($msgList as $singleMsg)
					{
						// Separate each custom feedback message slightly
						$customFeedback .= $singleMsg . apply_filters('wpcw_email_feedback_separator', "\n\n------\n\n");
					}
				}

				$emailData = str_replace('{CUSTOM_FEEDBACK}', $customFeedback, $emailData);
			break;
		}
	}

	return $emailData;
}


/**
 * Replace all of the email tags with the actual details.
 *
 * @param Object $unitParentData The parent data for a unit.
 * @param Object $userDetails The details of the user who's done the completing.
 * @param Array $tagList The list of tags found in the template.
 * @param String $emailData The data to replace the strings with.
 *
 * @return The modified email data ready for sending.
 */
function WPCW_email_replaceTags_generic($unitParentData, $userDetails, $tagList, $emailData)
{
	if (empty($tagList)) {
		return $emailData;
	}

	// Replace each tag for quiz-related data.
	foreach ($tagList as $tagToReplace)
	{
		switch ($tagToReplace)
		{
			case '{USER_NAME}':
				$emailData = str_replace('{USER_NAME}', $userDetails->display_name, $emailData);
				break;

			case '{FIRST_NAME}':
				if (!isset($userDetails->first_name)){
					$userDetails = get_userdata($userDetails->user_id);
				}
				$emailData = str_replace('{FIRST_NAME}', $userDetails->first_name, $emailData);
				break;

			case '{LAST_NAME}':
				if (!isset($userDetails->first_name)){
					$userDetails = get_userdata($userDetails->user_id);
				}
				$emailData = str_replace('{LAST_NAME}', $userDetails->last_name, $emailData);
				break;

			case '{SITE_NAME}':
				$emailData = str_replace('{SITE_NAME}', get_bloginfo('name'), $emailData);
				break;

			case '{SITE_URL}':
				$emailData = str_replace('{SITE_URL}', get_bloginfo('url'), $emailData);
				break;

			case '{COURSE_TITLE}':
				$emailData = str_replace('{COURSE_TITLE}', $unitParentData->course_title, $emailData);
				break;

			case '{MODULE_TITLE}':
				$moduleTitle = false;

				if (isset($unitParentData->module_title)) {
					$moduleTitle = $unitParentData->module_title;
				}

				$emailData = str_replace('{MODULE_TITLE}', $moduleTitle, $emailData);
				break;

			case '{MODULE_NUMBER}':
				$moduleNumber = false;

				if (isset($unitParentData->module_number)) {
					$moduleNumber = $unitParentData->module_number;
				}

				$emailData = str_replace('{MODULE_NUMBER}', $moduleNumber, $emailData);
				break;

			case '{UNIT_TITLE}':
				$emailData = str_replace('{UNIT_TITLE}', get_the_title($unitParentData->unit_id), $emailData);
				break;

			case '{UNIT_URL}':
				$emailData = str_replace('{UNIT_URL}', get_permalink($unitParentData->unit_id), $emailData);
				break;

			case '{CERTIFICATE_LINK}':
				// Certificates - generate a link if enabled.
				$certificateLink = false;
				if ('use_certs' == $unitParentData->course_opt_use_certificate)
				{
					$certificateDetails = WPCW_certificate_getCertificateDetails($userDetails->ID, $unitParentData->course_id, false);
					if ($certificateDetails) {
						$certificateLink = WPCW_certificate_generateLink($certificateDetails->cert_access_key);
					}
				}

				$emailData = str_ireplace('{CERTIFICATE_LINK}', $certificateLink, $emailData);
			break;
		}
	}

	return $emailData;
}


/**
 * Action called when a user is deleted in WordPress. Remove all progress
 * and certificate details.
 *
 * @param Integer $user_id The ID of the user that's just been deleted.
 */
function WPCW_actions_users_userDeleted($user_id)
{
	global $wpdb, $wpcwdb;
    $wpdb->show_errors();

    // Course progress summary for user needs to be removed.
    $wpdb->query($SQL = $wpdb->prepare("
				DELETE FROM $wpcwdb->user_courses
				WHERE user_id = %d
			", $user_id));

   // User's progress
   $wpdb->query($SQL = $wpdb->prepare("
				DELETE FROM $wpcwdb->user_progress
				WHERE user_id = %d
			", $user_id));

   // User's quiz answers
   $wpdb->query($SQL = $wpdb->prepare("
				DELETE FROM $wpcwdb->user_progress_quiz
				WHERE user_id = %d
			", $user_id));

  	// User's question locks
   	$wpdb->query($SQL = $wpdb->prepare("
				DELETE FROM $wpcwdb->question_rand_lock
				WHERE question_user_id = %d
			", $user_id));

   	// User's queue entries
   	$wpdb->query($SQL = $wpdb->prepare("
				DELETE FROM $wpcwdb->queue_dripfeed
				WHERE queue_user_id = %d
			", $user_id));

   // User's certificates
   $wpdb->query($SQL = $wpdb->prepare("
				DELETE FROM $wpcwdb->certificates
				WHERE cert_user_id = %d
			", $user_id));
}


/**
 * Function called when registration form loads up.
 * Looks for course_id parameter and adds hidden fields into the registration form.
 */
function WPCW_course_enrollment_via_shortcode(){

	if (isset($_GET['_wp_enroll']) && wp_verify_nonce($_GET['_wp_enroll'], 'wpcw_enroll'))
	{

	$courses = array();

		if(isset($_GET['course_id'])){
				foreach ($_GET['course_id'] as $key => $value){
					$html = '<input type="hidden" name="course_id['. $key .']" id="course_ID" value="' . $value . '" />';
					echo $html;

				}
		}
	}
}


/**
 * Action called when a new user is created in WordPress. Used to check if we need to
 * automatically add access for this user to access a training course.
 *
 * @param Integer $user_id The ID of the user that's just been added.
 */
function WPCW_actions_users_newUserCreated($user_id)
{
		// Checks for course ID on post which will enroll student into specified course.
	if(isset($_POST['course_id'])){
		$courses = array();
		foreach ($_POST['course_id'] as $key => $value)
		{
			$courses[$key] = $value;
		}
	}else{
		// See if an extension is taking over the checking of access control. If a function is
		// defined to return true, then this section of code is ignored.
		$ignoreOnNewUser = apply_filters('wpcw_extensions_ignore_new_user', false);
		if ($ignoreOnNewUser) {
			return;
		}


		global $wpdb, $wpcwdb;
	    $wpdb->show_errors();

	    // Get a list of all courses that want users added automatically.
	    $courses = $wpdb->get_col("
	    	SELECT *
	    	FROM $wpcwdb->courses
	    	WHERE course_opt_user_access = 'default_show'
	    ");
	}
    // None found
    if (!$courses || count($courses) < 1) {
    	return;
    }

    // Add access for this user to all courses we're associated with.
    WPCW_courses_syncUserAccess($user_id, $courses, 'sync');
}


/**
 * Function to add the specified list of course IDs for the specified user.
 *
 * @param Integer $user_id The ID of the user to update.
 * @param Mixed $courseIDs The ID or array of IDs of the course IDs to give the user access to.
 * @param Boolean $syncMode If 'sync', then remove access to any course IDs not mentioned in $courseIDs parameter. If 'add', then just add access for course IDs that have been specified.
 * @param Array $enrolmentDates An optional list of course IDs => Timestamps of enrollment dates to add to the database.
 *
 */
function WPCW_courses_syncUserAccess($user_id, $courseIDs, $syncMode = 'add', $enrolmentDates = false, $courseAuthorID = false )
{
	// Not a valid user
	if (!get_userdata($user_id)) {
		return;
	}

	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	// List of course IDs that actually exist.
	$courseList = array();

	// Got a list of IDs?
	if (is_array($courseIDs))
	{
		// List is empty, save a query
		if (count($courseIDs) > 0)
		{
			// Yep, this course actually exists
			foreach ($courseIDs as $potentialCourseID)
			{
				if ($courseDetails = WPCW_courses_getCourseDetails($potentialCourseID)) {
					$courseList[$potentialCourseID] = $courseDetails;
				}
			}
		}
	}

	// Got a single ID..., so add to list of courses to process having
	// checked ID belongs to a proper course.
	else
	{
		if ($courseDetails = WPCW_courses_getCourseDetails($courseIDs)) {
			$courseList[$courseIDs] = $courseDetails;
		}
	}

	// Check if we want to remove access for courses that are not mentioned.
	// We'll add any they should have access to in a mo.
	if ($syncMode == 'sync')
	{
		$str_courseIDs = false;
		$courseIDCount = count(array_keys($courseList));
		$courseIDsToRemove = false;

		// Actually got some IDs to remove, so create an SQL string with all IDs
		if ($courseIDCount > 0)
		{
			// List Course Ids
			$list_courseIDs = array_keys( $courseList );

			// If a teacher, we need to get all the courses authored by others as to not remove them.
			if ( ! current_user_can( 'manage_wpcw_settings' ) ) {
				if ( ! $courseAuthorID ) {
					$current_user = wp_get_current_user();
					$courseAuthorID = $current_user->ID;
				}
				$courseIdsFromOtherAuthors = $wpdb->get_col("
					SELECT uc.course_id
					FROM $wpcwdb->user_courses uc 
					LEFT JOIN $wpcwdb->courses c ON c.course_id = uc.course_id
					WHERE c.course_author != $courseAuthorID"
				);
				if ( ! empty( $courseIdsFromOtherAuthors ) ) {
					$list_courseIDs = array_merge( $courseIdsFromOtherAuthors, $list_courseIDs );
				}
			}

			// Make the list_courseIDs comma separated
			$str_courseIDs = implode( ",", $list_courseIDs );

			// Get the courses to delete.
			$list_courseIDsToDelete = $wpdb->get_col("SELECT course_id FROM $wpcwdb->courses WHERE course_id NOT IN ($str_courseIDs)", 0);

			if (!empty($list_courseIDsToDelete))
			{
				// Get array of courses to be removed
				$courseIDsToRemove = $wpdb->get_col($wpdb->prepare("SELECT course_id FROM $wpcwdb->user_courses WHERE user_id = %d AND course_id NOT IN ($str_courseIDs)", $user_id), 0);

				// Remove meta for this user all previous courses.
				// Previous version deleteted all courses then re-created them. As a result, data was being lost about email being sent.
				$wpdb->query($wpdb->prepare("DELETE FROM $wpcwdb->user_courses WHERE user_id = %d AND course_id NOT IN ($str_courseIDs)", $user_id));

				// Remove any associated notifications from the queue for just these courses
				WPCW_queue_dripfeed::updateQueueItems_removeUser_fromCourseList($user_id, $list_courseIDsToDelete);
			}
		}
		// Got no IDs - user is being removed from all courses.
		else
		{
			// If a teacher, we need to get all the courses authored by others as to not remove them.
			if ( ! current_user_can( 'manage_wpcw_settings' ) ) {
				if ( ! $courseAuthorID ) {
					$current_user = wp_get_current_user();
					$courseAuthorID = $current_user->ID;
				}
				$courseIdsFromOtherAuthors = $wpdb->get_col("
					SELECT uc.course_id
					FROM $wpcwdb->user_courses uc 
					LEFT JOIN $wpcwdb->courses c ON c.course_id = uc.course_id
					WHERE c.course_author != $courseAuthorID"
				);

				// Make the list_courseIDs comma separated
				$str_courseIDs = implode( ",", $courseIdsFromOtherAuthors );

				if(!$str_courseIDs) 
				{ 
					$str_courseIDs = "-1"; 
				}

				// Get list of all courses for removal
				$courseIDsToRemove = $wpdb->get_col($wpdb->prepare("SELECT course_id FROM $wpcwdb->user_courses WHERE user_id = %d AND course_id NOT IN ($str_courseIDs)", $user_id), 0);

				$wpdb->query($wpdb->prepare("DELETE FROM $wpcwdb->user_courses WHERE user_id = %d AND course_id NOT IN ($str_courseIDs)", $user_id));

				// Remove any associated notifications from the queue for all courses
				WPCW_queue_dripfeed::updateQueueItems_removeUser_fromAllCourses($user_id);
			} else {
				// Get list of all courses for removal
				$courseIDsToRemove = $wpdb->get_col($wpdb->prepare("SELECT course_id FROM $wpcwdb->user_courses WHERE user_id = %d", $user_id), 0);

				$wpdb->query($wpdb->prepare("DELETE FROM $wpcwdb->user_courses WHERE user_id = %d", $user_id));

				// Remove any associated notifications from the queue for all courses
				WPCW_queue_dripfeed::updateQueueItems_removeUser_fromAllCourses($user_id);
			}
		}
		do_action('wpcw_unenroll_user', $user_id, $courseIDsToRemove);
	}


	// Only process valid course IDs
	if (count($courseList) > 0)
	{
		$courses_enrolled = array();
		foreach($courseList as $validCourseID => $courseDetails)
		{
			$existingCourseDetails = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpcwdb->user_courses WHERE user_id = %d AND course_id = %d", $user_id, $validCourseID));

			// See if this is already in the database.
			if ($existingCourseDetails)
			{
				// Got an enrollment date for this course ID? Convert it to MySQL date and update the course. Don't both trying to
				// update the enrollment date if we're not specifically providing one.
				if (isset($enrolmentDates[$validCourseID]))
				{
					$dateForEnrolment = date('Y-m-d H:i:s', $enrolmentDates[$validCourseID]);

					$wpdb->query($wpdb->prepare("
							UPDATE $wpcwdb->user_courses
							SET course_enrolment_date = %s
							WHERE user_id = %d AND course_id = %d
						", $dateForEnrolment, $user_id, $validCourseID));
				}
			}

			// Course already exists.
			else
			{
				$dateForEnrolment = current_time('mysql');

				// Got an enrollment date for this course ID? Convert it to MySQL date and use it when adding this course.
				if (isset($enrolmentDates[$validCourseID])) {
					$dateForEnrolment = date('Y-m-d H:i:s', $enrolmentDates[$validCourseID]);
				}

				// Actually add reference in database as it doesn't exist.
				$wpdb->query($wpdb->prepare("INSERT INTO $wpcwdb->user_courses (user_id, course_id, course_progress, course_enrolment_date) VALUES(%d, %d, 0, %s)",
					$user_id, $validCourseID,
					$dateForEnrolment
				));

				// Generate array of courses user is enrolled in
				$courses_enrolled[] = $validCourseID;
			}

		    // Get a total count of units in this course
		    $SQL = $wpdb->prepare("
		    	SELECT COUNT(*)
		    	FROM $wpcwdb->units_meta
		    	WHERE parent_course_id = %d
		    ", $validCourseID);

		    $totalUnitCount = $wpdb->get_var($SQL);

			// Calculate the user's progress, in case they've still got completed progress
			// in the database.
			WPCW_users_updateUserUnitProgress($validCourseID, $user_id, $totalUnitCount);

			// Update the notifications, just in case the enrollment dates have changed for each course.
			WPCW_queue_dripfeed::updateQueueItems_updateNotifications_forCourse($user_id, $validCourseID);
		}
	do_action('wpcw_enroll_user', $user_id, $courses_enrolled);
	}
}


/**
 * Fetch a list of courses for the specified user.
 *
 * @param Integer $user_id The ID of the user to get the course list for.
 * @return Array The list of courses for this user (or false if there are none).
 */
function WPCW_users_getUserCourseList($user_id)
{
	global $wpdb, $wpcwdb;
    $wpdb->show_errors();

    $courseData = $wpdb->get_results($wpdb->prepare("
    	SELECT *
    	FROM $wpcwdb->user_courses uc
    		LEFT JOIN  $wpcwdb->courses c ON c.course_id = uc.course_id
   		WHERE user_id = %d
   		ORDER BY course_title ASC
    ", $user_id));

    return $courseData;
}

/**
 * Fetch a list of courses for the specified user - Admin
 *
 * @param Integer $user_id The ID of the user to get the course list for.
 * @return Array The list of courses for this user (or false if there are none).
 */
function WPCW_users_getUserCourseListAdmin($user_id)
{
	// Global
	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	// Vars
	$current_user = wp_get_current_user();

	// SQL course author id condition
	$SQL_COURSE_AUTHOR = false;
	if ( ! user_can( $current_user, 'manage_wpcw_settings' ) ) {
		$SQL_COURSE_AUTHOR = $wpdb->prepare(' AND course_author = %d', $current_user->ID );
	}

	$courseData = $wpdb->get_results($wpdb->prepare("
    	SELECT *
    	FROM $wpcwdb->user_courses uc
    		LEFT JOIN  $wpcwdb->courses c ON c.course_id = uc.course_id
   		WHERE user_id = %d
   		$SQL_COURSE_AUTHOR
   		ORDER BY course_title ASC
    ", $user_id));

	return $courseData;
}


/**
* Updates the database to generate a certificate entry. If a certificate already exists for the user/course ID,
 * then no new entry is created.
 *
 * @param Integer $userID The ID of the user that the certificate is being generated for.
 * @param Integer $courseID The ID of the associated course.
 */
function WPCW_certificate_generateCertificateEntry($userID, $courseID)
{
	if (!$userID || !$courseID) {
		return;
	}

	global $wpdb, $wpcwdb;
    $wpdb->show_errors();

    // Already have a record for this certificate.
    if ($certificateDetails = doesRecordExistAlready($wpcwdb->certificates, array('cert_user_id', 'cert_course_id'), array($userID, $courseID))) {
    	return $certificateDetails;
    }

    // Create anonymous entry to allow users to access a certificate when they've completed a course.  Means that certificates
    // stay existing even if units are added to a course.
    $data = array();
    $data['cert_user_id'] 		= $userID;
    $data['cert_course_id'] 	= $courseID;
    $data['cert_generated'] 	= current_time('mysql');
    $data['cert_access_key'] 	= md5(serialize($data)); // Unique key based on data we've just added

	$SQL = arrayToSQLInsert($wpcwdb->certificates, $data);
	$wpdb->query($SQL);

	// Return details of the added certificate
	return getRecordDetails($wpcwdb->certificates, array('cert_user_id', 'cert_course_id'), array($userID, $courseID));
}


/**
 * Get the certificate details for a user, or false if not found.
 *
 * @param Integer $userID The ID of the user to check.
 * @param Integer $courseID The ID of the associated course.
 * @param Boolean $tryToCreate If true, try to create the certificate if details don't exist.
 *
 * @return Object The certificate details if they were found, or false if not found.
 */
function WPCW_certificate_getCertificateDetails($userID, $courseID, $tryToCreate = true)
{
	global $wpcwdb;
	$certificateDetails = getRecordDetails($wpcwdb->certificates, array('cert_user_id', 'cert_course_id'), array($userID, $courseID));

	if ($tryToCreate && !$certificateDetails) {
		return WPCW_certificate_generateCertificateEntry($userID, $courseID);
	}

	return $certificateDetails;
}


/**
 * Get the certificate details for a user, or false if not found.
 *
 * @param String $accessID The unique access key for the certificate.
 *
 * @return Object The certificate details if they were found, or false if not found.
 */
function WPCW_certificate_getCertificateDetails_byAccessKey($accessKey)
{
	// Validate for a MD5 hash

	if (!preg_match('/^[A-Za-z0-9]{32}$/', $accessKey)) {
		return false;
	}

	global $wpcwdb;
	return getRecordDetails($wpcwdb->certificates, 'cert_access_key', $accessKey);
}


/**
 * Gets the user's name if set up, or their username otherwise.
 * @param Object $userDetails The user details as an object.
 * @return String The user's name.
 */
function WPCW_users_getUsersName($userDetails)
{
	if (!$userDetails) {
		return false;
	}

	// Generate the name from the user's first and last name. If they don't exist
	// then use the display name as a default.
	$name = $userDetails->user_firstname . ' ' . $userDetails->user_lastname;
	if (!trim($name)) {
		$name = $userDetails->data->display_name;
	}

	return $name;
}


/**
 * Return a URL to download a certificate.
 *
 * @param String $accessKey The access key for the certificate.
 * @return String The full URL to the certificate.
 */
function WPCW_certificate_generateLink($accessKey){
	return apply_filters('wpcw_certificate_generated_url', add_query_arg( array( 'page' => 'wpcw_pdf_create_certificate', 'certificate' => $accessKey ), esc_url( site_url( '/' ) ) ) );
}


/**
 * Converts a list of raw file extensions into a list of permitted file extensions.
 * @param String $rawInput The list of raw file extensions.
 * @return Array The list of file extensions.
 */
function WPCW_files_cleanFileExtensionList($rawInput)
{
	$list = array();

	// Turn comma list into array of items
	$rawList = explode(',', $rawInput);
	if (!empty($rawList))
	{
		// Check each item
		foreach ($rawList as $ext)
		{
			$ext = strtolower($ext);
			$ext = preg_replace('/[^a-z0-9]/', '', $ext); // Remove anything other than numbers and letters.

			// Got anything left? Add it to the list.
			if ($ext) {
				$list[] = $ext;
			}
		}
	}

	return $list;
}


/**
 * Returns the maximally uploadable file size in megabytes.
 *
 * @return  string
 */
function WPCW_files_getMaxUploadSize()
{
   $max_upload    = (int)(ini_get('upload_max_filesize'));
   $max_post      = (int)(ini_get('post_max_size'));
   $memory_limit  = (int)(ini_get('memory_limit'));
   return min($max_upload, $max_post, $memory_limit) . __('MB', 'wp_courseware');
}


/**
 * Create a directory that can be used to store the uploaded files in for the user completing a specific quiz.
 *
 * @param Object $quizDetails The details of the quiz being completed.
 * @param Integer $userID The ID of the user completing the quiz.
 * @param Boolean $createItToo If true, then create the new directory.
 *
 * @return Array The full server path to the newly created upload directory and URL version.
 */
function WPCW_files_getFileUploadDirectory_forUser($quizDetails, $userID, $createItToo = true)
{
	// Create path based on the quiz ID, user ID, and date.
	$keyString = sprintf('%d_%d_%s', $quizDetails->quiz_id, $userID, date('Ymd_His'));
	$pathName  = $keyString . '_' . md5('user_upload_directory' . $keyString);

	// Generate the full file path
	$fullPath = WP_CONTENT_DIR . '/wpcourseware_uploads/' . $pathName . '/';

	if ($createItToo && !file_exists($fullPath)) {
		@mkdir($fullPath, 0777, true);
	}

	// Create an empty index page to stop directory listings.
	if (file_exists($fullPath)) {
		touch($fullPath . 'index.php');
	}

	// Need URL and directory versions
	return array(
		'dir_path' 	=> $fullPath,
		'path_only'	=> '/wpcourseware_uploads/' . $pathName . '/'
	);
}


/**
 * Generates the upload directory with an empty index.php file to prevent directory listings.
 */
function WPCW_files_createFileUploadDirectory_base()
{
	// Generate the full file path
	$fullPath = WP_CONTENT_DIR . '/wpcourseware_uploads/';

	if (!file_exists($fullPath)) {
		@mkdir($fullPath, 0777, true);
	}

	// Create an empty index page to stop directory listings.
	if (file_exists($fullPath)) {
		touch($fullPath . 'index.php');
	}
}


/**
 * Generates the upload directory with an empty index.php file to prevent directory listings.
 */
function WPCW_files_getFileSize_human($fileName)
{
	$fileSizeBytes = filesize(WP_CONTENT_DIR . $fileName);
	if ($fileSizeBytes === FALSE) {
		return __('Not found.', 'wp_courseware');
	}

	return WPCW_files_formatBytes($fileSizeBytes, 0);
}


/**
 * Format a size into the right KB, MB, etc
 *
 * @param Integer $size The size in bytes.
 * @param Integer $precision The number of decimal places.
 *
 * @return String The file size as a string.
 */
function WPCW_files_formatBytes($size, $precision = 2)
{
    $base = log($size) / log(1024);
    $suffixes = array('', 'KB', 'MB', 'GB', 'TB');

    return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
}


/**
 * Creates a progress bar
 *
 * @param Integer $percentage The percentage completion so far.
 * @param String $cssClass The optional string to use for the CSS class for the progress bar.
 * @param String $extraHTML Any extra HTML to show for the progress bar line.
 *
 * @return String The HTML for the progress bar.
 */
function WPCW_content_progressBar($percentage, $cssClass = false, $extraHTML = false)
{
	return sprintf('
	<span class="wpcw_progress_wrap %s">
		<span class="wpcw_progress">
			<span class="wpcw_progress_bar" style="width: %d%%"></span>
		</span>
		<span class="wpcw_progress_percent">%d%%</span>
		%s
	</span>
	', $cssClass, $percentage, $percentage, $extraHTML);
}


/**
 * Given the ID of a tag, fetch the full details.
 *
 * @param Integer $tagID The ID of the tag to get.
 * @return Object The details of the tag to return.
 */
function WPCW_questions_tags_getTagDetails($tagID)
{
	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	return $wpdb->get_row($wpdb->prepare("
		SELECT *
		FROM $wpcwdb->question_tags
		WHERE question_tag_id = %d
	", $tagID));
}


/**
 * Given the name of a tag, fetch the full details.
 *
 * @param String $tagName The name of the tag to get.
 * @return Object The details of the tag to return.
 */
function WPCW_questions_tags_getTagDetails_byName($tagName)
{
	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	return $wpdb->get_row($wpdb->prepare("
		SELECT *
		FROM $wpcwdb->question_tags
		WHERE question_tag_name = %s
	", $tagName));
}


/**
 * Calculate how many questions there are.
 *
 * @return Integer The total number of questions.
 */
function WPCW_questions_getQuestionCount()
{
	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	return $wpdb->get_var("
		SELECT COUNT(*) as q_count
		FROM $wpcwdb->quiz_qs
		WHERE question_type != 'random_selection'
	");
}


/**
 * Get a list of tags for the specified question ID.
 * @param Integer $questionID The ID of the question to get tags for.
 * @return Array The list of tags for this question.
 */
function WPCW_questions_tags_getTagsForQuestion($questionID)
{
	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	return $wpdb->get_results($wpdb->prepare("
		SELECT qt.*
		FROM $wpcwdb->question_tag_mapping qtm
			LEFT JOIN $wpcwdb->question_tags qt ON qtm.tag_id = qt.question_tag_id
		WHERE question_id = %d
		  AND question_tag_name IS NOT NULL
		ORDER BY question_tag_name ASC
	", $questionID));
}


/**
 * Get a list of tags for the specified list of question IDs.
 * @param Array $questionList The list of question IDs to get tags for.
 * @return Array The list of tags for this question.
 */
function WPCW_questions_tags_getTagsForQuestionList($questionList)
{
	if (empty($questionList)) {
		return false;
	}

	global $wpcwdb, $wpdb;
	$wpdb->show_errors();

	// Generate the right number of %d in the SQL, removing final comma.
	$numberOfIDs = count($questionList);
	$sqlForIDs = rtrim(str_repeat('%d,', $numberOfIDs), ',');

	return $wpdb->get_results($wpdb->prepare("
		SELECT qtm.question_id, qt.*
		FROM $wpcwdb->question_tag_mapping qtm
			LEFT JOIN $wpcwdb->question_tags qt ON qtm.tag_id = qt.question_tag_id
		WHERE question_id IN ($sqlForIDs)
		  AND question_tag_name IS NOT NULL
		ORDER BY question_id ASC
	", $questionList));
}


/**
 * Convert a time in minutes to a human label.
 * @param Integer $minutes The time in minutes.
 * @return String The time in hours, minutes and seconds.
 */
function WPCW_time_convertMinutesToHumanLabel($minutes)
{
	return WPCW_time_convertSecondsToHumanLabel($minutes*60);
}


/**
 * Convert a time in seconds to a human label.
 * @param Integer $seconds The time in seconds.
 * @return String The time in hours, minutes and seconds.
 */
function WPCW_time_convertSecondsToHumanLabel($seconds)
{
	$lbl_seconds = 0;
	$lbl_minutes = 0;
	$lbl_hours = 0;

	// Calculate everything and break it down.
	if ($seconds > 0)
	{
		$lbl_hours = floor($seconds / 3600);
		$lbl_minutes = ($seconds / 60) % 60;
		$lbl_seconds = $seconds % 60;
	}

	// Start of with minutes and seconds - always use these.
	$labelToReturn = sprintf('%d %s %d %s',
		$lbl_minutes, __('mins', 'wp_courseware'),
		$lbl_seconds, __('secs', 'wp_courseware')
	);

	// If we have hours too, then prepend.
	if ($lbl_hours > 0) {
		$labelToReturn = sprintf('%d %s ', $lbl_hours, __('hrs', 'wp_courseware')) . $labelToReturn;
	}

	return $labelToReturn;
}


/**
 * Get all of the custom feedback messages for a specific quiz.
 *
 * @param Integer $quizID The ID of the quiz that's being shown.
 * @param Array $tagIDList A list of tag IDs to filter the messages for.
 *
 * @return Array The list of feedback messages for this quiz.
 */
function WPCW_quizzes_feedback_getFeedbackMessagesForQuiz($quizID, $tagIDList = null)
{
	// No results if there are no tags.
	if (!is_null($tagIDList) && empty($tagIDList)) {
		return false;
	}

	global $wpdb, $wpcwdb;
    $wpdb->show_errors();

    // If we have a list of tag IDs, then add to SQL.
    $extraSQL = false;
    if (!empty($tagIDList))
    {
    	$extraSQL = 'AND qfeedback_tag_id IN (' . implode(",", $tagIDList) . ')';
    }

    $SQL = $wpdb->prepare("
    	SELECT *
    	FROM $wpcwdb->quiz_feedback
    	WHERE qfeedback_quiz_id = %d
    	$extraSQL
    	ORDER BY qfeedback_tag_id ASC
   	", $quizID);

    return $wpdb->get_results($SQL);
}

?>