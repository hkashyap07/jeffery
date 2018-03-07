<?php
/**
 * Roles and Capabilities
 *
 * @package     WPCW
 * @subpackage  Classes/Roles
 * @copyright   Copyright (c) 2016, Fly Plugins
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       4.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * WPCW_Roles Class
 *
 * This class handles the role creation and assignment of capabilities for those roles.
 *
 * These roles let us have Instructors and future roles
 * do certain things inside the WP Courseware plugin.
 *
 * @since 4.0
 */
class WPCW_Roles {

	/**
	 * @var WPCW_Roles
	 * @since 4.0
	 */
	private static $instance;

	/**
	 * Singleton
	 *
	 * @return WPCW_Roles WPCourseware Roles Instance
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPCW_Roles ) ) {
			self::$instance = new WPCW_Roles();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @access  private
	 * @since   4.0
	 */
	private function __construct() {
		add_filter( 'editable_roles', array( $this, 'editable_roles' ) );
	}

	/**
	 * Change the dropdown of editable roles when adding a new user.
	 *
	 * @since   1.0.0
	 * @param   array   $roles
	 * @return  array   $roles
	 */
	public function editable_roles( $roles ) {

		// Global
		global $pagenow;

		// check
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return $roles;
		}

		// Check permissions
		if ( current_user_can( 'manage_wpcw_settings' ) ) {
			return $roles;
		}

		// Check screen
		if ( 'user-new.php' != $pagenow ) {
			return $roles;
		}

		// Allowed Roles
		$allowed_roles = array( 'subscriber' );

		// Filter roles
		foreach( $roles as $role_key => $role ) {
			if ( ! in_array( $role_key, $allowed_roles ) ) {
				unset( $roles[ $role_key ] );
			}
		}

		// Return Roles
		return $roles;
	}

	/**
	 * Add new roles with default WP caps
	 *
	 * @access  public
	 * @since   4.0
	 * @return  void
	 */
	public function add_roles() {
		// Instructor
		add_role( 'wpcw_instructor', __( 'Instructor', 'wp_courseware' ), array(
			'read'         => true,
			'edit_posts'   => false,
			'upload_files' => true,
			'delete_posts' => false
		) );
	}

	/**
	 * Add new wp_courseware specific capabilities
	 *
	 * @access  public
	 * @since   4.0
	 * @global  WP_Roles $wp_roles
	 * @return  void
	 */
	public function add_caps() {
		global $wp_roles;

		if ( class_exists( 'WP_Roles' ) ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}

		if ( is_object( $wp_roles ) ) {

			// Administrator Capabilities
			$wp_roles->add_cap( 'administrator', 'view_wpcw_courses' );
			$wp_roles->add_cap( 'administrator', 'manage_wpcw_settings' );

			// Add the main post type capabilities
			$capabilities = $this->get_core_caps();
			foreach ( $capabilities as $cap_group ) {
				foreach ( $cap_group as $cap ) {
					$wp_roles->add_cap( 'administrator', $cap );
				}
			}

			// Instructor Capabilities
			$wp_roles->add_cap( 'wpcw_instructor', 'view_wpcw_courses' );
			$wp_roles->add_cap( 'wpcw_instructor', 'edit_wpcw_course_unit' );
			$wp_roles->add_cap( 'wpcw_instructor', 'edit_wpcw_course_units' );
			$wp_roles->add_cap( 'wpcw_instructor', 'delete_wpcw_course_unit' );
			$wp_roles->add_cap( 'wpcw_instructor', 'delete_wpcw_course_units' );
			$wp_roles->add_cap( 'wpcw_instructor', 'delete_published_wpcw_course_units' );
			$wp_roles->add_cap( 'wpcw_instructor', 'publish_wpcw_course_units' );
			$wp_roles->add_cap( 'wpcw_instructor', 'edit_published_wpcw_course_units' );
			$wp_roles->add_cap( 'wpcw_instructor', 'list_users' );
			$wp_roles->add_cap( 'wpcw_instructor', 'create_users' );
		}
	}

	/**
	 * Gets the core post type capabilities
	 *
	 * @access  public
	 * @since   4.0
	 * @return  array   $capabilities Core post type capabilities
	 */
	public function get_core_caps() {
		$capabilities = array();

		$capability_types = array( 'wpcw_course_unit' );

		foreach ( $capability_types as $capability_type ) {
			$capabilities[ $capability_type ] = array(
				// Post type
				"edit_{$capability_type}",
				"read_{$capability_type}",
				"delete_{$capability_type}",
				"edit_{$capability_type}s",
				"edit_others_{$capability_type}s",
				"publish_{$capability_type}s",
				"read_private_{$capability_type}s",
				"delete_{$capability_type}s",
				"delete_private_{$capability_type}s",
				"delete_published_{$capability_type}s",
				"delete_others_{$capability_type}s",
				"edit_private_{$capability_type}s",
				"edit_published_{$capability_type}s",

				// Terms
				"manage_{$capability_type}_terms",
				"edit_{$capability_type}_terms",
				"delete_{$capability_type}_terms",
				"assign_{$capability_type}_terms",

				// Custom
				"import_{$capability_type}s"
			);
		}

		return $capabilities;
	}

	/**
	 * Remove core post type capabilities (called on uninstall)
	 *
	 * @access  public
	 * @since   4.0
	 * @return  void
	 */
	public function remove_caps() {

		global $wp_roles;

		if ( class_exists( 'WP_Roles' ) ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}

		if ( is_object( $wp_roles ) ) {

			// Administrator Capabilities
			$wp_roles->remove_cap( 'administrator', 'view_wpcw_courses' );
			$wp_roles->remove_cap( 'administrator', 'manage_wpcw_settings' );

			/** Remove the Main Post Type Capabilities */
			$capabilities = $this->get_core_caps();
			foreach ( $capabilities as $cap_group ) {
				foreach ( $cap_group as $cap ) {
					$wp_roles->remove_cap( 'administrator', $cap );
				}
			}

			// Instructor Capabilities
			$wp_roles->remove_cap( 'wpcw_instructor', 'view_wpcw_courses' );
			$wp_roles->remove_cap( 'wpcw_instructor', 'edit_wpcw_course_unit' );
			$wp_roles->remove_cap( 'wpcw_instructor', 'edit_wpcw_course_units' );
			$wp_roles->remove_cap( 'wpcw_instructor', 'delete_wpcw_course_unit' );
			$wp_roles->remove_cap( 'wpcw_instructor', 'delete_wpcw_course_units' );
			$wp_roles->remove_cap( 'wpcw_instructor', 'publish_wpcw_course_units' );
			$wp_roles->remove_cap( 'wpcw_instructor', 'edit_published_wpcw_course_units' );
			$wp_roles->remove_cap( 'wpcw_instructor', 'list_users' );
		}
	}
}

/**
 * The main function that returns WPCW_Roles
 *
 * @since   4.0
 * @return  object  WPCW_Roles  The WPCW_Roles object.
 */
function WPCW_Roles() {
	return WPCW_Roles::instance();
}

/** Initialize */
WPCW_Roles();