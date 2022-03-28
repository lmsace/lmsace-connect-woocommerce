<?php
/**
 * Add a custom product tab.
 */
function lac_custom_product_tabs( $tabs) {
	$tabs['lac_connect'] = array(
		'label'		=> __( 'LMSACE Connect', 'woocommerce' ),
		'target'	=> 'course_options',
		'class'		=> array( 'show_if_simple', 'show_if_variable'  ),
	);
	return $tabs;
}

/**
 * Contents of the gift card options product tab.
 */
function lac_moodle_courses_product_tab_content() {
	global $post, $LACONN;

	$result = $LACONN->Client->request(LACONN::services('get_courses_by_field'), array('fields' => array(), 'values' => array()));
	$options = [ 0 => __('No Course', LAC_TEXTDOMAIN) ];
	if (!empty($result) && isset($result->courses)) {
		$courses = $result->courses;
		foreach($courses as $course) {
			if (!in_array('manual', $course->enrollmentmethods)) {
				continue;
			}
			if ($course->id != '1') {
				$options[$course->id] = lac_format_string($course->shortname, 'en');
			}
		}
	}
	// Note the 'id' attribute needs to match the 'target' parameter set above
	?><div id='course_options' class='panel woocommerce_options_panel'><?php
		?><div class='options_group'><?php
			woocommerce_wp_select( array(
				'id'				=> LACONN_MOODLE_COURSE_ID,
				'label'				=> __( 'Select Course', 'lmsace-connect' ),
				'desc_tip'			=> 'true',
				'description'		=> __( 'Select the course to make this product as course product.', 'lmsace-connect' ),
				'custom_attributes'	=> array(),
				'name'		=> LACONN_MOODLE_COURSE_ID,
				'options' => $options
			) );
		?></div>
	</div><?php
}

/**
 * Save the custom fields.
 */
function lac_save_moodlecourse_option_fields( $post_id ) {
	if ( isset( $_POST[LACONN_MOODLE_COURSE_ID] ) ) :
		update_post_meta( $post_id, LACONN_MOODLE_COURSE_ID, absint( $_POST[LACONN_MOODLE_COURSE_ID] ) );
	endif;
}
