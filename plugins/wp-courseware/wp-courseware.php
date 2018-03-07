<?php
/**
 * Plugin Name: WP Courseware
 * Version: 4.0.2
 * Plugin URI: http://wpcourseware.com
 * Description: WP Courseware is WordPress's leading Learning Managment System (L.M.S.) plugin and is so simple you can create an online training course in minutes. It's as simple as drag and drop!
 * Author: Fly Plugins
 * Author URI: http://flyplugins.com
 *
 * Copyright 2012-2016 Fly Plugins - Lighthouse Media, LLC
 */

/** The current version of the database. */
define('WPCW_PLUGIN_VERSION', 			'4.0.2');		// Used for plugin updates
define('WPCW_DATABASE_VERSION', 		'4.000');	// Used for DB and scripts

/** The current version of the database. */
define('WPCW_DATABASE_KEY', 			'WPCW_Version');

/** The key used to store settings in the database. */
define('WPCW_DATABASE_SETTINGS_KEY', 	'WPCW_Settings');

/** The ID used for menus */
define('WPCW_PLUGIN_ID', 				'WPCW_wp_courseware');

/** The ID of the plugin for update purposes, must be the file path and file name. */
define('WPCW_PLUGIN_UPDATE_ID', 		'wp-courseware/wp-courseware.php');

/** The ID used for menus */
define('WPCW_MENU_POSITION', 			384289);

/** The ID used in units to select which template to use.  */
define('WPCW_TEMPLATE_META_ID', 		'_wpcw_template_to_use');

/** Identity details for licence authentication. */
define('EDD_SL_STORE_URL_WPCW', 		'http://flyplugins.com');
define('EDD_SL_ITEM_NAME_WPCW', 		'WP Courseware');

/** Load the licence and plugin updater class. */
if (!class_exists('EDD_SL_Plugin_Updater')) {
	include(dirname( __FILE__ ) . '/wplib/EDD_SL_Plugin_Updater.php');
}

/**
 * Are we the site admin? This is if we're not a multi-site, or we are a multi-site but in the network admin area.
 */
function WPCW_plugin_hasAdminRights() {
	return !is_multisite() || is_network_admin();
	//return true; // return true to disable MS capability.
}

// Admin Only
if (is_admin()) {
	// External Libs
	include_once 'wplib/utils_pagebuilder.inc.php';
	include_once 'wplib/utils_recordsform.inc.php';
	include_once 'wplib/utils_tablebuilder.inc.php';

	// Plugin-specific
	include_once 'lib/admin_only.inc.php';
	include_once 'lib/export_data.inc.php';
	include_once 'lib/class_courses_map.inc.php';

	// Data
	include_once 'lib/data_class_export.inc.php';
	include_once 'lib/data_class_import.inc.php';

	// Templates
	include_once 'lib/templates_backend.inc.php';
} else {
	// Frontend Only
	include_once 'lib/frontend_only.inc.php';

	// Templates
	include_once 'lib/templates_frontend.inc.php';

	// Shortcodes
	include_once 'lib/shortcodes.inc.php';
}

// PDF
include_once 'pdf/pdf_create_certificate.php';
include_once 'pdf/pdf_create_results.php';

// AJAX
include_once 'lib/ajax_admin.inc.php';
include_once 'lib/ajax_frontend.inc.php';

// Common
include_once 'lib/common.inc.php';
include_once 'lib/constants.inc.php';
include_once 'lib/email_defaults.inc.php';
include_once 'lib/class_user_progress.inc.php';

// Widgets
include_once 'lib/widget_progress.inc.php';

// Utils
include_once 'wplib/utils_sql.inc.php';
include_once 'wplib/utils_settings.inc.php';

// Dripfeed
include_once 'classes/class_queue_dripfeed.inc.php';

// Quizzes
include_once 'classes/class_quiz_base.inc.php';
include_once 'classes/class_quiz_multi.inc.php';
include_once 'classes/class_quiz_truefalse.inc.php';
include_once 'classes/class_quiz_open_entry.inc.php';
include_once 'classes/class_quiz_upload.inc.php';
include_once 'classes/class_quiz_random_selection.inc.php';

// Roles
require_once 'classes/class_wpcw_roles.php';

/**
 * Function that sets up the updater code to talk to the FlyPlugins servers
 * to allow you to update the plugin to the latest version.
 */
function WPCW_licence_edd_setupUpdater()
{
	// retrieve our license key from the DB
	$license_key = trim(TidySettings_getSettingSingle(WPCW_DATABASE_SETTINGS_KEY, 'licence_key'));

	// setup the updater
	$edd_updater = new EDD_SL_Plugin_Updater(EDD_SL_STORE_URL_WPCW, __FILE__,
		array(
			'version' 	=> WPCW_PLUGIN_VERSION, 			// Current version number
			'license' 	=> $license_key, 					// License key (used get_option above to retrieve from DB)
			'item_name' => EDD_SL_ITEM_NAME_WPCW, 			// Name of this plugin
			'author' 	=> 'Fly Plugins'  					// Author of this plugin
		)
	);

}
add_action('admin_init', 'WPCW_licence_edd_setupUpdater');

/**
 * Function that triggers activating the licence once the user has saved and updated their
 * licence key in the settings.
 */
