<?php
/**
 * Add a custom product tab.
 */
function lac_custom_product_tabs( $tabs) {
	$tabs['lac_connect'] = array(
		'label'		=> esc_html(__( 'LMSACE Connect', 'woocommerce' )),
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
	$options = [];
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

			$attr = array(
				'id'				=> LACONN_MOODLE_COURSE_ID,
				'label'				=> esc_html( __( 'Select Courses', 'lmsace-connect' )),
				'desc_tip'			=> 'true',
				'description'		=> esc_html( __( 'Select the course to make this product as course product.', 'lmsace-connect' )),
				'custom_attributes'	=> array('multiple' => true),
				'name'		=> LACONN_MOODLE_COURSE_ID."[]",
				'options' => $options,
			);
			// Change the select bar filter options.
			$attr = apply_filters( 'lmsace_connect_product_select_attributes', $attr );

			woocommerce_wp_select( $attr );

		?></div>
	</div><?php
}



