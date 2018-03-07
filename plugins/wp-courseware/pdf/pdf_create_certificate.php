<?php
/**
 * WPCW Create PDF Certificates
 */

/**
 * Create Certificates
 *
 * @param $wp
 */
function WPCW_create_pdf_certificates( $wp ) {

	// Check page
	if ( ! array_key_exists( 'page', $wp->query_vars ) || $wp->query_vars[ 'page' ] != 'wpcw_pdf_create_certificate' ) {
		return;
	}

	// Certificate class
	include_once 'pdf_certificates.inc.php';

	// Grab the certificate from the parameter
	$certificateID = WPCW_arrays_getValue( $_GET, 'certificate' );

	// Nothing to see.
	if ( ! $certificateID ) {
		WPCW_certificate_notFound();
	}

	// #### PREVIEW - Has a preview been requested? Is the user logged in and is permitted to preview.
	if ( 'preview' == $certificateID ) {

		// Get out if not logged in.
		if ( ! is_user_logged_in() ) {
			WPCW_certificate_notFound();
		}

		// See if the provided ID is a valid ID
		$current_user = wp_get_current_user();

		// Can't even view courses, get out.
		if ( ! user_can( $current_user, 'view_wpcw_courses' ) ) {
			WPCW_certificate_notFound();
		}

		// Get Course Id
		$courseID = ( isset( $_GET[ 'course_id' ] ) ) ? $_GET[ 'course_id' ] + 0 : false;

		// Check course
		if ( ! $courseID ) {
			wp_die( __( 'No course was found to preview its certificate.', 'wp_courseware' ) );
		}

		// Get Course Details
		$courseDetails = WPCW_courses_getCourseDetails( $courseID );

		// Check course details
		if ( ! $courseDetails ) {
			wp_die( __( 'No certificate was found that is associated with this course.', 'wp_courseware' ) );
		}

		// Check permissions, this condition allows admins to view all modules even if they are not the author.
		if ( ! user_can( $current_user->ID, 'manage_wpcw_settings' ) && $courseDetails->course_author != $current_user->ID ) {
			wp_die( __( 'You are not allowed to preview certificates for this course.', 'wp_courseware' ) );
		}

		// Generate certificate
		$cert = new WPCW_Certificate( $courseDetails );
		$cert->generatePDF( WPCW_users_getUsersName( $current_user ), $courseDetails->course_title, false, 'browser' );
		die();
	} else {
		// Check database for the certificate by the ID
		$certificateDetails = WPCW_certificate_getCertificateDetails_byAccessKey( $certificateID );

		// Not a valid certificate, abort
		if ( ! $certificateDetails ) {
			WPCW_certificate_notFound();
		}

		$courseDetails = WPCW_courses_getCourseDetails( $certificateDetails->cert_course_id );
		$userInfo      = get_userdata( $certificateDetails->cert_user_id );

		// Not a valid course or user data
		if ( ! $certificateDetails || ! $userInfo ) {
			WPCW_certificate_notFound();
		}

		// Generate certificate to download
		$cert = new WPCW_Certificate( $courseDetails );
		$cert->generatePDF( WPCW_users_getUsersName( $userInfo ), $courseDetails->course_title, $certificateDetails, 'browser' );
		die();
	}
}

/**
 * Show a generic error, details not found.
 */
function WPCW_certificate_notFound() {
	wp_die( __( 'No certificate was found.', 'wp_courseware' ) );
}

?>