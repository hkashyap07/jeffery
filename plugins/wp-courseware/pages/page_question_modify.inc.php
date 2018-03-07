<?php
/**
 * WP Courseware
 *
 * Functions relating to modifying a question.
 */

/**
 * Function that allows a question to be edited.
 */
function WPCW_showPage_ModifyQuestion_load() {

	// Variables
	$page            = new PageBuilder( true );
	$newQuestion     = false;
	$questionDetails = false;
	$questionID      = false;
	$newQuestion     = false;
	$questionHTML    = false;
	$canAddQuestion  = false;
	$canEditQuestion = false;
	$current_user    = wp_get_current_user();

	// Check processing, perform different actions if the question_id exists.
	if ( isset( $_GET[ 'question_id' ] ) || isset( $_POST[ 'question_id' ] ) ) {

		// Page Header
		$page->showPageHeader( __( 'Edit Single Question', 'wp_courseware' ), '70%', WPCW_icon_getPageIconURL() );

		// Check POST and GET
		if ( isset( $_GET[ 'question_id' ] ) ) {
			$questionID = $_GET[ 'question_id' ] + 0;
		} else if ( isset( $_POST[ 'question_id' ] ) ) {
			$questionID = $_POST[ 'question_id' ] + 0;
		}

		// See if the question has been submitted for saving.
		if ( 'true' == WPCW_arrays_getValue( $_POST, 'question_save_mode' ) ) {
			if ( 'true' == WPCW_arrays_getValue( $_POST, 'question_is_new_question' ) ) {
				// Save DAta
				$questionID = WPCW_handler_questions_processSave( false, true );

				// Message
				$directionMsg = '<br/></br>' . sprintf( __( 'Do you want to return to the <a href="%s">question pool</a>?', 'wp_courseware' ), admin_url( 'admin.php?page=WPCW_showPage_QuestionPool' ) );

				// Output message
				$page->showMessage( __( 'Question successfully added.', 'wp_courseware' ) . $directionMsg );
			} else {
				// Save Data
				WPCW_handler_questions_processSave( false, true );

				// Message
				$directionMsg = '<br/></br>' . sprintf( __( 'Do you want to return to the <a href="%s">question pool</a>?', 'wp_courseware' ), admin_url( 'admin.php?page=WPCW_showPage_QuestionPool' ) );

				// Output Mesage.
				$page->showMessage( __( 'Question successfully updated.', 'wp_courseware' ) . $directionMsg );
			}

			// Assume save has happened, so reload the settings.
			$questionDetails = WPCW_questions_getQuestionDetails( $questionID, true );
		} else {

			// Trying to edit a question
			$questionDetails = WPCW_questions_getQuestionDetails( $questionID, true );

			// Abort if question not found.
			if ( ! $questionDetails ) {
				$page->showMessage( __( 'Sorry, but that question could not be found.', 'wp_courseware' ), true );
				$page->showPageFooter();
				return;
			}
		}

		// Check permissions, this condition allows admins to view all modules even if they are not the author.
		if ( user_can( $current_user->ID, 'manage_wpcw_settings' ) ) {
			$canEditQuestion = true;
		}

		// Check Author
		if ( $questionDetails->question_author == $current_user->ID ) {
			$canEditQuestion = true;
		}

		// Back compat filter
		$canEditQuestion = apply_filters( 'wpcw_back_permissions_user_can_edit_question', $canEditQuestion, $current_user->ID, $questionDetails );

		// Add filters to override
		$canEditQuestion     = apply_filters( 'wpcw_permissions_user_can_edit_question', $canEditQuestion, $current_user, $questionDetails );
		$cantEditQuestionMsg = apply_filters( 'wpcw_permissions_user_can_edit_question_msg', esc_attr__( 'You are not permitted to edit this question.', 'wp_courseware' ), $current_user, $questionDetails );

		// Display message if no access.
		if ( ! $canEditQuestion ) {
			$page->showMessage( $cantEditQuestionMsg, true );
			$page->showPageFooter();

			return;
		}

		// Manually set the order to zero, as not needed for ordering in this context.
		$questionDetails->question_order = 0;

		switch ( $questionDetails->question_type ) {
			case 'multi':
				$quizObj = new WPCW_quiz_MultipleChoice( $questionDetails );
				break;

			case 'truefalse':
				$quizObj = new WPCW_quiz_TrueFalse( $questionDetails );
				break;

			case 'open':
				$quizObj = new WPCW_quiz_OpenEntry( $questionDetails );
				break;

			case 'upload':
				$quizObj = new WPCW_quiz_FileUpload( $questionDetails );
				break;

			default:
				die( __( 'Unknown quiz type: ', 'wp_courseware' ) . $questionDetails->question_type );
				break;
		}

		$quizObj->showErrors         = true;
		$quizObj->needCorrectAnswers = true;
		$quizObj->hideDragActions    = true;

	} else {
		// Set New Question
		$newQuestion = true;

		// Page Header
		$page->showPageHeader( __( 'Add a Question', 'wp_courseware' ), '75%', WPCW_icon_getPageIconURL() );

		// Check permissions
		if ( user_can( $current_user->ID, 'view_wpcw_courses' ) ) {
			$canAddQuestion = true;
		}

		// Add filter to override
		$canAddQuestion     = apply_filters( 'wpcw_permissions_user_can_add_qustion', $canAddQuestion, $current_user );
		$cantAddQuestionMsg = apply_filters( 'wpcw_permissions_user_can_add_question_msg', esc_attr__( 'You are not permitted to add a new question.', 'wp_courseware' ), $current_user );

		// Legacy Fitler
		$canAddQuestion     = apply_filters( 'wpcw_back_permissions_user_can_add_question', $canAddQuestion, $current_user->ID );
		$cantAddQuestionMsg = apply_filters( 'wpcw_back_msg_permissions_user_can_add_quesiton', esc_attr__( 'You are not permitted to add a new question.', 'wp_courseware' ), $current_user->ID );

		// Check
		if ( ! $canAddQuestion ) {
			$page->showMessage( $cantAddQuestionMsg, true );
			$page->showPageFooter();

			return;
		}

		// Question Type
		$questionType = ( isset( $_GET[ 'question_type' ] ) ) ? $_GET[ 'question_type' ] : false;
		$questionType = ( isset( $_POST[ 'question_type' ] ) ) ? $_POST['question_type' ] : $questionType;

		// If there is no question type defined, present a menu.
		if ( ! $questionType ) {
			echo '<div id="wpcw_quiz_details_questions">';
			printf( '<p>%s</p>', apply_filters( 'wpcw_no_question_type_selected_message', __( 'To add a question, please select a question type on the right of this page.', 'wp_courseware' ) ) );
			echo '</div>';
			echo WPCW_showPage_ModifyQuestion_Get_Sidebar();
			$page->showPageFooter();
			return;
		}

		// The empty forms for adding a new question
		$questionDetails = new stdClass();

		// Populate items
		$questionDetails->question_question            = '';
		$questionDetails->question_correct_answer      = false;
		$questionDetails->question_order               = 0;
		$questionDetails->question_answer_type         = false;
		$questionDetails->question_answer_hint         = false;
		$questionDetails->question_answer_explanation  = false;
		$questionDetails->question_answer_file_types   = 'doc, pdf, jpg, png, jpeg, gif';
		$questionDetails->question_image               = false;
		$questionDetails->question_usage_count         = 0;
		$questionDetails->question_multi_random_enable = 0;
		$questionDetails->question_multi_random_count  = 5;
		$questionDetails->question_author              = $current_user->ID;

		// Create some dummy answers.
		$questionDetails->question_data_answers = serialize( array(
			1 => array( 'answer' => '' ),
			2 => array( 'answer' => '' ),
			3 => array( 'answer' => '' )
		) );

		// Set placeholder class
		$questionDetails->question_id = sprintf( 'new_%s', $questionType );

		// Create Object depending on Question type
		switch ( $questionType ) {
			case 'multi':
				$quizObj = new WPCW_quiz_MultipleChoice( $questionDetails );
				break;

			case 'truefalse':
				$quizObj = new WPCW_quiz_TrueFalse( $questionDetails );
				break;

			case 'open':
				$quizObj = new WPCW_quiz_OpenEntry( $questionDetails );
				break;

			case 'upload':
				$quizObj = new WPCW_quiz_FileUpload( $questionDetails );
				break;

			default:
				die( __( 'Unknown quiz type: ', 'wp_courseware' ) . $questionType );
				break;
		}

		$quizObj->showErrors                   = false;
		$quizObj->editForm_questionNotSavedYet = true;
		$quizObj->hideDragActions              = true;

		// Create Object depending on Question type
		switch ( $questionType ) {
			case 'multi':
				$questionHTML = str_replace( '_new_multi', '_new_question_0', $quizObj->editForm_toString() );
				break;

			case 'truefalse':
				$questionHTML = str_replace( '_new_truefalse', '_new_question_0', $quizObj->editForm_toString() );
				break;

			case 'open':
				$questionHTML = str_replace( '_new_open', '_new_question_0', $quizObj->editForm_toString() );
				break;

			case 'upload':
				$questionHTML = str_replace( '_new_upload', '_new_question_0', $quizObj->editForm_toString() );
				break;

			default:
				$questionHTML = $quizObj->editForm_toString();
				break;
		}
	}

	// #wpcw_quiz_details_questions = needed for media uploader
	// .wpcw_question_holder_static = needed for wrapping the question using existing HTML.
	printf( '<div id="wpcw_quiz_details_questions" class="edit_question_single_details"><ul class="wpcw_question_holder_static">' );

	// Create form wrapper, so that we can save this question.
	if ( $newQuestion ) {
		printf( '<form method="POST" action="%s?page=WPCW_showPage_ModifyQuestion" />', admin_url( 'admin.php' ) );
	} else {
		printf( '<form method="POST" action="%s?page=WPCW_showPage_ModifyQuestion&question_id=%d" />', admin_url( 'admin.php' ), $questionDetails->question_id );
	}

	// Question hidden fields
	printf( '<input name="question_id" type="hidden" value="%d" />', $questionDetails->question_id );
	printf( '<input name="question_save_mode" type="hidden" value="true" />' );

	if ( $newQuestion ) {
		printf( '<input name="question_is_new_question" type="hidden" value="true" />' );
	}

	// Show the quiz so that it can be edited. We're re-using the code we have for editing questions,
	// to save creating any special form edit code.
	if ( $newQuestion ) {
		echo $questionHTML;
	} else {
		echo $quizObj->editForm_toString();
	}

	// Save and return buttons.
	printf( '<div class="wpcw_button_group"><br/>' );
	printf( '<a href="%s?page=WPCW_showPage_QuestionPool" class="button-secondary">%s</a>&nbsp;&nbsp;', admin_url( 'admin.php' ), __( '&laquo; Return to Question Pool', 'wp_courseware' ) );
	if ( $newQuestion ) {
		printf( '<input type="submit" class="button-primary" value="%s" />', __( 'Save Question Details', 'wp_courseware' ) );
	} else {
		printf( '<input type="submit" class="button-primary" value="%s" />', __( 'Save Question Details', 'wp_courseware' ) );
	}
	printf( '</div>' );
	printf( '</form>' );
	printf( '</ul></div>' );
	echo WPCW_showPage_ModifyQuestion_Get_Sidebar();
	$page->showPageFooter();
}