function WPCW_licence_edd_activateLicence()
{
	// Retrieve license key
	$license_key = trim(TidySettings_getSettingSingle(WPCW_DATABASE_SETTINGS_KEY, 'licence_key'));

	// Retrieve activation status
	$license_activation = TidySettings_getSettingSingle(WPCW_DATABASE_SETTINGS_KEY, 'license_activation');

	// data to send in our API request
	$api_params = array(
		'edd_action'=> $license_activation,
		'license' 	=> $license_key,
		'item_name' => urlencode(EDD_SL_ITEM_NAME_WPCW), // the name of our product in EDD
		'url'       => home_url()
	);

	// Call the custom API.
	//$response = wp_remote_get(add_query_arg( $api_params, EDD_SL_STORE_URL_WPCW), array('timeout' => 15, 'sslverify' => false));
	$response = wp_remote_post( EDD_SL_STORE_URL_WPCW, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

	// decode the license data
	$license_data = json_decode(wp_remote_retrieve_body($response));

	return;
}

/**
 * Function that checks the licence for this user and plugin is correct, reporting an status messages
 * if something isn't quite right with their usage.
 */
function WPCW_licence_edd_checkLicence()
{
	// Get license key from database
	$license_key = trim(TidySettings_getSettingSingle(WPCW_DATABASE_SETTINGS_KEY, 'licence_key'));

	// data to send in our API request
	$api_params = array(
		'edd_action' 	=> 'check_license',
		'license' 		=> $license_key,
		'item_name' 	=> urlencode(EDD_SL_ITEM_NAME_WPCW),
		'url'       	=> home_url()
	);

	// Call the custom API.
	//$response = wp_remote_get( add_query_arg($api_params, EDD_SL_STORE_URL_WPCW ), array('timeout' => 15, 'sslverify' => false) );
	$response = wp_remote_post( EDD_SL_STORE_URL_WPCW, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

	if (is_wp_error($response))
		return false;

	$license_data = json_decode( wp_remote_retrieve_body($response));

	// Determine message to give based on status of license
	if ($license_data->activations_left == '0' && $license_data->license == 'site_inactive')
	{
		$license_status = __('You have exceeded your license limit. Please login to the member portal to <a href="http://flyplugins.com/member-portal">upgrade your license</a> or deactivate one of your current licenses.', 'wp_courseware');
	}
	else
	{
		switch ($license_data->license)
		{
		  case 'invalid':
		  	$license_status = __('<a href="admin.php?page=WPCW_showPage_Settings">Register</a> your copy of WP Courseware by entering your licence key and activating it. Need a licence? <a href="http://wpcourseware.com">Purchase one now.</a>', 'wp_courseware');
		    break;

		  case 'expired':
		    $license_status = __('Your licence key has expired. Please <a href="http://wpcourseware.com">renew your licence.</a>', 'wp_courseware');
		    break;

		  case 'disabled':
		    $license_status = __('Your licence key is disabled.', 'wp_courseware');
		    break;

		  case 'site_inactive';
		  	$license_status = __('Your licence has not been activated on this site.', 'wp_courseware');
		  	break;

		  default:
		    return;
		} // end switch
	}

	// Print custom message
	echo '</tr><tr class="plugin-update-tr"><td colspan="5" class="plugin-update"><div class="update-message">' . $license_status . '</div></td>';
}
add_action('after_plugin_row_'.WPCW_PLUGIN_UPDATE_ID, 'WPCW_licence_edd_checkLicence', 20);

/**
 * Initialisation functions for plugin.
 */
function WPCW_plugin_init()
{
	// Load translation support
	$domain = 'wp_courseware'; // This is the translation locale.

	// Check the WordPress language directory for /wp-content/languages/wp_courseware/wp_courseware-en_US.mo first
	$locale = apply_filters('plugin_locale', get_locale(), $domain);
	load_textdomain($domain, WP_LANG_DIR.'/wp_courseware/'.$domain.'-'. $locale.'.mo');

	// Then load the plugin version
	load_plugin_textdomain($domain, FALSE, dirname(plugin_basename(__FILE__)).'/language/');

	// Run setup
	WPCW_plugin_setup(false);

	// ### Admin
	if (is_admin())
	{
		// Menus
		add_action('admin_menu', 								'WPCW_menu_MainMenu');
		add_action('admin_head', 								'WPCW_menu_MainMenu_cleanUnwantedEntries');

		// Network Only
		//add_action('network_admin_menu', 						'WPCW_menu_MainMenu_NetworkOnly');

		// Scripts and styles
		add_action('admin_print_scripts', 						'WPCW_addCustomScripts_BackEnd');
		add_action('admin_print_styles',  						'WPCW_addCustomCSS_BackEnd');

		// See if export has been requested
		WPCW_Export::tryExportCourse();

		// Post Related
		add_action( 'save_post', 								'WPCW_units_saveUnitPostMetaData', 10, 2);
		add_action( 'restrict_manage_posts', 					'WPCW_course_unit_course_filter' );
		add_filter( 'parse_query',								'WPCW_course_unit_course_filter_query' );
		add_filter( 'pre_get_posts',                            'WPCW_course_unit_permissions_filter_query' );
		add_filter( 'current_screen',                           'WPCW_course_unit_permissions_change_counts' );

		// User Related
		add_action('manage_users_columns',						'WPCW_users_manageColumns');
		add_action('manage_users_custom_column',				'WPCW_users_addCustomColumnContent',20,3);
		//add_action('pre_user_query',                            'WPCW_users_permissionUsersFilter',20,1);


		// Unit Related
		add_filter('manage_course_unit_posts_columns', 			'WPCW_units_manageColumns', 10);
		add_action('manage_course_unit_posts_custom_column', 	'WPCW_units_addCustomColumnContent', 10, 2);

		// Unit Deletion
		add_action('delete_post', 								'WPCW_units_deleteUnitHandler');
		add_action('trashed_post', 								'WPCW_units_deleteUnitHandler_inTrash');


		// Meta boxes
		add_action('add_meta_boxes', 							'WPCW_units_showEditScreenMetaBoxes');

		// AJAX - Admin
		add_action('wp_ajax_wpcw_handle_unit_ordering_saving', 		'WPCW_AJAX_handleUnitOrderingSaving');
		add_action('wp_ajax_wpcw_handle_unit_duplication', 			'WPCW_AJAX_handleUnitDuplication');
		add_action('wp_ajax_wpcw_handle_question_new_tag', 			'WPCW_AJAX_handleQuestionNewTag');
		add_action('wp_ajax_wpcw_handle_question_remove_tag', 		'WPCW_AJAX_handleQuestionRemoveTag');
		add_action('wp_ajax_wpcw_handle_tb_action_question_pool', 	'WPCW_AJAX_handleThickboxAction_QuestionPool');
		add_action('wp_ajax_wpcw_handle_tb_action_add_question', 	'WPCW_AJAX_handleThickboxAction_QuestionPool_addQuestion');


		// AJAX - Frontend (yeah, WP requires they go here)
		add_action('wp_ajax_wpcw_handle_unit_track_progress', 			'WPCW_AJAX_units_handleUserProgress');
		add_action('wp_ajax_wpcw_handle_unit_quiz_response', 			'WPCW_AJAX_units_handleQuizResponse');
		add_action('wp_ajax_wpcw_handle_unit_quiz_retake_request', 		'WPCW_AJAX_units_handleQuizRetakeRequest');
		add_action('wp_ajax_wpcw_handle_unit_quiz_jump_question', 		'WPCW_AJAX_units_handleQuizJumpQuestion');
		add_action('wp_ajax_wpcw_handle_unit_quiz_timer_begin', 		'WPCW_AJAX_units_handleQuizTimerBegin');
		add_action('wp_ajax_wpcw_handle_course_enrollment_button', 		'WPCW_AJAX_course_handleEnrollment_button');


		// Notices about permalinks
		add_action('admin_notices', 							'WPCW_plugin_permalinkCheck');

		// Notice about MS - Now disabled.
		//add_action('admin_notices', 							'WPCW_plugin_multisiteCheck');

		// CSV Export
		add_action('wp_loaded', 								'WPCW_data_handleDataExport');

		// User - Global reset functionality
		add_action('load-users.php', 							'WPCW_users_processUserResetAbility');
		add_action('admin_head', 								'WPCW_users_processUserResetAbility_showSuccess');
		add_action('restrict_manage_users', 					'WPCW_users_showUserResetAbility');

	}

	// ### Frontend
	else
	{
		// Scripts and styles
		WPCW_addCustomScripts_FrontEnd();

		// Shortcodes
		add_shortcode('wpcourse', 			'WPCW_shortcodes_showTrainingCourse');
		add_shortcode('wpcourse_progress', 	'WPCW_shortcodes_showTrainingCourseProgress');
		add_shortcode('wpcourse_enroll', 	'wpcw_create_enrollment_button');

		// Post Content
		add_filter('the_content', 			'WPCW_units_processUnitContent');

		// Templates - Course Units
		add_filter('single_template', 		'WPCW_templates_units_filterTemplateForUnit');
	}

	// Action when admin has updated the course details.
	add_action('wpcw_course_details_updated', 				'WPCW_actions_courses_courseDetailsUpdated');

	// Action when user has completed a unit/module/course
	add_action('wpcw_user_completed_unit',					'WPCW_actions_users_unitCompleted', 10, 3);
	add_action('wpcw_user_completed_module',				'WPCW_actions_users_moduleCompleted', 10, 3);
	add_action('wpcw_user_completed_course',				'WPCW_actions_users_courseCompleted', 10, 3);

	// Modified modules - when a module is created or edited
	add_action('wpcw_modules_modified',						'WPCW_actions_modules_modulesModified');

	// Action called when user has been created, and we check to see if that user should be added to
	// any of the defined courses.
	add_action('user_register', 							'WPCW_actions_users_newUserCreated');
	add_action( 'register_form',							'WPCW_course_enrollment_via_shortcode');

	// Action called when user has been deleted
	add_action('delete_user', 								'WPCW_actions_users_userDeleted');

	// Action called when quiz has been completed and needs grading or needs attention as user is blocked.
	add_action('wpcw_quiz_needs_grading',					'WPCW_actions_userQuizNeedsGrading_notifyAdmin', 10, 2);
	add_action('wpcw_quiz_user_needs_unblocking',			'WPCW_actions_userQuizUserNeedsUnblocking_notifyAdmin', 10, 2);

	// Action called when quiz has been graded or needs attention
	add_action('wpcw_quiz_graded',							'WPCW_actions_userQuizGraded_notifyUser', 10, 4);

	// Add handler for cron for sending out notifications
	add_action(WPCW_WPCRON_NOTIFICATIONS_DRIPFEED_ID, 		'WPCW_cron_notifications_dripfeed');

	// PDF Generation
	add_action('wp',                                        'WPCW_create_pdf_certificates');
	add_action('wp',                                        'WPCW_create_pdf_results');

	// Common
	WPCW_plugin_registerCustomPostTypes();

	/**
	 * Check if unit comments are enabled from Settings page, if so enable them.
	 */
	$unit_comments_setting = TidySettings_getSettingSingle('WPCW_Settings', 'enable_unit_comments', false);

	if ($unit_comments_setting == 'enable_comments') {
		add_post_type_support( 'course_unit', 'comments' );
	} else {
		remove_post_type_support ( 'course_unit', 'comments' );
	}

	// Create correct URL for unit
	add_filter('post_type_link', 'WPCW_units_createCorrectUnitURL', 1, 3);


	// Create permalink for course units
	global $wp_rewrite;
	$unit_structure = '/%module_number%/%course_unit%/';

	// Handle module and course unit tags
	$wp_rewrite->add_rewrite_tag("%module_number%", '(module-[^/]+)', "module_number=");
	$wp_rewrite->add_rewrite_tag("%course_unit%", '([^/]+)', "course_unit=");

	// Make it happen to format links automatically for course units.
	$wp_rewrite->add_permastruct('course_unit', $unit_structure, false);

	// Ensure the URLs are flushed for the first time
	$flushRules = get_option('wpcw_flush_rules');
	if (!$flushRules) {
		update_option('wpcw_flush_rules', 'done');
		$wp_rewrite->flush_rules();
	}

	// Now load any extensions
	do_action('wpcw_extensions_load');
}
add_action('init', 'WPCW_plugin_init');
add_action('widgets_init', create_function('', 'register_widget("WPCW_CourseProgress");'));

/**
 * Install the plugin, initialise the default settings, and create the tables for the websites and groups.
 */
function WPCW_plugin_setup($force)
{
	// Include database upgrade routines for upgrade.
	include_once 'lib/database.inc.php';

	$installed_ver  = get_option(WPCW_DATABASE_KEY) + 0;
	$current_ver    = WPCW_DATABASE_VERSION + 0;

	// Performing an upgrade
	if ($current_ver != $installed_ver || $force)
	{
		global $wpdb, $wpcwdb;
		$wpcwdb = new WPCW_Database();

		// If settings don't already exist, create new settings based on defaults
		// only when plugin activates.
		$existingSettings = TidySettings_getSettings(WPCW_DATABASE_SETTINGS_KEY);

		// The default settings that should exist on initialisation.
		$defaultSettings = array(

			// General settings
			'show_powered_by'		=> 'show_link',
			'course_enrollment_method'		=> 'sync',
			'use_default_css' 		=> 'show_css',
			'license_activation'    => 'activate_license',
			'cron_time_dripfeed'	=> 'twicedaily',
			'enable_unit_comments'  =>  'disable_comments',

			// Certificates
			'cert_background_type'	=> 'use_default',
			'cert_logo_enabled'		=> 'no_cert_logo',
			'cert_signature_type'	=> 'text',
			'cert_sig_text'			=> get_bloginfo('name'), // Site name for instructor
		);

		// No settings at all, so save all settings direct to the database.
		if (!$existingSettings) {
			TidySettings_saveSettings($defaultSettings, WPCW_DATABASE_SETTINGS_KEY);
		}

		// We have some settings. Ensure we have settings for all of them.
		else
		{
			// Check all settings
			foreach ($defaultSettings as $key => $value)
			{
				if (!isset($existingSettings[$key])) {
					$existingSettings[$key] = $value;
				}
			}

			// Save modified existing settings back to the settings
			TidySettings_saveSettings($existingSettings, WPCW_DATABASE_SETTINGS_KEY);
		}

		// Remove the flag for flushing rules
		delete_option('wpcw_flush_rules');

		// Upgrade database tables if version change.
		WPCW_database_upgradeTables($installed_ver, $force);

		// Create upload directory
		WPCW_files_createFileUploadDirectory_base();
	}
}

/**
 * Creates the correct URL for course units, showing module and course names.
 *
 * @param String $post_link The current permalinkf for the unit (which includes %module_number%).
 * @param Object $post The object of the post for which a URL is requested.
 */
function WPCW_units_createCorrectUnitURL($post_link, $post = 0, $leavename = FALSE)
{
	// Only filter if found module number
	if (strpos('%module_number%', $post_link) === 'FALSE') {
		return $post_link;
	}

	// Ensure we have access to the post object
	if (is_object($post)) {
		$post_id = $post->ID;
	} else {
		$post_id = $post;
		$post = get_post($post_id);
	}

	// Check that we've got access to the right course unit post type
	if (!is_object($post) || $post->post_type != 'course_unit') {
		return $post_link;
	}

	// Work out the module number for the unit.
	$moduleID = get_post_meta($post->ID, 'wpcw_associated_module', true) + 0;

	// V2.2 Fix - Using module NUMBER not module ID for the URL.
	$moduleDetails = WPCW_modules_getModuleDetails($moduleID);

	// Not found the right module, so remove the prefix.
	if (!$moduleDetails) {
		return str_replace('%module_number%', 'module-unassigned', $post_link);
	}

	// Put new slug in place of %module_number%
	return str_replace('%module_number%', 'module-'.$moduleDetails->module_number, $post_link);
}

/**
 * Create the main menu.
 */
function WPCW_menu_MainMenu()
{
	global $WPCW_course_dashboard_page;

	// Menu Icon
	$wpcw_menu_icon = WPCW_plugin_getPluginPath() . 'img/icon_training_16.png';

	// Since V2.8 - Allow access level to be changed via filter for pages.
	$fa_training_courses  = apply_filters( 'wpcw_back_menus_access_training_courses', 'view_wpcw_courses' );
	$fa_gradebook         = apply_filters( 'wpcw_back_menus_access_gradebook', 'view_wpcw_courses' );
	$fa_user_progress     = apply_filters( 'wpcw_back_menus_access_user_progress', 'view_wpcw_courses' );
	$fa_user_quiz_results = apply_filters( 'wpcw_back_menus_access_user_quiz_results', 'view_wpcw_courses' );

	// ### Main Menu Page
	$WPCW_course_dashboard_page = add_menu_page(
		__( 'WP Courseware', 'wp_courseware' ),
		__( 'Training Courses', 'wp_courseware' ),
		$fa_training_courses,
		WPCW_PLUGIN_ID,
		'WPCW_showPage_Dashboard',
		$wpcw_menu_icon,
		WPCW_MENU_POSITION
	);

	// ### Course Add/Modify
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - New Course', 'wp_courseware' ),
		__( 'Add Course', 'wp_courseware' ),
		'view_wpcw_courses',
		'WPCW_showPage_ModifyCourse',
		'WPCW_showPage_ModifyCourse'
	);

	// ### GradeBook for Course
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - Gradebook', 'wp_courseware' ),
		__( 'Gradebook', 'wp_courseware' ),
		$fa_gradebook,
		'WPCW_showPage_GradeBook',
		'WPCW_showPage_GradeBook'
	);

	// ### Module Add/Modify
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - Modify Module', 'wp_courseware' ),
		__( 'Add Module', 'wp_courseware' ),
		'view_wpcw_courses',
		'WPCW_showPage_ModifyModule',
		'WPCW_showPage_ModifyModule'
	);

	// Spacer
	add_submenu_page(
		WPCW_PLUGIN_ID,
		false,
		'<span class="wpcw_menu_section" style="display: block; margin: 1px 0 1px -5px; padding: 0; height: 1px; line-height: 1px; background: #CCC;"></span>',
		'view_wpcw_courses',
		'#',
		false
	);

	// ### Quiz Add/Modify
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - Modify Quiz', 'wp_courseware' ),
		__( 'Add Quiz/Survey', 'wp_courseware' ),
		'view_wpcw_courses',
		'WPCW_showPage_ModifyQuiz',
		'WPCW_showPage_ModifyQuiz'
	);

	// ### Quiz Summary
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - Quiz Summary', 'wp_courseware' ),
		__( 'Quiz Summary', 'wp_courseware' ),
		'view_wpcw_courses',
		'WPCW_showPage_QuizSummary',
		'WPCW_showPage_QuizSummary'
	);

	// ### Question Pool
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - Question Pool', 'wp_courseware' ),
		__( 'Question Pool', 'wp_courseware' ),
		'view_wpcw_courses',
		'WPCW_showPage_QuestionPool',
		'WPCW_showPage_QuestionPool'
	);

	// ### Add Question
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - Add Question', 'wp_courseware' ),
		__( 'Add Question', 'wp_courseware' ),
		'view_wpcw_courses',
		'WPCW_showPage_ModifyQuestion',
		'WPCW_showPage_ModifyQuestion'
	);

	// ### Module and Unit Ordering
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - Module &amp; Unit Ordering', 'wp_courseware' ),
		__( 'Module &amp; Unit Ordering', 'wp_courseware' ),
		'view_wpcw_courses', 'WPCW_showPage_CourseOrdering',
		'WPCW_showPage_CourseOrdering'
	);

	// Spacer
	add_submenu_page(
		WPCW_PLUGIN_ID,
		false,
		'<span class="wpcw_menu_section" style="display: block; margin: 1px 0 1px -5px; padding: 0; height: 1px; line-height: 1px; background: #CCC;"></span>',
		'view_wpcw_courses',
		'#',
		false
	);

	// ### Handle menu items for extensions
	$extensionList = array();
	$extensionList = apply_filters( 'wpcw_extensions_menu_items', $extensionList );
	if ( count( $extensionList ) > 0 ) {
		foreach ( $extensionList as $extensionItem ) {
			add_submenu_page(
				WPCW_PLUGIN_ID,
				__( 'WP Courseware - ', 'wp_courseware' ) . $extensionItem[ 'page_title' ],
				$extensionItem[ 'menu_label' ],
				'view_wpcw_courses',
				$extensionItem[ 'id' ],
				$extensionItem[ 'menu_function' ]
			);
		}

		add_submenu_page(
			WPCW_PLUGIN_ID,
			false,
			'<span class="wpcw_menu_section" style="display: block; margin: 1px 0 1px -5px; padding: 0; height: 1px; line-height: 1px; background: #CCC;"></span>',
			'view_wpcw_courses',
			'#',
			false
		);
	}

	// #### Import/export course stuff.
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - Import/Export Course', 'wp_courseware' ),
		__( 'Import/Export', 'wp_courseware' ),
		'view_wpcw_courses',
		'WPCW_showPage_ImportExport',
		'WPCW_showPage_ImportExport'
	);

	// Spacer
	add_submenu_page(
		WPCW_PLUGIN_ID,
		false,
		'<span class="wpcw_menu_section" style="display: block; margin: 1px 0 1px -5px; padding: 0; height: 1px; line-height: 1px; background: #CCC;"></span>',
		'view_wpcw_courses',
		'#',
		false
	);

	// #### Convert post/page to a course unit
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - Convert Page/Post to Course Unit', 'wp_courseware' ),
		__( 'Convert Page/Post', 'wp_courseware' ),
		'view_wpcw_courses',
		'WPCW_showPage_ConvertPage',
		'WPCW_showPage_ConvertPage'
	);

	// ### Settings
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - Settings', 'wp_courseware' ),
		__( 'Settings', 'wp_courseware' ),
		'manage_wpcw_settings',
		'WPCW_showPage_Settings',
		'WPCW_showPage_Settings'
	);

	// ### Certificate Settings - Menu removed in 4.0
	//add_submenu_page(
	//	WPCW_PLUGIN_ID,
	//	__( 'WP Courseware - Certificates', 'wp_courseware' ),
	//	__( 'Certificates', 'wp_courseware' ),
	//	'manage_wpcw_settings',
	//	'WPCW_showPage_Certificates',
	//	'WPCW_showPage_Certificates'
	//);

	// #### Documentation Page
	add_submenu_page(
		WPCW_PLUGIN_ID,
		__( 'WP Courseware - Documentation', 'wp_courseware' ),
		__( 'Documentation', 'wp_courseware' ),
		'view_wpcw_courses',
		'WPCW_showPage_Documentation',
		'WPCW_showPage_Documentation'
	);

	// ### Create page to allow admin to change package for users.
	add_users_page(
		__( 'WP Courseware - Update User Course Access Permissions', 'wp_courseware' ),
		__( 'Update Course Access', 'wp_courseware' ),
		'view_wpcw_courses',
		'WPCW_showPage_UserCourseAccess',
		'WPCW_showPage_UserCourseAccess'
	);

	// ### Detailed user progress
	add_users_page(
		__( 'WP Courseware - View User Progress', 'wp_courseware' ),
		__( 'View User Progress', 'wp_courseware' ),
		$fa_user_progress,
		'WPCW_showPage_UserProgess',
		'WPCW_showPage_UserProgess'
	);

	// ### Detailed quiz progress
	add_users_page(
		__( 'WP Courseware - View Quiz/Survey Results', 'wp_courseware' ),
		__( 'View Quiz Results', 'wp_courseware' ),
		$fa_user_quiz_results,
		'WPCW_showPage_UserProgess_quizAnswers',
		'WPCW_showPage_UserProgess_quizAnswers'
	);

	//Add screen options for Course Dashboard Page
	add_action( "load-$WPCW_course_dashboard_page", "WPCW_course_dashboard_screen_options" );
}

