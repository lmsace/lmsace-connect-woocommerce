<?php
/**
 * List of all the user enrolled courses.
 *
 * @package lmsace-connect
 */

global $LACONN;

$user = $LACONN->User->get_user();
$courses = $LACONN->User->get_user_courses($user);

if ( !empty($courses) ) {
	?>

	<div class="product-container" >
		<div class="product-row row">

	<?php
	$loginurl = apply_filters( 'lmsace_connect_courseurl', $thiscourseurl );

	$courseids = array_column($courses, 'courseid');
	$md_courses = $LACONN->Course->get_courses_byid( $courseids );
	foreach ( $courses as $key => $course ) {

		$course = (object) $course;
		$courseid = $course->courseid;
		// Get product id from the enrolled LMS course id.
		$product_id = $course->productid;

		if (empty($product_id)) continue;

		$product = wc_get_product( $product_id );
  		$thiscourseurl = $loginurl . $LACONN->site_url.'course/view.php?id='.$courseid;
		$coursename = isset($md_courses[$courseid]) ? $md_courses[$courseid]->fullname : '';

		?>
			<div class="product-item col-md-3">
				<div class="img-block">
					<img src="<?php echo esc_attr( $this->get_product_image($product) );?>" >
				</div>
				<div class="product-details">
					<label><?php echo esc_html( $coursename ); ?> </label>
					<h4 class="product-name"> <a href="<?php echo esc_attr( $thiscourseurl ); ?>"><?php echo esc_html( $product->get_name() );?> </a> </h4>
					<a class="button button-primary moodle-course-access" href="<?php echo esc_attr( $thiscourseurl ); ?>" > <?php echo esc_html( __("View Course", 'lmsconnect') ); ?> </a>
				</div>
			</div>
		<?php
	}
	?>
		</div>
	</div>
	<?php
} else {
	?>
	<div class="no-enrolled-courses">
		<h3> <?php echo esc_html( __('Enrolled Courses Not Found..', LAC_TEXTDOMAIN) ); ?></h3>
	</div>
<?php
}