/**
 * Function that dipslays the sidebar of the add new page
 */
function WPCW_showPage_ModifyQuestion_Get_Sidebar() {
	ob_start();
	$addQuestionUrl = add_query_arg( array( 'page' => 'WPCW_showPage_ModifyQuestion' ), esc_url( admin_url( 'admin.php' ) ) );
	?>
	<div class="wpcw_floating_menu" id="wpcw_add_quiz_menu">
		<div class="wpcw_add_quiz_block">
			<div class="wpcw_add_quiz_title"><?php _e( 'Question Types:', 'wp_courseware' ); ?></div>
			<div class="wpcw_add_quiz_options">
				<ul>
					<li>
						<a href="<?php echo add_query_arg( array( 'question_type' => 'multi' ), $addQuestionUrl ); ?>" class="button-secondary" id="wpcw_add_question_multi"><?php _e( 'Add Multiple Choice', 'wp_courseware' ); ?></a>
					</li>
					<li>
						<a href="<?php echo add_query_arg( array( 'question_type' => 'truefalse' ), $addQuestionUrl ); ?>" class="button-secondary" id="wpcw_add_question_truefalse"><?php _e( 'Add True/False', 'wp_courseware' ); ?></a>
					</li>
					<li>
						<a href="<?php echo add_query_arg( array( 'question_type' => 'open' ), $addQuestionUrl ); ?>" class="button-secondary" id="wpcw_add_question_open"><?php _e( 'Add Open Ended Question', 'wp_courseware' ); ?></a>
					</li>
					<li>
						<a href="<?php echo add_query_arg( array( 'question_type' => 'upload' ), $addQuestionUrl ); ?>" class="button-secondary" id="wpcw_add_question_upload"><?php _e( 'Add File Upload Question', 'wp_courseware' ); ?></a>
					</li>
				</ul>
			</div>
		</div>
	</div>
	<?php
	$html = ob_get_clean();
	return apply_filters( 'wpcw_add_question_sidebar_html', $html );
}