/**
 * Create the main menu.
 */
function WPCW_menu_MainMenu_NetworkOnly()
{
	// Menu Icon
	$wpcw_menu_icon = WPCW_plugin_getPluginPath() . 'img/icon_training_16.png';
	add_menu_page(
		__( 'WP Courseware', 'wp_courseware' ),
		__( 'WP Courseware', 'wp_courseware' ),
		'manage_options',
		WPCW_PLUGIN_ID,
		'WPCW_showPage_Settings_Network',
		$wpcw_menu_icon, WPCW_MENU_POSITION
	);
}

/**
 * Create screen optionsn for the course dashboard page.
 */
function WPCW_course_dashboard_screen_options(){

	$user_id = get_current_user_id();

	// Check for form submission
	if(isset($_POST['course_dashboard_screen_options_submit']) AND $_POST['course_dashboard_screen_options_submit'] == 1)
	{
		if(isset($_POST['quiz_notification_hide']) && $_POST['quiz_notification_hide'] = 'show')
		{
			update_user_meta( $user_id, 'wpcw_course_dashboard_quiz_notification_hide', 'show', 'hide');
		}else
		{
			update_user_meta( $user_id, 'wpcw_course_dashboard_quiz_notification_hide', 'hide', 'show');
		}
		if(isset($_POST['wpcw_courses_per_page']) && $_POST['wpcw_courses_per_page'] > 0)
		{
			update_user_meta( $user_id, 'wpcw_courses_per_page', $_POST['wpcw_courses_per_page'] , '');
		}
	}

	//Initiate the $screen variable.
	$screen = get_current_screen();

	//Add our custom HTML to the screen options panel.
	add_filter('screen_layout_columns', function( $html ) {

		$user_id = get_current_user_id();
		$html = sprintf('<div id="screen-options-wrap">
			<form name="course_dashboard_screen_options_form" method="post">
				<input type="hidden" name="course_dashboard_screen_options_submit" value="1">
				<legend>WP Courseware - Course Dashboard Options</legend>
					<fieldset >
						<label>Enable/Disable - Quiz Notifications</label><input class="hide-column-tog" name="quiz_notification_hide" type="checkbox" id="quiz_notification_hide" value="%s" %s>
					</fieldset>
					<fieldset >
						<label for="wpcw_courses_per_page">Number of courses per page:</label><input type="number" step="1" min="1" max="50" class="screen-per-page" name="wpcw_courses_per_page" id="wpcw_courses_per_page" maxlength="3" value="%d">
					</fieldset>
						<p class="submit"><input type="submit" name="screen-options-apply" id="screen-options-apply" class="button button-primary" value="Apply"></p>
			</form>
			</div>',
			$quiz_notify = (get_user_meta($user_id, 'wpcw_course_dashboard_quiz_notification_hide', true) != false) ? get_user_meta($user_id, 'wpcw_course_dashboard_quiz_notification_hide', true) : 'hide',
			$quiz_notify_check = (get_user_meta($user_id, 'wpcw_course_dashboard_quiz_notification_hide', true) != 'show') ? '' : 'checked',
			$course_per_page = (get_user_meta($user_id, 'wpcw_courses_per_page', true) != false) ? get_user_meta($user_id, 'wpcw_courses_per_page', true) : 20
		);

		echo  $html ;
	});
	//Register our new screen options tab.
	$screen->add_option('my_option', '');
}

