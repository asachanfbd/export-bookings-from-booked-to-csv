<?php 

/**
 * @package Export_Bookings_from_Booked_to_CSV
 * @version 1.0.0
 */
/*
Plugin Name: "Booked - Appointment Booking for WordPress" Plugin's Booking Export to CSV

*/
/**
 * Main plugin class
 *
 * @since 0.1
 **/
class BOOKED_Export_Bookings {
	
	private $error = '';
	/**
	 * Class contructor
	 *
	 * @since 0.1
	 **/
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'admin_init', array( $this, 'generate_csv' ) );
	}

	/**
	 * Add administration menus
	 *
	 * @since 0.1
	 **/
	public function add_admin_pages() {
		add_menu_page('Bookings Export', 'Bookings Export', 'manage_options', __FILE__, array( $this,'export_bookings_to_csv'), 'dashicons-media-spreadsheet', 58);
	}
	
	/**
	 * Process content of CSV file
	 *
	 * @since 0.1
	**/
	public function export_bookings_to_csv(){
		echo '<h1>Export Bookings</h1>';
		
		$dateArr = $this->getAllDates('_appointment_timestamp');
		?>
		<div class="wrap">
			<h2>Export Bookings Information</h2>
			<?php $this->show_errors(); ?>
			<form method="post" name="csv_exporter_form" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'export-bookings-bookings_export', '_wpnonce-export-bookings-bookings_export' ); ?>
				<p><h3>Filter your export:</h3></p>
				<label>Start Date:</label>
				<select name="startdate" id="startdate">
					<option value="">Select Start Date</option>
					<?php foreach($dateArr as $startKey => $startVal){?>
						<option value="<?php echo $startKey;?>"><?php echo $startVal;?></option>
					<?php }?>
				</select>
				<label>End Date:</label>
				<select name="enddate" id="enddate">
					<option value="">Select End Date</option>
					<?php foreach($dateArr as $endKey => $endVal){?>
						<option value="<?php echo $endKey;?>"><?php echo $endVal;?></option>
					<?php }?>
				</select>
				<br>
				<br>
				<input type="submit" name="Submit" value="Export Bookings" class="button button-primary button-large" />
			</form>
		</div>
		<?php 
	}
	
	public function getAllDates($meta_key){
		global $wpdb;
		
		$returned_dates = $wpdb->get_results("select * from $wpdb->postmeta where meta_key='".$meta_key."'");
		
		$dateArr = array();
		foreach( $returned_dates as $dates){
			$formatted = $this->getFormattedTime($dates->meta_value, 'date');
			if(!isset($skip_same[$formatted])){
				$dateArr[$dates->meta_value] = $formatted;
				$skip_same[$formatted] = $dates->meta_value;
			}
		}
		return $dateArr;
	}
	 
	public function getFormattedTime($time , $format='datetime'){
		if($format == 'date')
			return date('d-M-Y', $time);

		if($format == 'dateexcel')
			return date('Y-m-d', $time);
	}

	public function show_errors()
	{
		if($this->error != ''){
		?>
			<div class="error notice">
			    <p><?php echo $this->error; ?></p>
			</div>
		<?php
		}
	}
	
	public function generate_csv(){
		if ( isset( $_POST['_wpnonce-export-bookings-bookings_export'] ) ) {
			if(!(isset($_POST['startdate']) && isset($_POST['enddate']))){
				$this->error = 'Error: Start Date and End Date are required.';
				return;
			}
			if($_POST['startdate'] > $_POST['enddate']){
				$this->error = 'Error: Please select start data and end date properly. Start date should be smaller.';
				return;
			}
			$start_date = $_POST['startdate'];
			$end_date = $_POST['enddate'];
			check_admin_referer( 'export-bookings-bookings_export', '_wpnonce-export-bookings-bookings_export' );
			global $wpdb;
		
			$export = $wpdb->get_results("select p.*, u.user_email, u.display_name from $wpdb->posts p JOIN $wpdb->users as u on u.id = p.post_author where p.post_type = 'booked_appointments'");
			
			//header array
			$data[] = array('Booking Date', 'Display Name', 'Email', 'Time', 'Phone Number', 'Address', 'Landmark', 'City');

			// fetch the data
			foreach ( $export as $ex ) 
			{
				$booking_date = '';
				$display_name = $ex->display_name;
				$email = $ex->user_email;
				$time_slot = '';
				$phone_number = '';
				$address = '';
				$landmark = '';
				$city = '';

				$bookings = $wpdb->get_results("select * from $wpdb->postmeta pm where pm.post_id = $ex->ID");
				foreach($bookings as $booking){
					if($booking->meta_key == '_appointment_timestamp' ){
						if(!(date('Ymd', $booking->meta_value) >= date('Ymd', $start_date) && date('Ymd', $booking->meta_value) <= date('Ymd', $end_date))){
							break;
						}
						$booking_date = $this->getFormattedTime($booking->meta_value, 'dateexcel');
					}
					if($booking->meta_key == '_appointment_timeslot' ){
						$time_slot = $booking->meta_value;
					}
					if($booking->meta_key == '_cf_meta_value' && $booking->meta_value != ''){
						$dom = new DOMDocument;
						$dom->loadHTML($booking->meta_value);
						$books = $dom->getElementsByTagName('p');
						if($books){
							foreach ($books as $book) {
								if(stristr($book->nodeValue, 'Phone Number')){
									$phone_number = str_replace('Phone Number', '', $book->nodeValue);
								}
								if(stristr($book->nodeValue, 'Address')){
									$address = str_replace('Address', '', $book->nodeValue);
								}
								if(stristr($book->nodeValue, 'Landmark')){
									$landmark = str_replace('Landmark', '', $book->nodeValue);
								}
								if(stristr($book->nodeValue, 'City')){
									$city = str_replace('City', '', $book->nodeValue);
								}
							}
						}
					}
				}
				if($booking_date != ''){
					$data[] = array($booking_date, $display_name, $email, $time_slot, $phone_number, $address, $landmark, $city);
				}
			}
			$this->array_to_csv_download($data);
			exit;
		}
	}
	
	function array_to_csv_download($array, $filename = "export.csv", $delimiter=",") {
		ob_start();
		// open raw memory as file so no temp files needed, you might run out of memory though
		$f = fopen('php://output', 'w'); 
		// loop over the input array
		foreach ($array as $line) { 
			// generate csv lines from the inner arrays
			fputcsv($f, $line); 
		}
		fclose($f);
		header("Content-Type: text/csv");
		header("Content-Disposition: attachment; filename=".$filename);
		// Disable caching
		header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
		header("Pragma: no-cache"); // HTTP 1.0
		header("Expires: 0"); // Proxies
	}
}

new BOOKED_Export_Bookings;