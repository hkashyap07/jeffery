<?php
/**
 * WPCW Database upgrade and creation functions.
 */

/**
 * Function to upgrade the database tables.
 *
 * @param Integer $installedVersion The version that exists prior to the upgrade.
 * @param Boolean $forceUpgrade If true, we force an upgrade.
 * @param Boolean $showErrors If true, show any debug errors.
 */
function WPCW_database_upgradeTables($installedVersion, $forceUpgrade, $showErrors = false)
{
	// globals
	global $wpdb, $wpcwdb;

	// Show errors if debugging
	if ( $showErrors ) {
		$wpdb->show_errors();
	}

	// Always upgrade tables. Conditionally execute any other table changes.
	$upgradeNow = true;

	// #### Courses Table
	$SQL = "CREATE TABLE $wpcwdb->courses (
			  course_id int(11) unsigned NOT NULL AUTO_INCREMENT,
			  course_title varchar(150) NOT NULL,
			  course_desc text NULL,
			  course_author bigint(20) unsigned NOT NULL default '0',
			  course_opt_completion_wall varchar(20) NOT NULL,
			  course_opt_use_certificate varchar(20) NOT NULL DEFAULT 'no_certs',
			  course_opt_user_access varchar(20) NOT NULL,
			  course_unit_count int(11) unsigned NULL DEFAULT '0',
			  course_from_name varchar(150) NOT NULL,
			  course_from_email varchar(150) NOT NULL,
			  course_to_email varchar(150) NOT NULL,
			  course_opt_prerequisites longtext NOT NULL,
			  course_message_unit_complete text NULL,
			  course_message_course_complete text NULL,
			  course_message_unit_not_logged_in text NULL,
			  course_message_unit_pending text NULL,
			  course_message_unit_no_access text NULL,
			  course_message_prerequisite_not_met text NULL,
			  course_message_unit_not_yet text NULL,
			  course_message_unit_not_yet_dripfeed text NULL,
			  course_message_quiz_open_grading_blocking text NULL,
			  course_message_quiz_open_grading_non_blocking text NULL,
			  email_complete_module_option_admin varchar(20) NOT NULL,
			  email_complete_module_option varchar(20) NOT NULL,
			  email_complete_module_subject varchar(300) NOT NULL,
			  email_complete_module_body text NULL,
			  email_complete_course_option_admin varchar(20) NOT NULL,
			  email_complete_course_option varchar(20) NOT NULL,
			  email_complete_course_subject varchar(300) NOT NULL,
			  email_complete_course_body text NULL,
			  email_quiz_grade_option varchar(20) NOT NULL,
			  email_quiz_grade_subject varchar(300) NOT NULL,
			  email_quiz_grade_body text NULL,
			  email_complete_course_grade_summary_subject varchar(300) NOT NULL,
			  email_complete_course_grade_summary_body text NULL,
			  email_unit_unlocked_subject varchar(300) NOT NULL,
			  email_unit_unlocked_body text NULL,
			  cert_signature_type varchar(20) NOT NULL DEFAULT 'text',
			  cert_sig_text varchar(300) NOT NULL,
			  cert_sig_image_url varchar(300) NOT NULL DEFAULT  '',
			  cert_logo_enabled varchar(20) NOT NULL DEFAULT 'no_cert_logo',
			  cert_logo_url varchar(300) NOT NULL DEFAULT '',
			  cert_background_type varchar(20) NOT NULL DEFAULT 'use_default',
			  cert_background_custom_url varchar(300) NOT NULL DEFAULT  '',
			  PRIMARY KEY  (course_id)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8";
	WPCW_database_installTable($wpcwdb->courses, $SQL, $upgradeNow);

	// Added in 3.8 - New dripfeed - not got access yet.
	if ($forceUpgrade || $installedVersion < 3.8)
	{
		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses SET course_message_unit_not_yet_dripfeed = %s
			WHERE course_message_unit_not_yet_dripfeed IS NULL
			   OR course_message_unit_not_yet_dripfeed = ''", __("This unit isn't available just yet. Please check back in about {UNIT_UNLOCKED_TIME}. ", 'wp_courseware')));

	}

	// Added in 2.2 - New course completed message
	if ($forceUpgrade || $installedVersion < 2.2)
	{
		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses SET course_message_course_complete = %s
			WHERE course_message_course_complete IS NULL
			   OR course_message_course_complete = ''", __("You have now completed the whole course. Congratulations!", 'wp_courseware')));
	}

	// Added in 2.4 - New quiz messages
	if ($forceUpgrade || $installedVersion < 2.4)
	{
		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses
			  SET course_message_quiz_open_grading_blocking = %s
			WHERE course_message_quiz_open_grading_blocking IS NULL
			   OR course_message_quiz_open_grading_blocking = ''",
				__('Your quiz has been submitted for grading by the course instructor. Once your grade has been entered, you will be able access the next unit.', 'wp_courseware')
		));

		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses
			  SET course_message_quiz_open_grading_non_blocking = %s
			WHERE course_message_quiz_open_grading_non_blocking IS NULL
			   OR course_message_quiz_open_grading_non_blocking = ''",
				__('Your quiz has been submitted for grading by the course instructor. You have now completed this unit.', 'wp_courseware')
		));

		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses
			  SET email_quiz_grade_option = %s
			WHERE email_quiz_grade_option IS NULL
			   OR email_quiz_grade_option = ''",
				'send_email'
		));

		// After grade completion email.
		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses
			  SET email_quiz_grade_subject = %s
			WHERE email_quiz_grade_subject IS NULL
			   OR email_quiz_grade_subject = ''",
				EMAIL_TEMPLATE_QUIZ_GRADE_SUBJECT
		));

		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses
			  SET email_quiz_grade_body = %s
			WHERE email_quiz_grade_body IS NULL
			   OR email_quiz_grade_body = ''",
				EMAIL_TEMPLATE_QUIZ_GRADE_BODY
		));

		// Grade summary email
		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses
			  SET email_complete_course_grade_summary_subject = %s
			WHERE email_complete_course_grade_summary_subject IS NULL
			   OR email_complete_course_grade_summary_subject = ''",
				EMAIL_TEMPLATE_COURSE_SUMMARY_WITH_GRADE_SUBJECT
		));

		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses
			  SET email_complete_course_grade_summary_body = %s
			WHERE email_complete_course_grade_summary_body IS NULL
			   OR email_complete_course_grade_summary_body = ''",
				EMAIL_TEMPLATE_COURSE_SUMMARY_WITH_GRADE_BODY
		));
	}

	// Added in 3.8 - New messages for unlocked units
	if ($forceUpgrade || $installedVersion < 3.8)
	{
		// Unit unlocked email
		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses
			  SET email_unit_unlocked_subject = %s
			WHERE email_unit_unlocked_subject IS NULL
			   OR email_unit_unlocked_subject = ''",
				EMAIL_TEMPLATE_UNIT_UNLOCKED_SUBJECT
		));

		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses
			  SET email_unit_unlocked_body = %s
			WHERE email_unit_unlocked_body IS NULL
			   OR email_unit_unlocked_body = ''",
				EMAIL_TEMPLATE_UNIT_UNLOCKED_BODY
		));
	}

	// Added in 3.9 - New course prerequisite message
	if ($forceUpgrade || $installedVersion < 3.9)
	{
		$SQL = $wpdb->query($wpdb->prepare("
			UPDATE $wpcwdb->courses SET course_message_prerequisite_not_met = %s
			WHERE course_message_prerequisite_not_met IS NULL
			   OR course_message_prerequisite_not_met = ''", __("This course can not be accessed until the prerequisites for this course are complete.", 'wp_courseware')));

	}

	// Added in 4.0 - New course certificate data per course
	if ($forceUpgrade || $installedVersion < 4.0){
		WPCW_database_migrate_certificateData();
	}

	// Added in 4.0 - Update user progress quiz table's quiz_data column to LONGTEXT data type to accomadate for really long answers
	if ($forceUpgrade || $installedVersion < 4.0)
	{
		$wpdb->query("ALTER TABLE $wpcwdb->user_progress_quiz CHANGE quiz_data quiz_data LONGTEXT");
	}

	// #### Modules Table
	$SQL = "CREATE TABLE $wpcwdb->modules (
			  module_id int(11) unsigned NOT NULL AUTO_INCREMENT,
			  parent_course_id int(11) unsigned NOT NULL DEFAULT '0',
			  module_author bigint(20) unsigned NOT NULL default '0',
			  module_title varchar(150) NOT NULL,
			  module_desc text NULL,
			  module_order int(11) unsigned NOT NULL DEFAULT '10000',
			  module_number int(11) unsigned NOT NULL DEFAULT '0',
			  PRIMARY KEY  (module_id)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->modules, $SQL, $upgradeNow);

	// #### Units Meta Table
	$SQL = "CREATE TABLE $wpcwdb->units_meta (
			  unit_id int(11) unsigned NOT NULL,
			  parent_module_id int(11) unsigned NOT NULL DEFAULT '0',
			  parent_course_id int(11) unsigned NOT NULL DEFAULT '0',
			  unit_author bigint(20) unsigned NOT NULL default '0',
			  unit_order int(11) unsigned NOT NULL DEFAULT '0',
			  unit_number int(11) unsigned NOT NULL DEFAULT '0',
			  unit_drip_type varchar(50) NOT NULL DEFAULT '',
			  unit_drip_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			  unit_drip_interval int(11) NOT NULL DEFAULT '432000',
			  unit_drip_interval_type varchar(15) NOT NULL DEFAULT 'interval_days',
			  PRIMARY KEY  (unit_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->units_meta, $SQL, $upgradeNow);

	// #### User Courses Allocations Table
	$SQL = "CREATE TABLE $wpcwdb->user_courses (
			  user_id int(11) unsigned NOT NULL,
			  course_id int(11) unsigned NOT NULL,
			  course_progress int(11) NOT NULL DEFAULT '0',
			  course_final_grade_sent VARCHAR(30) NOT NULL DEFAULT '',
			  course_enrolment_date datetime DEFAULT '0000-00-00 00:00:00',
			  UNIQUE KEY user_id (user_id,course_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->user_courses, $SQL, $upgradeNow);

	// Upgrade the enrollment dates if we've added a version that has the new enrollement dates.
	if ($forceUpgrade || $installedVersion < 3.7) {
		WPCW_database_migration_enrolementDates();
	}

	// #### User Progress Table
	$SQL = "CREATE TABLE $wpcwdb->user_progress (
			  user_id int(11) unsigned NOT NULL,
			  unit_id int(11) unsigned NOT NULL,
			  unit_completed_date datetime DEFAULT NULL,
			  unit_completed_status varchar(20) NOT NULL,
			  PRIMARY KEY  (user_id,unit_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->user_progress, $SQL, $upgradeNow);

	// #### Quizzes Table
	$SQL = "CREATE TABLE $wpcwdb->quiz (
			  quiz_id int(11) unsigned NOT NULL AUTO_INCREMENT,
			  quiz_title varchar(150) NOT NULL,
			  quiz_desc text NULL,
			  quiz_author bigint(20) unsigned NOT NULL default '0',
			  parent_unit_id int(11) unsigned NOT NULL DEFAULT '0',
			  parent_course_id int(11) NOT NULL DEFAULT '0',
			  quiz_type varchar(15) NOT NULL,
			  quiz_pass_mark int(11) NOT NULL DEFAULT '0',
			  quiz_show_answers varchar(15) NOT NULL DEFAULT 'no_answers',
			  quiz_show_survey_responses varchar(15) NOT NULL DEFAULT 'no_responses',
			  quiz_attempts_allowed int(11) NOT NULL DEFAULT '-1',
			  show_answers_settings VARCHAR(500) NOT NULL DEFAULT '',
			  quiz_paginate_questions VARCHAR(15) NOT NULL DEFAULT 'no_paging',
			  quiz_paginate_questions_settings VARCHAR(500) NOT NULL DEFAULT '',
			  quiz_timer_mode varchar(25) NOT NULL DEFAULT 'no_timer',
			  quiz_timer_mode_limit int(11) unsigned NOT NULL DEFAULT '15',
			  quiz_results_downloadable varchar(10) NOT NULL DEFAULT 'on',
			  quiz_results_by_tag varchar(10) NOT NULL DEFAULT 'on',
			  quiz_results_by_timer varchar(10) NOT NULL DEFAULT 'on',
			  quiz_recommended_score varchar(20) NOT NULL DEFAULT 'no_recommended',
			  show_recommended_percentage int(10) unsigned NOT NULL DEFAULT 50,
			  PRIMARY KEY  (quiz_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->quiz, $SQL, $upgradeNow);

	// Added in 2.61
	if ($forceUpgrade || $installedVersion < 2.61)
	{
		// These are the defaults for the new quiz show answers settings that have been added in V2.61
		$default_showAnswers = 'a:5:{s:19:"show_correct_answer";s:2:"on";s:16:"show_user_answer";s:2:"on";s:16:"show_explanation";s:2:"on";s:12:"mark_answers";s:2:"on";s:18:"show_results_later";s:2:"on";}';

		$SQL = $wpdb->prepare("
			UPDATE $wpcwdb->quiz
			SET show_answers_settings = %s
			WHERE show_answers_settings = ''
		", $default_showAnswers);

		$wpdb->query($SQL);
	}

	// Defaults for the new paging option that was added in V3.00
	if ($forceUpgrade || $installedVersion < 3.00)
	{
		// These are the defaults for the new quiz paging settings that have been added in V2.61
		$default_pagingOption = 'a:3:{s:30:"allow_review_before_submission";s:2:"on";s:30:"allow_students_to_answer_later";s:2:"on";s:28:"allow_nav_previous_questions";s:2:"on";}';

		$SQL = $wpdb->prepare("
			UPDATE $wpcwdb->quiz
			SET quiz_paginate_questions_settings = %s
			WHERE quiz_paginate_questions_settings = ''
		", $default_pagingOption);

		$wpdb->query($SQL);
	}

	// #### Quiz - Questions
	$SQL = "CREATE TABLE $wpcwdb->quiz_qs (
			  question_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  question_author bigint(20) unsigned NOT NULL default '0',
			  question_type VARCHAR(20) NOT NULL DEFAULT 'multi',
			  question_question text NULL,
			  question_answers text NULL,
			  question_data_answers text NULL,
			  question_correct_answer VARCHAR(300) NOT NULL,
			  question_answer_type VARCHAR(50) NOT NULL DEFAULT '',
			  question_answer_hint text NULL,
			  question_answer_explanation text NULL,
			  question_image VARCHAR(300) NOT NULL DEFAULT '',
			  question_answer_file_types VARCHAR(300) NOT NULL DEFAULT '',
			  question_usage_count int(11) UNSIGNED DEFAULT 0,
			  question_expanded_count int(11) UNSIGNED DEFAULT 1,
			  question_multi_random_enable int(2) UNSIGNED DEFAULT 0,
			  question_multi_random_count  int(4) UNSIGNED DEFAULT 5,
			  PRIMARY KEY  (question_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->quiz_qs, $SQL, $upgradeNow);

	// #### Added in 2.5 - Quiz - Upgrade questions to use the new serialised array format.
	if ($forceUpgrade || $installedVersion < 2.5)
	{
		WPCW_database_upgrade_quizQuestions();
	}

	// #### Quiz - User Progress
	$SQL = "CREATE TABLE $wpcwdb->user_progress_quiz (
			  user_id int(11) NOT NULL,
			  unit_id int(11) NOT NULL,
			  quiz_id bigint(20) NOT NULL,
			  quiz_attempt_id int(11) NOT NULL DEFAULT '0',
			  quiz_completed_date datetime NOT NULL,
			  quiz_started_date datetime NOT NULL,
			  quiz_correct_questions int(11) unsigned NOT NULL,
			  quiz_grade FLOAT(8,2) NOT NULL DEFAULT '-1',
			  quiz_question_total int(11) unsigned NOT NULL,
			  quiz_data text NULL,
			  quiz_is_latest VARCHAR(50) DEFAULT 'latest',
			  quiz_needs_marking int(11) unsigned NOT NULL DEFAULT '0',
			  quiz_needs_marking_list TEXT NULL,
			  quiz_next_step_type VARCHAR(50) DEFAULT '',
			  quiz_next_step_msg TEXT DEFAULT '',
			  quiz_paging_status VARCHAR(20) NOT NULL DEFAULT 'complete',
			  quiz_paging_next_q int(11) NOT NULL DEFAULT 0,
			  quiz_paging_incomplete int(11) NOT NULL DEFAULT 0,
			  quiz_completion_time_seconds BIGINT NOT NULL DEFAULT 0,
			  UNIQUE KEY unique_progress_item (user_id,unit_id,quiz_id,quiz_attempt_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->user_progress_quiz, $SQL, $upgradeNow);

	// #### Added in 2.4 - Quiz - User Progress - grade data added to the table.
	if ($forceUpgrade || $installedVersion < 2.4)
	{
		set_time_limit(0);

		// All quizzes with a grade of -1 and all items are graded.
		$SQL_NEED_UPDATE = "
			SELECT user_id, unit_id, quiz_id, quiz_attempt_id, quiz_data
			FROM $wpcwdb->user_progress_quiz
			WHERE quiz_grade = -1
			  AND quiz_needs_marking = 0";

		$quizProgressToUpdate = $wpdb->get_row($SQL_NEED_UPDATE);
		while ($quizProgressToUpdate)
		{
			$quizData = maybe_unserialize($quizProgressToUpdate->quiz_data);
			$newGrade = WPCW_quizzes_calculateGradeForQuiz($quizData);

			if ($newGrade > -1)
			{
				$wpdb->query($wpdb->prepare("
					UPDATE $wpcwdb->user_progress_quiz
					  SET quiz_grade = %s
					WHERE unit_id = %d
					  AND user_id = %d
					  AND quiz_id = %d
					  AND quiz_attempt_id = %d
				", $newGrade,
					$quizProgressToUpdate->unit_id, $quizProgressToUpdate->user_id,
					$quizProgressToUpdate->quiz_id, $quizProgressToUpdate->quiz_attempt_id
				));
			}

			$quizProgressToUpdate = $wpdb->get_row($SQL_NEED_UPDATE);
			flush();
		}
	}

	// #### Added in 2.4 - Quiz - User Progress - remove old unique index by checking for it first.
	if ($forceUpgrade || $installedVersion < 2.8)
	{
		$SQL = "SHOW INDEX FROM $wpcwdb->user_progress_quiz WHERE KEY_NAME = 'user_id'";
		if ($wpdb->get_row($SQL))
		{
			$SQL = "ALTER TABLE $wpcwdb->user_progress_quiz DROP INDEX user_id";
			$wpdb->query($SQL);
		}
	}

	// #### Mapping of membership levels
	$SQL = "CREATE TABLE $wpcwdb->map_member_levels (
			  	course_id int(11) NOT NULL,
  				member_level_id varchar(100) NOT NULL,
  				UNIQUE KEY course_id (course_id,member_level_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->map_member_levels, $SQL, $upgradeNow);

	// #### Mapping of certificates
	$SQL = "CREATE TABLE $wpcwdb->certificates (
			  cert_user_id int(11) NOT NULL,
			  cert_course_id int(11) NOT NULL,
			  cert_access_key varchar(50) NOT NULL,
			  cert_generated datetime NOT NULL,
			  UNIQUE KEY cert_user_id (cert_user_id,cert_course_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->certificates, $SQL, $upgradeNow);

	// #### Questions - Tags
	$SQL = "CREATE TABLE $wpcwdb->question_tags (
				question_tag_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				question_tag_name varchar(150) NOT NULL,
				question_tag_usage int(11) unsigned NOT NULL,
				question_tag_author bigint(20) unsigned NOT NULL default '0',
				PRIMARY KEY  (question_tag_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->question_tags, $SQL, $upgradeNow);

	// #### Questions - Tag Mappings
	$SQL = "CREATE TABLE $wpcwdb->question_tag_mapping (
				question_id bigint(20) unsigned NOT NULL,
				tag_id bigint(20) unsigned NOT NULL,
				UNIQUE KEY question_tag_id (question_id,tag_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->question_tag_mapping, $SQL, $upgradeNow);

	// #### Questions/Quizzes - Mappings
	$SQL = "CREATE TABLE $wpcwdb->quiz_qs_mapping (
				parent_quiz_id bigint(20) unsigned NULL,
				question_id bigint(20) unsigned NOT NULL,
				question_order int(11) unsigned NOT NULL DEFAULT '0',
				UNIQUE KEY question_assoc_id (parent_quiz_id,question_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->quiz_qs_mapping, $SQL, $upgradeNow);

	// #### Random Questions - Lock selections to a user so that they don't
	// see different variations if refreshed.
	$SQL = "CREATE TABLE $wpcwdb->question_rand_lock (
				question_user_id int(11) unsigned NOT NULL,
				rand_question_id int(11) unsigned NOT NULL,
				parent_unit_id int(11) unsigned NOT NULL,
				question_selection_list text NOT NULL,
				UNIQUE KEY wpcw_question_rand_lock (question_user_id,rand_question_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->question_rand_lock, $SQL, $upgradeNow);

	// #### Added in 3.0 - Upgrade questions to have multiple quiz parents
	// by creating a quiz-to-question mapping. We need to migrate all of
	// the previous questions to refer to the new quiz parent.
	if ($forceUpgrade || $installedVersion < 3.0)
	{
		// See if we still have the old versions of the columns. Simply as parent_quiz_id and question_order
		// are now deprecated.
		$gotCol_parent_quiz_id = $wpdb->get_row("SHOW COLUMNS FROM $wpcwdb->quiz_qs LIKE 'parent_quiz_id'");
		$gotCol_question_order = $wpdb->get_row("SHOW COLUMNS FROM $wpcwdb->quiz_qs LIKE 'question_order'");

		// Yep, found both versions of the columns.
		if ($gotCol_parent_quiz_id && $gotCol_question_order)
		{
			// Need to get data from all questions
			$SQL_NEED_UPDATE = "
				SELECT question_id, parent_quiz_id, question_order
				FROM $wpcwdb->quiz_qs
			";

			$questionsToUpdate = $wpdb->get_results($SQL_NEED_UPDATE);
			if ($questionsToUpdate)
			{
				set_time_limit(0);

				foreach ($questionsToUpdate as $singleQuestion)
				{
					// Create the association in the new table, ignoring if it exists already.
					$wpdb->query($wpdb->prepare("
						INSERT IGNORE INTO $wpcwdb->quiz_qs_mapping
						(parent_quiz_id, question_id, question_order)
						VALUES (%d, %d, %d)
					", $singleQuestion->parent_quiz_id, $singleQuestion->question_id, $singleQuestion->question_order));

					// Update question to indicate it's usage
					$countNumber = $wpdb->get_var($wpdb->prepare("
						SELECT COUNT(parent_quiz_id)
						FROM $wpcwdb->quiz_qs_mapping
						WHERE question_id = %d
						", $singleQuestion->question_id));

					$wpdb->query($wpdb->prepare("
						UPDATE $wpcwdb->quiz_qs
						SET question_usage_count = %d
						WHERE question_id = %d
						", $countNumber, $singleQuestion->question_id));

					flush();
				}
			} // end if $questionsToUpdate

			// Data migrated, now we just rename the old columns, rather than delete. Just in case something
			// goes wrong. We can remove some day in the future.
			$wpdb->query("ALTER TABLE $wpcwdb->quiz_qs CHANGE parent_quiz_id deprecated_parent_quiz_id bigint(20) AFTER question_answers");
			$wpdb->query("ALTER TABLE $wpcwdb->quiz_qs CHANGE question_order deprecated_question_order int(11) AFTER question_answers");


		} // end if ($gotCol_parent_quiz_id && $gotCol_question_order)

	}

	// #### List of quiz custom feedback
	$SQL = "CREATE TABLE $wpcwdb->quiz_feedback (
			  	qfeedback_id int(11) unsigned NOT NULL AUTO_INCREMENT,
  				qfeedback_tag_id bigint(20) unsigned NOT NULL,
  				qfeedback_quiz_id int(1) unsigned NOT NULL,
  				qfeedback_summary varchar(300) NOT NULL,
  				qfeedback_score_type varchar(20) NOT NULL DEFAULT 'below',
  				qfeedback_score_grade int(11) unsigned NOT NULL DEFAULT '50',
  				qfeedback_message text NOT NULL,
  				PRIMARY KEY  (qfeedback_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->quiz_feedback, $SQL, $upgradeNow);

	// #### Queue used for notifying trainees that the next unit is available.
	$SQL = "CREATE TABLE $wpcwdb->queue_dripfeed (
			  	queue_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  				queue_unit_id int(11) unsigned NOT NULL,
  				queue_course_id int(11) unsigned NOT NULL,
  				queue_user_id int(11) NOT NULL,
  				queue_trigger_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  				queue_enqueued_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  				PRIMARY KEY  (queue_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
	WPCW_database_installTable($wpcwdb->queue_dripfeed, $SQL, $upgradeNow);

	// Update settings once upgrade has happened
	update_option(WPCW_DATABASE_KEY, WPCW_DATABASE_VERSION);
}

/**
 * Install or upgrade a table for this plugin.
 * @param String $tableName The name of the table to upgrade/install.
 * @param String $SQL The core SQL to create or upgrade the table
 * @param String $upgradeTables If true, we're upgrading to a new level of database tables.
 */
function WPCW_database_installTable($tableName, $SQL, $upgradeTables)
{
	global $wpdb;

	// Determine if the table exists or not.
	$tableExists = ($wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName);

	// Table doesn't exist or needs upgrading
	if (!$tableExists || $upgradeTables)
	{
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($SQL);
	}
}

/**
 * Function that upgrades quiz questions to use the new structure for storage
 * so that they can also contain images too.
 */
function  WPCW_database_upgrade_quizQuestions()
{
	global $wpdb, $wpcwdb;

	$listOfQuestions = $wpdb->get_results("
		SELECT *
		FROM $wpcwdb->quiz_qs
		WHERE question_type = 'multi'
		  AND question_data_answers = ''
	");

	if (!empty($listOfQuestions))
	{
		foreach ($listOfQuestions as $questionItem)
		{
			// Turn list into an array
			$answerListRaw = explode("\n", $questionItem->question_answers);

			// Base64 encode each item in case we have HTML to worry about.
			$dataToSave = array();

			// Check if list is empty. If it is, that's fine. We just save an empty array to the database.
			if (!empty($answerListRaw))
			{
				foreach ($answerListRaw as $idx => $data)
				{
					// Creates the following
					// [1] => array('answer' => 'data')
					// [2] => array('answer' => 'data')
					$dataToSave[$idx+1] = array('answer' => base64_encode($data));
				}
			}

			// Serialise the data
			$dataToSave = serialize($dataToSave);

			$wpdb->query($wpdb->prepare("
				UPDATE $wpcwdb->quiz_qs
				   SET question_data_answers = %s
				 WHERE question_id = %d
			", $dataToSave, $questionItem->question_id));
		}
	}
}

/**
 * Migrate the enrolement dates for the users on the system for courses.
 */
function WPCW_database_migration_enrolementDates()
{
	global $wpdb, $wpcwdb;

	// ### 1 - Find the blank enrolement dates that need updating. Use
	// group by, as some users will be enroled on multiple courses, and this
	// saves work by updating multiple at once.
	$findCoursesToUpdateSQL = "
		SELECT *
		FROM $wpcwdb->user_courses
		WHERE course_enrolment_date = '0000-00-00 00:00:00'
		GROUP BY user_id
		LIMIT 200
	";
	$rowsToUpdate = $wpdb->get_results($findCoursesToUpdateSQL);

	// ### 2 - Loop through all records that need an update. Using a few records at a
	// time to save memory.
	while (!empty($rowsToUpdate))
	{
		// ### 3 - Iterate through each user and their progress, and get their
		// started date from their user meta.
		foreach ($rowsToUpdate as $singleRow)
		{
			$dateUpdated = false;

			// ### 4 Try to get the meta data for this user. If the user has been deleted
			// but the course table still has a user, then $userData will return false.
			$userData = get_userdata($singleRow->user_id);
			if ($userData)
			{
				// Check date we've got is valid before we use it
	            $registered = $userData->user_registered;
	            if ($registered != '0000-00-00 00:00:00')
	            {
	            	$wpdb->query($wpdb->prepare("
	            		UPDATE $wpcwdb->user_courses
	            		SET course_enrolment_date = %s
	            		WHERE user_id = %d",
	            	$registered, $singleRow->user_id));

	            	$dateUpdated = true;
	            }
			}// end of userData

			// #### 5 - Just in case we have an invalid date from user_registered, we set today's
	        // date. As we don't want any infinite loops from the null date loop above.
			if (!$dateUpdated)
			{
				$wpdb->query($wpdb->prepare("
	            		UPDATE $wpcwdb->user_courses
	            		SET course_enrolment_date = %s
	            		WHERE user_id = %d",
	            	current_time('mysql'), $singleRow->user_id));
			}
		}

		// Continue the loop by looking for the next ones.
		$rowsToUpdate = $wpdb->get_results($findCoursesToUpdateSQL);
	}
}

/**
 * Migrate certificate data to each course
 */
function WPCW_database_migrate_certificateData() {
	global $wpdb, $wpcwdb;
	// ### 1 - Define SQL to lookup courses with certs enabled.
	$findCoursesToUpdateSQL = "SELECT * FROM $wpcwdb->courses";
	// ### 2 - Get Results from db.
	$rowsToUpdate = $wpdb->get_results( $findCoursesToUpdateSQL );
	// ### 3 - Get Settings for the certificate
	$wpcwSettings = TidySettings_getSettings( WPCW_DATABASE_SETTINGS_KEY );
	// ### 4 - Get the Settings Values
	$wpcwSettingsValues = array(
		'cert_signature_type'        => WPCW_arrays_getValue( $wpcwSettings, 'cert_signature_type' ),
		'cert_sig_text'              => WPCW_arrays_getValue( $wpcwSettings, 'cert_sig_text' ),
		'cert_sig_image_url'         => WPCW_arrays_getValue( $wpcwSettings, 'cert_sig_image_url' ),
		'cert_logo_enabled'          => WPCW_arrays_getValue( $wpcwSettings, 'cert_logo_enabled' ),
		'cert_logo_url'              => WPCW_arrays_getValue( $wpcwSettings, 'cert_logo_url' ),
		'cert_background_type'       => WPCW_arrays_getValue( $wpcwSettings, 'cert_background_type' ),
		'cert_background_custom_url' => WPCW_arrays_getValue( $wpcwSettings, 'cert_background_custom_url' )
	);
	// ### 5 - Go through each db row
	foreach ( $rowsToUpdate as $singleRow ) {
		// Check
		if ( ! isset( $singleRow->course_id ) ) {
			continue;
		}
		// ### 6 - Migrate Certificate Settings already set.
		$wpdb->query(
			$wpdb->prepare( "
		            UPDATE $wpcwdb->courses
		            SET cert_signature_type = %s, 
		                cert_sig_text = %s, 
		                cert_sig_image_url = %s, 
		                cert_logo_enabled = %s,
		                cert_logo_url = %s,
		                cert_background_type = %s,
		                cert_background_custom_url = %s
		            WHERE course_id = %d;",
				$wpcwSettingsValues[ 'cert_signature_type' ],
				$wpcwSettingsValues[ 'cert_sig_text' ],
				$wpcwSettingsValues[ 'cert_sig_image_url' ],
				$wpcwSettingsValues[ 'cert_logo_enabled' ],
				$wpcwSettingsValues[ 'cert_logo_url' ],
				$wpcwSettingsValues[ 'cert_background_type' ],
				$wpcwSettingsValues[ 'cert_background_custom_url' ],
				$singleRow->course_id
			)
		);
	}
}

?>