/**
 * Add the styles needed for the page for this plugin.
 */
function WPCW_addCustomCSS_BackEnd()
{
	// Global scripts object used to get current jQuery version.
	global $wp_scripts;

	// Get screen object to allow for conditional loading of css
	$screen = get_current_screen();
	$needDatePicker = false;

	switch ($screen->id)
	{
		// User admin summary
		case 'users':
			wp_enqueue_style('wpcw-backend-users', 	WPCW_plugin_getPluginPath() . 'css/wpcw_backend_users.css', false, WPCW_DATABASE_VERSION);
			break;

		// Course Units - specific style, also needs date picker.
		case 'course_unit':
				$needDatePicker = true;

				// CSS for the course unit
				wp_enqueue_style('wpcw-backend-units', 	WPCW_plugin_getPluginPath() . 'css/wpcw_backend_units.css', array('jquery-ui-css'), WPCW_DATABASE_VERSION);
			break;

		// Page for controlling course access for users
		case 'users_page_WPCW_showPage_UserCourseAccess':
				$needDatePicker = true;
			break;
	}

	// Need jQuery UI for date picker on this screen.
	if ($needDatePicker)
	{
		wp_enqueue_style("jquery-ui-css", 			"//ajax.googleapis.com/ajax/libs/jqueryui/".(string)$wp_scripts->registered['jquery-ui-core']->ver."/themes/smoothness/jquery-ui.min.css");
		wp_enqueue_style("wpcw-ext-datetimepicker", 	WPCW_plugin_getPluginPath() . 'ext/datetimepicker/jquery-ui-timepicker-addon.min.css', array('jquery-ui-css'), WPCW_DATABASE_VERSION);
	}

	if (!WPCW_areWeOnPluginPage())
	return;

	// Standard styles
	wp_enqueue_style('thickbox');

	// jQuery UI - needed within the UI for WPCW.
	wp_enqueue_style("jquery-ui-css", 		"//ajax.googleapis.com/ajax/libs/jqueryui/".(string)$wp_scripts->registered['jquery-ui-core']->ver."/themes/smoothness/jquery-ui.min.css");


	// Our plugin-specific CSS
	wp_enqueue_style('wpcw-backend', 		WPCW_plugin_getPluginPath() . 'css/wpcw_backend.css', array('jquery-ui-css'), WPCW_DATABASE_VERSION);
}

/**
 * Add the scripts needed for the page for this plugin.
 */
function WPCW_addCustomScripts_BackEnd()
{
	// Get screen object to allow for conditional loading of scripts
	$screen = get_current_screen();
	$needDatePicker = false;

	switch ($screen->id)
	{
		// User admin summary
		case 'users':
				wp_enqueue_script('wpcw-backend-users', WPCW_plugin_getPluginPath() . 'js/wpcw_backend_users.js', array('jquery'), WPCW_DATABASE_VERSION);

				// Variable declarations for admin pages
				wp_localize_script(
							'wpcw-backend-users', 		// What we're attaching too
							'wpcw_js_consts_usr',		// Handle for this code
				array(
						'confirm_bulk_change' 			=> __('Are you sure you wish to reset the progress of the selected users? This CANNOT be undone.', 'wp_courseware'),
						'confirm_single_change' 		=> __('Are you sure you wish to reset the progress of this user? This CANNOT be undone.', 'wp_courseware'),
						'status_copying' 				=> __('Copying...', 'wp_courseware'),
				));
			break;

		// Course Units - Edit page
		case 'edit-course_unit':
				wp_enqueue_script('wpcw-backend-units', WPCW_plugin_getPluginPath() . 'js/wpcw_backend_units.js', array('jquery'), WPCW_DATABASE_VERSION);

				wp_localize_script(
							'wpcw-backend-units', 		// What we're attaching too
							'wpcw_js_consts_units',		// Handle for this code
				array(
						'status_copying' 				=> __('Copying...', 'wp_courseware'),
				));
			break;

		// Course Units - Date Picker and Unit-specific Date Picker
		case 'course_unit':
				$needDatePicker = true;
				wp_enqueue_script('wpcw-backend-units', WPCW_plugin_getPluginPath() . 'js/wpcw_backend_units.js', array('jquery'), WPCW_DATABASE_VERSION);

				wp_localize_script(
							'wpcw-backend-units', 		// What we're attaching too
							'wpcw_js_consts_units',		// Handle for this code
				array(
						'status_copying' 				=> __('Copying...', 'wp_courseware'),
				));
			break;


		// Page for controlling course access for users
		case 'users_page_WPCW_showPage_UserCourseAccess':
				$needDatePicker = true;
			break;
	}




	// Need jQuery UI for date picker on this screen.
	if ($needDatePicker)
	{
		global $wp_locale;

		// Localization for the date picker.
		$aryArgs = array(
	        'closeText'         => __('Done', 						'wp_courseware'),
	        'currentText'       => __('Today', 						'wp_courseware'),
			'monthStatus'       => __( 'Show a different month', 	'wp_courseware'),

			'timeText'			=> __('Time', 						'wp_courseware'),
			'hourText'			=> __('Hour', 						'wp_courseware'),
			'minuteText'		=> __('Minute', 					'wp_courseware'),
			'secondText'		=> __('Second', 					'wp_courseware'),

	        'monthNames'        => WPCW_arrays_stripStringIndices($wp_locale->month),
	        'monthNamesShort'   => WPCW_arrays_stripStringIndices($wp_locale->month_abbrev),
	        'dayNames'          => WPCW_arrays_stripStringIndices($wp_locale->weekday ),
	        'dayNamesShort'     => WPCW_arrays_stripStringIndices($wp_locale->weekday_abbrev),
	        'dayNamesMin'       => WPCW_arrays_stripStringIndices($wp_locale->weekday_initial),

	        // Get the start of week from WP general setting
	        'firstDay'          => get_option('start_of_week'),

	        // Is Right to left language? default is false
	        'text_direction'    => $wp_locale->text_direction,
	    );

	    // Load resources needed for the translated date picker. Includes library for showing time date picker too.
	    wp_enqueue_script('jquery-ui-datepicker');
	    wp_enqueue_script('jquery-ui-slider');

	    wp_enqueue_script('wpcw-ext-datetimepicker', WPCW_plugin_getPluginPath() . 'ext/datetimepicker/jquery-ui-timepicker-addon.min.js', array('jquery'), WPCW_DATABASE_VERSION);
	    wp_enqueue_script('wpcw-backend-datepicker', WPCW_plugin_getPluginPath() . 'js/wpcw_backend_datepicker.js', array('jquery', 'jquery-ui-datepicker', 'wpcw-ext-datetimepicker'), WPCW_DATABASE_VERSION);


	    // Pass the array to the enqueued JS
    	wp_localize_script('wpcw-backend-datepicker', 'wpcw_dateL10n', $aryArgs );
	}

	// Load script that binds query arg to core-generated button for dismissible notice
	wp_enqueue_script( 'dismiss-notice', WPCW_plugin_getPluginPath() . 'js/wpcw_dismiss_notice.js', array( 'jquery' ) );

	if (!WPCW_areWeOnPluginPage()) {
		return;
	}

	wp_enqueue_media();

	// Our plugin-specific JS
	wp_enqueue_script('wpcw-backend', WPCW_plugin_getPluginPath() . 'js/wpcw_backend.js', array('jquery', 'media-upload', 'thickbox', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse', 'jquery-ui-sortable', 'jquery-ui-spinner'), WPCW_DATABASE_VERSION);

	// Variable declarations
	wp_localize_script(
				'wpcw-backend', 			// What we're attaching too
				'wpcw_js_consts_adm',		// Handle for this code
	array(
			'order_nonce' 					=> wp_create_nonce('wpcw-order-nonce'), 	// Nonce security token
			'confirm_whole_course_reset' 	=> __('Are you sure you wish to reset the progress of all users on this course? This CANNOT be undone.', 'wp_courseware'),
			'confirm_access_change_users' 	=> __('Are you sure you wish to add access for this course for all users?', 'wp_courseware'),
			'confirm_access_change_admins' 	=> __('Are you sure you wish to add access for this course for all admins?', 'wp_courseware'),
			'msg_question_duplicate' 		=> __('That question already exists in this quiz, so cannot be added again.', 'wp_courseware'),
			'name_tag_whole_pool' 			=> __('Entire Question Pool', 'wp_courseware'),
	));
}

/**
 * WordPress stores names arrays (month and weekday and all variants) with text value indices, so this
 * creates an array with numerical indices instead.
 *
 * @param Array $arrayToClean The array to strip out the text indices.
 * return Array The array with numerical indicies.
 */
function WPCW_arrays_stripStringIndices($arrayToClean)
{
	$newArray = array();
	foreach ($arrayToClean as $objArrayItem)
	{
		$newArray[] = $objArrayItem;
	}
	return $newArray;
}

/**
 * Add the scripts we want loaded in the header.
 */
function WPCW_addCustomScripts_FrontEnd()
{
	if (is_admin()) {
		return;
	}

	// Our plugin-specific scripts

	// Don't use CSS for frontend if setting says so.
	if (TidySettings_getSettingSingle(WPCW_DATABASE_SETTINGS_KEY, 'use_default_css') != 'hide_css') {
		wp_enqueue_style('wpcw-frontend', 			WPCW_plugin_getPluginPath() . 'css/wpcw_frontend.css', false, WPCW_DATABASE_VERSION);
	}

	// Countdown Timer
	wp_enqueue_script('wpcw-countdown-plugin', 		WPCW_plugin_getPluginPath() . 'ext/countdown/jquery.plugin.min.js', array('jquery'), WPCW_DATABASE_VERSION);
	wp_enqueue_script('wpcw-countdown', 			WPCW_plugin_getPluginPath() . 'ext/countdown/jquery.countdown.min.js', array('jquery', 'wpcw-countdown-plugin'), WPCW_DATABASE_VERSION);

	// AJAX Form Script for quizzes
	wp_enqueue_script('wpcw-jquery-form', 		WPCW_plugin_getPluginPath() . 'js/jquery.form.js', array('jquery'), WPCW_DATABASE_VERSION);

	// Plugin-specific JS
	wp_enqueue_script('wpcw-frontend', 			WPCW_plugin_getPluginPath() . 'js/wpcw_front.js', array('jquery', 'wpcw-jquery-form', 'wpcw-countdown'), WPCW_DATABASE_VERSION);


	// Variable declarations
	wp_localize_script(
				'wpcw-frontend', 	// What we're attaching too
				'wpcw_js_consts_fe',		// Handle for this code
	array(
			'ajaxurl' 				=> admin_url('admin-ajax.php','WPCW_plugin_getPluginPath()'),				// URL for admin AJAX
			'enrollment_nonce' 		=> wp_create_nonce('wpcw-enrollment-nonce'), 	// Nonce security token for enrollement shortcode
			'progress_nonce' 		=> wp_create_nonce('wpcw-progress-nonce'), 	// Nonce security token
			'str_uploading'			=> __('Uploading:', 'wp_courseware'),		// Uploading message.
			'str_quiz_all_fields'	=> __('Please provide an answer for all of the questions on this page.', 'wp_courseware'),

			// Timer units
			'timer_units_hrs' 		=> __('hrs', 'wp_courseware'),
			'timer_units_mins' 		=> __('mins', 'wp_courseware'),
			'timer_units_secs' 		=> __('secs', 'wp_courseware'),

	));
}

/**
 * Return the URL for the page Icon.
 * @return String The URL for the page icon.
 */
function WPCW_icon_getPageIconURL() {
	return WPCW_plugin_getPluginPath() . 'img/icon_training_32.png';
}

/**
 * Get the URL for the plugin path including a trailing slash.
 * @return String The URL for the plugin path.
 */
function WPCW_plugin_getPluginPath() {
	return plugin_dir_url( __FILE__ );
}

/**
 * Get the directory path for the plugin path including a trailing slash.
 * @return String The URL for the plugin path.
 */
function WPCW_plugin_getPluginDirPath() {
	$folder = basename(dirname(__FILE__));
	return WP_PLUGIN_DIR . "/" . trailingslashit($folder);
}

/**
 * Determine if we're on a page just related to this plugin in the admin area.
 * @return Boolean True if we're on an admin page, false otherwise.
 */
function WPCW_areWeOnPluginPage()
{
	// Checks for admin.php?page=
	if ($currentPage = WPCW_arrays_getValue($_GET, 'page'))
	{
		// This handles any admin page for our plugin.
		if (substr($currentPage, 0, 5) == 'WPCW_') {
			return true;
		}
	}

	return false;
}

/**
 * Creates the course unit post type.
 */
function WPCW_plugin_registerCustomPostTypes()
{
	$labels = array(
        'name' 					=> __( 'Course Units', 						'wp_courseware'),
        'singular_name' 		=> __( 'Course Unit', 						'wp_courseware'),
        'add_new' 				=> __( 'Add New', 							'wp_courseware'),
        'add_new_item' 			=> __( 'Add New Course Unit', 				'wp_courseware'),
        'edit_item'	 			=> __( 'Edit Course Unit', 					'wp_courseware'),
        'new_item' 				=> __( 'New Course Unit', 					'wp_courseware'),
        'view_item' 			=> __( 'View Course Unit', 					'wp_courseware'),
        'search_items' 			=> __( 'Search Course Units', 				'wp_courseware'),
        'not_found' 			=> __( 'No course units found', 			'wp_courseware'),
        'not_found_in_trash'	=> __( 'No course units found in Trash', 	'wp_courseware'),
        'parent_item_colon' 	=> __( 'Parent Course Unit:', 				'wp_courseware'),
        'menu_name' 			=> __( 'Course Units', 						'wp_courseware'),
	);

	$args = array(
		'labels'              => $labels,
		'hierarchical'        => false,
		'supports'            => array( 'title', 'editor', 'revisions' ),
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => 100,
		'menu_icon'           => WPCW_plugin_getPluginPath() . 'img/icon_training_16.png',
		'show_in_nav_menus'   => false,
		'publicly_queryable'  => true,
		'exclude_from_search' => true,
		'has_archive'         => false,
		'query_var'           => true,
		'can_export'          => true,
		'rewrite'             => false,
		'capability_type'     => 'wpcw_course_unit',
		'map_meta_cap'        => true,
		'menu_position'       => WPCW_MENU_POSITION + 1
	);

	register_post_type( 'course_unit', $args );
}

/**
 * Hide items from the menu we don't want, but still want access to.
 */
function WPCW_menu_MainMenu_cleanUnwantedEntries()
{
	global $submenu;

	// Rename the Training Courses page to include a count of quizzes that need attention.
	$quizCount = WPCW_quizzes_getCoursesNeedingAttentionCount();
	if ($quizCount > 0)
	{
		if (isset($submenu[WPCW_PLUGIN_ID])) {
			$submenu[WPCW_PLUGIN_ID][0][0] .= sprintf('<span class="update-plugins count-%d"><span class="update-count">%s</span></span>', $quizCount, $quizCount);
		}
	}

	// Hide context pages
	WPCW_menu_removeSubmenuItem(WPCW_PLUGIN_ID, 'WPCW_showPage_CourseOrdering');
	WPCW_menu_removeSubmenuItem(WPCW_PLUGIN_ID, 'WPCW_showPage_ConvertPage');
	WPCW_menu_removeSubmenuItem(WPCW_PLUGIN_ID, 'WPCW_showPage_GradeBook');
	//WPCW_menu_removeSubmenuItem(WPCW_PLUGIN_ID, 'WPCW_showPage_ModifyQuestion');

	// Hide User Menus
	WPCW_menu_removeSubmenuItem('users.php', 'WPCW_showPage_UserCourseAccess');
	WPCW_menu_removeSubmenuItem('users.php', 'WPCW_showPage_UserProgess');
	WPCW_menu_removeSubmenuItem('users.php', 'WPCW_showPage_UserProgess_quizAnswers');

	// Hide from single profile menus
	WPCW_menu_removeSubmenuItem('profile.php', 'WPCW_showPage_UserCourseAccess');
	WPCW_menu_removeSubmenuItem('profile.php', 'WPCW_showPage_UserProgess');
	WPCW_menu_removeSubmenuItem('profile.php', 'WPCW_showPage_UserProgess_quizAnswers');
}

/**
 * Checks to see if the permalinks are using '/%postname%/' for WPCW to work correctly.
 */
function WPCW_plugin_permalinkCheck() {
	global $current_user;
	$userid = $current_user->ID;
	$permalink_structure = get_option('permalink_structure');

	// Only show this notice if user hasn't already dismissed it
	if ( !get_user_meta( $userid, 'ignore_permalinks_notice' ) &&
	 ('/%postname%/' != $permalink_structure) ) {
		printf('<div class="updated notice is-dismissible">
					<p>%s</p>
					<p>%s <strong><a href="%s">%s</a></strong>.</p>
				</div>',
			__("For <strong>WP Courseware</strong> unit URLs to work correctly, please ensure your <strong>permalinks</strong> use just <code>/%postname%/</code>.", 'wp_courseware'),
			__("You can update the permalink settings to use just <strong>Post Name</strong> on the", 'wp_courseware'),
			admin_url('options-permalink.php'),
			__('Permalink Settings page', 'wp_courseware')
		);
	}
}

/**
 * Adds user meta value when dismiss button is clicked
 */
function WPCW_dismiss_admin_notice() {
	global $current_user;
	$userid = $current_user->ID;

	// If dismiss button has been clicked, user meta field is added
	if ( isset( $_GET['wpcw_perma_hide'] ) && '1' == $_GET['wpcw_perma_hide'] ) {
		add_user_meta( $userid, 'ignore_permalinks_notice', 'yes', true );
	}
}
add_action( 'admin_init', 'WPCW_dismiss_admin_notice' );

/**
 * Message shown to say that multi-site is not currently supported.
 */
function WPCW_plugin_multisiteCheck()
{
	if (!is_multisite()) {
		return;
	}

	printf('<div class="updated error"><p>%s</p></div>', __('<b>WP Courseware</b> is not currently supported on WordPress Multisite. <b>Yet</b>.', 'wp_courseware'));
}

/**
 * Function called on initialisation of the function to clean up orphan tags.
 */
function WPCW_tag_cleanup()
{
	global $wpdb, $wpcwdb;
	$wpdb->show_errors();

	// Initialise database as it's not yet been created.
	$wpcwdb = new WPCW_Database();

	// If table doesn't exist, don't run maintenance.
	if ( !is_null($wpdb->get_var("SHOW TABLES LIKE '$wpcwdb->question_tag_mapping'")) )
	{

		// We want to find all unique questions that have been deleted. We do this by
		// joining the tag table with the table of questions. WHERE filters out where
		// a tag exists, but a question does.
		$tags = $wpdb->get_results("SELECT tm.*, qq.question_id AS jn_question_id
				FROM $wpcwdb->question_tag_mapping tm
				LEFT JOIN $wpcwdb->quiz_qs qq ON qq.question_id = tm.question_id
				WHERE qq.question_id IS NULL
				");

		// Delete the tag from each question that we've found above, which will also
		// update the tag popularity once deleted.
		if($tags){
			foreach ($tags as $tag)
			{
				WPCW_questions_tags_removeTag($tag->question_id, $tag->tag_id);
			}
		}
	}
}

/**
 * Function executed when the plugin is enabled in WP admin.
 */
function WPCW_plugin_activationChecks()
{
	// Clean up tags
	WPCW_tag_cleanup();

	// Setup or clear the scheduler for the notifications timers. If the setting is 'never'
	// then clear the scheduler. If it's anything else, then add it.
	$dripfeedSetting = TidySettings_getSettingSingle(WPCW_DATABASE_SETTINGS_KEY, 'cron_time_dripfeed');
	WPCW_queue_dripfeed::installNotificationHook_dripfeed($dripfeedSetting);

	// Create Roles & Capabilities
	WPCW_Roles()->add_roles();
	WPCW_Roles()->add_caps();
}
register_activation_hook(__FILE__, 'WPCW_plugin_activationChecks');

/**
 * Check to see if roles have been created, if not create them.
 */
function WPCW_plugin_rolesCheck() {
	// global
	global $wp_roles;

	// Check for the roles object, if it doesn't exist somehow, exit.
	if ( ! is_object( $wp_roles ) ) {
		return;
	}

	// Create Roles & Capabilities, if the instructor role does not exist.
	if ( empty( $wp_roles->roles ) || ! array_key_exists( 'wpcw_instructor', $wp_roles->roles ) ) {
		// Create Roles & Capabilities
		WPCW_Roles()->add_roles();
		WPCW_Roles()->add_caps();
	}
}
add_action( 'admin_init', 'WPCW_plugin_rolesCheck' );

/**
 * Function executed when the plugin is disabled in WP admin.
 */
function WPCW_plugin_deactivationChecks()
{
	// Remove any cron-associated tasks from the system.
	wp_clear_scheduled_hook( WPCW_WPCRON_NOTIFICATIONS_DRIPFEED_ID );

	// Resets Permalinks nag if plugin is deactivated
	global $current_user;
	$userid = $current_user->ID;

	// Removes user meta that keeps nag away
	delete_user_meta( $userid, 'ignore_permalinks_notice', 'yes' );
}
register_deactivation_hook(__FILE__, 'WPCW_plugin_deactivationChecks');

/**
 * Function executed when the plugin is uninstalled
 */
function WPCW_plugin_uninstallChecks() {
	// Remove Roles
	WPCW_Roles()->remove_caps();
}
register_uninstall_hook(__FILE__, 'WPCW_plugin_uninstallChecks');

?>