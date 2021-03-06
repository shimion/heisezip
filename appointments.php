<?php
/*
Plugin Name: Appointments+
Description: (This Plugin is highly customize to fit with the requirments. So please do not update it. Worked version 1.4.8...)Lets you accept appointments from front end and manage or create them from admin side.
Plugin URI: http://premium.wpmudev.org/project/appointments-plus/
Version: 10
Author: WPMU DEV
Author URI: http://premium.wpmudev.org/
Textdomain: appointments
WDP ID: 679841
*/

/*
Copyright 2007-2013 Incsub (http://incsub.com)
Author - Hakan Evin <hakan@incsub.com>
Contributor - Ve Bailovity (Incsub)
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

define('inc',__dir__.'/includes/');
require_once(inc . 'app-admin-settings-working_hours.php');
require_once(inc . 'appointment-listing.php');
require_once(inc . 'ture-time.php');
require_once(inc . 'working-shedule.php');
require_once(inc . 'paypal-return.php');
include('test.php');

//add_action( 'init', 'receive_paypal' );
add_action( 'init', 'create_post_type' );
function create_post_type() {
  register_post_type( 'coupon',
    array(
      'labels' => array(
        'name' => __( 'Coupon code' ),
        'singular_name' => __( 'coupon' )
      ),
      'public' => true,
      'has_archive' => false,
    )
  );
}


add_action( 'init', 'create_post_type_2' );
function create_post_type_2() {
  register_post_type( 'insurance_waiver',
    array(
      'labels' => array(
        'name' => __( 'Insurance Waiver' ),
        'singular_name' => __( 'insurance waiver' )
      ),
      'public' => true,
      'has_archive' => false,
    )
  );
}

add_shortcode( 'return_info', 'thank_you_shortcode' );
function thank_you_shortcode(){
	$custom = $_POST['custom'];
		if(!empty($custom)){
			//print_r($_POST['custom']);
			$values= explode( "|", $custom );
			$date = $values[6];
			$start = date('H:i:s', strtotime($values[7]));
			$end =  date('H:i:s', strtotime($values[8]));
			$num_att =  $values[0];
			$res_activity =  $values[13];
			$email_massage = '';
			//$email_massage .= 'You have a appointment registered. Here is the information.'. '<br>';;
			$email_massage .= 'Date: '.  $date. '<br>';
			$email_massage .= 'Start: '.  $start. '<br>';
			$email_massage .= 'End: '.  $end. '<br>';
			$email_massage .= 'Number of attendees: '.  $num_att. '<br>';
			$email_massage .= 'Status: '.  $values[2] . '<br>';;
			if($res_activity==1){
			$email_massage .= 'Reservation Code: '.  $values[1] . '<br>';
		//	$email_massage .= 'Reservation Number: '.  $values[1] . '/n';
			}
			
			$email_massage .= 'Price : '.  $values[10] . '<br>';
			$email_massage .= 'Price Total: '.  $values[9] . '<br>';
			$email_massage .= ''. '<br>';
			$email_massage .= 'Thank You '. '<br>';
			return  $email_massage;
		}
		
	}




function add_roles_hooker() {
       add_role( 'hooker', 'Hooker', array( 'read' => true, 'level_0' => true ) );
   }
   add_action( __FILE__, 'add_roles_hooker' );


if ( !class_exists( 'Appointments' ) ) {

class Appointments {

	var $version = "1.4.8";
	public $time_choice_first = array();
    public $time_choice_second = array();
	public $day_array = array();
	public $default_hours = array();

	function __construct() {
		$this->day_array = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thusday');
		$this->plugin_dir = plugin_dir_path(__FILE__);
		$this->plugin_url = plugins_url(basename(dirname(__FILE__)));
		$this->time_choice_first = array('09:00', '10:30', '12:00', '01:30', '03:00', '04:30', '06:00', '07:30');
        $this->time_choice_second = array('10:30', '12:00', '01:30', '03:00', '04:30', '06:00');
		$this->default_hours = maybe_unserialize(get_option( 'default_hours' ));
		$this->default_user_ID = array('16', '17');
		// Read all options at once
		$this->options = get_option( 'appointments_options' );

		// To follow WP Start of week, time, date settings
		$this->local_time = current_time('timestamp');
		if ( !$this->start_of_week = get_option('start_of_week') ) $this->start_of_week = 0;

		$this->time_format = get_option('time_format');
		if (empty($this->time_format)) $this->time_format = "H:i";

		$this->date_format = get_option('date_format');
		if (empty($this->date_format)) $this->date_format = "Y-m-d";

		$this->datetime_format = $this->date_format . " " . $this->time_format;
		add_action( 'admin_enqueue_scripts', array( &$this, 'load_scripts_styles_backend' ) );
		//add_action( 'init', array( &$this, 'receive_paypal' ) );		// Modify database in case a user is deleted
		add_action( 'delete_user', array( &$this, 'delete_user' ) );		// Modify database in case a user is deleted
		add_action( 'init', array( &$this, 'app_create_post' ) );		// Modify database in case a user is deleted
		add_action( 'wpmu_delete_user', array( &$this, 'delete_user' ) );	// Same as above
		add_action( 'remove_user_from_blog', array( &$this, 'remove_user_from_blog' ), 10, 2 );	// Remove his records only for that blog

		add_action( 'plugins_loaded', array(&$this, 'localization') );		// Localize the plugin
		add_action( 'init', array( &$this, 'init' ), 20 ); 						// Initial stuff
		//add_action( 'init', array( &$this, 'delete_entry_older_this_month' )); 						// Initial stuff
		add_action( 'init', array( &$this, 'cancel' ), 19 ); 				// Check cancellation of an appointment
		add_filter( 'the_posts', array(&$this, 'load_styles') );			// Determine if we use shortcodes on the page
		add_action( 'wp_ajax_nopriv_app_paypal_ipn', array(&$this, 'handle_paypal_return')); // Send Paypal to IPN function

		// Add/edit some fields on the user pages
		add_action( 'show_user_profile', array(&$this, 'show_profile') );
		add_action( 'edit_user_profile', array(&$this, 'show_profile') );
		add_action( 'personal_options_update', array(&$this, 'save_profile') );
		add_action( 'edit_user_profile_update', array(&$this, 'save_profile') );

		// Admin hooks
		add_action( 'admin_menu', array( &$this, 'admin_init' ) ); 						// Creates admin settings window
		add_action( 'admin_notices', array( &$this, 'admin_notices' ) ); 				// Warns admin
		add_action( 'admin_print_scripts', array(&$this, 'admin_scripts') );			// Load scripts
		add_action( 'admin_print_styles', array(&$this, 'admin_css') );					// Add style to all admin pages
		//add_action( 'admin_print_styles-appointments_page_app_settings', array( &$this, 'admin_css_settings' ) ); // Add style to settings page - DEPRECATED since v1.4.2-BETA-2
		add_action( 'right_now_content_table_end', array($this, 'add_app_counts') );	// Add app counts
		add_action( 'wp_ajax_delete_log', array( &$this, 'delete_log' ) ); 				// Clear log
		add_action( 'wp_ajax_inline_edit', array( &$this, 'inline_edit' ) ); 			// Add/edit appointments
		add_action( 'wp_ajax_inline_edit_save', array( &$this, 'inline_edit_save' ) ); 	// Save edits
		add_action( 'wp_ajax_bk_save_att_data', array( &$this, 'bk_save_att_data' ) ); 	// Save edits inner button
		add_action( 'wp_ajax_js_error', array( &$this, 'js_error' ) ); 					// Track js errors
		add_action( 'wp_ajax_app_export', array( &$this, 'export' ) ); 					// Export apps

		// Front end ajax hooks
		add_action( 'wp_ajax_pre_confirmation', array( &$this, 'pre_confirmation' ) ); 			// Get pre_confirmation results
		add_action( 'wp_ajax_nopriv_pre_confirmation', array( &$this, 'pre_confirmation' ) ); 	// Get pre_confirmation results
		add_action( 'wp_ajax_post_confirmation', array( &$this, 'post_confirmation' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_nopriv_post_confirmation', array( &$this, 'post_confirmation' ) ); // Do after final confirmation
		add_action( 'wp_ajax_app_custom_save_reserved', array( &$this, 'app_custom_save_reserved' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_nopriv_app_custom_save_reserved', array( &$this, 'app_custom_save_reserved' ) ); // Do after final confirmation

		// Backend three line ajax hooks
		add_action( 'wp_ajax_check_user', array( &$this, 'check_user' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_check_time_frame', array( &$this, 'check_time_frame' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_check_allinfo', array( &$this, 'check_allinfo' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_block', array( &$this, 'block' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_update_data_for_3line', array( &$this, 'update_data_for_3line' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_update_data_for_hok', array( &$this, 'update_data_for_hok' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_update_data_for_3line_unav', array( &$this, 'update_data_for_3line_unav' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_edit_block_function', array( &$this, 'edit_block_function' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_add_block_function', array( &$this, 'add_block_function' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_delete_user_app', array( &$this, 'delete_user_app' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_add_new_data', array( &$this, 'add_new_data' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_add_save_user', array( &$this, 'add_save_user' ) ); 		// Do after final confirmation
		add_action( 'wp_ajax_add_save_user_block', array( &$this, 'add_save_user_block' ) ); 		// Do after final confirmation
		//add_action( 'wp_ajax_nopriv_check_user', array( &$this, 'check_user' ) ); // Do after final confirmation

		add_action( 'wp_ajax_cancel_app', array( &$this, 'cancel' ) ); 							// Cancel appointment from my appointments
		add_action( 'wp_ajax_nopriv_cancel_app', array( &$this, 'cancel' ) ); 					// Cancel appointment from my appointments
		add_action('admin_init', array( &$this, 'save_data_ture_time' ));
		//Add default Time 
		add_action('admin_init', array($this, 'default_hours'), 10);
		// API login after the options have been initialized
		add_action('init', array($this, 'setup_api_logins'), 10);
		
		// Widgets
		require_once( $this->plugin_dir . '/includes/widgets.php' );
		add_action( 'widgets_init', array( &$this, 'widgets_init' ) );

		// Buddypress
		require_once($this->plugin_dir . '/includes/class_app_buddypress.php');
		if (class_exists('App_BuddyPress')) App_BuddyPress::serve();

		// Membership2 Integration
		$m2_integration = $this->plugin_dir . '/includes/class_app_membership2.php';
		if ( file_exists( $m2_integration ) ) {
			require_once $m2_integration;
		}

		// Caching
		if ( 'yes' == @$this->options['use_cache'] ) {
			add_filter( 'the_content', array( &$this, 'pre_content' ), 8 );				// Check content before do_shortcode
			add_filter( 'the_content', array( &$this, 'post_content' ), 100 );			// Serve this later than do_shortcode
			add_action( 'wp_footer', array( &$this, 'save_script' ), 8 );				// Save script to database
			add_action( 'permalink_structure_changed', array( &$this, 'flush_cache' ) );// Clear cache in case permalink changed
			add_action( 'save_post', array( &$this, 'save_post' ), 10, 2 ); 			// Clear cache if it has shortcodes
		}
		$this->pages_to_be_cached = array();
		$this->had_filter = false; // There can be a wpautop filter. We will check this later on.

		// Membership integration
		$this->membership_active = false;
		add_action( 'plugins_loaded', array( &$this, 'check_membership_plugin') );

		// Marketpress integration
		$this->marketpress_active = $this->mp = false;
		$this->mp_posts = array();
		add_action( 'plugins_loaded', array( &$this, 'check_marketpress_plugin') );

		$this->gcal_api = false;
		add_action('init', array($this, 'setup_gcal_sync'), 10);

		// Database variables
		global $wpdb;
		$this->db 					= &$wpdb;
		$this->wh_table 			= $wpdb->prefix . "app_working_hours";
		$this->exceptions_table 	= $wpdb->prefix . "app_exceptions";
		$this->services_table 		= $wpdb->prefix . "app_services";
		$this->workers_table 		= $wpdb->prefix . "app_workers";
		$this->app_table 			= $wpdb->prefix . "app_appointments";
		$this->app_appointments_custom 			= $wpdb->prefix . "app_appointments_custom";
		$this->transaction_table 	= $wpdb->prefix . "app_transactions";
		$this->cache_table 			= $wpdb->prefix . "app_cache";
		// DB version
		$this->db_version 			= get_option( 'app_db_version' );

		// Set log file location
		$uploads = wp_upload_dir();
		if ( isset( $uploads["basedir"] ) )
			$this->uploads_dir 	= $uploads["basedir"] . "/";
		else
			$this->uploads_dir 	= WP_CONTENT_DIR . "/uploads/";
		$this->log_file 		= $this->uploads_dir . "appointments-log.txt";

		// Other default settings
		$this->script = $this->uri = $this->error_url = '';
		$this->location = $this->service = $this->worker = 0;
		$this->gcal_image = '<img src="' . $this->plugin_url . '/images/gc_button1.gif" />';
		$this->locale_error = false;

		// Create a salt, if it doesn't exist from the previous installation
		if ( !$salt = get_option( "appointments_salt" ) ) {
			$salt = mt_rand();
			add_option( "appointments_salt", $salt ); // Save it to be used until it is cleared manually
		}
		$this->salt = $salt;

		// Deal with zero-priced appointments auto-confirm
		if ('yes' == $this->options['payment_required'] && !empty($this->options['allow_free_autoconfirm'])) {
			if (!defined('APP_CONFIRMATION_ALLOW_FREE_AUTOCONFIRM')) define('APP_CONFIRMATION_ALLOW_FREE_AUTOCONFIRM', true);
		}
	}
	

	function default_hours(){
		if($_POST['default_submit_hours']){
			
			$option_name = 'default_hours' ;
				$new_value = maybe_serialize($_POST['default_hours']) ;
				
				if ( get_option( $option_name ) !== false ) {
				
					// The option already exists, so we just update it.
					update_option( $option_name, $new_value );
				
				} else {
				
					// The option hasn't been added yet. We'll add it with $autoload set to 'no'.
					$deprecated = null;
					$autoload = 'no';
					add_option( $option_name, $new_value, $deprecated, $autoload );
				}
			
			}
		}
	
	
	function app_create_post(){
	//print_r($_POST);
	if(!empty($_REQUEST['submit_insu'])){
		$fname = $_REQUEST['fname'];
		$lname = $_REQUEST['lname'];
		$uni_id = uniqid();
			$my_post = array(
				//'ID' => '25', // Post id is required to update the post.
				'post_title' => $_REQUEST['fname'] .' '.$_REQUEST['lname'],
				'post_name' => $uni_id,
				'post_status' => 'publish',
				'post_type' => 'insurance_waiver',
				//'post_author' => 1,
				//'post_category' => array(7)
			);
			$newpost_id=wp_insert_post( $my_post );
			if($newpost_id!=0)
			{
				$array = $_POST;
				foreach ($array as $key=> $arr){
				add_post_meta ($newpost_id, $key, $arr);
					
					}
				
/*				add_post_meta ($newpost_id, 'initial_1', $_REQUEST['initial_1']);
				add_post_meta ($newpost_id, 'initial_2', $_REQUEST['initial_2']);
				add_post_meta ($newpost_id, 'initial_3', $_REQUEST['initial_3']);
				add_post_meta ($newpost_id, 'initial_4', $_REQUEST['initial_4']);
				add_post_meta ($newpost_id, 'initial_5', $_REQUEST['initial_5']);
				add_post_meta ($newpost_id, 'fname', $_REQUEST['fname']);
				add_post_meta ($newpost_id, 'lname', $_REQUEST['lname']);
				add_post_meta ($newpost_id, 'cnumber', $_REQUEST['cnumber']);
				add_post_meta ($newpost_id, 'signature', $_REQUEST['signature']);
				add_post_meta ($newpost_id, 'email_address', $_REQUEST['email_address']);
				add_post_meta ($newpost_id, 'legal_guardian_day', $_REQUEST['legal_guardian_day']);
				add_post_meta ($newpost_id, 'legal_guardian_month', $_REQUEST['legal_guardian_month']);
				add_post_meta ($newpost_id, 'legal_guardian_year', $_REQUEST['legal_guardian_year']);
*/



			}
	}
		
	
	
}


	function get_provider(){
			global $wpdb;
			$users = $wpdb->get_results( 'SELECT * FROM `wp_users`');
				$html = '<select id="select_provider" name="select_provider">';
			foreach ( $users as $user ) {
				
						$html .= '<option value="'.$user->ID.'">'.$user->display_name  . '</option>';
				 
			}
				$html .= '</select>';
				 
				 return  $html;
		}






	//edit block for appointment listing section
	function add_block_function(){
		if(!empty($_POST['id']) and !empty($_POST['date']))
		$id = $_POST['id'];
		$date = $_POST['date'];
		global $wpdb;
		$user_ID = $id;
		$data = $date . ':' . $user_ID; 
			//$button_value = 'Add New';
			$form = '<form method="post" id="form"  class="form_block" p_id=""><table><h3>Date</h3><p><input id="date_time" data-provide="datepicker" data-date-format="yyyy-mm-dd"></p><h3>Provider</h3><p>'.$this->get_provider().'</p><h3>Start Hours:</h3><tr><td><p>Start:</p><select name="open[Sunday][start]" id="start_start" autocomplete="off">'.$this->option_array('07:30', array('start'=>'07:30', 'end'=>'23:30')).'</select></td><td><p>End:</p><select id="start_end" name="open[Sunday][end]" autocomplete="off">'.$this->option_array('07:00', array('start'=>'07:30', 'end'=>'23:30')).'</select></td></tr></table><table><h3>Break Hours:</h3><tr><td><strong>Enable Break Hours:</strong> <select id="enable_break">'.$this->option_break_enable('1').'</select></td></tr><tr><td><p>Start:</p><select id="break_start" name="close[Sunday][start]" autocomplete="off">'.$this->option_array('07:30', array('start'=>'00:00', 'end'=>'23:30')).'</select></td><td><p>End:</p><select name="open[Sunday][end]" id="break_end" autocomplete="off">'.$this->option_array('00:00', array('start'=>'00:00', 'end'=>'23:30')).'</select></td></tr></table></form>';
		$reply_array = array(
							'date'	=> $date,
							'user_ID'	=> $user_ID,
							'button_value'	=> $button_value,
							'data'		=> $data,
							'form'			=>$form,
							'check'		=> $user_count
						);

		//$reply_array = apply_filters( 'app_pre_confirmation_reply', $reply_array );

		die( json_encode( $reply_array ));
		
		}






	//edit block for appointment listing section
	function edit_block_function(){
		if(!empty($_POST['id']) and !empty($_POST['date']))
		$id = $_POST['id'];
		$date = $_POST['date'];
		global $wpdb;
		$user_ID = $id;
		$data = $date . ':' . $user_ID; 
		if(current_user_can('hooker')){
		$user_count = $wpdb->get_row( "SELECT * FROM wp_app_hooker WHERE `date` = '$date%' AND ID = $user_ID " );
		}else{
		$user_count = $wpdb->get_row( "SELECT * FROM wp_three_line_appointment WHERE `date` = '$date%' AND ID = $user_ID " );
		}
		if($user_count != NULL){
			//$button_value = 'Edit';
			$form = '<form method="post" id="form" class="form_block" p_id="'.$id.'"><table><h3>Start Hours:</h3><tr><td><p>Start:</p><select name="open[Sunday][start]" id="start_start" autocomplete="off">'.$this->option_array($user_count->working_hours_start, array('start'=>'07:30', 'end'=>'23:30')).'</select></td><td><p>End:</p><select id="start_end" name="open[Sunday][end]" autocomplete="off">'.$this->option_array($user_count->working_hours_end, array('start'=>'07:30', 'end'=>'23:30')).'</select></td></tr></table><table><h3>Break Hours:</h3><tr><td><strong>Enable Break Hours: </strong><select id="enable_break">'.$this->option_break_enable($user_count->break_enable, array('start'=>'07:30', 'end'=>'23:30')).'</select></td></tr><tr><td><p>Start:</p><select id="break_start" name="close[Sunday][start]" autocomplete="off">'.$this->option_array($user_count->break_start, array('start'=>'07:30', 'end'=>'23:30')).'</select></td><td><p>End:</p><select name="open[Sunday][end]" id="break_end" autocomplete="off">'.$this->option_array($user_count->break_end, array('start'=>'07:30', 'end'=>'23:30')).'</select></td></tr></table></form>';
			}else{
			//$button_value = 'Add New';
			$form = '<form method="post" id="form"  class="form_block" p_id="'.$id.'"><table><h3>Start Hours:</h3><tr><td><p>Start:</p><select name="open[Sunday][start]" id="start_start" autocomplete="off">'.$this->option_array('07:30', array('start'=>'07:30', 'end'=>'23:30')).'</select></td><td><p>End:</p><select id="start_end" name="open[Sunday][end]" autocomplete="off">'.$this->option_array('07:30', array('start'=>'07:30', 'end'=>'23:30')).'</select></td></tr></table><table><h3>Break Hours:</h3><tr><td><strong>Enable Break Hours:</strong> <select id="enable_break">'.$this->option_break_enable('1').'</select></td></tr><tr><td><p>Start:</p><select id="break_start" name="close[Sunday][start]" autocomplete="off">'.$this->option_array('07:30', array('start'=>'07:30', 'end'=>'23:30')).'</select></td><td><p>End:</p><select name="open[Sunday][end]" id="break_end" autocomplete="off">'.$this->option_array('07:30', array('start'=>'07:30', 'end'=>'23:30')).'</select></td></tr></table><p id="submit_3line_hours" class="button-primary">Submit</p></form>';
			}
		$reply_array = array(
							'date'	=> $date,
							'user_ID'	=> $user_ID,
							'button_value'	=> $button_value,
							'data'		=> $data,
							'form'			=>$form,
							'check'		=> $user_count
						);

		//$reply_array = apply_filters( 'app_pre_confirmation_reply', $reply_array );

		die( json_encode( $reply_array ));
		
		}
	
	//block function for appointmentlinsting.php
	
	function block(){
		global $wpdb;
		$data = $_POST['data'];
		$query = $wpdb->get_row( "SELECT * FROM `wp_three_line_appointment` WHERE `ID` = $data" );
		if($query->block=='0'){
			$result = $wpdb->query("UPDATE `wp_three_line_appointment` SET block = 1 WHERE ID = $query->ID ");
				$pass = 'background-color: #32E007;    border: 1px solid #32E007;    padding: 2px 10px;    border-radius: 3px;    color: #FFF;';
		}elseif($query->block=='1'){
			$result = $wpdb->query("UPDATE `wp_three_line_appointment` SET block = 0 WHERE ID = $query->ID ");
				$pass = 'background-color: #FD4444;    border: 1px solid #FD4444;    padding: 2px 10px;    border-radius: 3px;    color: #FFF;';
			}
		
		die( json_encode( array('result'=>$pass) ));	
			
		} 
	
	function attendees_fields_form_jquery($i){
		$script .= 'var att_name_'.$i.' = $("#att_name_'.$i.'").val();';
		$script .= 'var att_age_'.$i.' = $("#att_age_'.$i.'").val();';
		$script .= 'var att_weight_'.$i.' = $("#att_weight_'.$i.'").val();';
		$script .= 'var att_wev_'.$i.' = $("#att_wev_'.$i.'").val();';
		
		return $script;
		}


	function attendees_fields_jquery($arr){
			
			if(!empty($arr)){
				$i = 1;
				$output = '';
				while ($i<=$arr){
					$output .= $this->attendees_fields_form_jquery($i);
					$i++;
					
					}
				}
				
				return $output;
		}




	function attendees_fields_form_ajax($i){
		$script = '';
		$script .= '"+att_name_'.$i.'+"|"+att_age_'.$i.'+"|"+att_weight_'.$i.'+"|"+att_wev_'.$i.'+"';		
		
		return $script;
		}


	function attendees_fields_ajax($arr){
			
			if(!empty($arr)){
				$i = 1;
				$output = '';
				while ($i<=$arr){ 
					if($i>1){
						$output .= '!';
				//	$output .= '"+att_name_'.$i.'+"|"+att_age_'.$i.'+"|"+att_weight_'.$i.'+"';	;
					$output .= '"+att_name_'.$i.'+"|"+att_age_'.$i.'+"|"+att_weight_'.$i.'+"|"+att_wev_'.$i.'+"';		
						}else{
				//	$output .= '"+att_name_'.$i.'+"|"+att_age_'.$i.'+"|"+att_weight_'.$i.'+"';	;
					$output .= '"+att_name_'.$i.'+"|"+att_age_'.$i.'+"|"+att_weight_'.$i.'+"|"+att_wev_'.$i.'+"';		
						}
					$i++;
	
					
					}
				}
				
				return $output;
		}





	function attendees_fields_form($i){
			$output = '';
			$output = '<h3>Attendees Information - '.$i.'</h3>';
			$output .= '<div>Name:</div><div><input class="f_name_'.$i.'" type="text" name="att_name_'.$i.'" id="att_name_'.$i.'" value="" required></div>';
			$output .= '<div>Age:</div><div><input class="f_age_'.$i.'" type="text" name="att_age_'.$i.'" id="att_age_'.$i.'" value=""></div>';
			$output .= '<div>Weight:</div><div><input  class="f_weight_'.$i.'" type="text" name="att_weight_'.$i.'" id="att_weight_'.$i.'" value=""></div>';
			$output .= '<div>Insurance Waiver:</div><div><input type="text"  class="f_url_'.$i.'" name="att_wev_'.$i.'" id="att_wev_'.$i.'" value=""></div><div><a href="/insurance/" target="_blank">Click here to generate your Insurance Waiver. </a></div>';
			return $output;
		}



	function validator_generate_var($i){
		$output ='';
		
		$output .="var f_name_".$i." = $('.f_name_".$i."').val();";
		
		$output .="var f_age_".$i." = $('.f_age_".$i."').val();";
		
		$output .="var f_weight_".$i." = $('.f_weight_".$i."').val();";
		
		$output .="var att_wev_".$i." = $('.att_wev_".$i."').val();";
		
		return $output;
		}
		
		
	function validator_generate($i){
		$output ='';
		
		$output .="if(f_name_".$i."==null){
            alert('Please enter the name field')
        }
		
		";
		
		$output .="
        else if(f_age_".$i."==null){
            alert('Please enter the age field')
        }
		
		";
		
		$output .="
        else if(f_weight_".$i."==null){
            alert('Please enter the weight field')
        }
		
		";
		
		$output .="
        else if(att_wev_".$i."==null){
            alert('Please enter the Insurance Waiver URL')
        }
		
		";
		
		return $output;
		}
		
		

	function attendees_validate_fields($arr){
			
			if(!empty($arr)){
				$i = 1;
				$output = '';
				while ($i<=$arr){
					$output .= $this->validator_generate_var($i);
					$output .= $this->validator_generate($i);
					$i++;
					
					}
				}
				
				return $output;
		}






	function attendees_fields($arr){
			
			if(!empty($arr)){
				$i = 1;
				$output = '';
				while ($i<=$arr){
					$output .= $this->attendees_fields_form($i);
					$i++;
					
					}
				}
				
				return $output;
		}

	function get_event_dates(){
		global $wpdb;
		
		$user_ID = get_current_user_id();
		if(current_user_can('hooker')){
		$user_count = $wpdb->get_results( "SELECT * FROM wp_app_hooker WHERE worker= '$user_ID'" );
		}else{
		$user_count = $wpdb->get_results( "SELECT * FROM wp_three_line_appointment WHERE worker= '$user_ID'" );
			}
		 $initTime = date("Y")."-".date("m")."-".date("d")." ".date("H").":00:00";
		if(!empty($user_count) and is_array($user_count)){
			foreach($user_count as $u){
			$htm .= '{ "date": "'.$u->date.' '.$u->working_hours_start. ':00 ", "type": "meeting"}, ';
			}
		}
		
		return $htm;
		}


	function get_event_dates_all(){
		global $wpdb;
		
		$user_count = $wpdb->get_results( "SELECT * FROM wp_app_appointments" );
		 $initTime = date("Y")."-".date("m")."-".date("d")." ".date("H").":00:00";
		if(!empty($user_count) and is_array($user_count)){
			foreach($user_count as $u){
			//$htm .= '{ "date": "'.$u->start.' ", "type": "appointment"}, ';
			}
		}
		
		return $htm;
		}



	function option_break_enable($select=null){
			$array = array(
				'1' => 'No',
				'0' => 'Yes',
			);
		
			foreach($array as $key=>$value){
				$output .='<option '.$select.' value="'.$key.'"';
				if($select==$key) {
				$output .='selected="selected"';	
					}
				$output .='>'.$value.'</option>';
				
				}
			return $output;	
		}


	function get_day_limitation($date){
		global $wpdb;
		$time_frame = $wpdb->get_row( "SELECT * FROM wp_app_time_frame WHERE `date` = '$date%'" );
		$time = json_decode($time_frame->time);
		if(empty($time_frame)){
				if(!empty($time)){
				return $time;
				}
			}
		
		}
	
	
	function option_array($select, $restriction = array()){
			$array = array(
				'00:00' => '12:00 am',
				//'00:30' => '12:30 am',
				//'01:00' => '1:00 am',
				'01:30' => '1:30 am',
				//'02:00' => '2:00 am',
				//'02:30' => '2:30 am',
				'03:00' => '3:00 am',
				//'03:30' => '3:30 am',
				//'04:00' => '4:00 am',
				'04:30' => '4:30 am',
				//'05:00' => '5:00 am',
				//'05:30' => '5:30 am',
				'06:00' => '6:00 am',
				//'06:30' => '6:30 am',
				//'07:00' => '7:00 am',
				'07:30' => '7:30 am',
				//'08:00' => '8:00 am',
				//'08:30' => '8:30 am',
				'09:00' => '9:00 am',
				//'09:30' => '9:30 am',
				//'10:00' => '10:00 am',
				'10:30' => '10:30 am',
				//'11:00' => '11:00 am',
				//'11:30' => '11:30 am',
				'12:00' => '12:00 pm',
				//'12:30' => '12:30 pm',
				//'13:00' => '1:00 pm',
				'13:30' => '1:30 pm',
				//'14:00' => '2:00 pm',
				//'14:30' => '2:30 pm',
				'15:00' => '3:00 pm',
				//'15:30' => '3:30 pm',
				//'16:00' => '4:00 pm',
				'16:30' => '4:30 pm',
				//'17:00' => '5:00 pm',
				//'17:30' => '5:30 pm',
				'18:00' => '6:00 pm',
				//'18:30' => '6:30 pm',
				//'19:00' => '7:00 pm',
				'19:30' => '7:30 pm',
				//'20:00' => '8:00 pm',
				//'20:30' => '8:30 pm',
				'21:00' => '9:00 pm',
				//'21:30' => '9:30 pm',
				//'22:00' => '10:00 pm',
				'22:30' => '10:30 pm',
				//'23:00' => '11:00 pm',
				//'23:30' => '11:30 pm',
				
			);
			
			foreach($array as $key=>$value){
				if($restriction != NULL and !empty($restriction)){
						if(!empty($restriction['start']) and !empty($restriction['end'])){
							if($restriction['start'] <= $key and $restriction['end'] >= $key){
							$output .='<option '.$select.' value="'.$key.'"';
							if($select==$key) {
							$output .='selected="selected"';	
								}
							$output .='>'.$value.'</option>';
							}
						}else{
							$output .='<option '.$select.' value="'.$key.'"';
							if($select==$key) {
							$output .='selected="selected"';	
								}
							$output .='>'.$value.'</option>';
							
						}
					}
			}
			return $output;	
		
		}


	function delete_user_app(){
				if(!empty($_POST["data"])){
				$data = $_POST["data"];
				$values 		= explode( ":", $data );
				$date = $values[0];
				$user_ID = $values[1];
				}
		global $wpdb;
		$user_ID = get_current_user_id();
		if(current_user_can('hooker')){
			$tb = 'wp_app_hooker';
		}else{
			$tb = 'wp_three_line_appointment';
			}
		$result = $wpdb->delete( $tb, 							
							array( 
								'worker' => $user_ID, 
								'date' => $date, 
							),
							array('%s')
					 );
					 
					 
					 
			if( $result != false){
				$reply_array = array(
					'result' => 'This schedule has been deleted.',
								'worker' => $user_ID, 
								'date' => $date, 
				);
				}else{
				$reply_array = array(
					'result' => 'We are unable to delete it.',
								'worker' => $user_ID, 
								'date' => $date, 
				);
				}	
						die( json_encode( $reply_array ));
		}
	
	
	function check_user(){
		if(!empty($_POST['date']))
		$date = $_POST['date'];
		
		global $wpdb;
		$user_ID = get_current_user_id();
		$data = $date . ':' . $user_ID; 
		if(current_user_can('hooker')){
		$user_count = $wpdb->get_row( "SELECT * FROM wp_app_hooker WHERE `date` = '$date%' AND worker LIKE '$user_ID' " );
		}else{
		$user_count = $wpdb->get_row( "SELECT * FROM wp_three_line_appointment WHERE `date` = '$date%' AND worker LIKE '$user_ID' " );
			}
		if($user_count != NULL){
			//$button_value = 'Edit';
			if($user_count->block=='0'){
			$form = '<form method="post" id="form"><table><h3>Start Hours:</h3><tr><td><p>Start:</p><select name="open[Sunday][start]" id="start_start" autocomplete="off">'.$this->option_array($user_count->working_hours_start).'</select></td><td><p>End:</p><select id="start_end" name="open[Sunday][end]" autocomplete="off">'.$this->option_array($user_count->working_hours_end).'</select></td></tr></table><table><h3>Break Hours:</h3><tr><td><strong>Enable Break Hours: </strong><select id="enable_break">'.$this->option_break_enable($user_count->break_enable).'</select></td></tr><tr><td><p>Start:</p><select id="break_start" name="close[Sunday][start]" autocomplete="off">'.$this->option_array($user_count->break_start).'</select></td><td><p>End:</p><select name="open[Sunday][end]" id="break_end" autocomplete="off">'.$this->option_array($user_count->break_end).'</select></td></tr></table><p id="submit_3line_hours" class="button-primary">Update</p><p id="submit_3line_hours_delete" class="button-primary" style="margin-left: 20px;">Delete</p></form>';
			}else{
			$form = '<form method="post" id="form"><strong>SORRY YOU DO NOT HAVE PERMISSION TO EDIT IT</strong></form>';	
				}
			}else{
			//$button_value = 'Add New';
			$form = '<form method="post" id="form"><table><h3>Start Hours:</h3><tr><td><p>Start:</p><select name="open[Sunday][start]" id="start_start" autocomplete="off">'.$this->option_array('00:00').'</select></td><td><p>End:</p><select id="start_end" name="open[Sunday][end]" autocomplete="off">'.$this->option_array('00:00').'</select></td></tr></table><table><h3>Break Hours:</h3><tr><td><strong>Enable Break Hours:</strong> <select id="enable_break">'.$this->option_break_enable('1').'</select></td></tr><tr><td><p>Start:</p><select id="break_start" name="close[Sunday][start]" autocomplete="off">'.$this->option_array('00:00').'</select></td><td><p>End:</p><select name="open[Sunday][end]" id="break_end" autocomplete="off">'.$this->option_array('00:00').'</select></td></tr></table><p id="submit_3line_hours" class="button-primary">Submit</p></form>';
			}
		$reply_array = array(
							'date'	=> $date,
							'user_ID'	=> $user_ID,
							'button_value'	=> $button_value,
							'data'		=> $data,
							'form'			=>$form,
							'check'		=> $user_count
						);

		//$reply_array = apply_filters( 'app_pre_confirmation_reply', $reply_array );

		die( json_encode( $reply_array ));
		
		}



	function save_data_ture_time(){
		
			if(!empty($_REQUEST['submit_hours']) and $_REQUEST['submit_hours'] == 'Submit'):
				if(!empty($_REQUEST['hours']))
				$hours = $_REQUEST['hours'];
				if(!empty($_REQUEST['date']))
				$date = $_REQUEST['date'];
				
				
				global $wpdb;
				$time_frame = $wpdb->get_row( "SELECT * FROM wp_app_time_frame WHERE `date` = '$date%'" );
				if($time_frame!= NULL){
				$result = $wpdb->update( 
							'wp_app_time_frame', 
							array( 
								'date' => $date, 
								'time' => json_encode($hours), 
							), 
							array( 'ID' => $time_frame->ID ), 
							array( 
								'%s',
								'%s',
							),
							array( '%d' ) 
						);
					}else{
				$result = $wpdb->insert( 
							'wp_app_time_frame', 
							array( 
								'date' => $date, 
								'time' => json_encode($hours), 
							), 
							array( 
								'%s',
							) 
						);
					}
				if($result){
					wp_redirect(admin_url('admin.php?page=tour-time&update=yes')); exit;
					
					}else{
					wp_redirect(admin_url('admin.php?page=tour-time&update=no')); exit;
						}
				
				
				
			endif;
		}



	//check time frame
	function check_time_frame(){
		if(!empty($_POST['date']))
		$date = $_POST['date'];
		
		global $wpdb;
		$time_frame = $wpdb->get_row( "SELECT * FROM wp_app_time_frame WHERE `date` = '$date%'" );
		//var_dump($time_frame);
			$form = '<form method="post" id="form"><table id="table_wapper">';
			
		if($time_frame != NULL){
			//$button_value = 'Edit';
			$time_decode = json_decode($time_frame->time);
			//$count = count($time_decode);
			$count = 0;
			//foreach($time_decode as $key=>$value){
			$form .='<tr id="counting_tr"><td><p>Start:</p><select name="hours[start]" id="start_start" autocomplete="off">'.$this->option_array($time_decode->start, array('start'=>'00:00', 'end'=>'23:30')).'</select></td><td><p>End:</p><select id="start_end" name="hours[end]" autocomplete="off">'.$this->option_array($time_decode->end, array('start'=>'00:00', 'end'=>'23:30')).'</select></td><td><p>&nbsp;</p><!--<a href="#" class="dashicons dashicons-plus-alt add_more_timeframe" counting="'.$count.'"></a>--></td></tr>';
		//	}
			
			}else{
			//$button_value = 'Add New';
			$form .= '<tr id="counting_tr"><td><p>Start:</p><select name="hours[start]" id="start_start" autocomplete="off">'.$this->option_array('00:00', array('start'=>'00:00', 'end'=>'23:30')).'</select></td><td><p>End:</p><select id="start_end" name="hours[end]" autocomplete="off">'.$this->option_array('00:00', array('start'=>'00:00', 'end'=>'23:30')).'</select></td><td><p>&nbsp;</p><!--<a href="#" class="dashicons dashicons-plus-alt add_more_timeframe" counting="'.$count.'"></a>--></td></tr>';
			//$count = 0;
			}
			
			$form .='</table><input type="submit" value="Submit" name="submit_hours" class="button-primary" /><input type="hidden" value="'.$date.'" name="date" /></form>';
			
		$reply_array = array(
							'date'	=> $date,
							'user_ID'	=> $user_ID,
							'button_value'	=> $button_value,
							'data'		=> $data,
							'form'			=>$form,
							'check'		=> $user_count
						);

		//$reply_array = apply_filters( 'app_pre_confirmation_reply', $reply_array );

		die( json_encode( $reply_array ));
		
		}




	function update_data_for_3line(){
		$time = $_POST['time'];
		$app_id = $_POST['app_id'];
		global $wpdb;
		$updaye = $wpdb->query("INSERT INTO `wp_worker_assinged_3line_table` (`ID`, `app_id`, `time`, `type`) VALUES (NULL, '$app_id', '$time', '2')" );
		
		if(!empty($updaye) or $updaye !=NULL or $updaye !=0){
			die(json_encode( array('result'=>'Thank You')));
			
			}else{
			die( json_encode( array('result'=>'2312')));
				
				}
		
		
	}
	


	function update_data_for_hok(){
		$time = $_POST['time'];
		$app_id = $_POST['app_id'];
		global $wpdb;
		$updaye = $wpdb->query("INSERT INTO `wp_worker_assinged_3line_table` (`ID`, `app_id`, `time`, `type`) VALUES (NULL, '$app_id', '$time', '3')" );
		
		if(!empty($updaye) or $updaye !=NULL or $updaye !=0){
			die(json_encode( array('result'=>'Thank You')));
			
			}else{
			die( json_encode( array('result'=>'2312')));
				
				}
		
		
	}
	
	


	
	function update_data_for_3line_unav(){
		$time = $_POST['time'];
		$app_id = $_POST['app_id'];
		$upp_id = $_POST['upp_id'];
		$type = $_POST['type'];
		global $wpdb;
		$updaye = $wpdb->query("DELETE FROM `wp_worker_assinged_3line_table` WHERE ID = '".$upp_id."' AND type LIKE '$type'" );
		
		if(!empty($updaye) or $updaye !=0){
			die(json_encode( array('result'=>'Thank You')));
			
			}else{
			die( json_encode( array('result'=>'2312')));
				
				}
		
		
	}




	function line_sec_hours($date, $start, $end){
			$start_db = strtotime($start);
			//$start_db = date ("H:i:s", $start_db);
			$end_db = strtotime($end);
			//$end_db = date ("H:i:s", $end_db);
		global $wpdb;
		$apps = $wpdb->get_results( "SELECT * FROM `wp_three_line_appointment` WHERE date = '$date'" );
		$apps_hook = $wpdb->get_results( "SELECT * FROM `wp_app_hooker` WHERE date = '$date'" );
		$real_apps = array_merge($apps, $apps_hook);
			$html = '';
			$form .= '<tr id="'.$start_db.'" start= "'.$start.'" end="'.$end.'">';
			$form .= '<th>';
			$form .= '<div>'.$start.'</div>';
			$form .= '</th>';
			$form .= '<th id="assinged">';
			if(!empty($apps) or $apps != NULL){
			$i=0;	
			foreach($real_apps as $worker_hour){	$i++;
			$wp_worker_assinged_3line_table = $wpdb->get_row( "SELECT * FROM `wp_worker_assinged_3line_table` WHERE time LIKE $start_db AND app_id = '$worker_hour->ID'" );
			
			if(strtotime ($worker_hour->working_hours_start) <= $start_db and $start_db <= strtotime ($worker_hour->working_hours_end) ){
					if($worker_hour->break_enable=='0'){
						if(strtotime ($worker_hour->break_start)<=$start_db and $start_db <= strtotime ($worker_hour->break_end) ){
							
							}else{
						global $wpdb;
						if($wp_worker_assinged_3line_table != 0 or !empty($wp_worker_assinged_3line_table) or $wp_worker_assinged_3line_table != null){
						$user_info = get_userdata($worker_hour->worker);
							if(!empty($user_info->user_login) or $user_info->user_login != NULL ){
						$form .= '<div class="btn btn-success"   id="'.$start_db.'_unassinged_'.$i.'" time="'.$start_db.'" app_id="'.$worker_hour->ID.'" upp_id="'.$wp_worker_assinged_3line_table->ID.'" type="'.$wp_worker_assinged_3line_table->type.'">'.$user_info->user_login .'</div>';
						}
						}
						$form .= '<script type="text/javascript">
							jQuery(document).ready(function($) {
								$( "#'.$start_db.'_unassinged_'.$i.'" ).click(function() {
								var time = $("#'.$start_db.'_unassinged_'.$i.'").attr( "time" );
								var app_id = $("#'.$start_db.'_unassinged_'.$i.'").attr( "app_id" );
								var upp_id = $("#'.$start_db.'_unassinged_'.$i.'").attr( "upp_id" );
								var type = $("#'.$start_db.'_unassinged_'.$i.'").attr( "type" );
								var updaate_3ln_data_upp = {action: "update_data_for_3line_unav", time: time, app_id: app_id, upp_id: upp_id, type: type,  nonce: "'.wp_create_nonce().'"};	
												$.post(ajaxurl, updaate_3ln_data_upp, function(response) {
														
															if ( response && response.error ){
																alert(response.result);
																
															}else{
																alert("Updated.");
																$( "#'.$start_db.'_unassinged_'.$i.'" ).addClass("my_clicked");
															}
													
													},"json");
													
									})
							})
							
							';
							
							$form .= '</script>';
						
					}
						
					}else{
						if($wp_worker_assinged_3line_table != 0 and !empty($wp_worker_assinged_3line_table)){
						$user_info = get_userdata($worker_hour->worker);
							if(!empty($user_info->user_login) or $user_info->user_login != NULL ){
							$form .= '<div class="btn btn-success" id="'.$start_db.'_unassinged_'.$i.'" time="'.$start_db.'" app_id="'.$worker_hour->ID.'" upp_id="'.$wp_worker_assinged_3line_table->ID.'" type="'.$wp_worker_assinged_3line_table->type.'">'.$user_info->user_login .'</div>';
							}
						}
						$form .= '<script type="text/javascript">
							jQuery(document).ready(function($) {
								$( "#'.$start_db.'_unassinged_'.$i.'" ).click(function() {
								var time = $("#'.$start_db.'_unassinged_'.$i.'").attr( "time" );
								var app_id = $("#'.$start_db.'_unassinged_'.$i.'").attr( "app_id" );
								var upp_id = $("#'.$start_db.'_unassinged_'.$i.'").attr( "upp_id" );
								var type = $("#'.$start_db.'_unassinged_'.$i.'").attr( "type" );
								var updaate_3ln_data_upp = {action: "update_data_for_3line_unav", time: time, app_id: app_id, upp_id: upp_id, type: type,  nonce: "'.wp_create_nonce().'"};	
												$.post(ajaxurl, updaate_3ln_data_upp, function(response) {
														
															if ( response && response.error ){
																alert(response.result);
																
															}else{
																alert("Updated.");
																$( "#'.$start_db.'_unassinged_'.$i.'" ).addClass("my_clicked");
															}
													
													},"json");
													
									})
							})
							
							';
							
							$form .= '</script>';
						
						
						}
				}
			}
			}else{
						$form .= '<div>N/A</div>';
			}
			
			$form .= '</th>';
			$form .= '<th>';
			
			if(!empty($apps) or $apps != NULL){
						$i = 0;
						foreach($apps as $worker_hour){	 $i++;
						$wp_worker_assinged_3line_table = $wpdb->get_row( "SELECT * FROM `wp_worker_assinged_3line_table` WHERE time LIKE $start_db AND app_id = '$worker_hour->ID'" );
						if(strtotime ($worker_hour->working_hours_start) <= $start_db and $start_db <= strtotime ($worker_hour->working_hours_end) ){
								if($worker_hour->break_enable=='0'){
									if(strtotime ($worker_hour->break_start)<=$start_db and $start_db <= strtotime ($worker_hour->break_end) ){
										
										}else{
										$user_ID= $worker_hour->worker;
										}
									
								}else{
								$user_ID = $worker_hour->worker;
									}
							}
						if($wp_worker_assinged_3line_table == 0 or empty($wp_worker_assinged_3line_table)){
						$user_info = get_userdata($user_ID);
							if(!empty($user_info->user_login) or $user_info->user_login != NULL ){
							$form .= '<div class="btn btn-info" id="'.$start_db.'_assinged_'.$i.'" time="'.$start_db.'" app_id="'.$worker_hour->ID.'">'.$user_info->user_login.'</div>';
							}
						}
							$form .= '<script type="text/javascript">
							jQuery(document).ready(function($) {
								$( "#'.$start_db.'_assinged_'.$i.'" ).click(function() {
								var time = $("#'.$start_db.'_assinged_'.$i.'").attr( "time" );
								var app_id = $("#'.$start_db.'_assinged_'.$i.'").attr( "app_id" );
								var updaate_3ln_data = {action: "update_data_for_3line", time: time, app_id: app_id, nonce: "'.wp_create_nonce().'"};	
												$.post(ajaxurl, updaate_3ln_data, function(response) {
														
															if ( response && response.error ){
																alert(response.result);
																
															}else{
																alert("Updated.");
																$( "#'.$start_db.'_assinged_'.$i.'" ).addClass("my_clicked");
															}
													
													},"json");
													
									})
							})
							
							';
							
							$form .= '</script>';
						
						}
						}else{
						$form .= '<div>N/A</div>';
						}			
			$form .= '</th>';
			$form .= '<th id="hooker_th">';
			
			
			if(!empty($apps_hook) or $apps_hook != NULL){
						$i = 0;
						foreach($apps_hook as $worker_hour){	 $i++;
						$wp_worker_assinged_3line_table = $wpdb->get_row( "SELECT * FROM `wp_worker_assinged_3line_table` WHERE `type` LIKE  '3' AND `time` LIKE  '$start_db' AND `app_id` = '$worker_hour->ID'" );
						if(strtotime ($worker_hour->working_hours_start) <= $start_db and $start_db <= strtotime ($worker_hour->working_hours_end) ){
								if($worker_hour->break_enable=='0'){
									if(strtotime ($worker_hour->break_start)<=$start_db and $start_db <= strtotime ($worker_hour->break_end) ){
										
										}else{
										$user_ID= $worker_hour->worker;
										}
									
								}else{
								$user_ID = $worker_hour->worker;
									}
							}
						if($wp_worker_assinged_3line_table == 0 or empty($wp_worker_assinged_3line_table)){
						$user_info = get_userdata($user_ID);
						if(!empty($user_info->user_login) or $user_info->user_login != NULL ){
						$form .= '<div class="btn btn-warning" id="'.$start_db.'_assinged_hok_'.$i.'" time="'.$start_db.'" app_id="'.$worker_hour->ID.'">'.$user_info->user_login.'</div>';
						}
						}
							$form .= '<script type="text/javascript">
							jQuery(document).ready(function($) {
								$( "#'.$start_db.'_assinged_hok_'.$i.'" ).click(function() {
								var time = $("#'.$start_db.'_assinged_hok_'.$i.'").attr( "time" );
								var app_id = $("#'.$start_db.'_assinged_hok_'.$i.'").attr( "app_id" );
								var updaate_hok_data = {action: "update_data_for_hok", time: time, app_id: app_id, nonce: "'.wp_create_nonce().'"};	
												$.post(ajaxurl, updaate_hok_data, function(response) {
														
															if ( response && response.error ){
																alert(response.result);
																
															}else{
																alert("Updated.");
																$( "#'.$start_db.'_assinged_hok_'.$i.'" ).addClass("my_clicked");
																/*var parr_id = $("#'.$start_db.'_assinged_hok_'.$i.'").parent().parent().attr("id");
																var parr_id = "#"+parr_id+" > #assinged";
																//var real_trans = (parr_id).children();
																
																$("#'.$start_db.'_assinged_hok_'.$i.'").remove();
																
																$(parr_id).closest("#assinged").html("<div id=\''.$start_db.'_unassinged_'.$i.'\' time=\''.$start_db.'\' app_id=\''.$worker_hour->ID.'\' upp_id=\''.$wp_worker_assinged_3line_table->ID.'\' type=\''.$wp_worker_assinged_3line_table->type.'\'>'.$user_info->user_login .'</div> ");*/
																
																
															}
													
													},"json");
													
									})
							})
							
							';
							
							$form .= '</script>';
						
						}
						}else{
						$form .= '<div>N/A</div>';
						}			
			
			
			
			$form .= '</th>';
			$form .= '</tr>';
			
			
			
			
			
			
			return $form;
		
		}
		
		
	function show_all_availavla_time_this_date($date){
			global $wpdb;
			$apps = $wpdb->get_results( "SELECT * FROM `wp_three_line_appointment` WHERE date = '$date'" );
			$form .= '<h2 style="clear:both; padding-top:20px;">Tour Guides Available on this Day</h2>';
			$form .= '<table style="width:100%;border: 1px solid #CCC; margin-top: 20px;" class="widefat">';

			$form .='<thead>';
			$form .= '<tr style="background-color: #848181;">';
			$form .= '<th>';
			$form .= '<div class="bold">Tour Guide Name</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Start</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">End</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break Enable</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break Start</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break End</div>';
			$form .= '</th>';
			$form .= '</tr>';
			$form .='</thead>';
			
			$form .='<tfoot>';
			$form .= '<tr style="background-color: #848181;">';
			$form .= '<th>';
			$form .= '<div class="bold">Tour Guide Name</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Start</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">End</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break Enable</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break Start</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break End</div>';
			$form .= '</th>';
			$form .= '</tr>';
			$form .='</tfoot>';
			
			
			$form .='<tbody>';
			if(!empty($apps) or $apps != NULL){
			foreach($apps as $app){
			$form .= '<tr>';			


			$form .= '<th>';
			$user_info = get_userdata($app->worker);
			$form .= '<div>'. $user_info->user_login.'</div>';
			$form .= '</th>';			

			$form .= '<th>';
			$form .= '<div>'. date('h:i:s A', strtotime($app->working_hours_start)).'</div>';
			$form .= '</th>';			

			$form .= '<th>';
			$form .= '<div>'. date('h:i:s A', strtotime($app->working_hours_end)).'</div>';
			$form .= '</th>';			


			$form .= '<th>';
			if($app->break_enable=='1'){
			$form .= '<div>No</div>';
			}else{
			$form .= '<div>Yes</div>';
				}
			$form .= '</th>';			

			$form .= '<th>';
			$form .= '<div>'. date('h:i:s A', strtotime($app->break_start)).'</div>';
			$form .= '</th>';			

			$form .= '<th>';
			$form .= '<div>'. date('h:i:s A', strtotime($app->break_end)).'</div>';
			$form .= '</th>';			



			$form .= '<th>';
						
			$form .= '</th>';			



			$form .= '</tr>';			
			
			}
			
			}
			
			
			$form .='</tbody>';
			$form .='</table>';
		
		
		return $form;
		
		
		}	

	function show_all_hooker_time_this_date($date){
			global $wpdb;
			$apps = $wpdb->get_results( "SELECT * FROM `wp_app_hooker` WHERE date = '$date'" );
			$form .= '<h2 style="clear:both; padding-top:20px;">Hookers Available on this Day</h2>';
			$form .= '<table style="width:100%;border: 1px solid #CCC; margin-top: 20px;" class="widefat">';

			$form .='<thead>';
			$form .= '<tr style="background-color: #848181;">';
			$form .= '<th>';
			$form .= '<div class="bold">Tour Guide Name</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Start</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">End</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break Enable</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break Start</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break End</div>';
			$form .= '</th>';
			$form .= '</tr>';
			$form .='</thead>';
			
			$form .='<tfoot>';
			$form .= '<tr style="background-color: #848181;">';
			$form .= '<th>';
			$form .= '<div class="bold">Tour Guide Name</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Start</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">End</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break Enable</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break Start</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Break End</div>';
			$form .= '</th>';
			$form .= '</tr>';
			$form .='</tfoot>';
			
			
			$form .='<tbody>';
			if(!empty($apps) or $apps != NULL){
			foreach($apps as $app){
			$form .= '<tr>';			


			$form .= '<th>';
			$user_info = get_userdata($app->worker);
			$form .= '<div>'. $user_info->user_login.'</div>';
			$form .= '</th>';			

			$form .= '<th>';
			$form .= '<div>'. date('h:i:s A', strtotime($app->working_hours_start)).'</div>';
			$form .= '</th>';			

			$form .= '<th>';
			$form .= '<div>'. date('h:i:s A', strtotime($app->working_hours_end)).'</div>';
			$form .= '</th>';			


			$form .= '<th>';
			if($app->break_enable=='1'){
			$form .= '<div>No</div>';
			}else{
			$form .= '<div>Yes</div>';
				}
			$form .= '</th>';			

			$form .= '<th>';
			$form .= '<div>'. date('h:i:s A', strtotime($app->break_start)).'</div>';
			$form .= '</th>';			

			$form .= '<th>';
			$form .= '<div>'. date('h:i:s A', strtotime($app->break_end)).'</div>';
			$form .= '</th>';			



			$form .= '<th>';
						
			$form .= '</th>';			



			$form .= '</tr>';			
			
			}
			
			}
			
			
			$form .='</tbody>';
			$form .='</table>';
		
		
		return $form;
		
		
		}	


	function refresh_scr(){
		$form = ' <script>
					jQuery(document).ready(function($) {
						$( "#refresh_ev" ).click(function() {
							//alert($(this).attr( "date" ));
							
							var date_each = $(this).attr( "date" );
								var three_data = {action: "check_allinfo", date: date_each, nonce: "<?php wp_create_nonce() ?>"};	
								$.post(ajaxurl, three_data, function(response) {
										
											if ( response && response.error ){
												alert(response.error);
												
											}else{
												$( ".content_section" ).closest( ".content_section" ).remove();
												var button_place = response.button_value;	
												$(".button-place").html(response.button_value);
												$(".eventCalendar-subtitle").attr("data", response.data);
												$( "#apply_form" ).append( response.form );
											}
									
									},"json");
									
									

						});						
					});						
        </script>';
		return $form;
		}


	function check_allinfo(){
		if(!empty($_POST['date']))
		$date = $_POST['date'];
		
		$data = $date . ':' . $user_ID; 
		//if($user_count != NULL){
			//$button_value = 'Edit';
			$form = '';
			$form .= '<div class="content_section">';
			$form .= '<h1>'.date('l jS', strtotime($date)).'</h1>';
			$form .= '<h2>10-Line Schedule <button type="button" class="btn btn-primary eventCalendar-day" id="refresh_ev" date="'.$date.'" style="float:right;">Refresh List </button></h2>';
			$form .= $this->refresh_scr();
			$form .= '<table style="width:100%;border: 1px solid #CCC;margin-top: 20px;" class="widefat">';

			$form .='<thead>';
			$form .= '<tr style="background-color: #848181;">';
			$form .= '<th>';
			$form .= '<div class="bold">Time</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Assigned Tour Guides</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Available</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Hooker</div>';
			$form .= '</th>';
			$form .= '</tr>';
			$form .='</thead>';
			
			$form .='<tfoot>';
			$form .= '<tr style="background-color: #848181;">';
			$form .= '<th>';
			$form .= '<div class="bold">Time</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Assigned Tour Guides</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Available</div>';
			$form .= '</th>';
			$form .= '<th>';
			$form .= '<div class="bold">Hooker</div>';
			$form .= '</th>';
			$form .= '</tr>';
			$form .='</tfoot>';
			
			$form .='<tbody>';
			$form .= $this->line_sec_hours($date, '7:30am', '9:30am');
			$form .= $this->line_sec_hours($date, '9:00am', '10:30am');
			$form .= $this->line_sec_hours($date, '10:30am', '12:00pm');
			$form .= $this->line_sec_hours($date, '12:00pm', '1:30pm');
			$form .= $this->line_sec_hours($date, '1:30pm', '3:00pm');
			$form .= $this->line_sec_hours($date, '3:00pm', '4:30pm');
			$form .= $this->line_sec_hours($date, '4:30pm', '6:00pm');
			$form .= $this->line_sec_hours($date, '6:00pm', '7:30pm');
			$form .= $this->line_sec_hours($date, '7:30pm', '9:00pm');
				
			$form .='</tbody>';
				


			$form .= '</table>';
			$form .= '';
			$form .= '';
			
			$form .= $this->show_all_availavla_time_this_date($date);
			$form .= $this->show_all_hooker_time_this_date($date);
			
			
			$form .= '</div>';
		
		$reply_array = array(
							
							'form'			=>$form,
						);

		//$reply_array = apply_filters( 'app_pre_confirmation_reply', $reply_array );
		
		die( json_encode( $reply_array ));
		//}
		}







	function add_save_user(){
				
				if(!empty($_POST["start_start"]))
				$start_start = $_POST["start_start"];
				
				if(!empty($_POST["start_end"]))
				$start_end = $_POST["start_end"];
				
				
				
				if(!empty($_POST["break_start"]))
				$break_start = mysql_real_escape_string($_POST["break_start"]);
				
				
				if(!empty($_POST["break_end"]))
				$break_end = mysql_real_escape_string($_POST["break_end"]);
				
				if(!empty($_POST["enable_break"]))
				$enable_break = $_POST["enable_break"];
				
				
				if(!empty($_POST["data"])){
				$data = $_POST["data"];
				$values 		= explode( ":", $data );
				$date = $values[0];
				$user_ID = $values[1];
				}
				global $wpdb;
				if(current_user_can('hooker')){
				$user_count = $wpdb->get_row( "SELECT * FROM wp_app_hooker WHERE `date` = '$date' AND worker= '$user_ID'" );
				$tb = 'wp_app_hooker';
				}else{
				$user_count = $wpdb->get_row( "SELECT * FROM wp_three_line_appointment WHERE `date` = '$date' AND worker= '$user_ID'" );
				$tb = 'wp_three_line_appointment';
				}
				if($user_count==NULL){
					global $wpdb;
					$result = $wpdb->insert( 
							$tb, 
							array( 
								'worker' => $user_ID, 
								'date' => $date, 
								'working_hours_start' => $start_start, 
								'working_hours_end' => $start_end, 
								'break_enable' => $enable_break, 
								'break_start' => $break_start, 
								'break_end' => $break_end, 
							), 
							array( 
								'%s',
							) 
						);
							if($result):
								$reply_array = array(
									'success' => 'Your schedule has been added.'
										);
								endif;
						
						
					}else{
						global $wpdb;
					
					$result = $wpdb->update( 
							$tb, 
							array( 
								'worker' => $user_ID, 
								'date' => $date, 
								'working_hours_start' => $start_start, 
								'working_hours_end' => $start_end, 
								'break_enable' => $enable_break, 
								'break_start' => $break_start, 
								'break_end' => $break_end, 
							), 
								array( 'ID' => $user_count->ID ), 
							array( 
								'%s',
							) 
						);
						
						/*$result = $wpdb->query("UPDATE `wp_three_line_appointment` SET `worker` = '$user_ID', `date` = '$date', `working_hours_start` = '$start_start', `working_hours_end` = '$start_end', `break_enable` = '1', `break_start` = '1', `break_end` = '1' WHERE `wp_three_line_appointment`.`ID` = 39;");*/
						if($result):
							$reply_array = array(
								'success' => 'This schedule has been updated.',
								'worker' => $user_ID, 
								'date' => $date, 
								'working_hours_start' => $start_start, 
								'working_hours_end' => $start_end, 
								'break_enable' => $enable_break, 
								'break_hours_start' => $break_start, 
								'break_hours_start' => $break_end, 
									);
							endif;


					}
						die( json_encode( $reply_array ));
					
		
		}



	function add_save_user_block(){
				if(!empty($_POST["id"]))
				$id = $_POST["id"];

				if(!empty($_POST["start_start"]))
				$start_start = $_POST["start_start"];
				
				if(!empty($_POST["start_end"]))
				$start_end = $_POST["start_end"];
				
				if(!empty($_POST["date_time"]))
				$date_time = $_POST["date_time"];

				$today = date("Y-m-d");
				$today_time = strtotime($today);
				$get_date = strtotime($date_time);
				if ($get_date < $today_time) { 
				$reply_array = array(
								'error' => 'The Date is already Passed',
									);
									die( json_encode( $reply_array ));
				}
				
				
				if(!empty($_POST["select_provider"]))
				$select_provider = $_POST["select_provider"];
				
				
				
				if(!empty($_POST["break_start"]))
				$break_start = mysql_real_escape_string($_POST["break_start"]);
				
				
				if(!empty($_POST["break_end"]))
				$break_end = mysql_real_escape_string($_POST["break_end"]);
				
				if(!empty($_POST["enable_break"]))
				$enable_break = $_POST["enable_break"];
				
				
				
				
				
				global $wpdb;
				$user_count = $wpdb->get_row( "SELECT * FROM wp_three_line_appointment WHERE ID = ".$id."" );
				if($user_count != NULL){
						global $wpdb;
					$result = $wpdb->update( 
							'wp_three_line_appointment', 
							array( 
								//'worker' => $user_ID, 
							//	'date' => $date, 
								'working_hours_start' => $start_start, 
								'working_hours_end' => $start_end, 
								'break_enable' => $enable_break, 
								'break_start' => $break_start, 
								'break_end' => $break_end, 
							), 
								array( 'ID' => $user_count->ID ), 
							array( 
								'%s',
							) 
						);
											if(!empty($result) or $result != false):
							$reply_array = array(
								'success' => 'This schedule has been updated.',
							//	'worker' => $user_ID, 
							//	'date' => $date, 
								'working_hours_start' => $start_start, 
								'working_hours_end' => $start_end, 
								'break_enable' => $enable_break, 
								'break_hours_start' => $break_start, 
								'break_hours_start' => $break_end, 
									);
							endif;
	
						
			}else{
				
					if(empty($id)){
						$result = $wpdb->insert( 
							'wp_three_line_appointment', 
							array( 
								'worker' => $select_provider, 
								'date' => $date_time, 
								'working_hours_start' => $start_start, 
								'working_hours_end' => $start_end, 
								'break_enable' => $enable_break, 
								'break_start' => $break_start, 
								'break_end' => $break_end, 
								//'block' => '0', 
								//'assinged' => '0', 
							),
							array( 
								'%s',
							) 
						);
						
						if(!empty($result) or $result != false):
							$reply_array = array(
								'success' => 'New schedule is added',
							//	'worker' => $user_ID, 
							//	'date' => $date, 
								'working_hours_start' => $start_start, 
								'working_hours_end' => $start_end, 
								'break_enable' => $enable_break, 
								'break_hours_start' => $break_start, 
								'break_hours_start' => $break_end, 
									);
							endif;
						
						

					}
				
						/*$result = $wpdb->query("UPDATE `wp_three_line_appointment` SET `worker` = '$user_ID', `date` = '$date', `working_hours_start` = '$start_start', `working_hours_end` = '$start_end', `break_enable` = '1', `break_start` = '1', `break_end` = '1' WHERE `wp_three_line_appointment`.`ID` = 39;");*/


					}
						die( json_encode( $reply_array ));
					
		
		}



		
		
	function add_new_data(){
				if(!empty($_POST["data"]))
				$data = $_POST["data"];
				$values 		= explode( ":", $data );
				
				$date = $values[0];
				$user_ID = $values[1];
				if(current_user_can('hooker')){
				$user_count = $wpdb->get_row( "SELECT * FROM wp_app_hooker WHERE `date` = '$date' AND worker= '$user_ID'" );
				}else{
				$user_count = $wpdb->get_row( "SELECT * FROM wp_three_line_appointment WHERE `date` = '$date' AND worker= '$user_ID'" );
				}
				
				if($user_count==NULL){
					$row_form = 'yes';
					}
		
		}	
		
	

	function setup_gcal_sync () {
		// GCal Integration
		$this->gcal_api = false;
		// Allow forced disabling in case of emergency
		if ( !defined( 'APP_GCAL_DISABLE' ) ) {
			require_once $this->plugin_dir . '/includes/class.gcal.php';
			$this->gcal_api = new AppointmentsGcal();
		}
	}

	function setup_api_logins () {
		if (!@$this->options['accept_api_logins']) return false;

		add_action('wp_ajax_nopriv_app_facebook_login', array($this, 'handle_facebook_login'));
		add_action('wp_ajax_nopriv_app_get_twitter_auth_url', array($this, 'handle_get_twitter_auth_url'));
		add_action('wp_ajax_nopriv_app_twitter_login', array($this, 'handle_twitter_login'));
		add_action('wp_ajax_nopriv_app_ajax_login', array($this, 'ajax_login'));
		add_action('wp_ajax_nopriv_app_google_plus_login', array($this, 'handle_gplus_login'));

		// Google+ login
		if (!class_exists('LightOpenID')) {
			if( function_exists('curl_init') || in_array('https', stream_get_wrappers()) ) {
				include_once( $this->plugin_dir . '/includes/lightopenid/openid.php' );
				$this->openid = new LightOpenID;
			}
		}
		else
			$this->openid = new LightOpenID;

		if ( @$this->openid ) {

			if ( !session_id() )
				@session_start();

			add_action('wp_ajax_nopriv_app_get_google_auth_url', array($this, 'handle_get_google_auth_url'));
			add_action('wp_ajax_nopriv_app_google_login', array($this, 'handle_google_login'));

			$this->openid->identity = 'https://www.google.com/accounts/o8/id';
			$this->openid->required = array('namePerson/first', 'namePerson/last', 'namePerson/friendly', 'contact/email');
			if (!empty($_REQUEST['openid_ns'])) {
				$cache = $this->openid->getAttributes();
				if (isset($cache['namePerson/first']) || isset($cache['namePerson/last']) || isset($cache['contact/email'])) {
					$_SESSION['app_google_user_cache'] = $cache;
				}
			}
			if ( isset( $_SESSION['app_google_user_cache'] ) )
				$this->_google_user_cache = $_SESSION['app_google_user_cache'];
			else
				$this->_google_user_cache = '';
		}
	}





function receive_paypal(){
	
	if(!empty($_POST['custom'])){
		
		
		$values= explode( ",", $_POST["custom"] );
		//print_r($values);
		$worker 		= $values[0];
		$created 		= $values[1];
		$user 			= $values[2];
		$name 			= $values[3];
		$email 			= $values[4];
		$phone 			= $values[5];
		$address 		= $values[6];
		$city 			= $values[7];
		$location 		= $values[8];
		$service 		= $values[9];
		$price 			= $values[10];
		$coupon 		= $values[11];
		$status 		= $values[12];
		$start 			= $values[13];
		$end 			= $values[14];
		$note 			= $values[15];
		//print($worker);
		
		global $wpdb;
		$wpdb->query("INSERT INTO `wp_app_appointments_custom` (`ID`, `created`, `user`, `name`, `email`, `phone`, `address`, `city`, `location`, `service`, `worker`, `price`, `coupon`, `status`, `start`, `end`, `sent`, `sent_worker`, `note`, `gcal_ID`, `gcal_updated`) VALUES (NULL, ".$created.", ".$user.", ".$name.", $email, NULL, NULL, NULL, '0', '0', '1', '23', 'ssss', 'pending', '2015-07-16 00:00:00', '2015-07-30 00:00:00', NULL, NULL, NULL, NULL, NULL)" );
		

		
		if(!empty($worker)){
			$arrs= explode( "|", $worker );
			//print_r($arrs);
			global $wpdb;	
			foreach($arrs as $val){
				//print($val);
				global $wpdb;
				$wpdb->query("INSERT INTO `wp_app_appointments` (`ID`, `created`, `user`, `name`, `email`, `phone`, `address`, `city`, `location`, `service`, `worker`, `price`, `coupon`, `status`, `start`, `end`, `sent`, `sent_worker`, `note`, `gcal_ID`, `gcal_updated`) VALUES (NULL, ".$created.", ".$user.", ".$name.", $email, NULL, NULL, NULL, '0', '0', ".$val.", '23', 'ssss', 'pending', '2015-07-16 00:00:00', '2015-07-30 00:00:00', NULL, NULL, NULL, NULL, NULL)" );

				
				}
			
			}
		
		}



					//return $result;	


	
	}















/**
***************************************************************************************************************
* Methods for optimization
*
* $l: location ID - For future use
* $s: service ID
* $w: worker ID
* $stat: Status (open: working or closed: not working)
* IMPORTANT: This plugin is NOT intended for hundreds of services or service providers,
*  but it is intended to make database queries as cheap as possible with smaller number of services/providers.
*  If you have lots of services and/or providers, codes will not scale and appointments pages will be VERY slow.
*  If you need such an application, override some of the methods below with a child class.
***************************************************************************************************************
*/

	/**
	 * Get location, service, worker
	 */
	function get_lsw() {
		$this->location = $this->get_location_id();
		$this->service = $this->get_service_id();
		$this->worker = $this->get_worker_id();
	}

	/**
	 * Get location ID for future use
	 */
	function get_location_id() {
		if ( isset( $_REQUEST["app_location_id"] ) )
			return (int)$_REQUEST["app_location_id"];

		return 0;
	}

	/**
	 * Get smallest service ID
	 * We assume total number of services is not too high, which is the practical case.
	 * Otherwise this method might be expensive
	 * @return integer
	 */
	function get_first_service_id() {
		$min = wp_cache_get( 'min_service_id' );
		if ( false === $min ) {
			$services = $this->get_services();
			if ( $services ) {
				$min = 9999999;
				foreach ( $services as $service ) {
					if ( $service->ID < $min )
						$min = $service->ID;
				}
				wp_cache_set( 'min_service_id', $min );
			}
			else
				$min = 0; // No services ?? - Not possible but let's be safe
		}
		return apply_filters('app-services-first_service_id', $min);
	}

	/**
	 * Get service ID from front end
	 * @return integer
	 */
	function get_service_id() {
		if ( isset( $_REQUEST["app_service_id"] ) )
			return (int)$_REQUEST["app_service_id"];
		else if ( !$service_id = $this->get_first_service_id() )
			$service_id = 0;

		return $service_id;
	}

	/**
	 * Get worker ID from front end
	 * worker = provider
	 * @return integer
	 */
	function get_worker_id() {
		if ( isset( $_REQUEST["app_provider_id"] ) )
			return (int)$_REQUEST["app_provider_id"];

		if ( isset( $_REQUEST["app_worker_id"] ) )
			return (int)$_REQUEST["app_worker_id"];

		return 0;
	}

	/**
	 * Get all services
	 * @param order_by: ORDER BY clause for mysql
	 * @return array of objects
	 */
	function get_services( $order_by="ID" ) {
		$order_by = $this->sanitize_order_by( $order_by );
		$services = wp_cache_get( 'all_services_' . $order_by );
		if ( false === $services ) {
			$services = $this->db->get_results("SELECT * FROM " . $this->services_table . " ORDER BY ". esc_sql($order_by) ." " );
			wp_cache_set( 'all_services_' . $order_by, $services );
		}
		return $services;
	}

	 /**
	 * Allow only certain order_by clauses
	 * @since 1.2.8
	 */
	function sanitize_order_by( $order_by="ID" ) {
		$whitelist = apply_filters( 'app_order_by_whitelist', array( 'ID', 'name', 'start', 'end', 'duration', 'price',
					'ID DESC', 'name DESC', 'start DESC', 'end DESC', 'duration DESC', 'price DESC', 'RAND()' ) );
		if ( in_array( $order_by, $whitelist ) )
			return $order_by;
		else
			return 'ID';
	}

	/**
	 * Get a single service with given ID
	 * @param ID: Id of the service to be retrieved
	 * @return object
	 */
	function get_service( $ID ) {
		$service = wp_cache_get( 'service_'. $ID );
		if ( false === $service ) {
			$services = $this->get_services();
			if ( $services ) {
				foreach ( $services as $s ) {
					if ( $s->ID == $ID ) {
						$service = $s;
						break;
					}
				}
				wp_cache_set( 'service_'. $ID, $service );
			}
			else
				$service = null;
		}
		return $service;
	}

	/**
	 * Get services given by a certain worker
	 * @param w: ID of the worker
	 * @since 1.2.3
	 * @return array of objects
	 */
	function get_services_by_worker( $w ) {
		$services_by_worker = wp_cache_get( 'services_by_worker_' . $w );
		if ( false === $services_by_worker ) {
			$services_by_worker = array();
			$worker = $this->get_worker( $w );
			if ( $worker && is_object( $worker ) ) {
				$services_provided = $this->_explode( $worker->services_provided );
				asort( $services_provided ); // Sort by service ID from low to high
				foreach( $services_provided as $service_id ) {
					$services_by_worker[] = $this->get_service( $service_id );
				}
			}
			wp_cache_set( 'services_by_worker_' . $w , $services_by_worker );
		}
		return $services_by_worker;
	}

	/**
	 * Get all workers
	 * @param order_by: ORDER BY clause for mysql
	 * @return array of objects
	 */
	function get_workers( $order_by="ID" ) {
		$order_by = $this->sanitize_order_by( $order_by );
		$workers = wp_cache_get( 'all_workers_' . str_replace( ' ', '_', $order_by ) );
		if ( false === $workers ) {
			// Sorting by name requires special case
			if ( stripos( $order_by, 'name' ) !== false ) {
				$workers_ = $this->db->get_results("SELECT * FROM " . $this->workers_table . " " );
				if ( stripos( $order_by, 'desc' ) !== false )
					usort( $workers_, array( &$this, 'get_workers_desc' ) );
				else
					usort( $workers_, array( &$this, 'get_workers_asc' ) );
				$workers = $workers_;
			}
			else
				$workers = $this->db->get_results("SELECT * FROM " . $this->workers_table . " ORDER BY ". esc_sql($order_by) ." " );
			wp_cache_set( 'all_workers_' . str_replace( ' ', '_', $order_by ), $workers );
		}
		return $workers;
	}

	/**
	 * Helper function to sort workers in ascending order
	 * @since 1.1.9
	 * @return integer
	 */
	function get_workers_asc( $a, $b ) {
		return strcmp( $this->get_worker_name( $a->ID ), $this->get_worker_name( $b->ID ) );
	}

	/**
	 * Helper function to sort workers in descending order
	 * @since 1.1.9
	 * @return integer
	 */
	function get_workers_desc( $a, $b ) {
		return strcmp( $this->get_worker_name( $b->ID ), $this->get_worker_name( $a->ID ) );
	}

	/**
	 * Get a single worker with given ID
	 * @param ID: Id of the worker to be retrieved
	 * @return object
	 */
	function get_worker( $ID ) {
		$worker = null;
		$workers = $this->get_workers();
		if ( $workers ) {
			foreach ( $workers as $w ) {
				if ( $w->ID == $ID ) {
					$worker = $w;
					break;
				}
			}
		}
		return $worker;
	}

	/**
	 * Get workers giving a specific service (by its ID)
 	 * We assume total number of workers is not too high, which is the practical case.
	 * Otherwise this method would be expensive
	 * @param ID: Id of the service to be retrieved
	 * @param order_by: ORDER BY clause for mysql
	 * @return array of objects
	 */
	function get_workers_by_service( $ID, $order_by="ID" ) {
		//$order_by = $this->sanitize_order_by( $order_by );
		$workers_by_service = false;
		$workers = $this->get_workers( $order_by );
		if ( $workers ) {
			$workers_by_service = array();
			foreach ( $workers as $worker ) {
				if ( strpos( $worker->services_provided, ':'.$ID.':' ) !== false )
					$workers_by_service[] = $worker;
			}
		}
		return $workers_by_service;
	}

	/**
	 * Check if there is only one worker giving the selected service
	 * @param service: Id of the service for which check will be done
 	 * @since 1.1.1
	 * @return string (worker ID if there is one, otherwise 0)
	 */
	function is_single_worker( $service ) {
		$workers = $this->get_workers_by_service( $service );
		if ( $workers && 1 === count( $workers ) && is_object( $workers[0] ) ) {
			return $workers[0]->ID;
		}
		else return 0;
	}

	/**
	 * Return a row from working hours table, i.e. days/hours we are working or we have break
	 * @param stat: open (works), or closed (breaks).
	 * @return object
	 */
	function get_work_break( $l, $w, $stat ) {
		$work_break = null;
		$work_breaks = wp_cache_get( 'work_breaks_'. $l . '_' . $w );

		if ( false === $work_breaks ) {
			$work_breaks = $this->db->get_results( $this->db->prepare("SELECT * FROM {$this->wh_table} WHERE worker=%d AND location=%d", $w, $l) );
			wp_cache_set( 'work_breaks_'. $l . '_' . $w, $work_breaks );
		}
		if ( $work_breaks ) {
			foreach ( $work_breaks as $wb ) {
				if ( $wb->status == $stat ) {
					$work_break = $wb;
					break;
				}
			}
		}
		return $work_break;
	}

	/**
	 * Return a row from exceptions table, i.e. days we are working or having holiday
	 * @return object
	 */
	function get_exception( $l, $w, $stat ) {
		$exception = null;
		$exceptions = wp_cache_get( 'exceptions_'. $l . '_' . $w );
		if ( false === $exceptions ) {
			$exceptions = $this->db->get_results( $this->db->prepare("SELECT * FROM {$this->exceptions_table} WHERE worker=%d AND location=%d", $w, $l) );
			wp_cache_set( 'exceptions_'. $l . '_' . $w, $exceptions );
		}
		if ( $exceptions ) {
			foreach ( $exceptions as $e ) {
				if ( $e->status == $stat ) {
					$exception = $e;
					break;
				}
			}
		}
		return $exception;
	}

	/**
	 * Return an appointment given its ID
	 * @param app_id: ID of the appointment to be retreived from database
	 * @since 1.1.8
	 * @return object
	 */
	function get_app( $app_id ) {
		if ( !$app_id )
			return false;
		$app = wp_cache_get( 'app_'. $app_id );
		if ( false === $app ) {
			$app = $this->db->get_row( $this->db->prepare("SELECT * FROM {$this->app_table} WHERE ID=%d", $app_id) );
			wp_cache_set( 'app_'. $app_id, $app );
		}
		return $app;
	}

	/**
	 * Return all reserve appointments (i.e. pending, paid, confirmed or reserved by GCal)
	 * @param week: Optionally appointments only in the number of week in ISO 8601 format (since 1.2.3).
	 * Weekly gives much better results in RAM usage compared to monthly, with a tolerable, slight increase in number of queries
	 * @return array of objects
	 */
	function get_reserve_apps( $l, $s, $w, $week=0 ) {
		$apps = wp_cache_get( 'reserve_apps_'. $l . '_' . $s . '_' . $w . '_' . $week );
		if ( false === $apps ) {
			$location = $l ? "location='" . $this->db->escape($location) . "' AND" : '';
			if ( 0 == $week ) {
				$apps = $this->db->get_results($this->db->prepare(
					"SELECT * FROM {$this->app_table} " .
						"WHERE {$location} service=%d AND worker=%d " .
					"AND (status='pending' OR status='paid' OR status='confirmed' OR status='reserved')",
					$s, $w)
				);
			} else {
// @FIX: Problem: an appointment might already be ticked as "completed",
// because of it's start time being in the past. Its end time, however, can still easily be
// in the future. For long-running appointments (e.g. 2-3h) this could break the schedule slots
// and show a registered- and paid for- slot as "available", when it's actually not.
// E.g. http://premium.wpmudev.org/forums/topic/appointments-booking-conflictoverlapping-bookings
				$apps = $this->db->get_results($this->db->prepare(
					"SELECT * FROM {$this->app_table} " .
					"WHERE {$location} service=%d AND worker=%d " .
					//" AND (status='pending' OR status='paid' OR status='confirmed' OR status='reserved') AND WEEKOFYEAR(start)=".$week. " " ); // THIS IS A PROBLEM! It doesn't take into account the completed events ALTHOUGH they may very well still be there
					"AND (status='pending' OR status='paid' OR status='confirmed' OR status='reserved' OR status='completed') AND WEEKOFYEAR(start)=%d",
					$s, $w, $week)
				);
// *ONLY* applied to weekly-scoped data gathering, because otherwise this would possibly
// return all kinds of irrelevant data (appointments passed LONG time ago).
// End @FIX
			}
			wp_cache_set( 'reserve_apps_'. $l . '_' . $s . '_' . $w . '_' . $week, $apps );
		}
		return $apps;
	}

	/**
	 * Return all reserve appointments by worker ID
	 * @param week: Optionally appointments only in the number of week in ISO 8601 format (since 1.2.3)
	 * @return array of objects
	 */
	function get_reserve_apps_by_worker( $l, $w, $week=0 ) {
		$apps = wp_cache_get( 'reserve_apps_by_worker_'. $l . '_' . $w . '_' . $week );
		if ( false === $apps ) {
			$services = $this->get_services();
			if ( $services ) {
				$apps = array();
				foreach ( $services as $service ) {
					$apps_worker = $this->get_reserve_apps( $l, $service->ID, $w, $week );
					if ( $apps_worker )
						$apps = array_merge( $apps, $apps_worker );
				}
			}
			wp_cache_set( 'reserve_apps_by_worker_'. $l . '_' . $w . '_' . $week, $apps );
		}
		return $apps;
	}

	/**
	 * Return reserve appointments by service ID
	 * @param week: Optionally appointments only in the number of week in ISO 8601 format (since 1.2.3)
	 * @since 1.1.3
	 * @return array of objects
	 */
	function get_reserve_apps_by_service( $l, $s, $week=0 ) {
		$apps = wp_cache_get( 'reserve_apps_by_service_'. $l . '_' . $s . '_' . $week );
		if ( false === $apps ) {
			$workers = $this->get_workers_by_service( $s );
			$apps = array();
			if ( $workers ) {
				foreach ( $workers as $worker ) {
					$apps_service = $this->get_reserve_apps( $l, $s, $worker->ID, $week );
					if ( $apps_service )
						$apps = array_merge( $apps, $apps_service );
				}
			}
			// Also include appointments by general staff for this service
			$apps_service_0 = $this->get_reserve_apps( $l, $s, 0, $week );
			if ( $apps_service_0 )
				$apps = array_merge( $apps, $apps_service_0 );

			// Remove duplicates
			$apps = $this->array_unique_object_by_ID( $apps );

			wp_cache_set( 'reserve_apps_by_service_'. $l . '_' . $s . '_' . $week, $apps );
		}
		return $apps;
	}

	/**
	 * Find if a user is worker
	 * @param user_id: Id of the user who will be checked if he is worker
	 * @return bool
	 */
	function is_worker( $user_id=0 ) {
		global $wpdb, $current_user;
		if ( !$user_id )
			$user_id = $current_user->ID;

		$result = $this->get_worker( $user_id );
		if ( $result != null )
			return true;

		return false;
	}

	/**
	 * Find if a user is dummy
	 * @param user_id: Id of the user who will be checked if he is dummy
	 * since 1.0.6
	 * @return bool
	 */
	function is_dummy( $user_id=0 ) {
		global $wpdb, $current_user;
		if ( !$user_id )
			$user_id = $current_user->ID;

		// A dummy should be a worker
		$result = $this->get_worker( $user_id );
		if ( $result == null )
			return false;

		// This is only supported after V1.0.6 and if DB is altered
		if ( !$this->db_version )
			return false;

		if ( $result->dummy )
			return true;

		return false;
	}


	/**
	 * Find worker name given his ID
	 * @return string
	 */
	function get_worker_name( $worker=0, $php=true ) {
		global $current_user;
		$user_name = '';
		if ( 0 == $worker ) {
			// Show different text to authorized people
			if ( is_admin() || App_Roles::current_user_can( 'manage_options', App_Roles::CTX_STAFF ) || $this->is_worker( $current_user->ID ) )
				$user_name = __('Our staff', 'appointments');
			else
				$user_name = __('A specialist', 'appointments');
		}
		else {
			$userdata = get_userdata( $worker );
			if (is_object($userdata) && !empty($userdata->app_name)) {
				$user_name = $userdata->app_name;
			}
			if (empty($user_name)) {
				if ( !$php )
					$user_name = $userdata->user_login;
				else
					$user_name = $userdata->display_name;

				if ( !$user_name ){
                                        $first_name = get_user_meta($worker, 'first_name', true);
                                        $last_name = get_user_meta($worker, 'last_name', true);
					$user_name = $first_name . " " . $last_name;
                                }
				if ( "" == trim( $user_name ) )
					$user_name = $userdata->user_login;
			}
		}
		return apply_filters( 'app_get_worker_name', $user_name, $worker );
	}

	/**
	 * Find worker email given his ID
	 * since 1.0.6
	 * @return string
	 */
	function get_worker_email( $worker=0 ) {
		// Real person
		if ( !$this->is_dummy( $worker ) ) {
			$worker_data = get_userdata( $worker );
			if ( $worker_data )
				$worker_email = $worker_data->user_email;
			else
				$worker_email = '';
			return apply_filters( 'app_worker_email', $worker_email, $worker );
		}
		// Dummy
		if ( isset( $this->options['dummy_assigned_to'] ) && $this->options['dummy_assigned_to'] ) {
			$worker_data = get_userdata( $this->options['dummy_assigned_to'] );
			if ( $worker_data )
				$worker_email = $worker_data->user_email;
			else
				$worker_email = '';
			return apply_filters( 'app_dummy_email', $worker_email, $worker );
		}

		// If not set anything, assign to admin
		return $this->get_admin_email( );
	}

	/**
	 * Return admin email
	 * since 1.2.7
	 * @return string
	 */
	function get_admin_email( ) {
		global $current_site;
		$admin_email = get_option('admin_email');
		if ( !$admin_email )
			$admin_email = 'admin@' . $current_site->domain;

		return apply_filters( 'app_get_admin_email', $admin_email );
	}

	/**
	 * Find service name given its ID
	 * @return string
	 */
	function get_service_name( $service=0 ) {
		// Safe text if we delete a service
		$name = __('Not defined', 'appointments');
		$result = $this->get_service( $service );
		if ( $result != null )
			$name = $result->name;

		$name = apply_filters( 'app_get_service_name', $name, $service );

		return stripslashes( $name );
	}

	/**
	 * Find client name given his appointment
	 * @return string
	 */
	function get_client_name( $app_id ) {
		$name = '';
		// This is only used on admin side, so an optimization is not required.
		$result = $this->db->get_row( $this->db->prepare("SELECT * FROM {$this->app_table} WHERE ID=%d", $app_id) );
		if ( $result !== null ) {
			// Client can be a user
			if ( $result->user ) {
				$userdata = get_userdata( $result->user );
				if ( $userdata ) {
					$href = function_exists('bp_core_get_user_domain') && (defined('APP_BP_LINK_TO_PROFILE') && APP_BP_LINK_TO_PROFILE)
						? bp_core_get_user_domain($result->user)
						: admin_url("user-edit.php?user_id="). $result->user
					;
					$name = '<a href="' . apply_filters('app_get_client_name-href', $href, $app_id, $result) . '" target="_blank">' .
						($result->name && !(defined('APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES') && APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES) ? $result->name : $userdata->user_login) .
					'</a>';
				}
				else
					$name = $result->name;
			}
			else {
				$name = $result->name;
				if ( !$name )
					$name = $result->email;
			}
		}
		return apply_filters( 'app_get_client_name', $name, $app_id, $result );
	}

	/**
	 * Get price for the current service and worker
	 * If worker has additional price (optional), it is added to the service price
	 * @param paypal: If set true, deposit price is calculated
	 * @return string
	 */
	function get_price( $paypal=false ) {
		global $current_user;
		$this->get_lsw();
		$service_obj = $this->get_service( $this->service );
		$worker_obj = $this->get_worker( $this->worker );

		if ( $worker_obj != null && $worker_obj->price )
			$worker_price = $worker_obj->price;
		else
			$worker_price = 0;

		$price = $service_obj->price + $worker_price;

		/**
		 * Filter allows other plugins or integrations to apply a discount to
		 * the price.
		 */
		$price = apply_filters( 'app_get_price_prepare', $price, $paypal, $this );

		// Discount
		if ( $this->is_member() && isset( $this->options["members_discount"] ) && $this->options["members_discount"] ) {
			// Special condition: Free for members
			if ( 100 == $this->options["members_discount"] )
				$price = 0;
			else
				$price = $price * ( 100 - $this->options["members_discount"] )/100;
		}

		if ( $paypal ) {
			// Deposit
			if ( isset( $this->options["percent_deposit"] ) && $this->options["percent_deposit"] )
				$price = $price * $this->options["percent_deposit"] / 100;
			if ( isset( $this->options["fixed_deposit"] ) && $this->options["fixed_deposit"] )
				$price = $this->options["fixed_deposit"];

			// It is possible to ask special amounts to be paid
			$price = apply_filters( 'app_paypal_amount', $price, $this->service, $this->worker, $current_user->ID );
		} else {
			$price = apply_filters( 'app_get_price', $price, $this->service, $this->worker, $current_user->ID );
		}

		// Use number_format right at the end, cause it converts the number to a string.
		$price = number_format( $price, 2 );
		return $price;
	}

	/**
	 * Get deposit given price
	 * This is required only for manual pricing
	 * @param price: the full price
	 * @since 1.0.8
	 * @return string
	 */
	function get_deposit( $price ) {

		$deposit = 0;

		if ( !$price )
			return apply_filters( 'app_get_deposit', 0 );

		// Discount
		if ( $this->is_member() && isset( $this->options["members_discount"] ) && $this->options["members_discount"] ) {
			// Special condition: Free for members
			if ( 100 == $this->options["members_discount"] )
				$price = 0;
			else
				$price = number_format( $price * ( 100 - $this->options["members_discount"] )/100, 2 );
		}

		// Deposit
		if ( isset( $this->options["percent_deposit"] ) && $this->options["percent_deposit"] )
			$deposit = number_format( $price * $this->options["percent_deposit"] / 100, 2 );
		if ( isset( $this->options["fixed_deposit"] ) && $this->options["fixed_deposit"] )
			$deposit = $this->options["fixed_deposit"];

		return apply_filters( 'app_get_deposit', $deposit );
	}


	/**
	 * Get the capacity of the current service
	 * @return integer
	 */
	function get_capacity() {
		$capacity = wp_cache_get( 'capacity_'. $this->service );
		if ( false === $capacity ) {
			// If no worker is defined, capacity is always 1
			$count = count( $this->get_workers() );
			if ( !$count ) {
				$capacity = 1;
			}
			else {
				// Else, find number of workers giving that service and capacity of the service
				$worker_count = count( $this->get_workers_by_service( $this->service ) );
				$service = $this->get_service( $this->service );
				if ( $service != null ) {
					if ( !$service->capacity ) {
						$capacity = $worker_count; // No service capacity limit
					}
					else
						$capacity = min( $service->capacity, $worker_count ); // Return whichever smaller
				}
				else
					$capacity = 1; // No service ?? - Not possible but let's be safe
			}
			wp_cache_set( 'capacity_'. $this->service, $capacity );
		}
		return apply_filters( 'app_get_capacity', $capacity, $this->service, $this->worker );
	}

/**
**************************************
* Methods for Specific Content Caching
* Developed especially for this plugin
**************************************
*/

	/**
	 * Check if plugin should use cache
	 * Available for visitors for the moment
	 * TODO: extend this for logged in users too
	 * @since 1.0.2
	 */
	function use_cache() {
		if ( 'yes' == $this->options["use_cache"] && !is_user_logged_in() )
			return true;

		return false;
	}

	/**
	 * Add a post ID to the array to be cached
	 *
	 */
	function add_to_cache( $post_id ) {
		if ( $this->use_cache() )
			$this->pages_to_be_cached[] = $post_id;
	}

	/**
	 * Serve content from cache DB if is available and post is supposed to be cached
	 * This is called before do_shortcode (this method's priority: 8)
	 * @return string (the content)
	 */
	function pre_content( $content ) {
		global $post;
		// Check if this page is to be cached
		if ( !in_array( $post->ID, $this->pages_to_be_cached ) )
			return $content;

		// Get uri and mark it for other functions too
		// The other functions are called after this (content with priority 100 and the other with footer hook)
		$this->uri = $this->get_uri();

		$result = $this->db->get_row( $this->db->prepare("SELECT * FROM {$this->cache_table} WHERE uri=%s", $this->uri) );
		if ( $result != null ) {
			// Clear uri so other functions do not deal with update/insert
			$this->uri = false;
			// We need to serve the scripts too
			$this->script = $result->script;

			// If wpautop had filter, it is almost certain that it was removed
			if ( $this->had_filter )
				$new_content = $result->content;
			else
				$new_content = wpautop( $result->content );

			return $new_content . '<!-- Served from WPMU DEV Appointments+ Cache '. $result->created .' -->';
		}
		// If cache is empty return content
		// If wpautop had filter, it is almost certain that it was removed
		if ( $this->had_filter )
			return $content;
		else
			return wpautop( $content ); // Add wpautop which we removed before
	}

	/**
	 * Save newly created content to cache DB
	 * @return string (the content)
	 */
	function post_content( $content ) {
		// Check if this page is to be cached.
		if ( !$this->uri )
			return $content;
		// Also don't save empty content
		if ( !trim( $content ) ) {
			$this->uri = '';
			return $content;
		}
		// At this point it means there is no such a row, so we can safely insert
		$this->db->insert( $this->cache_table,
					array(
						'uri' 		=> $this->uri,
						'created' 	=> date ("Y-m-d H:i:s", $this->local_time ),
						'content'	=> $content
					)
			);
		return $content;
	}

	/**
	 * Save newly created scripts at wp footer location
	 * @return none
	 */
	function save_script() {
		// Check if this page is to be cached
		if ( !$this->uri || !trim( $this->script ) )
			return;
		// There must be already such a row
		$this->db->update( $this->cache_table,
			array( 'script'	=> $this->script ),
			array( 'uri' 	=> $this->uri )
		);
	}

	/**
	 * Get request uri
	 * @return string
	 */
	function get_uri() {
		// Get rid of # part
		if ( strpos( $_SERVER['REQUEST_URI'], '#' ) !== false ) {
			$uri_arr = explode( '#', $_SERVER['REQUEST_URI'] );
			$uri = $uri_arr[0];
		}
		else
			$uri = $_SERVER['REQUEST_URI'];

		return $uri;
	}

	/**
	 * Clear cache in case saved post has our shortcodes
	 * @return none
	 */
	function save_post( $post_id, $post ) {
		if ( strpos( $post->post_content, '[app_' ) !== false )
			$this->flush_cache();
	}

	/**
	 * Flush both database and object caches
	 *
	 */
	function flush_cache( ) {
		wp_cache_flush();
		if ( 'yes' == @$this->options["use_cache"] )
			$result = $this->db->query( "TRUNCATE TABLE {$this->cache_table} " );
	}

/****************
* General methods
*****************
*/

	/**
     * Provide options if asked outside the class
 	 * @return array
     */
	function get_options() {
		return $this->options;
	}

	/**
	 * Save a message to the log file
	 */
	function log( $message='' ) {
		if ( $message ) {
			$to_put = '<b>['. date_i18n( $this->datetime_format, $this->local_time ) .']</b> '. $message;
			// Prevent multiple messages with same text and same timestamp
			if ( !file_exists( $this->log_file ) || strpos( @file_get_contents( $this->log_file ), $to_put ) === false )
				@file_put_contents( $this->log_file, $to_put . chr(10). chr(13), FILE_APPEND );
		}
	}

	/**
	 * Remove tabs and breaks
	 */
	function esc_rn( $text ) {
		$text = str_replace( array("\t","\n","\r"), "", $text );
		return $text;
	}

	/**
	 * Converts number of seconds to hours:mins acc to the WP time format setting
	 * @param integer secs Seconds
	 * @param string $forced_format Forcing the return timestamp format
	 * @return string
	 */
	function secs2hours( $secs, $forced_format=false ) {
		$min = (int)($secs / 60);
		$hours = "00";
		if ( $min < 60 )
			$hours_min = $hours . ":" . $min;
		else {
			$hours = (int)($min / 60);
			if ( $hours < 10 )
				$hours = "0" . $hours;
			$mins = $min - $hours * 60;
			if ( $mins < 10 )
				$mins = "0" . $mins;
			$hours_min = $hours . ":" . $mins;
		}
		if (!empty($forced_format)) $hours_min = date_i18n($forced_format, strtotime($hours_min . ":00"));
		else if ($this->time_format) $hours_min = date_i18n($this->time_format, strtotime($hours_min . ":00")); // @TODO: TEST THIS THOROUGHLY!!!!

		return $hours_min;
	}

	/**
	 * Return an array of preset base times, so that strange values are not set
	 * @return array
	 */
	function time_base() {
		$default = array( 10,15,30,60,90,120 );
		$a = $this->options["additional_min_time"];
		// Additional time bases
		if ( isset( $a ) && $a && is_numeric( $a ) )
			$default[] = $a;
		return apply_filters( 'app_time_base', $default );
	}

	/**
	 *	Return minimum set interval time
	 *  If not set, return a safe time.
	 *	@return integer
	 */
	function get_min_time(){
		if ( isset( $this->options["min_time"] ) && $this->options["min_time"] && $this->options["min_time"]>apply_filters( 'app_safe_min_time', 9 ) )
			return apply_filters('app-time-min_time', (int)$this->options["min_time"]);
		else
			return apply_filters('app-time-min_time', apply_filters( 'app_safe_time', 10 ));
	}

	/**
	 *	Number of days that an appointment can be taken
	 *	@return integer
	 */
	function get_app_limit() {
		if ( isset( $this->options["app_limit"] ) && $this->options["app_limit"] )
			return apply_filters( 'app_limit', (int)$this->options["app_limit"] );
		else
			return apply_filters( 'app_limit', 365 );
	}

	/**
	 * Return an array of weekdays
	 * @return array
	 */
	function weekdays() {
		return array(
			__('Sunday', 'appointments') => 'Sunday',
			__('Monday', 'appointments') => 'Monday',
			__('Tuesday', 'appointments') => 'Tuesday',
			__('Wednesday', 'appointments') => 'Wednesday',
			__('Thursday', 'appointments') => 'Thursday',
			__('Friday', 'appointments') => 'Friday',
			__('Saturday', 'appointments') => 'Saturday'
		);
	}

	/**
	 * Return all available statuses
	 * @return array
	 */
	function get_statuses() {
		return apply_filters( 'app_statuses',
					array(
						'pending'	=> __('Pending', 'appointments'),
						'paid'		=> __('Paid', 'appointments'),
						'confirmed'	=> __('Confirmed', 'appointments'),
						'completed'	=> __('Completed', 'appointments'),
						'reserved'	=> __('Reserved by GCal', 'appointments'),
						'removed'	=> __('Removed', 'appointments')
						)
				);
	}


	/**
	 * Return a selected field name to further customize them and make translation easier
	 * @return string (name of the field)
	 */
	function get_field_name( $key ) {

		$field_names = array(
						'name'		=> __('Name', 'appointments'),
						'email'		=> __('Email', 'appointments'),
						'phone'		=> __('Phone', 'appointments'),
						'address'	=> __('Address', 'appointments'),
						'city'		=> __('City', 'appointments'),
						'note'		=> __('Note', 'appointments')
					);

		$field_names = apply_filters( 'app_get_field_name', $field_names );

		if ( array_key_exists( $key, $field_names ) )
			return $field_names[$key];
		else
			return __( 'Not defined', 'appointments' );
	}

	/**
	 * Return an array of all available front end box classes
	 * @return array
	 */
	function get_classes() {
		return apply_filters( 'app_box_class_names',
							array(
								'free'			=> __('Free', 'appointments'),
								'busy'			=> __('Busy', 'appointments'),
								'notpossible'	=> __('Not possible', 'appointments')
								)
				);
	}

	/**
	 * Return a default color for a selected box class
	 * @return string
	 */
	function get_preset( $class, $set ) {
		if ( 1 == $set )
			switch ( $class ) {
				case 'free'			:	return '48c048'; break;
				case 'busy'			:	return 'ffffff'; break;
				case 'notpossible'	:	return 'ffffff'; break;
				default				:	return '111111'; break;
			}
		else if ( 2 == $set )
			switch ( $class ) {
				case 'free'			:	return '73ac39'; break;
				case 'busy'			:	return '616b6b'; break;
				case 'notpossible'	:	return '8f99a3'; break;
				default				:	return '111111'; break;
			}
		else if ( 3 == $set )
			switch ( $class ) {
				case 'free'			:	return '40BF40'; break;
				case 'busy'			:	return '454C54'; break;
				case 'notpossible'	:	return '454C54'; break;
				default				:	return '111111'; break;
			}
	}

	/**
	 * Change status for a given app ID
	 * @return bool
	 */
	function change_status( $stat, $app_id ) {
		global $wpdb;

		if (!$app_id || !$stat) return false;

		$result = $wpdb->update($this->app_table,
			array('status' => $stat),
			array('ID' => $app_id)
		);

		if ($result) {
			$this->flush_cache();
			do_action( 'app_change_status', $stat, $app_id );

			//if ( ($stat == 'paid' || $stat == 'confirmed') && is_object( $this->gcal_api ) ) {
			if (is_object($this->gcal_api) &&  $this->gcal_api->is_syncable_status($stat)) {
				$this->gcal_api->update( $app_id );
			}
			return true;
		}
		return false;
	}


/************************************************************
* Methods for Shortcodes and those related to shortcodes only
*************************************************************
*/


	/**
	 * Generate an excerpt from the selected service/worker page
	 * Applies custom filter set instead of the default one.
	 */
	function get_excerpt( $page_id, $thumb_size, $thumb_class, $worker_id=0 ) {
		$text = '';
		if ( !$page_id )
			return $text;
		$page = get_post( $page_id );
		if ( !$page )
			return $text;

		$text = $page->post_content;

		$text = strip_shortcodes( $text );

		$text = apply_filters('app_the_content', $text, $page_id, $worker_id );
		$text = str_replace(']]>', ']]&gt;', $text);
		$excerpt_length = apply_filters('app_excerpt_length', 55);
		$excerpt_more = apply_filters('app_excerpt_more', ' &hellip; <a href="'. esc_url( get_permalink($page->ID) ) . '" target="_blank">' . __( 'More information <span class="meta-nav">&rarr;</span>', 'appointments' ) . '</a>');
		$text = wp_trim_words( $text, $excerpt_length, $excerpt_more );

		$thumb = $this->get_thumbnail( $page_id, $thumb_size, $thumb_class, $worker_id );

		return apply_filters( 'app_excerpt', $thumb. $text, $page_id, $worker_id );
	}

	/**
	 * Fetch content from the selected service/worker page.
	 * Applies custom filter set instead of the default one.
	 */
	function get_content( $page_id, $thumb_size, $thumb_class, $worker_id=0 ) {
		$content = '';
		if ( !$page_id )
			return $content;
		$page = get_post( $page_id );
		if ( !$page )
			return $content;

		$thumb = $this->get_thumbnail( $page_id, $thumb_size, $thumb_class, $worker_id );

		$app_content = apply_filters( 'app_pre_content', wpautop( $this->strip_app_shortcodes( $page->post_content ), $page_id, $worker_id ) );

		return apply_filters( 'app_content', $thumb. $app_content, $page_id, $worker_id );
	}

	/**
	 * Clear app shortcodes
	 * @since 1.1.9
	 */
	function strip_app_shortcodes( $content ) {
		// Don't even try to touch a non string, just in case
		if ( !is_string( $content ) )
			return $content;
		else
			return preg_replace( '%\[app_(.*?)\]%is', '', $content );
	}

	/**
	 * Get html code for thumbnail or avatar
	 */
	function get_thumbnail( $page_id, $thumb_size, $thumb_class, $worker_id ) {

		if ( $thumb_size && 'none' != $thumb_size ) {
			if ( strpos( $thumb_size, 'avatar' ) !== false ) {
				if ( strpos( $thumb_size, ',' ) !== false ) {
					$size_arr = explode( ",", $thumb_size );
					$size = $size_arr[1];
				}
				else
					$size = 96;
				$thumb = get_avatar( $worker_id, $size );
				if ( $thumb_class ) {
					// Dirty, but faster than preg_replace
					$thumb = str_replace( "class='", "class='".$thumb_class." ", $thumb );
					$thumb = str_replace( 'class="', 'class="'.$thumb_class.' ', $thumb );
				}
			}
			else {
				if ( strpos( $thumb_size, ',' ) !== false )
					$size = explode( ",", $thumb_size );
				else
					$size = $thumb_size;

				$thumb = get_the_post_thumbnail( $page_id, $size, apply_filters( 'app_thumbnail_attr', array('class'=>$thumb_class) ) );
			}
		}
		else
			$thumb = '';

		return apply_filters( 'app_thumbnail', $thumb, $page_id, $worker_id );
	}

	/**
	 * Check and return necessary fields to the front end
	 * @return json object
	 */
	function pre_confirmation() {

		$values 		= explode( ":", $_POST["value"] );
		$location 		= $values[0];
		$service 		= $values[1];
		$worker 		= $values[2];
		$start 			= $values[3];
		$end 			= $values[4];
		$post_id		= $values[5];

		// A little trick to pass correct lsw variables to the get_price, is_busy and get_capacity functions
		$_REQUEST["app_location_id"] = $location;
		$_REQUEST["app_service_id"] = $service;
		$_REQUEST["app_provider_id"] = $worker;
		$this->get_lsw();

		// Alright, so before we go further, let's check if we can
		if (!is_user_logged_in() && (!empty($this->options['login_required']) && 'yes' == $this->options['login_required'])) {
			die(json_encode(array(
				'error' => __('You need to login to make an appointment.', 'appointments'),
			)));
		}

		$price = $this->get_price( );
		
		// It is possible to apply special discounts
		$price = apply_filters( 'app_display_amount', $price, $service, $worker );
		$price = apply_filters( 'app_pre_confirmation_price', $price, $service, $worker, $start, $end );

		$display_currency = !empty($this->options["currency"])
			? App_Template::get_currency_symbol($this->options["currency"])
			: App_Template::get_currency_symbol('USD')
		;

		global $wpdb;

		/*if ( $this->is_busy( $start,  $end, $this->get_capacity() ) )
			die( json_encode( array("error"=>apply_filters( 'app_booked_message',__( 'We are sorry, but this time slot is no longer available. Please refresh the page and try another time slot. Thank you.', 'appointments')))));*/

		$service_obj = $this->get_service( $service );
		$service = '<label><span>'. __('Tour Type: ', 'appointments' ).  '</span>'. apply_filters( 'app_confirmation_service', stripslashes( $service_obj->name ), $service_obj->name ) . '</label>';
		$start = '<label><span>'.__('Date and time: ', 'appointments' ). '</span>'. apply_filters( 'app_confirmation_start', date_i18n( $this->datetime_format, $start ), $start ) . '</label>';
		$end = '<label><span>'.__('Lasts (approx): ', 'appointments' ). '</span>'. apply_filters( 'app_confirmation_lasts', $service_obj->duration . " ". __('minutes', 'appointments'), $service_obj->duration ) . '</label>';
		if ( $price > 0 ){
			if(!empty($_POST['app_value_att'])) {
				if(!empty($_REQUEST['app_service_id']) and $_REQUEST['app_service_id']==2){
						$attendees = $_POST['app_value_att'];
						if($attendees>6 and $attendees<24){
							$price = 55;
						}elseif($attendees>24){
							$price = 50;
						}
				}else{
						if($attendees>24){
							$price = 25;
						}
				}

			$price = $price * $_POST['app_value_att'];
			}
			
			$price = '<label><span>'.__('Price: ', 'appointments' ).  '</span><a style="text-decoration:none; border-bottom: none;" class="price_changer">'. apply_filters( 'app_confirmation_price', $price . " " . $display_currency, $price ) . '</a></label>';
		}else{
			$price = 0;
		}
		
		if ( $worker )
			$worker = '<label><span>'. __('Service provider: ', 'appointments' ).  '</span>'. apply_filters( 'app_confirmation_worker', stripslashes( $this->get_worker_name( $worker ) ), $worker ) . '</label>';
		else
			$worker = '';

		if ( $this->options["ask_name"] )
			$ask_name = "ask";
		else
			$ask_name = "";

		if ( $this->options["ask_email"] )
			$ask_email = "ask";
		else
			$ask_email = "";

		if ( $this->options["ask_phone"] )
			$ask_phone = "ask";
		else
			$ask_phone = "";

		if ( $this->options["ask_address"] )
			$ask_address = "ask";
		else
			$ask_address = "";

		if ( $this->options["ask_city"] )
			$ask_city = "ask";
		else
			$ask_city = "";

		if ( $this->options["ask_note"] )
			$ask_note = "ask";
		else
			$ask_note = "";

		if ( isset( $this->options["gcal"] ) && 'yes' == $this->options["gcal"] )
			$ask_gcal = "ask";
		else
			$ask_gcal = "";

		$reply_array = array(
							'service'	=> $service,
							'worker'	=> $worker,
							'start'		=> $start,
							'end'		=> $end,
							'price'		=> $price,
							'name'		=> $ask_name,
							'email'		=> $ask_email,
							'phone'		=> $ask_phone,
							'address'	=> $ask_address,
							'city'		=> $ask_city,
							'note'		=> $ask_note,
							'gcal'		=> $ask_gcal
						);

		$reply_array = apply_filters( 'app_pre_confirmation_reply', $reply_array );

		die( json_encode( $reply_array ));
	}
	
	







//shimion 
	function check_and_compare_date($start, $end){
		global $wpdb;
		$start = date ("Y-m-d H:i:s", $start);
		$end = date ("Y-m-d H:i:s", $end);
		$app_appointments = $wpdb->get_var( "SELECT COUNT(*) FROM wp_app_appointments WHERE start = '$start%' AND end = '$end%'" );
		if(!empty($app_appointments) and $app_appointments>=15)
			return true;
		}


	
	
	function check_and_conform_attendence($start, $end){
		global $wpdb;
		$start = date ("Y-m-d H:i:s", $start);
		$end = date ("Y-m-d H:i:s", $end);
		$app_appointments = $wpdb->get_var( "SELECT COUNT(*) FROM wp_app_appointments WHERE start = '$start%' AND end = '$end%'" );
		if(!empty($app_appointments) and $app_appointments<=30){
			return $app_appointments;
			}
		}
	

	function check_cuppon($code){
		global $wpdb;
		if(!empty($code)){
		$query = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE `post_title` LIKE '$code'");
		
		if(!empty($query) and $query !=0){
			$id = $query->ID;
		$meta_value = $wpdb->get_row("SELECT * FROM `$wpdb->postmeta` WHERE `post_id` = $id AND `meta_key` LIKE 'price'");
			if(!empty($meta_value) and $meta_value != 0 and $meta_value != null){
				$val = $meta_value->meta_value;
				}	
			}else{
			die( json_encode( array("error"=>__( 'Coupon code does not match.', 'appointments'))));	
				
			}
			
			return $val;
			
		
		}
	}


function worker_break($start, $end, $id){
		global $wpdb;
		$worker = $wpdb->get_row( "SELECT * FROM wp_app_working_hours WHERE `status` LIKE 'closed' AND `worker` = ".$id."  ");
		//$start = date ("Y-m-d H:i:s", $start);
		//$end = date ("Y-m-d H:i:s", $end);
		$hour_min = date ("H:i A", $start);
		$day = date ("l", $start);
		$month = date ("m", $start);
		if(!empty($worker)){
			
				$fletch_hours = maybe_unserialize( $worker->hours );
				if(!empty($fletch_hours) and is_array($fletch_hours)){
					foreach ($fletch_hours as $key=>$fletch_hour){
						if($key==$day){
								$values=$fletch_hour; 
								//$id = $worker->worker;
								//break;
								if(!empty($values)){
											if ( isset( $values["active"] ) && 'yes' == $values["active"] ) {

											$start  = $values['start'];
											$end  = $values['end'];
											if($hour_min > $start and $end > $hour_min){
													$worker_id = $worker->worker;	
													break;	
												 
												}
												
												
											}
									}




								


							}
							
							
					}
				}
					
			}
					return $worker_id;
			
	}






//get the worker availability
function worker_availability($start, $end, $id){
		global $wpdb;
		$worker = $wpdb->get_row( "SELECT * FROM wp_app_working_hours WHERE `status` LIKE 'open' AND `worker` = ".$id."  ");
		//$start = date ("Y-m-d H:i:s", $start);
		//$end = date ("Y-m-d H:i:s", $end);
		$hour_min = date ("H:i A", $start);
		$day = date ("l", $start);
		$month = date ("m", $start);
		if(!empty($worker)){
			
				$fletch_hours = maybe_unserialize( $worker->hours );
				if(!empty($fletch_hours) and is_array($fletch_hours)){
					foreach ($fletch_hours as $key=>$fletch_hour){
						if($key==$day){
								$values=$fletch_hour; 
								//$id = $worker->worker;
								//break;
								if(!empty($values)){
											if ( isset( $values["active"] ) && 'yes' == $values["active"] ) {

											$start  = $values['start'];
											$end 	= $values['end'];
											//if($hour_min < $start and $end > $hour_min){
													$worker_id = $worker->worker;	
													break;	
												 
											//	}
												
												
											}
									}




								


							}
							
							
					}
				}
					
			}
					return $worker_id;
			
	}


// getting worker by query
function get_worker_new($start, $end){
		global $wpdb;
		$user_query = $wpdb->get_results( "SELECT * FROM `wp_app_workers` ");
		//print($user_query);
		$hour_min = date ("H:i", $start);
		$hour_min = strtotime ($hour_min);
		$day = date ("l", $start);
		$month = date ("m", $start);
		if(!empty($user_query)){
			$user_ID = array();
				
				foreach($user_query as $u){
//					$user_ID[] = $u->ID;
						//$worker_id .= $this->worker_availability($start, $end, $u->ID);
							$worker = $wpdb->get_row( "SELECT * FROM wp_app_working_hours WHERE `status` LIKE 'open' AND `worker` = ".$u->ID."  ");
							$work_off = $wpdb->get_row( "SELECT * FROM wp_app_working_hours WHERE `status` LIKE 'closed' AND `worker` = ".$u->ID."  ");
							//$start = date ("Y-m-d H:i:s", $start);
							//$end = date ("Y-m-d H:i:s", $end);
							
							if(!empty($worker)){
								
									$fletch_hours = maybe_unserialize( $worker->hours );
									$fletch_hours_off = maybe_unserialize( $work_off->hours );
									if(!empty($fletch_hours) and is_array($fletch_hours)){
										$day_on =$fletch_hours[$day];
										$day_off =$fletch_hours_off[$day];
													//$values=$fletch_hour; 
													//$id = $worker->worker;
													//break;
													if(!empty($day_on)){
																		$start  = $day_off['start'];
																		$start  = strtotime ($start);
																		//print($start). '<br>';
																		$end  = $day_off['end'];
																		$end  = strtotime ($end);
/*																		print('<br>');
																		print($u->ID);
																		print('<br>');
																		print('start: ' . $start);
																		print('<br>');
																		print('hours: '.$hour_min);
																		print('<br>');
																		print('end:   ' . $end);
																		print('<br>');
*/																		
																if ( isset( $day_on["active"] ) and $day_on["active"] == 'yes' ) {
					
																	
																if ( isset( $day_off["active"] ) and $day_off["active"] == 'yes' ) {
																		if($hour_min >= $start and $hour_min <= $end){
																		}else{
																		$user_ID[] = $worker->worker;	
																		}
																		
																	
																	}elseif( isset( $day_off["active"] ) and $day_off["active"] == 'no' ){
																			$user_ID[] = $worker->worker;
																		
																	}
																
																	
					
																	}
																	
																}
												
									}
										
								}
								//if(!empty($worker_id)){
									//$user_ID[] = $worker_id;
								//}
						
						}
						
	
					
			}
			
			return $user_ID;
		
	}




//get worker for 3 line
function get_worker_three_line($ccs, $cce){
		global $wpdb;
		$hour_min = date ("H:i", $ccs);
		$hour_min = strtotime ($hour_min);
		$hour_min_end = date ("H:i", $cce);
		$day = date ("d", $ccs);
		$day_name = date ("l", $ccs);
		//print('<br>Day: '.date ("l", $ccs).'<br>');
		$month = date ("m", $ccs);
		$year = date ("Y", $ccs);
		
		$date= $year .'-'. $month .'-'.$day ;

							/*$hour_min = date ("H:i", $start);
							$hour_min = strtotime ($hour_min);
							$day = date ("l", $start);
							$month = date ("m", $start);*/
		$user_ID = array();
		//print_r($this->day_array);
		//if(!array_search($day_name, $this->day_array)){
		$working_hours = $wpdb->get_results( "SELECT * FROM wp_three_line_appointment WHERE `date` = '$date%'");
		$working_hours_hook = $wpdb->get_results( "SELECT * FROM wp_app_hooker WHERE `date` = '$date%'");
		
		$working_hours = array_merge($working_hours, $working_hours_hook);
		if(!empty($working_hours) and is_array($working_hours)){
			
			foreach($working_hours as $worker_hour){
			$wp_worker_assinged_3line_table = $wpdb->get_row( "SELECT * FROM `wp_worker_assinged_3line_table` WHERE `time` LIKE  '$hour_min' AND `app_id` = '$worker_hour->ID'" );
			if(strtotime ($worker_hour->working_hours_start) <= $hour_min and $hour_min <= strtotime ($worker_hour->working_hours_end) ){
					if($worker_hour->break_enable=='0'){
						if(strtotime ($worker_hour->break_start)<=$hour_min and $hour_min <= strtotime ($worker_hour->break_end) ){
							
							}else{
								if($wp_worker_assinged_3line_table != 0 or !empty($wp_worker_assinged_3line_table) or $wp_worker_assinged_3line_table != null){
								$user_ID[] = $worker_hour->worker;
								}
							}
						
					}else{
								if($wp_worker_assinged_3line_table != 0 or !empty($wp_worker_assinged_3line_table) or $wp_worker_assinged_3line_table != null){
								$user_ID[] = $worker_hour->worker;
								}
						}
					}
				}
			}
	//	}
			
			//$user_ID = array('1', '2');
			//print_r($user_ID );
			
			
			return $user_ID;
	}
	

/*function get_single_three_line_user_availibity_of_specific_date($ccs, $id){
	$start = date ("Y-m-d H:i:s", $ccs);
	global $wpdb;
	$app_appointment = $wpdb->get_var( "SELECT COUNT(*) FROM wp_app_appointments WHERE start = '$start%' AND `worker` = ".$id."" );
	if($app_appointment >= 3){
		 return false;
		}else{
		 return true;	
		}
	
	}

*/

function get_default_users_for_specific_day_and_hours($ccs){
		global $wpdb;
		$day_array = $this->day_array;
		$hour_min = date ("H:i", $ccs);
		//$hour_min = strtotime ($hour_min);
		$hour_min_end = date ("H:i", $cce);
		$day_name = date ("l", $ccs);
		$month = date ("m", $ccs);
		$year = date ("Y", $ccs);
		$user_ID = $this->default_user_ID;
		$date= $year .'-'. $month .'-'.$day ;

							/*$hour_min = date ("H:i", $start);
							$hour_min = strtotime ($hour_min);
							$day = date ("l", $start);
							$month = date ("m", $start);*/
	//	if(array_search($day_array, $day_name)){
		$day = $day_name;
		//print($hour_min);
		if($day == 'Sunday' or $day == 'Monday' or $day == 'Tuesday' or $day == 'Wednesday' or $day == 'Thursday'){		
			if($hour_min == '09:00' or $hour_min == '10:30' or $hour_min == '12:00' or $hour_min == '13:30' or $hour_min == '15:00' or $hour_min == '16:30' or $hour_min == '18:00' or $hour_min == '19:30' ){	
				$user_ID = array();
				//if($this->get_single_three_line_user_availibity_of_specific_date($ccs, '16')== true){
				$user_ID[] = '16';
				//}
				//if($this->get_single_three_line_user_availibity_of_specific_date($ccs, '17')== true){
				$user_ID[] = '17';
				//}
				//print('<br>');		
				//print_r($user_ID );		
				return $user_ID;
				}
			}				
	
	}
	

//check the IDS ARRAY
function check_appointments_already_taken($start, $end){
		global $wpdb;
		if(!empty($_REQUEST['app_service_id']) and $_REQUEST['app_service_id']==2){
		$ids = $this->get_worker_three_line($start, $end);
		}else{
		$ids = $this->get_worker_new($start, $end);
		}

		$start = date ("Y-m-d H:i:s", $start);
		$end = date ("Y-m-d H:i:s", $end);
		//$app_appointment = $wpdb->get_var( "SELECT COUNT(*) FROM wp_app_appointments WHERE start = '$start%' AND end = '$end%'" );
		//if($app_appointment == NULL) $app_appointment =0;
		$worker_array = array();
		if(!empty($ids)){
			$i=0;
			foreach($ids as $id){ 
			$i++;	
				$app_appointment = $wpdb->get_var( "SELECT COUNT(*) FROM wp_app_appointments WHERE start = '$start%' AND `worker` = ".$id." AND end = '$end%'" );
				if($app_appointment== NULL) $app_appointment = 0;
				
					$app_appointment = 3 - $app_appointment;	
					$worker_array[$id] = $app_appointment;
					
					}
			}
		
		
			return $worker_array;
		
	}




	function get_all_worker_at_one_array($start, $end){
			$check_appointments_already_taken = $this->check_appointments_already_taken($start, $end);
			$worker_id = array();
			foreach ($check_appointments_already_taken as $key=>$value){
				if($value == 3){
					$worker_id[] = $key;
					$worker_id[] = $key;
					$worker_id[] = $key;
				}elseif($value == 2){
					$worker_id[] = $key;
					$worker_id[] = $key;
				}elseif($value == 1){
					$worker_id[] = $key;
				}
				}
			return $worker_id;
		}

	





//check_worker

	function check_worker($start, $end, $num){
			$get_all_worker_at_one_array = $this->get_all_worker_at_one_array($start, $end);
			$arr = array();
			foreach($get_all_worker_at_one_array as $key=>$value){
				if($key<$num){
				$arr[] = $value;
				}
				}
			
			return $arr;
		}

	function notification_if_not_available_service_provider($start, $end){
			$get_all_worker_at_one_array = count($this->get_all_worker_at_one_array($start, $end));
			
			return $get_all_worker_at_one_array;
			
		}

	



	function get_appointment_availablity_by_date_and_attendence_for_specific_time_checking($ccs, $cce){
				global $wpdb;
				
				$start = date ("Y-m-d H:i:s", $ccs);
				$end = date ("Y-m-d H:i:s", $cce);
				$requested_appointment = $_REQUEST['attendees'];
				$app_appointments_taken = $wpdb->get_var( "SELECT COUNT(*) FROM wp_app_appointments WHERE start = '$start%' AND end = '$end%'" );
				//print( 'Appointment Aready Taken: '. $app_appointments_taken).'<br>';
				if(!empty($_REQUEST['app_service_id']) and $_REQUEST['app_service_id']==2){
				$ids = count($this->get_worker_three_line($ccs, $cce));
                //$user_ID = $this->get_default_users_for_specific_day_and_hours($ccs);
				}else{
				//$ids = count($this->get_worker_new($ccs, $cce));
				$output = false;
				}
				//print( 'Worker Number: '. $ids .'<br>');
				if($ids != null or $ids != 0){
					if($ids==1){
					$availity = 3;
					}else{
					$availity = $ids * 3;
					}
				}
				if(!empty($app_appointments_taken) or $app_appointments_taken !=0  or $app_appointments_taken != NULL){
					$availity = $availity - $app_appointments_taken;
				}
				
				//if($availity<=$requested_appointment){
				if(!empty($availity) or $availity != 0){	
							if($availity<$requested_appointment){
								$output = false;
							}else{
								$output = true;
			
							}
					}else{
						$output = false;
					
				}
				
				return $output;
				
		
		}




	function check_time_which_need_to_fill_first($ccs){
		$date = date ("Y-m-d", $ccs);
		$new_css = date ("Y-m-d H:i:s", $ccs);
		
		$time = array(array('start'=>'09:00', 'end'=>'10:30'), array('start'=>'12:00', 'end'=>'13:30'), array('start'=>'15:00', 'end'=>'16:30'), array('start'=>'18:00', 'end'=>'19:30'));
		//print_r($time);
		$i = 0;
		$out = false;
		foreach($time as $array){
			//print_r($array);
			$i++;
			$new_ccs = new DateTime($date .' '. $array['start']);
			$new_ccs = $new_ccs->getTimestamp();
			$new_cee = new DateTime($date .' '. $array['end']);
			$new_cee = $new_cee->getTimestamp();
			//print('<br>Made By Array date: ' .$new_css . '<br>');
			//print('Array date: ' .$array['start']. '<br>');
			//print('new End date: ' .$new_cee . '<br>');
			
			
			//print('new date: ' .date ("Y-m-d H:i:s", $new_ccs) . ' Old date: ' .date ("Y-m-d H:i:s", $ccs). '<br>');
				// return true;
				
				if(!empty($_REQUEST['app_service_id']) and $_REQUEST['app_service_id']==2){
				
				$out = $this->get_appointment_availablity_by_date_and_attendence_for_specific_time_checking($new_ccs, $new_cee);
				if($out == true){
                    echo $i . '<br>';
					//break;
                    
				}
				}
				
			}
				return $out;
		}



	function after_9am_12pm_and_3pm_6pm_sign_up_1030am_0130am_and_0430pm_0730pm_register($ccs, $cce){
				global $wpdb;
				$start = date ("H:i", $ccs);
				$day = date ("l", $ccs);
				$end = date ("H:i", $cce);
        $date_check = date ("Y-m-d H:i:s", $ccs);
				$requested_appointment = $_REQUEST['attendees'];
				//$app_appointments_taken = $wpdb->get_var( "SELECT COUNT(*) FROM wp_app_appointments WHERE start = '$start%' AND end = '$end%'" );
				//echo 'Date:' . $start . ' ' .  $end . '<br>';
				
				if($day == 'Sunday' or $day == 'Monday' or $day == 'Tuesday' or $day == 'Wednesday' or $day == 'Thursday'){
					if($start == '10:30' or $start =='13:30' or $start =='16:30' or $start =='19:30' ){
					if(!empty($_REQUEST['app_service_id']) and $_REQUEST['app_service_id']==2){
									$ids = count($this->get_worker_three_line($ccs, $cce));
									}else{
									$ids = count($this->get_worker_new($ccs, $cce));
									}
							
							$output =  false;
							if(!empty($ids) or $ids != 0 ){
								echo '<br>  Start: '.$date_check. '<br>';
                                echo '<br>  ID: '.$ids. '<br>';
							$output = $this->check_time_which_need_to_fill_first($ccs);
							}else{
						      $output = false;		
						}
					}else{
					$output = false;
				}
				}else{
					$output = false;
				}
				
				return $output;
				
		}




	// add default guide for specific date
	
	function add_default_guide_for_specifiction_days($ccs, $cce){
		
		$time = array(array('start'=>'09:00', 'end'=>'10:30'), array('start'=>'12:00', 'end'=>'13:30'), array('start'=>'15:00', 'end'=>'16:30'), array('start'=>'18:00', 'end'=>'19:30'));
		
		$i = 0;
		$out = false;
		foreach($time as $array){
			
			$i++;
			$new_ccs = new DateTime($date .' '. $array['start']);
			$new_ccs = $new_ccs->getTimestamp();
			$new_cee = new DateTime($date .' '. $array['end']);
			$new_cee = $new_cee->getTimestamp();
			print('<br>Made By Array date: ' .$new_css . '<br>');
			print('Array date: ' .$array['start']. '<br>');
			//print('new End date: ' .$new_cee . '<br>');
			//echo $i . '<br>';
			
			//print('new date: ' .date ("Y-m-d H:i:s", $new_ccs) . ' Old date: ' .date ("Y-m-d H:i:s", $ccs). '<br>');
				// return true;
				
				if(!empty($_REQUEST['app_service_id']) and $_REQUEST['app_service_id']==2){
				$ids = count($this->get_worker_three_line($new_ccs, $new_cce));
				}else{
				$ids = count($this->get_worker_new($new_ccs, $new_cce));
				}
				if(!empty($ids) or $ids != 0){
				$out = $this->get_appointment_availablity_by_date_and_attendence_for_specific_time_checking($new_ccs, $new_cee);
				if($out == true){
					break;
				}
				}
				
			}
				return $out;
		
		
		
		}




	function get_appointment_availablity_by_date_and_attendence($ccs, $cce){
				global $wpdb;
				
				$start = date ("Y-m-d H:i:s", $ccs);
				$end = date ("Y-m-d H:i:s", $cce);
				$requested_appointment = $_REQUEST['attendees'];
				$app_appointments_taken = $wpdb->get_var( "SELECT COUNT(*) FROM wp_app_appointments WHERE start = '$start%' AND end = '$end%'" );
				//print( 'Number find: '. $app_appointments_taken).'<br>';
				if(!empty($_REQUEST['app_service_id']) and $_REQUEST['app_service_id']==2){
				$ids = count($this->get_worker_three_line($ccs, $cce));
				}else{
				$ids = count($this->get_worker_new($ccs, $cce));
				}
				//print('<br>available: '.$ids.'<br>');
				if($ids != null or $ids != 0){
					if($ids==1){
						$availity = 3;
					}else{
						$availity = $ids * 3;
					}
				}
				
				
				//print('<br>'.$availity.'<br>');
				if(!empty($app_appointments_taken) and $app_appointments_taken !=0 ){
					$availity = $availity - $app_appointments_taken;
				}
				
				
				//if($availity<=$requested_appointment){
				if($availity<$requested_appointment){
					if($ids != null or $ids != 0){
						if($availity>=6){
						return false;
						}else{
						return true;
						}
					}
					
					
				}else{
					return false;
				}
				
		
		}

	
	


	function get_appointment_availablity_by_date_and_attendence_reservationcode($ccs, $cce){
				global $wpdb;
				
				$start = date ("Y-m-d H:i:s", $ccs);
				$end = date ("Y-m-d H:i:s", $cce);
				$requested_appointment = $_REQUEST['attendees'];
				$reservation_code = $_REQUEST['reservation_code'];
				$app_appointments_taken = $wpdb->get_var( "SELECT COUNT(*) FROM wp_app_appointments WHERE start = '$start%' AND end = '$end%' AND reservation_code LIKE '$reservation_code' AND `status` LIKE  'reserved'" );
/*				if(!empty($_REQUEST['app_service_id']) and $_REQUEST['app_service_id']==2){
				$ids = count($this->get_worker_three_line($ccs, $cce));
				}else{
				$ids = count($this->get_worker_new($ccs, $cce));
				}
				if($ids != null){
					if($ids==1){
					$availity = 3;
					}else{
					$availity = $ids * 3;
					}
				}
*/	

				
				//if($availity<=$requested_appointment){
				if($requested_appointment<=$app_appointments_taken){
					return true;
					}else{
					return false;
					}
				
		
		}

	
	
	




	
	/*Check the database all appointment date. if get more than 15 it will return true. */
	

	/**
	 * Make checks on submitted fields and save appointment
	 * @return json object
	 */
	function post_confirmation() {

		if ( !$this->check_spam() )
			die( json_encode( array("error"=>apply_filters( 'app_spam_message',__( 'You have already applied for an appointment. Please wait until you hear from us.', 'appointments')))));

		global $wpdb, $current_user, $post;

		$values 		= explode( ":", $_POST["value"] );
		$location 		= $values[0];
		$service 		= $values[1];
		$worker 		= $values[2];
		$start 			= $values[3];
		$end 			= $values[4];
		$post_id		= $values[5];
		$number_done		= $values[6];
		//$refund		= $values[7];

		if ( is_user_logged_in( ) ) {
			$user_id = $current_user->ID;
			$userdata = get_userdata( $current_user->ID );
			$user_email = $userdata->email;

			$user_name = $userdata->display_name;
			if ( !$user_name ){
                                $first_name = get_user_meta($worker, 'first_name', true);
                                $last_name = get_user_meta($worker, 'last_name', true);
                                $user_name = $first_name . " " . $last_name;
                        }
			if ( "" == trim( !$user_name ) )
				$user_name = $userdata->user_login;
		}
		else{
			$user_id = 0;
			$user_email = '';
			$user_name = '';
		}

		// A little trick to pass correct lsw variables to the get_price, is_busy and get_capacity functions
		$_REQUEST["app_location_id"] = $location;
		$_REQUEST["app_service_id"] = $service;
		$_REQUEST["app_provider_id"] = $worker;
		$this->get_lsw();

		// Default status
		$status = 'pending';

		if ( 'yes' != $this->options["payment_required"] && isset( $this->options["auto_confirm"] ) && 'yes' == $this->options["auto_confirm"] )
			$status = 'confirmed';

		// We may have 2 prices now: 1) Service full price, 2) Amount that will be paid to Paypal
		$price = $this->get_price( );
		$price = apply_filters( 'app_post_confirmation_price', $price, $service, $worker, $start, $end );
		$paypal_price = $this->get_price( true );
		$paypal_price = apply_filters( 'app_post_confirmation_paypal_price', $paypal_price, $service, $worker, $start, $end );

		// Break here - is the appointment free and, if so, shall we auto-confirm?
		if (
			!$price && !$paypal_price // Free appointment ...
			&&
			'pending' === $status && "yes" === $this->options["payment_required"] // ... in a paid environment ...
			&&
			(!empty($this->options["auto_confirm"]) && "yes" === $this->options["auto_confirm"]) // ... with auto-confirm activated
		) {
			$status = defined('APP_CONFIRMATION_ALLOW_FREE_AUTOCONFIRM') && APP_CONFIRMATION_ALLOW_FREE_AUTOCONFIRM
				? 'confirmed'
				: $status
			;
		}

		if ( isset( $_POST["app_name"] ) )
			$name = sanitize_text_field( $_POST["app_name"] );
		else
			$name = $user_name;

		$name_check = apply_filters( "app_name_check", true, $name );
		if ( !$name_check )
			$this->json_die( 'name' );

		if ( isset( $_POST["app_email"] ) )
			$email = $_POST["app_email"];
		else
			$email = $user_email;

		if ( $this->options["ask_email"] && !is_email( $email ) )
			$this->json_die( 'email' );

		if ( isset( $_POST["app_phone"] ) )
			$phone = sanitize_text_field( $_POST["app_phone"] );
		else
			$phone = '';

		$phone_check = apply_filters( "app_phone_check", true, $phone );
		if ( !$phone_check )
			$this->json_die( 'phone' );

		if ( isset( $_POST["app_address"] ) )
			$address = sanitize_text_field( $_POST["app_address"] );
		else
			$address = '';

		$address_check = apply_filters( "app_address_check", true, $address );
		if ( !$address_check )
			$this->json_die( 'address' );

		if ( isset( $_POST["app_city"] ) )
			$city = sanitize_text_field( $_POST["app_city"] );
		else
			$city = '';

		$city_check = apply_filters( "app_city_check", true, $city );
		if ( !$city_check )
			$this->json_die( 'city' );

		if ( isset( $_POST["app_note"] ) )
			$note = sanitize_text_field( $_POST["app_note"] );
		else
			$note = '';

		if ( isset( $_POST["number_done"] ) )
			$number_done = sanitize_text_field( $_POST["number_done"] );
		else
			$number_done = '';

		if ( isset( $_POST["app_gcal"] ) && $_POST["app_gcal"] )
			$gcal = $_POST["app_gcal"];
		else
			$gcal = '';

		if ( isset( $_POST["attendees"] ) && $_POST["attendees"] )
			$attendees = $_POST["attendees"];
		else
			$attendees = '';

		if ( isset( $_POST["coupon"] ) && $_POST["coupon"] )
			$coupon = $_POST["coupon"];
		else
			$coupon = '';


		if ( isset( $_POST["refund"] ) && $_POST["refund"] )
			$refund = $_POST["refund"];
		else
			$refund = '';

		if ( isset( $_POST["att_info"] ) && $_POST["att_info"] )
			$att_info = $_POST["att_info"];
		else
			$att_info = '';


		if ( isset( $_POST["reservation"] ) && $_POST["reservation"] )
			$reservation = $_POST["reservation"];
		else
			$reservation = '0';


		if ( isset( $_POST["reservation_number"] ) && $_POST["reservation_number"] )
			$reservation_number = $_POST["reservation_number"];
		else
			$reservation_number = '0';




		do_action('app-additional_fields-validate');

		$er_ms ='Please fillup the Attendees Information First';
		if(!empty($att_info)){
			
		$wprl_att_info= explode( "!", $att_info );
		//die( json_encode( array("error"=>$wprl_att_info)));
		foreach ($wprl_att_info as $ar){
		if(!empty($ar))	{
			$ar= explode( "|", $ar );
			
			if(!empty($ar)){
				foreach($ar as $a){
					if(empty($a)){
						die( json_encode( array("error"=>$er_ms)));
						
						}
					}
				
				}else{
				die( json_encode( array("error"=>$er_ms)));
			}
			
		}else{
			die( json_encode( array("error"=>$er_ms)));
			}
		
			}
		
		}
		
		
		

		// It may be required to add additional data here
		$note = apply_filters( 'app_note_field', $note );

		$service_result = $this->get_service( $service );

		if ( $service_result !== null )
			$duration = $service_result->duration;
		if ( !$duration )
			$duration = $this->get_min_time(); // In minutes

		$duration = apply_filters( 'app_post_confirmation_duration', $duration, $service, $worker, $user_id );
//shimion

		$checking = $this->check_and_conform_attendence($start, $end);

/*		if(!empty($checking) and $checking != 0):
		$new_checking = $checking + $attendees;
		$remaining_checking = 30 - $checking ;
		$massage = 'You have remaining only '.$remaining_checking.' attendees';
		if ( $new_checking >= 31 ){
			die( json_encode( array("error"=>apply_filters( 'app_booked_message', __( $massage, 'appointments')))));
		}
		endif;*/


		$status = apply_filters( 'app_post_confirmation_status', $status, $price, $service, $worker, $user_id );
		$num =0;
		$cu = $this->check_cuppon($coupon)	;
		
		

			if($refund=='twenty'){
				if(!empty($cu)){
					die( json_encode( array("error"=>__( 'Coupon will only work with full price.', 'appointments'))));
					}
				$price = 20;
				//$status = 'pending';
				$status = 'deposit';
				}else{
				
				if(!empty($_REQUEST['app_service_id']) and $_REQUEST['app_service_id']==2){
						if($attendees>6 and $attendees<24){
							$price = 55;
						}elseif($attendees>24){
							$price = 50;
						}
				}else{
						if($attendees>24){
							$price = 25;
						}
				}
						
						
						if($cu){
							$price = $price-$cu;
						}else{
							$price = $price;
						}
				$status = 'confirmed';
		
			}	
			$worker_checking = $this->notification_if_not_available_service_provider($start, $end);
			/*if(!empty($worker_checking) and $worker_checking<$attendees){
				$tx = 'We will be able to add only '.$worker_checking.' appointments but not for others because of other service providers are not available';
			die( json_encode( array("error"=>__( $tx, 'appointments'))));
				}*/

			
			if($reservation==1){
				$price_count = $price * $reservation_number;
				}else{
				$price_count = $price * $attendees;
				}

		//we had to stop it		
		/*$result = $wpdb->insert( $this->app_table,
							array(
								'created'	=>	date ("Y-m-d H:i:s", $this->local_time ),
								'user'		=>	$user_id,
								'name'		=>	$name,
								'email'		=>	$email,
								'phone'		=>	$phone,
								'address'	=>	$address,
								'city'		=>	$city,
								'location'	=>	$location,
								'service'	=>	$service,
								'worker'	=> 	$this->check_worker($start, $end),
								'price'		=>	$price,
								'coupon'	=>	$coupon,
								'status'	=>	$status,
								'start'		=>	date ("Y-m-d H:i:s", $start),
								'end'		=>	date ("Y-m-d H:i:s", $start + ($duration * 60 ) ),
								'note'		=>	$note
							)
						);*/
						
						$worker_custom = $this->check_worker($start, $end, $attendees);
						
						if(!empty($worker_custom)){
							$i=0;
							$a='';
							foreach($worker_custom as $key=>$value){
								$i++;
								if($i==1){
								$a .= $value;
									}else{
								$a .=  "|" . $value;
										}
								}
							}
						
						$c_worker = $a;
						
						
						$result = array(
								'worker'	=> 	$c_worker,
								'created'	=>	date ("Y-m-d H:i:s", $this->local_time ),
								'user'		=>	$user_id,
								'name'		=>	$name,
								'email'		=>	$email,
								'phone'		=>	$phone,
								'address'	=>	'none',
								'city'		=>	'none',
								'location'	=>	'none',
								'service'	=>	$service,
								'price'		=>	$price,
								'coupon'	=>	$coupon,
								'status'	=>	$status,
								'start'		=>	date ("Y-m-d H:i:s", $start),
								'end'		=>	date ("Y-m-d H:i:s", $start + ($duration * 60 ) ),
								'note'		=>	'none',
								'att_info'			=>$att_info,
								'reservation'			=>$reservation,
								'reservation_number'			=>$reservation_number,
							);
							
						
						if(!empty($result)){
							$i=0;
							foreach($result as $key=>$value){
								$i++;
								if($i==1){
								$res .= $value;
									}else{
								$res .=  "," . $value;
										}
								}
							}
							
						$result = $res;
						$work_custom = str_replace("|", ",", $c_worker);
						$cid = uniqid();
						$reservation_code = uniqid();
				if($reservation==1){
				$custom2 = $reservation_number .'|'. $reservation_code .'|'.$status .'|reserved|' . $email . '|' . $work_custom .'|'. date ("Y-m-d ", $start) .'|'. date ("H:i:s", $start) .'|'.date ("H:i:s", $start + ($duration * 60 ) ).'|'. $price_count .'|'. $price .'|'. $attendees . '|' . $name . '|'. $reservation;
				
				}else{
				$custom2 = $attendees .'|'. $reservation_code .'|'.$status .'|removed|' . $email . '|' . $work_custom .'|'. date ("Y-m-d ", $start) .'|'. date ("H:i:s", $start) .'|'.date ("H:i:s", $start + ($duration * 60 )) .'|'. $price_count . '|'. $price . '|' . $attendees . '|' . $name . '|'. $reservation;
				}

						
						
						
		/*if ( !$result ) {
			die( json_encode( array("error"=>__( 'Appointment could not be saved. Please contact website admin.', 'appointments'))));
		}*/
		
		
		//$result = array_merge($result, $worker_checking);

		// A new appointment is accepted, so clear cache
		$insert_id = $wpdb->insert_id; // Save insert ID
		$this->flush_cache();
		$this->save_cookie( $insert_id, $name, $email, $phone, $address, $city, $gcal );
		do_action( 'app_new_appointment', $insert_id );

		// Send confirmation for pending, payment not required cases, if selected so
		if ( 'yes' != $this->options["payment_required"] && isset( $this->options["send_notification"] )
			&& 'yes' == $this->options["send_notification"] && 'deposit' == $status )
			$this->send_notification( $insert_id );

		// Send confirmation if we forced it
		if ( 'confirmed' == $status && isset( $this->options["send_confirmation"] ) && 'yes' == $this->options["send_confirmation"] )
			$this->send_confirmation( $insert_id );

		// Add to GCal API
		if (is_object($this->gcal_api) && $this->gcal_api->is_syncable_status($status)) {
			$this->gcal_api->insert( $insert_id );
		}

		// GCal button
		if ( isset( $this->options["gcal"] ) && 'yes' == $this->options["gcal"] && $gcal )
			$gcal_url = $this->gcal( $service, $start, $start + ($duration * 60 ), false, $address, $city );
		else
			$gcal_url = '';

		// Check if this is a App Product page and add variation if it is
		$post = get_post( $post_id );
		if ( $this->check_marketpress_plugin() && 'product' == $post->post_type && strpos( $post->post_content, '[app_' ) !== false ) {
			$mp = 1;
			$variation = $this->add_variation( $insert_id, $post_id, $service, $worker, $start, $end );
		}
		else
			$mp = $variation = 0;

		if ( isset( $this->options["gcal_same_window"] ) && $this->options["gcal_same_window"] )
			$gcal_same_window = 1;
		else
			$gcal_same_window = 0;

		if ( isset( $this->options["payment_required"] ) && 'yes' == $this->options["payment_required"] ) {
			die( json_encode(
							array(
							"cell"				=> $_POST["value"],
							"app_id"			=> $insert_id,
							"refresh"			=> 0,
							//"price"				=> $paypal_price,
							"price"				=> $price_count,
							"service_name"		=> stripslashes( $service_result->name ),
							'gcal_url'			=> $gcal_url,
							'gcal_same_window'	=> $gcal_same_window,
							'mp'				=> $mp,
							'variation'			=> $variation,
							'coupon'			=>$this->check_cuppon($coupon),
							'refund'			=> $refund,
							'result'			=>$result,
							'att_info'			=>$att_info,
							'reservation'		=>$reservation,
							'reservation_number'=>$reservation_number,
							'custom2'=>$custom2,
							'cid'	=> $cid,
							'reservation_code'=>$reservation_code,
							)
						)
					);
		}
		else {
			die( json_encode(
							array(
							"cell"				=> $_POST["value"],
							"app_id"			=> $insert_id,
							"refresh"			=> 1,
							'gcal_url'			=> $gcal_url,
							'gcal_same_window'	=> $gcal_same_window,
							)
				));
		}
	}

	/**
	 * Build GCal url for GCal Button. It requires UTC time.
	 * @param start: Timestamp of the start of the app
	 * @param end: Timestamp of the end of the app
	 * @param php: If this is called for php. If false, called for js
	 * @param address: Address of the appointment
	 * @param city: City of the appointment
	 * @return string
	 */
	 
	 
	function app_custom_save_reserved(){
		
		$custom = $_POST['save_reserved'];
			if(!empty($custom)){
					//print_r($_POST['custom']);
					
					$values= explode( ",", $custom );
					
					$worker 		= $values[0];
					$created 		= $values[1];
					$user 			= $values[2];
					$name 			= $values[3];
					$email 			= $values[4];
					$phone 			= $values[5];
					$address 		= $values[6];
					$city 			= $values[7];
					$location 		= $values[8];
					$service 		= $values[9];
					$price 			= $values[10];
					$coupon 		= $values[11];
					$status 		= $values[12];
					$start 			= $values[13];
					$end 		= $values[14];
					$note 		= $values[15];
					$att_info 		= $values[16];
					$reservation 		= $values[17];
					$reservation_number 		= $values[18];
					//print($worker);
							//if($reservation==1){
								$reservation_code = $_POST['app_custom_reservation_code'];
							//	}
					$cid = $_POST['app_cid'];
								global $wpdb;
								$result1 = $wpdb->insert( 'wp_app_appointments_custom',
										array(
											'created'	=>	$created,
											'cid'		=>	$cid,
										//	'user'		=>	$user_id,
											'user'		=>	$user,
											'name'		=>	$name,
											'email'		=>	$email,
											'phone'		=>	$phone,
											'address'	=>	$address,
											'city'		=>	$city,
											'location'	=>	$location,
											'service'	=>	$service,
											//'worker'	=> 	$worker,
											'price'		=>	$price,
											'coupon'	=>	$coupon,
											'status'	=>	$status,
											'start'		=>	$start,
											'end'		=>	$end,
											//'note'		=>	$note,
											//'att_info'		=>	$att_info,
											'reservation'		=>	$reservation,
											'reservation_code'		=>	$reservation_code,
										),
										array('%s')
									);
									
									
							if($result1 === false){
										die( json_encode(
														array(
														"result"				=> $wpdb->show_errors(),
														"express"				=> 'sorry',
														//"cid"					=>$cid
														)
											));
										}		
			
			
					
					if(!empty($worker)){
						$arrs= explode( "|", $worker );
						//print($worker);
						//print_r($arrs);
						$arrs_c = count($arrs);
						
						$att_info= explode( "!", $att_info );
						//print_r($att_info);
						$att_info_c = count($att_info);
						
						$result_less = $att_info_c  - $arrs_c;
						//echo $result_less ;
						if(!empty($result_less) and $result_less=!0){
							$i = 0;
							$cr_arr=array();
							while($i<$result_less){
								//echo  $i;
								$cr_arr[] = '0';
								$i++;
								}
							//print_r($cr_arr);	
							$arrs = array_merge($arrs, $cr_arr);	
							
								
							}
						
						
						$array_combine = array_combine($att_info,$arrs);	
						//print_r($array_combine);
						$i = 0;
						foreach($array_combine as $key=>$val){
							$i++;
							
							if($reservation==1){
								$status = 'reserved';
								}else{
								$status = 'removed';	
								}
							
							//$status = 'reserved';
							/*if($reservation==1){
							if($i>$reservation_number){
								$status = 'reserved';
								}
							}*/
							
								$each_info= explode( "|", $key );
								$name = $each_info['0'];
								$age = $each_info['1'];
								$weight = $each_info['2'];
								$ins = $each_info['3'];
								global $wpdb;
								$result = $wpdb->insert( $this->app_table,
										array(
											'created'	=>	$created,
											'cid'		=>	$cid,
											'reservation'		=>	$reservation,
											'reservation_code'		=>	$reservation_code,
											'user'		=>	$user,
											'name'		=>	$name,
											'age'		=>	$age,
											'weight'	=>	$weight,
											'insurance_waver'	=>	$ins,
											'email'		=>	$email,
											'phone'		=>	$phone,
											'address'	=>	$address,
											'city'		=>	$city,
											'location'	=>	$location,
											'service'	=>	$service,
											'worker'	=> 	$val,
											'price'		=>	$price,
											'coupon'	=>	$coupon,
											'status'	=>	$status,
											'start'		=>	$start,
											'end'		=>	$end,
											'note'		=>	$note,
										),
										array('%s')
									);
									
							
							}
								
						}
					
					}		
		
		
		
		
		
		
			die( json_encode(
							array(
							"save_reserved"				=> $_POST["save_reserved"],
							"cid"					=>$cid
							)
				));
		
		} 
	 
	 
	 
	function gcal( $service, $start, $end, $php=false, $address, $city ) {
		// Find time difference from Greenwich as GCal asks UTC
		$tdif = current_time('timestamp') - time();
		$text = sprintf( __('%s Appointment', 'appointments'), $this->get_service_name( $service ) );
		if ( !$php )
			$text = esc_js( $text );

		if ( isset( $this->options["gcal_location"] ) && '' != trim( $this->options["gcal_location"] ) )
			$location = esc_js( str_replace( array('ADDRESS', 'CITY'), array($address, $city), $this->options["gcal_location"] ) );
		else
			$location = esc_js( get_bloginfo( 'description' ) );

		$param = array(
					'action'	=> 'TEMPLATE',
					'text'		=> $text,
					'dates'		=> date( "Ymd\THis\Z", $start - $tdif ) . "/" . date( "Ymd\THis\Z", $end - $tdif ),
					'sprop'		=> 'website:' . home_url(),
					'location'	=> $location
				);

		return add_query_arg( apply_filters( 'app_gcal_variables', $param, $service, $start, $end ), 'http://www.google.com/calendar/event' );
	}

	/**
	 * Die showing which field has a problem
	 * @return json object
	 */
	function json_die( $field_name ) {
		die( json_encode( array("error"=>sprintf( __( 'Something wrong about the submitted %s', 'appointments'), $this->get_field_name($field_name)))));
	}

	/**
	 * Check for too frequent back to back apps
	 * return true means no spam
	 * @return bool
	 */
	function check_spam() {
		global $wpdb;
		if ( !isset( $this->options["spam_time"] ) || !$this->options["spam_time"] ||
			!isset( $_COOKIE["wpmudev_appointments"] ) )
			return true;

		$apps = unserialize( stripslashes( $_COOKIE["wpmudev_appointments"] ) );

		if ( !is_array( $apps ) || empty( $apps ) )
			return true;

		// Get details of the appointments
		$q = '';
		foreach ( $apps as $app_id ) {
			// Allow only numeric values
			if ( is_numeric( $app_id ) )
				$q .= " ID=".$app_id." OR ";
		}
		$q = rtrim( $q, "OR " );

		$checkdate = date( 'Y-m-d H:i:s', $this->local_time - $this->options["spam_time"] );

		$results = $wpdb->get_results( "SELECT * FROM " . $this->app_table .
					" WHERE created>'".$checkdate."' AND status='pending' AND (".$q.")  " );
		// A recent app is found
		if ( $results )
			return false;

		return true;
	}

	/**
	 *	IPN handling for Paypal
	 */
	 
	function set_html_content_type() {
		return 'text/html';
	}	 
	function handle_paypal_return() {
		add_filter( 'wp_mail_content_type', array($this,'set_html_content_type') );
		// PayPal IPN handling code
		$this->options = get_option( 'appointments_options' );

		if ((isset($_POST['payment_status']) || isset($_POST['txn_type'])) && isset($_POST['custom'])) {

			if ($this->options['mode'] == 'live') {
				$domain = 'https://www.paypal.com';
			} else {
				$domain = 'https://www.sandbox.paypal.com';
			}

			$req = 'cmd=_notify-validate';
			if (!isset($_POST)) $_POST = $HTTP_POST_VARS;
			foreach ($_POST as $k => $v) {
				if (get_magic_quotes_gpc()) $v = stripslashes($v);
				$req .= '&' . $k . '=' . $v;
			}

			$header = 'POST /cgi-bin/webscr HTTP/1.0' . "\r\n"
					. 'Content-Type: application/x-www-form-urlencoded' . "\r\n"
					. 'Content-Length: ' . strlen($req) . "\r\n"
					. "\r\n";

			@set_time_limit(60);
			if ($conn = @fsockopen($domain, 80, $errno, $errstr, 30)) {
				fputs($conn, $header . $req);
				socket_set_timeout($conn, 30);

				$response = '';
				$close_connection = false;
				while (true) {
					if (feof($conn) || $close_connection) {
						fclose($conn);
						break;
					}

					$st = @fgets($conn, 4096);
					if ($st === false) {
						$close_connection = true;
						continue;
					}

					$response .= $st;
				}
				
				//print_r($response);

				$error = '';
				$lines = explode("\n", str_replace("\r\n", "\n", $response));
				// looking for: HTTP/1.1 200 OK
				if (count($lines) == 0) $error = 'Response Error: Header not found';
				else if (substr($lines[0], -7) != ' 200 OK') $error = 'Response Error: Unexpected HTTP response';
				else {
					// remove HTTP header
					while (count($lines) > 0 && trim($lines[0]) != '') array_shift($lines);

					// first line will be empty, second line will have the result
					if (count($lines) < 2) $error = 'Response Error: No content found in transaction response';
					else if (strtoupper(trim($lines[1])) != 'VERIFIED') $error = 'Response Error: Unexpected transaction response';
				}

				if ($error != '') {
					$this->log( $error );
					exit;
				}
			}

			// We are using server time. Not Paypal time.
			$timestamp = $this->local_time;

			$new_status = false;
			// process PayPal response
			switch ($_POST['payment_status']) {
				case 'Partially-Refunded':
					break;

				case 'In-Progress':
					break;

				case 'Completed':
				case 'Processed':
				
					$this->receive_paypal_2($_POST['custom']);
					$message = sprintf( __('Paypal confirmation arrived. Please check appointment name: %s', 'appointments'), $this->mail_massage_name($_POST['custom']) );
					wp_mail( $this->get_admin_email(), 'Appointment time', $_POST['custom'] ); 
					$reservation_massage = '';
					//$reservation_massage .= 'Hello ';
					wp_mail( $this->collect_email_address($_POST['custom']), 'Appointment are registered', $this->mail_massage($_POST['custom']) ); 
					//mail( 'shimion_b@yahoo.com', 'Diff one', 'verifing if it is working.' ); 
					$message = sprintf( __('You have an appointment at %s', 'appointments'), '' );
					//$this->Worker_send_mail($_POST['custom'], 'You have a new appointments', $this->message_headers());
					
					$workers = $this->set_uniquer_ids_for_sending_email($_POST['custom']);
					foreach($workers as $worker){
									$user = get_user_by( 'id',$worker);
									wp_mail( $user->user_email, __('You have a new appointment','appointments'), sprintf( __('You have an appointment at %s', 'appointments'), $this->get_start_date_for_sending_email($_POST['custom']) ), $header );
						}
					
					
					
					//$this->semt_email_service_provider_by_id($_POST['custom']);
					// case: successful payment
					$amount = $_POST['mc_gross'];
					$currency = $_POST['mc_currency'];

					$this->record_transaction('1', $amount, $currency, $timestamp, $_POST['txn_id'], $_POST['payment_status'], '');
					break;

				case 'Reversed':
					// case: charge back
					$note = __('Last transaction has been reversed. Reason: Payment has been reversed (charge back)', 'appointments');
					$amount = $_POST['mc_gross'];
					$currency = $_POST['mc_currency'];

					$this->record_transaction($_POST['custom'], $amount, $currency, $timestamp, $_POST['txn_id'], $_POST['payment_status'], $note);
					break;

				case 'Refunded':
					// case: refund
					$note = __('Last transaction has been reversed. Reason: Payment has been refunded', 'appointments');
					$amount = $_POST['mc_gross'];
					$currency = $_POST['mc_currency'];

					$this->record_transaction($_POST['custom'], $amount, $currency, $timestamp, $_POST['txn_id'], $_POST['payment_status'], $note);
					break;

				case 'Denied':
					// case: denied
					$note = __('Last transaction has been reversed. Reason: Payment Denied', 'appointments');
					$amount = $_POST['mc_gross'];
					$currency = $_POST['mc_currency'];

					$this->record_transaction($_POST['custom'], $amount, $currency, $timestamp, $_POST['txn_id'], $_POST['payment_status'], $note);

					break;

				case 'Pending':
					// case: payment is pending
					$pending_str = array(
						'address' => __('Customer did not include a confirmed shipping address', 'appointments'),
						'authorization' => __('Funds not captured yet', 'appointments'),
						'echeck' => __('eCheck that has not cleared yet', 'appointments'),
						'intl' => __('Payment waiting for aproval by service provider', 'appointments'),
						'multi-currency' => __('Payment waiting for service provider to handle multi-currency process', 'appointments'),
						'unilateral' => __('Customer did not register or confirm his/her email yet', 'appointments'),
						'upgrade' => __('Waiting for service provider to upgrade the PayPal account', 'appointments'),
						'verify' => __('Waiting for service provider to verify his/her PayPal account', 'appointments'),
						'*' => ''
						);
					$reason = @$_POST['pending_reason'];
					$note = __('Last transaction is pending. Reason: ', 'appointments') . (isset($pending_str[$reason]) ? $pending_str[$reason] : $pending_str['*']);
					$amount = $_POST['mc_gross'];
					$currency = $_POST['mc_currency'];

					// Save transaction.
					$this->record_transaction($_POST['custom'], $amount, $currency, $timestamp, $_POST['txn_id'], $_POST['payment_status'], $note);

					break;

				default:
					// case: various error cases
			}
		} else {
			// Did not find expected POST variables. Possible access attempt from a non PayPal site.
			// This is IPN response, so echoing will not help. Let's log it.
			$this->log( 'Error: Missing POST variables. Identification is not possible.' );
			exit;
		}
		exit;
	}

	/**
	 * Find timestamp of first day of month for a given time
	 * @param time: input whose first day will be found
	 * @param add: how many months to add
	 * @return integer (timestamp)
	 * @since 1.0.4
	 */
	function first_of_month( $time, $add ) {
		$year = date( "Y", $time );
		$month = date( "n",  $time ); // Notice "n"

		return mktime( 0, 0, 0, $month+$add, 1, $year );
	}
	

	/**
	 * Helper function to create a monthly schedule
	 */
	function get_monthly_calendar( $timestamp=false, $class='', $long, $widget ) {
		global $wpdb;

		$this->get_lsw();

		$price = $this->get_price( );

		$date = $timestamp ? $timestamp : $this->local_time;

		$year = date("Y", $date);
		$month = date("m",  $date);
		$time = strtotime("{$year}-{$month}-01");

		$days = (int)date('t', $time);
		$first = (int)date('w', strtotime(date('Y-m-01', $time)));
		$last = (int)date('w', strtotime(date('Y-m-' . $days, $time)));

		$schedule_key = sprintf('%sx%s', strtotime(date('Y-m-01', $time)), strtotime(date('Y-m-' . $days, $time)));

		$tbl_class = $class;
		$tbl_class = $tbl_class ? "class='{$tbl_class}'" : '';

		$ret = '';
		$ret .= '<div class="app_monthly_schedule_wrapper">';

		$ret .= '<a id="app_schedule">&nbsp;</a>';
		$ret  = apply_filters( 'app_monthly_schedule_before_table', $ret );
		$ret .= "<table width='100%' {$tbl_class}>";
		$ret .= $this->_get_table_meta_row_monthly('thead', $long);
		$ret .= '<tbody>';

		$ret = apply_filters( 'app_monthly_schedule_before_first_row', $ret );

		if ( $first > $this->start_of_week )
			$ret .= '<tr><td class="no-left-border" colspan="' . ($first - $this->start_of_week) . '">&nbsp;</td>';
		else if ( $first < $this->start_of_week )
			$ret .= '<tr><td class="no-left-border" colspan="' . (7 + $first - $this->start_of_week) . '">&nbsp;</td>';
		else
			$ret .= '<tr>';

		$todays_no = date("w", $this->local_time ); // Number of today
		$working_days = $this->get_working_days( $this->worker, $this->location ); // Get an array of working days
		$capacity = $this->get_capacity();
		$time_table = '';

		for ($i=1; $i<=$days; $i++) {
			$date = date('Y-m-' . sprintf("%02d", $i), $time);
			$dow = (int)date('w', strtotime($date));
			$ccs = strtotime("{$date} 00:00");
			$cce = strtotime("{$date} 23:59");
			if ($this->start_of_week == $dow)
				$ret .= '</tr><tr>';

			$class_name = '';
			// First mark passed days
			if ( $this->local_time > $cce )
				$class_name = 'notpossible app_past';
			// Then check if this time is blocked
			else if ( isset( $this->options["app_lower_limit"] ) && $this->options["app_lower_limit"]
				&&( $this->local_time + $this->options["app_lower_limit"] * 3600) > $cce )
				$class_name = 'notpossible app_blocked';
			// Check today is holiday
			else if ( $this->is_holiday( $ccs, $cce ) )
				$class_name = 'notpossible app_holiday';
			// Check if we are working today
			else if ( !in_array( date("l", $ccs ), $working_days ) && !$this->is_exceptional_working_day( $ccs, $cce ) )
				$class_name = 'notpossible notworking';
			// Check if we are exceeding app limit at the end of day
			else if ( $cce > $this->local_time + ( $this->get_app_limit() + 1 )*86400 )
				$class_name = 'notpossible';
			// If nothing else, then it must be free unless all time slots are taken
			else {
				// At first assume all cells are busy
				$this->is_a_timetable_cell_free = false;

				$time_table .= $this->get_timetable( $ccs, $capacity, $schedule_key );

				// Look if we have at least one cell free from get_timetable function
				if ( $this->is_a_timetable_cell_free )
					$class_name = 'free';
				else
					$class_name = 'busy';
				// Clear time table for widget
				if ( $widget )
					$time_table = '';
			}
			// Check for today
			if ( $this->local_time > $ccs && $this->local_time < $cce )
				$class_name = $class_name . ' today';

			$ret .= '<td class="'.$class_name.'" title="'.date_i18n($this->date_format, $ccs).'"><p>'.$i.'</p>
			<input type="hidden" class="appointments_select_time" value="'.$ccs .'" /></td>';

		}
		if ( 0 == (6 - $last + $this->start_of_week) )
			$ret .= '</tr>';
		else if ( $last > $this->start_of_week )
			$ret .= '<td class="no-right-border" colspan="' . (6 - $last + $this->start_of_week) . '">&nbsp;</td></tr>';
		else if ( $last + 1 == $this->start_of_week )
			$ret .= '</tr>';
		else
			$ret .= '<td class="no-right-border" colspan="' . (6 + $last - $this->start_of_week) . '">&nbsp;</td></tr>';

		$ret = apply_filters( 'app_monthly_schedule_after_last_row', $ret );
		$ret .= '</tbody>';
		$ret .= $this->_get_table_meta_row_monthly('tfoot', $long);
		$ret .= '</table>';
		$ret  = apply_filters( 'app_monthly_schedule_after_table', $ret );
		$ret .= '</div>';

		$ret .= '<div class="app_timetable_wrapper">';
		$ret .= $time_table;
		$ret .= '</div>';

		$ret .= '<div style="clear:both"></div>';

		$script  = '';
		$script .= 'var selector = ".app_monthly_schedule_wrapper table td.free", callback = function (e) {';
			$script .= '$(selector).off("click", callback);';
			$script .= 'var selected_timetable=$(".app_timetable_"+$(this).find(".appointments_select_time").val());';
			$script .= '$(".app_timetable:not(selected_timetable)").hide();';
			$script .= 'selected_timetable.show("slow", function () { $(selector).on("click", callback); });';
		$script .= '};';
		$script .= '$(selector).on("click", callback);';

		$this->add2footer( $script );

		return $ret;
	}
	
	
	
	function _implement_new_time_frame($day_start){
		$date = date ("Y-m-d", $day_start);
		//print($date);
		global $wpdb;
		$dafault_time_frame = $this->default_hours;
		$time_frame = $wpdb->get_row( "SELECT * FROM wp_app_time_frame WHERE `date` = '$date%'" );
		//echo $hour. '<br>';
		//$time_start_new= $time->start *3600 + $day_start;
		//$time_end_new= $time->end *3600 + $day_start;
		$start_n = 23;
		 $end_n = 0;
		$arr = array();
		if(!empty($time_frame) and $time_frame != NULL){
				$time_decode = json_decode($time_frame->time);
					$start = date( "G", strtotime( $this->to_military($time_decode->start ) ) );
					$end = date( "G", strtotime( $this->to_military($time_decode->end ) ) );
					if($start < $start_n){
					$arr['start']= $start;
					}
					if($end > $end_n){
					$end_n = $time_decode->end;
					$arr['end']= $end;
					}
			
			}else{
		//$arr = array('start'=> $start, 'end'=> $end);
			$arr = $dafault_time_frame;
						
		}
		
		
		return $arr;
		
	}
	
	
	
	function get_start_end_hours($date){
		$date = date ("Y-m-d", $date);
		global $wpdb;
		$start_default = 23;
		 $end_default = 0;
		$arr = array();
		$querys = $wpdb->get_results( "SELECT * FROM  `wp_three_line_appointment` WHERE  `date` =  '$date'" );
		//print_r($querys);
		if(!empty($querys)){
			foreach($querys as $query){
					
						$start = date( "G", strtotime( $this->to_military($query->working_hours_start ) ) );
						$end = date( "G", strtotime( $this->to_military($query->working_hours_end ) ) );
						if($start < $start_default){
						$arr['start'] = $query->working_hours_start;
						}
						if($end > $end_default){
						$arr['end'] = $query->working_hours_end;
						
						}
					}
			}else{
			$arr = $this->default_hours;
			$start = date( "G", strtotime( $this->to_military($arr['start'] ) ) );
			$end = date( "G", strtotime( $this->to_military($arr['end'] ) ) );	
				if($start < $start_default){
					$arr['start'] = $arr['start'];
					}
				if($end > $end_default){
					$arr['end'] = $arr['end'];
					}
				}	
				//print('Get Start: ' .$arr['start'] .' ' . $start);	
				//print('<br>Get End: ' .$arr['end'] . ' ' . $end . '<br>');	
				return $arr;
			
				

		}
	

	function get_start_end_hours_break($date){
		$date = date ("Y-m-d", $date);
		global $wpdb;
		$start_default = 23;
		 $end_default = 0;
		$arr = array();
		$querys = $wpdb->get_results( "SELECT * FROM  `wp_three_line_appointment` 
WHERE  `date` =  '$date'" );
		//print_r($querys);
			foreach($querys as $query){
					
						$start = date( "G", strtotime( $this->to_military($query->working_hours_start ) ) );
						$end = date( "G", strtotime( $this->to_military($query->working_hours_end ) ) );
						if($start < $start_default){
						$arr['start'] = $query->working_hours_start;
						}
						if($end > $end_default){
						$arr['end'] = $query->working_hours_end;
						
						}
					}
					
					
				return $arr;
			
				

		}

    
    // @get the match value of array
    // @return bull
    
    function search_and_match($key_word, $search_array=array()){
        
        $key = array_search($key_word, $search_array); 
           
                if(empty($key) or $key == NULL or $key == FALSE){
                    $result = true;
                    
                }else{
                  $result = false;   
            }
            return $key_word;
        
        
    }
    
	

	/**
	 * Helper function to create a time table for monthly schedule
	 */
	function get_timetable( $day_start, $capacity, $schedule_key=false ) {
		// We need this only for the first timetable
		// Otherwise $time will be calculated from $day_start
		if ( isset( $_GET["wcalendar"] ) && (int)$_GET['wcalendar'] )
			$time = (int)$_GET["wcalendar"];
		else
			$time = $this->local_time;

		// Are we looking to today?
		// If today is a working day, shows its free times by default
		
		//print(date( "Y-m-d h:m", $day_start). '<br>');
		if ( date( 'Ymd', $day_start ) == date( 'Ymd', $time ) )
			$style = '';
		else
			$style = ' style="display:none"';

		$start = $end = 0;
		if ( $min_max = $this->min_max_wh( 0, 0 ,  $day_start) ) {
			$start = $min_max["min"];
			$end = $min_max["max"];
		}
		if ( $start >= $end ) {
			$start = 8;
			$end = 18;
		}
		//echo date( 'Y-m-d', $day_start ). '<br>';
		$start = apply_filters( 'app_schedule_starting_hour', $start );
		$end = apply_filters( 'app_schedule_ending_hour', $end );

       // $first = $this->time_choice_first;
       // $second = $this->time_choice_second;        
		//var_dump($first);
       // var_dump($second);
       // echo $start .'|'. $end. '<br>';
		$first = $start *3600 + $day_start; // Timestamp of the first cell
		$last = $end *3600 + $day_start; // Timestamp of the last cell
		//print(date( 'Y-m-d', $day_start ) . '<br>');
		//print($first . '!!' . $last . "<br>");
		
		//var_dump($start_end);
		
		//$first = $start_end['start']; // Timestamp of the first cell
		//$last = $start_end['end']; // Timestamp of the last cell
		//print($start_end['start']  . '||' . $start_end['end']. '<br><br>');
		
		
		$min_step_time = $this->get_min_time() * 60; // Cache min step increment

		if (defined('APP_USE_LEGACY_DURATION_CALCULUS') && APP_USE_LEGACY_DURATION_CALCULUS) {
			$step = $min_step_time; // Timestamp increase interval to one cell ahead
		} else {
			$service = $this->get_service($this->service);
			$step = (!empty($service->duration) ? $service->duration : $min_step_time) * 60; // Timestamp increase interval to one cell ahead
		}

		if (!(defined('APP_USE_LEGACY_DURATION_CALCULUS') && APP_USE_LEGACY_DURATION_CALCULUS)) {
			/*Shimion*/
			if($_REQUEST['app_service_id']== 2){
				$start_unpacked_days = $this->get_start_end_hours($day_start);
				
				}else{
				$start_unpacked_dayss = $this->get_start_end_hours($day_start);
			$start_result = $this->get_work_break( $this->location, $this->worker, 'open' );
			if (!empty($start_result->hours)) $start_unpacked_days = maybe_unserialize($start_result->hours);
			}
		} else $start_unpacked_days = array();
		if (defined('APP_BREAK_TIMES_PADDING_CALCULUS') && APP_BREAK_TIMES_PADDING_CALCULUS) {
			$break_result = $this->get_work_break($this->location, $this->worker, 'closed');
			if (!empty($break_result->hours)) $break_times = maybe_unserialize($break_result->hours);
		} else $break_times = array();

		//print_r( $start_unpacked_days);
		//print_r( $break_times);


		$ret  = '';
		$ret .= '<div class="app_timetable app_timetable_'.$day_start.'"'.$style.'>';
		$ret .= '<div class="app_timetable_title">';
		$ret .= date_i18n( $this->date_format, $day_start );
		$ret .= '</div>';

		// Allow direct step increment manipulation,
		// mainly for service duration based calculus start/stop times
		$step = apply_filters('app-timetable-step_increment', $step);

		for ( $t=$first; $t<$last; $t=$t+$step ) {
			
			$ccs = apply_filters('app_ccs', $t); 				// Current cell starts
			$cce = apply_filters('app_cce', $ccs + $step);		// Current cell ends

// Fix for service durations calculus and workhours start conflict with different duration services
// Example: http://premium.wpmudev.org/forums/topic/problem-with-time-slots-not-properly-allocating-free-time
			
			
			if (!empty($start_unpacked_days) && !(defined('APP_USE_LEGACY_DURATION_CALCULUS') && APP_USE_LEGACY_DURATION_CALCULUS)) {
			if($_REQUEST['app_service_id']==2){
				$this_day_key = date('l', $t);
					$this_day_opening_timestamp = strtotime(date('Y-m-d ' . $start_unpacked_days['start'], $ccs));
					//print('<br>'.date('Y-m-d ' . $this_day_key . ' ' . $start_unpacked_days['start'], $ccs).'<br>');
						//print('<br>com: '.$this_day_opening_timestamp.'<br>');
						//print('<br>my: '.$this_day_opening_timestamps.'<br>');
						//print('<br>T: '.$t.'<br>');
					if ($t < $this_day_opening_timestamp) {
							$t = ($t - $step) + (apply_filters('app_safe_time', 1) * 60);
							continue;
						}
						
				}else{
				
				$this_day_key = date('l', $t);
				if (!empty($start_unpacked_days[$this_day_key])) {
					// Check slot start vs opening start
					$this_day_opening_timestamp = strtotime(date('Y-m-d ' . $start_unpacked_days[$this_day_key]['start'], $ccs));
					//print($this_day_opening_timestamp.' testing');
					//print(date( "G", $t).'||'. $last . '<br>');
					if ($t < $this_day_opening_timestamp) {
						$t = ($t - $step) + (apply_filters('app_safe_time', 1) * 60);
						continue;
					}

					// Check slot end vs opening end - optional, but still applies
					//$this_day_closing_timestamp = strtotime(date('Y-m-d ' . $start_unpacked_days[$this_day_key]['end'], $ccs));
					//if ($cce > $this_day_closing_timestamp) continue;
				}
			}
			
		}
// Breaks are not behaving like paddings, which is to be expected.
// This fix (2) will force them to behave more like paddings
		if($_REQUEST['app_service_id']==2){
			
			}else{
			if (!empty($break_times[$this_day_key]['active']) && defined('APP_BREAK_TIMES_PADDING_CALCULUS') && APP_BREAK_TIMES_PADDING_CALCULUS) {
				$active = $break_times[$this_day_key]['active'];
				$break_starts = $break_times[$this_day_key]['start'];
				$break_ends = $break_times[$this_day_key]['end'];
				if (!is_array($active) && 'no' !== $active) {
					$break_start_ts = strtotime(date('Y-m-d ' . $break_starts, $ccs));
					$break_end_ts = strtotime(date('Y-m-d ' . $break_ends, $ccs));
					if ($t == $break_start_ts) {
						$t += ($break_end_ts - $break_start_ts) - $step;
						continue;
					}
				} else if (is_array($active) && in_array('yes', array_values($active))) {
					$has_break_time = false;
					for ($idx=0; $idx<count($break_starts); $idx++) {
						$break_start_ts = strtotime(date('Y-m-d ' . $break_starts[$idx], $ccs));
						$break_end_ts = strtotime(date('Y-m-d ' . $break_ends[$idx], $ccs));
						if ($t == $break_start_ts) {
							$has_break_time = $break_end_ts - $break_start_ts;
							break;
						}
					}
					if ($has_break_time) {
						$t += ($has_break_time - $step);
						continue;
					}
				}
			}
		}
// End fixes area
			
			//print(date('Y-m-d  h:m' , $ccs) .'   '.date('Y-m-d  h:m' , $cce) .'<br>');
			
			$is_busy = $this->is_busy( $ccs, $cce, $capacity );
			$title = apply_filters('app-schedule_cell-title', date_i18n($this->datetime_format, $ccs), $is_busy, $ccs, $cce, $schedule_key);

			$class_name = '';
			
			if(!empty($_REQUEST['reservation_code'])){
				$rese = $this->get_appointment_availablity_by_date_and_attendence_reservationcode($ccs, $cce);
				if($rese){
				$class_name = 'free';
				}else{
				$class_name = 'busy reservation_not_available';
					}
				}else{	
			
			
			
						
				// Mark now
				if ( $this->local_time > $ccs && $this->local_time < $cce )
					$class_name = 'notpossible now';
				// Mark passed hours
				else if ( $this->local_time > $ccs )
					$class_name = 'notpossible app_past';
				// Then check if this time is blocked
				else if ( isset( $this->options["app_lower_limit"] ) && $this->options["app_lower_limit"]
					&&( $this->local_time + $this->options["app_lower_limit"] * 3600) > $cce )
					$class_name = 'notpossible app_blocked';
				// Check if this is break
				else if ( $this->is_break( $ccs, $cce ) )
					$class_name = 'notpossible app_break';
				// Then look for appointments
				//else if ( $is_busy )
					//$class_name = $this->check_and_compare_date( $ccs, $cce);
					
				//else if($this->check_and_compare_date( $ccs, $cce))	
				//	$class_name = 'busy';
					
				else if(empty($_REQUEST['app_service_id']))	
					$class_name = 'busy';
					
				//else if(!empty($_REQUEST['reservation_code']))	
				//	$class_name = 'busy';
				
				
				
					
				else if($this->get_appointment_availablity_by_date_and_attendence( $ccs, $cce))	
					$class_name = 'busy';
				// Then check if we have enough time to fulfill this app
				else if ( !$this->is_service_possible( $ccs, $cce, $capacity ) )
					$class_name = 'notpossible service_notpossible';
				// Then check if we have enough time to fulfill this app
				//else if (!$this->is_first_choice_then_second_chioce( $ccs, $cce))
				  //  $class_name = 'busy';
				// If nothing else, then it must be free
				
				
				//else if($this->after_9am_12pm_and_3pm_6pm_sign_up_1030am_0130am_and_0430pm_0730pm_register($ccs, $cce) == true)
				//	$class_name = 'notpossible checking';
				
				
				else {
					
					$class_name = 'free '; //$this->is_first_choice_then_second_chioce( $ccs, $cce) this gona be remove ;
					
					// We found at least one timetable cell to be free
					$this->is_a_timetable_cell_free = true;
				}
			
		}
			
			
			
			$class_name = apply_filters( 'app_class_name', $class_name, $ccs, $cce );
			
			$ret .= '<div class="app_timetable_cell '.$class_name.'" title="'.esc_attr($title).'">'.
						$this->secs2hours( $ccs - $day_start ). '<input type="hidden" attendent="'.$_REQUEST['attendees'].'" class="appointments_take_appointment" value="'.$this->pack( $ccs, $cce ).'" />';

			$ret .= '</div>';
		}

		$ret .= '<div style="clear:both"></div>';

		$ret .= '</div>';

		return $ret;

	}
	

	
	
	

	function _get_table_meta_row_monthly ($which, $long) {
		if ( !$long )
			$day_names_array = $this->arrange( $this->get_short_day_names(), false );
		else
			$day_names_array = $this->arrange( $this->get_day_names(), false );
		$cells = '<th>' . join('</th><th>', $day_names_array) . '</th>';
		return "<{$which}><tr>{$cells}</tr></{$which}>";
	}

	/**
	 * Helper function to create a weekly schedule
	 */
	function get_weekly_calendar( $timestamp=false, $class='', $long ) {
		global $wpdb;

		$this->get_lsw();

		$price = $this->get_price( );

		$year = date("Y", $this->local_time);
		$month = date("m",  $this->local_time);

		$date = $timestamp ? $timestamp : $this->local_time;

		$sunday = $this->sunday( $date ); // Timestamp of first Sunday of any date

		$start = $end = 0;
		if ( $min_max = $this->min_max_wh( 0, 0 ,  $day_start) ) {
			$start = $min_max["min"];
			$end = $min_max["max"];
		}
		if ( $start >= $end ) {
			$start = 8;
			$end = 18;
		}
		$start = apply_filters( 'app_schedule_starting_hour', $start );
		$end = apply_filters( 'app_schedule_ending_hour', $end );

		$first = $start *3600 + $sunday; // Timestamp of the first cell of first Sunday
		$last = $end *3600 + $sunday; // Timestamp of the last cell of first Sunday
		$schedule_key = sprintf("%sx%s", $date, $date+(7*86400));

		if (defined('APP_USE_LEGACY_DURATION_CALCULUS') && APP_USE_LEGACY_DURATION_CALCULUS) {
			$step = $this->get_min_time() * 60; // Timestamp increase interval to one cell below
		} else {
			$service = $this->get_service($this->service);
			$step = (!empty($service->duration) ? $service->duration : $this->get_min_time()) * 60; // Timestamp increase interval to one cell below
		}

		$days = $this->arrange( array(0,1,2,3,4,5,6), -1, true ); // Arrange days acc. to start of week

		$tbl_class = $class;
		$tbl_class = $tbl_class ? "class='{$tbl_class}'" : '';

		$ret = '';
		$ret .= '<a name="app_schedule">&nbsp;</a>';
		$ret = apply_filters( 'app_schedule_before_table', $ret );
		$ret .= "<table width='100%' {$tbl_class}>";
		$ret .= $this->_get_table_meta_row('thead', $long);
		$ret .= '<tbody>';

		$ret = apply_filters( 'app_schedule_before_first_row', $ret );

		$todays_no = date("w", $this->local_time ); // Number of today
		$working_days = $this->get_working_days( $this->worker, $this->location ); // Get an array of working days
		$capacity = $this->get_capacity();

		// Allow direct step increment manipulation,
		// mainly for service duration based calculus start/stop times
		$step = apply_filters('app-timetable-step_increment', $step);

		for ( $t=$first; $t<$last; $t=$t+$step ) {
			foreach ( $days as $key=>$i ) {
				if ( $i == -1 ) {
					$from = apply_filters( 'app_weekly_calendar_from', $this->secs2hours( $t - $sunday ), $t );
					$to = apply_filters( 'app_weekly_calendar_to', $this->secs2hours( $t - $sunday + $step ), $t );
					$ret .= "<td class='appointments-weekly-calendar-hours-mins'>".$from." &#45; ".$to."</td>";
				}
				else {
					$ccs = apply_filters('app_ccs', $t + $i * 86400); // Current cell starts
					$cce = apply_filters('app_cce', $ccs + $step); // Current cell ends

					$class_name = '';
					$is_busy = $this->is_busy( $ccs, $cce, $capacity );
					$title = apply_filters('app-schedule_cell-title', date_i18n($this->datetime_format, $ccs), $is_busy, $ccs, $cce, $schedule_key);

			if(!empty($_REQUEST['reservation_code'])){
				$rese = $this->get_appointment_availablity_by_date_and_attendence_reservationcode($ccs, $cce);
				if($rese){
				$class_name = 'free';
				}else{
				$class_name = 'busy reservation_not_available';
					}
				}else{			

					// Also mark now
					if ( $this->local_time > $ccs && $this->local_time < $cce )
						$class_name = 'notpossible now';
					// Mark passed hours
					else if ( $this->local_time > $ccs )
						$class_name = 'notpossible app_past';
					// Then check if this time is blocked
					else if ( isset( $this->options["app_lower_limit"] ) && $this->options["app_lower_limit"]
						&&( $this->local_time + $this->options["app_lower_limit"] * 3600) > $cce )
						$class_name = 'notpossible app_blocked';
					// Check today is holiday
					else if ( $this->is_holiday( $ccs, $cce ) )
						$class_name = 'notpossible app_holiday';
					// Check if we are working today
					else if ( !in_array( date("l", $ccs ), $working_days ) && !$this->is_exceptional_working_day( $ccs, $cce ) )
						$class_name = 'notpossible notworking';
					// Check if this is break
					else if ( $this->is_break( $ccs, $cce ) )
						$class_name = 'notpossible app_break';
					// Then look for appointments
					else if ( $is_busy )
						$class_name = 'busy';
					// Then check if we have enough time to fulfill this app
					else if ( !$this->is_service_possible( $ccs, $cce, $capacity ) )
						$class_name = 'notpossible service_notpossible';
					// If nothing else, then it must be free
					else
						$class_name = 'free';
						
				}

					$class_name = apply_filters( 'app_class_name', $class_name, $ccs, $cce );

					$ret .= '<td class="'.$class_name.'" title="'.esc_attr($title).'">
					<input type="hidden" class="appointments_take_appointment" value="'.$this->pack( $ccs, $cce ).'" /></td>';
				}
			}
			$ret .= '</tr><tr>'; // Close the last day of the week
		}
		$ret = apply_filters( 'app_schedule_after_last_row', $ret );
		$ret .= '</tbody>';
		$ret .= $this->_get_table_meta_row('tfoot', $long);
		$ret .= '</table>';
		$ret = apply_filters( 'app_schedule_after_table', $ret );

		return $ret;
	}

	function _get_table_meta_row ($which, $long) {
		if ( !$long )
			$day_names_array = $this->arrange( $this->get_short_day_names(), __(' ', 'appointments') );
		else
			$day_names_array = $this->arrange( $this->get_day_names(), __(' ', 'appointments') );
		$cells = '<th class="hourmin_column">&nbsp;' . join('</th><th>', $day_names_array) . '</th>';
		return "<{$which}><tr>{$cells}</tr></{$which}>";
	}

	function get_day_names () {
		return array(
			__('Sunday', 'appointments'),
			__('Monday', 'appointments'),
			__('Tuesday', 'appointments'),
			__('Wednesday', 'appointments'),
			__('Thursday', 'appointments'),
			__('Friday', 'appointments'),
			__('Saturday', 'appointments'),
		);
	}

	function get_short_day_names () {
		return array(
			__('Su', 'appointments'),
			__('Mo', 'appointments'),
			__('Tu', 'appointments'),
			__('We', 'appointments'),
			__('Th', 'appointments'),
			__('Fr', 'appointments'),
			__('Sa', 'appointments'),
		);
	}

	/**
	 * Returns the timestamp of Sunday of the current time or selected date
	 * @param timestamp: Timestamp of the selected date or false for current time
	 * @return integer (timestamp)
	 */
	function sunday( $timestamp=false ) {

		$date = $timestamp ? $timestamp : $this->local_time;
		// Return today's timestamp if today is sunday and start of the week is set as Sunday
		if ( "Sunday" == date( "l", $date ) && 0 == $this->start_of_week )
			return strtotime("today", $date);
		// Else return last week's timestamp
		else
			return strtotime("last Sunday", $date );
	}

	/**
	 * Arranges days array acc. to start of week, e.g 1234567 (Week starting with Monday)
	 * @param days: input array
	 * @param prepend: What to add as first element
	 * @pram nod: If number of days (true) or name of days (false)
	 * @return array
	 */
	function arrange( $days, $prepend, $nod=false ) {
		if ( $this->start_of_week ) {
			for ( $n = 1; $n<=$this->start_of_week; $n++ ) {
				array_push( $days, array_shift( $days ) );
			}
			// Fix for displaying past days; apply only for number of days
			if ( $nod ) {
				$first = false;
				$temp = array();
				foreach ( $days as $key=>$day ) {
					if ( !$first )
						$first = $day; // Save the first day
					if ( $day < $first )
						$temp[$key] = $day + 7; // Latter days should be higher than the first day
					else
						$temp[$key] = $day;
				}
				$days = $temp;
			}
		}
		if ( false !== $prepend )
			array_unshift( $days, $prepend );

		return $days;
	}

	/**
	 * Get which days of the week we are working
	 * @return array (may be empty)
	 */
	function get_working_days( $worker=0, $location=0 ) {
		global $wpdb;
		$working_days = array();
		$result = $this->get_work_break( $location, $worker, 'open' );
		if ( $result !== null ) {
			$days = maybe_unserialize( $result->hours );
			if ( is_array( $days ) ) {
				foreach ( $days as $day_name=>$day ) {
					if ( isset( $day["active"] ) && 'yes' == $day["active"] ) {
						$working_days[] = $day_name;
					}
				}
			}
		}
		return $working_days;
	}

	/**
	 * Check if this is an exceptional working day
	 * Optionally a worker is selectable ( $w != 0 )
	 * @return bool
	 */
	function is_exceptional_working_day( $ccs, $cce, $w=0 ) {
		// A worker can be forced
		if ( !$w )
			$w = $this->worker;
		$is_working_day = false;
		$result = $this->get_exception( $this->location, $w, 'open' );
		if ( $result != null  && strpos( $result->days, date( 'Y-m-d', $ccs ) ) !== false )
			$is_working_day = true;

		return apply_filters( 'app_is_exceptional_working_day', $is_working_day, $ccs, $cce, $this->service, $w );
	}

	/**
	 * Check if today is holiday
	 * Optionally a worker is selectable ( $w != 0 )
	 * @return bool
	 */
	function is_holiday( $ccs, $cce, $w=0 ) {
		// A worker can be forced
		if ( !$w )
			$w = $this->worker;
		$is_holiday = false;
		$result = $this->get_exception( $this->location, $w, 'closed' );
		if ( $result != null  && strpos( $result->days, date( 'Y-m-d', $ccs ) ) !== false )
			$is_holiday = true;

		return apply_filters( 'app_is_holiday', $is_holiday, $ccs, $cce, $this->service, $w );
	}

	/**
	 * Check if it is break time
	 * Optionally a worker is selectable ( $w != 0 )
	 * @return bool
	 */
	function is_break( $ccs, $cce, $w=0 ) {
		global $wpdb;
		if(!empty($_REQUEST['app_service_id']) and $_REQUEST['app_service_id']==2){
			
		//$start = date ("Y-m-d H:i:s", $start);
		//$end = date ("Y-m-d H:i:s", $end);
		$hour_min = date ("H:i", $ccs);
		$hour_min_end = date ("H:i", $cce);
		$day = date ("d", $ccs);
		$month = date ("m", $ccs);
		$year = date ("Y", $ccs);
		
		$date= $year .'-'. $month .'-'.$day ;

							/*$hour_min = date ("H:i", $start);
							$hour_min = strtotime ($hour_min);
							$day = date ("l", $start);
							$month = date ("m", $start);*/


		$working_hours = $wpdb->get_results( "SELECT * FROM wp_three_line_appointment WHERE `date` = '$date%'");
		
		if(!empty($working_hours) and is_array($working_hours)){
			foreach($working_hours as $worker_hour):
			if($worker_hour->working_hours_start <= $hour_min){
				return false;
				}
			endforeach;
			}else{
			$working_hours = $this->default_hours;
			//print_r($working_hours);
				//print('<br>Cal: '.$hour_min.'<br>');
				//print('<br>Mine: '.$working_hours['start']. '<br>');
				
				if($worker_hours['start'] <= $hour_min){
					return false;
					}else{
					return true;	
				}
			}

			
			
			//return true;
			
		}else{
			// A worker can be forced
		if ( !$w )
			$w = $this->worker;

		// Try getting cached preprocessed hours
		$days = wp_cache_get('app-break_times-for-' . $w);
		if (!$days) {
			// Preprocess and cache workhours
			// Look where our working hour ends
			$result_days = $this->get_work_break($this->location, $w, 'closed');
			if ($result_days && is_object($result_days) && !empty($result_days->hours)) $days = maybe_unserialize($result_days->hours);
			if ($days) wp_cache_set('app-break_times-for-' . $w, $days);
		}
		if (!is_array($days) || empty($days)) return false;

		// What is the name of this day?
		$this_days_name = date("l", $ccs );
		// This days midnight
		$this_day = date("d F Y", $ccs );

		foreach( $days as $day_name=>$day ) {
			if ( $day_name == $this_days_name && isset( $day["active"] ) && 'yes' == $day["active"] ) {
				$end = $this->to_military( $day["end"] );
				// Special case: End is 00:00
				if ( '00:00' == $end )
					$end = '24:00';
				if ( $ccs >= strtotime( $this_day. " ". $this->to_military( $day["start"] ), $this->local_time ) &&
					$cce <= $this->str2time( $this_day, $end ) ) {
					return true;
				}
			} else if ($day_name == $this_days_name && isset($day["active"]) && is_array($day["active"])) {
				foreach ($day["active"] as $idx => $active) {
					$end = $this->to_military( $day["end"][$idx] );
					// Special case: End is 00:00
					if ('00:00' == $end) $end = '24:00';

					if (
						$ccs >= strtotime( $this_day. " ". $this->to_military( $day["start"][$idx] ), $this->local_time )
						&&
						$cce <= $this->str2time( $this_day, $end )
					) {
						return true;
					}
				}
			}
		}
		
		
		return false;
			}
		
	}

	/**
	 * Check if a specific worker is working at this time slot
	 * @return bool
	 * @since 1.2.2
	 */
	function is_working( $ccs, $cse, $w ) {
		if ( $this->is_exceptional_working_day( $ccs, $cse, $w ) )
			return true;
		if ( $this->is_holiday( $ccs, $cse, $w ) )
			return false;
		if ( $this->is_break( $ccs, $cse, $w ) )
			return false;

		return true;
	}

	/**
	 * Correctly calculate timestamp based on day and hours:min
	 * This is required as php versions prior to 5.3 cannot calculate 24:00
	 * @param $this_day: Date in d F Y format
	 * @param $end: time in military hours:min format
	 * @since 1.1.8
	 * @return integer (timestamp)
	 */
	function str2time( $this_day, $end ) {
		if ( '24:00' != $end )
			return strtotime( $this_day. " ". $end, $this->local_time );
		else
			return ( strtotime( $this_day. " 23:59", $this->local_time ) + 60 );
	}

	/**
	 * Check if time is enough for this service
	 * e.g if we are working until 6pm, it is not possible to take an app with 60 mins duration at 5:30pm
	 * Please note that "not possible" is an exception
	 * @return bool
	 */
	function is_service_possible( $ccs, $cce, $capacity ) {

		// If this cell exceeds app limit then return false
		if ( $this->get_app_limit() < ceil( ( $ccs - $this->local_time ) /86400 ) )
			return false;

		$result = $this->get_service( $this->service );
		if ( !$result !== null ) {
			$duration = $result->duration;
			if( !$duration )
				return true; // This means min time will be applied. No need to look

			// The same for break time
			if ( isset( $this->options["allow_overwork_break"] ) && 'yes' == $this->options["allow_overwork_break"] )
				$allow_overwork_break = true;
			else
				$allow_overwork_break = false;

			// Check for further appointments or breaks on this day, if this is a lasting appointment
			if ( $duration > $this->get_min_time() ) {
				$step = ceil( $duration/$this->get_min_time() );
				$min_secs = $this->get_min_time() *60;
				if ( $step < 20 ) { // Let's not exaggerate !
					for ( $n =1; $n < $step; $n++ ) {
						if ( $this->is_busy( $ccs + $n * $min_secs, $ccs + ($n+1) * $min_secs, $capacity ) )
							return false; // There is an appointment in the predeeding times
						// We can check breaks here too
						if ( !$allow_overwork_break ) {
							if ( $this->is_break( $ccs + $n * $min_secs, $ccs + ($n+1) * $min_secs ) )
								return false; // There is a break in the predeeding times
						}
					}
				}
			}
			// Now look where our working hour ends

			$days = wp_cache_get('app-open_times-for-' . $this->worker);
			if (!$days) {
				// Preprocess and cache workhours
				// Look where our working hour ends
				$result_days = $this->get_work_break($this->location, $this->worker, 'open');
				if ($result_days && is_object($result_days) && !empty($result_days->hours)) $days = maybe_unserialize($result_days->hours);
				if ($days) wp_cache_set('app-open_times-for-' . $this->worker, $days);
			}
			if (!is_array($days) || empty($days)) return true;

			// If overwork is allowed, lets mark this
			if ( isset( $this->options["allow_overwork"] ) && 'yes' == $this->options["allow_overwork"] )
				$allow_overwork = true;
			else
				$allow_overwork = false;

			// What is the name of this day?
			$this_days_name = date("l", $ccs );
			// This days midnight
			$this_day = date("d F Y", $ccs );
			// Will the service exceed or working time?
			$css_plus_duration = $ccs + ($duration *60);

			foreach( $days as $day_name=>$day ) {
				if ( $day_name == $this_days_name && isset( $day["active"] ) && 'yes' == $day["active"] ) {

					// Special case: End time is 00:00
					$end_mil = $this->to_military( $day["end"] );
					if ( '00:00' == $end_mil )
						$end_mil = '24:00';

					if ( $allow_overwork ) {
						if ( $ccs >= $this->str2time( $this_day, $end_mil ) )
							return false;
					}
					else {
						if (  $css_plus_duration > $this->str2time( $this_day, $end_mil ) )
							return false;
					}

					// We need to check a special case where schedule starts on eg 4pm, but our work starts on 4:30pm.
					if ( $ccs < strtotime( $this_day . " " . $this->to_military( $day["start"] ) , $this->local_time ) )
						return false;
				}
			}

		}
		return true;
	}

	/**
	 * Return available number of workers for a time slot
	 * e.g if one worker works between 8-11 and another works between 13-15, there is no worker between 11-13
	 * This is called from is_busy function
	 * since 1.0.6
	 * @return integer
	 */
	function available_workers( $ccs, $cce ) {
		// If a worker is selected we dont need to do anything special

		if ( $this->worker )
			return $this->get_capacity();

		// Dont proceed further if capacity is forced
		if ( has_filter( 'app_get_capacity' ) )
			return apply_filters( 'app_get_capacity', 1, $this->service, $this->worker );

		$n = 0;
		$workers = $this->get_workers_by_service( $this->service );
		if (!$workers) return $this->get_capacity(); // If there are no workers for this service, apply the service capacity

		foreach( $workers as $worker ) {

			// Try getting cached preprocessed hours
			$days = wp_cache_get('app-open_times-for-' . $worker->ID);
			if (!$days) {
				// Preprocess and cache workhours
				// Look where our working hour ends
				$result_days = $this->get_work_break($this->location, $worker->ID, 'open');
				if ($result_days && is_object($result_days) && !empty($result_days->hours)) $days = maybe_unserialize($result_days->hours);
				if ($days) wp_cache_set('app-open_times-for-' . $worker->ID, $days);
			}
			if (!is_array($days) || empty($days)) continue;


			if ( is_array( $days ) ) {
				// What is the name of this day?
				$this_days_name = date("l", $ccs );
				// This days midnight
				$this_day = date("d F Y", $ccs );

				foreach( $days as $day_name=>$day ) {
					if ( $day_name == $this_days_name && isset( $day["active"] ) && 'yes' == $day["active"] ) {
						$end = $this->to_military( $day["end"] );
						// Special case: End is 00:00
						if ( '00:00' == $end )
							$end = '24:00';
						if (
							$ccs >= strtotime( $this_day. " ". $this->to_military( $day["start"] ), $this->local_time )
							&&
							$cce <= $this->str2time( $this_day, $end )
							&&
							!$this->is_break( $ccs, $cce, $worker->ID )
						) $n++;
					}
				}
			}

		}

		// We have to check service capacity too
		$service = $this->get_service( $this->service );
		if ( $service != null ) {
			if ( !$service->capacity ) {
				$capacity = $n; // No service capacity limit
			}
			else
				$capacity = min( $service->capacity, $n ); // Return whichever smaller
		}
		else
			$capacity = 1; // No service ?? - Not possible but let's be safe

		return $capacity;
	}

	/**
	 * Check if a cell is not available, i.e. all appointments taken OR we dont have workers for this time slot
	 * @return bool
	 */
	function is_busy( $start, $end, $capacity ) {
		$week= date( "W", $start );
		$period = new App_Period($start, $end);

		// If a specific worker is selected, we will look at his schedule first.
		if ( 0 != $this->worker ) {
			$apps = $this->get_reserve_apps_by_worker( $this->location, $this->worker, $week );
			if ( $apps ) {
				foreach ( $apps as $app ) {
					//if ( $start >= strtotime( $app->start ) && $end <= strtotime( $app->end ) ) return true;
					if ($period->contains($app->start, $app->end)) return true;
				}
			}
		}

		// If we're here, no worker is set or (s)he's not busy by default. Let's go for quick filter trip.
		$is_busy = apply_filters('app-is_busy', false, $period, $capacity);
		if ($is_busy) return true;

		// If we are here, no preference is selected (provider_id=0) or selected provider is not busy. There are 2 cases here:
		// 1) There are several providers: Look for reserve apps for the workers giving this service.
		// 2) No provider defined: Look for reserve apps for worker=0, because he will carry out all services
		if ( $this->get_workers() != null ) {
			$workers = $this->get_workers_by_service( $this->service );
			$apps = array();
			if ( $workers ) {
				foreach( $workers as $worker ) {
					if ( $this->is_working( $start, $end, $worker->ID ) ) {
						$app_worker = $this->get_reserve_apps_by_worker( $this->location, $worker->ID, $week );
						if ( $app_worker && is_array( $app_worker ) )
							$apps = array_merge( $apps, $app_worker );

						// Also include appointments by general staff for services that can be given by this worker
						$services_provided = $this->_explode( $worker->services_provided );
						if ( $services_provided && is_array( $services_provided ) && !empty( $services_provided ) ) {
							foreach ( $services_provided as $service_ID ) {
								$apps_service_0 = $this->get_reserve_apps( $this->location, $service_ID, 0, $week );
								if ( $apps_service_0 && is_array( $apps_service_0 ) )
									$apps = array_merge( $apps, $apps_service_0 );
							}
						}
					}
				}
				// Remove duplicates
				$apps = $this->array_unique_object_by_ID( $apps );
			}
		}
		else
			$apps = $this->get_reserve_apps_by_worker( $this->location, 0, $week );

		$n = 0;
		foreach ( $apps as $app ) {
// @FIX: this will allow for "only one service and only one provider per time slot"
if ($this->worker && $this->service && ($app->service != $this->service)) {
	continue;
	// This is for the following scenario:
	// 1) any number of providers per service
	// 2) any number of services
	// 3) only one service and only one provider per time slot:
	// 	- selecting one provider+service makes this provider and selected service unavailable in a time slot
	// 	- other providers are unaffected, other services are available
}
// End @FIX
			//if ( $start >= strtotime( $app->start ) && $end <= strtotime( $app->end ) ) $n++;
			if ($period->contains($app->start, $app->end)) $n++;
		}

		if ( $n >= $this->available_workers( $start, $end ) )
			return true;

		// Nothing found, so this time slot is not busy
		return false;
	}

	/**
	 * Remove duplicate appointment objects by app ID
	 * @since 1.1.5.1
	 * @return array of objects
	 */
	function array_unique_object_by_ID( $apps ) {
		if ( !is_array( $apps ) || empty( $apps ) )
			return array();
		$idlist = array();
		// Save array to a temp area
		$result = $apps;
		foreach ( $apps as $key=>$app ) {
			if ( isset( $app->ID ) ) {
				if ( in_array( $app->ID, $idlist ) )
					unset( $result[$key] );
				else
					$idlist[] = $app->ID;
			}
		}
		return $result;
	}

	/**
	 * Get the maximum and minimum working hour
	 * @return array
	 */
	function min_max_wh( $worker=0, $location=0 ,  $day_start) {
		if($_REQUEST['app_service_id']== 2){
		$start_end = $this->_implement_new_time_frame($day_start);
		//echo 'my: ' .$start_end['start'] . '|'. $start_end['end'] . '<br>';
		
			$start = $start_end['start'];
			$end = $start_end['end'];
		
		return array( "min"=>$start, "max"=>$end );

			
			}else{
		$this->get_lsw();
		$result = $this->get_work_break( $this->location, $this->worker, 'open' );
		if ( $result !== null ) {
			$days = maybe_unserialize( $result->hours );
			if ( is_array( $days ) ) {
				$min = 24; $max = 0;
				foreach ( $days as $day ) {
					if ( isset( $day["active"] ) && 'yes' == $day["active"] ) {
						$start = date( "G", strtotime( $this->to_military( $day["start"] ) ) );
						$end_timestamp = strtotime( $this->to_military( $day["end"] ) );
						$end = date( "G", $end_timestamp );
						// Add 1 hour if there are some minutes left. e.g. for 10:10pm, make max as 23
						if ( '00' != date( "i", $end_timestamp ) && $end != 24 )
							$end = $end + 1;
						if ( $start < $min )
							$min = $start;
						if ( $end > $max )
							$max = $end;
						// Special case: If end is 0:00, regard it as 24
						if ( 0 == $end && '00' == date( "i", $end_timestamp ) )
							$max = 24;
					}
				}
				return array( "min"=>$min, "max"=>$max );
			}
		}
		return false;
		}
	}

	/**
	 * Convert any time format to military format
	 * @since 1.0.3
	 * @return string
	 */
	function to_military( $time, $end=false ) {
		// Already in military format
		if ( 'H:i' == $this->time_format )
			return $time;
		// In one of the default formats
		if ( 'g:i a' == $this->time_format  || 'g:i A' == $this->time_format )
			return date( 'H:i', strtotime( $time ) );

		// Custom format. Use a reference time
		// ref will something like 23saat45dakika
		$ref = date_i18n( $this->time_format, strtotime( "23:45" ) );
		if ( strpos( $ref, "23" ) !== false )
			$twentyfour = true;
		else
			$twentyfour = false;
		// Now ref is something like saat,dakika
		$ref = ltrim( str_replace( array( '23', '45' ), ',', $ref ), ',' );
		$ref_arr = explode( ',', $ref );
		if ( isset( $ref_arr[0] ) ) {
			$s = $ref_arr[0]; // separator. We will replace it by :
			if ( isset($ref_arr[1]) && $ref_arr[1] )
				$e = $ref_arr[1];
			else {
				$e = 'PLACEHOLDER';
				$time = $time. $e; // Add placeholder at the back
			}
			if ( $twentyfour )
				$new_e = '';
			else
				$new_e = ' a';
		}
		else
			return $time; // Nothing found ??

		return date( 'H:i', strtotime( str_replace( array($s,$e), array(':',$new_e), $time ) ) );
	}


	/**
	 * Pack several fields as a string using glue ":"
	 * location : service : worker : ccs : cce : post ID
	 * @return string
	 */
	function pack( $ccs, $cce ){
		global $post;
		if ( is_object( $post ) )
			$post_id = $post->ID;
		else
			$post_id = 0;
		$checking = $this->check_and_conform_attendence($ccs, $cce);
		return $this->location . ":" . $this->service . ":" . $this->worker . ":" . $ccs . ":" . $cce . ":" . $post_id .":" . $checking;
	}

	/**
	 * Save a cookie so that user can see his appointments
	 */
	function save_cookie( $app_id, $name, $email, $phone, $address, $city, $gcal ) {
		if ( isset( $_COOKIE["wpmudev_appointments"] ) )
			$apps = unserialize( stripslashes( $_COOKIE["wpmudev_appointments"] ) );
		else
			$apps = array();

		$apps[] = $app_id;

		// Prevent duplicates
		$apps = array_unique( $apps );
		// Add 365 days grace time
		$expire = $this->local_time + 3600 * 24 * ( $this->options["app_limit"] + 365 );

		$expire = apply_filters( 'app_cookie_time', $expire );

		if ( defined('COOKIEPATH') ) $cookiepath = COOKIEPATH;
		else $cookiepath = "/";
		if ( defined('COOKIEDOMAIN') ) $cookiedomain = COOKIEDOMAIN;
		else $cookiedomain = '';

		@setcookie("wpmudev_appointments", serialize($apps), $expire, $cookiepath, $cookiedomain);

		$data = array(
					"n"	=> $name,
					"e"	=> $email,
					"p"	=> $phone,
					"a"	=> $address,
					"c"	=> $city,
					"g"	=> $gcal
					);
		@setcookie("wpmudev_appointments_userdata", serialize($data), $expire, $cookiepath, $cookiedomain);

		// May be required to clean up or modify userdata cookie
		do_action( 'app_save_cookie', $app_id, $apps );

		// Save user data too
		if ( is_user_logged_in() && defined('APP_USE_LEGACY_USERDATA_OVERWRITING') && APP_USE_LEGACY_USERDATA_OVERWRITING ) {
			global $current_user;
			if ( $name )
				update_user_meta( $current_user->ID, 'app_name', $name );
			if ( $email )
				update_user_meta( $current_user->ID, 'app_email', $email );
			if ( $phone )
				update_user_meta( $current_user->ID, 'app_phone', $phone );
			if ( $address )
				update_user_meta( $current_user->ID, 'app_address', $address );
			if ( $city )
				update_user_meta( $current_user->ID, 'app_city', $city );

			do_action( 'app_save_user_meta', $current_user->ID, array( 'name'=>$name, 'email'=>$email, 'phone'=>$phone, 'address'=>$address, 'city'=>$city ) );
		}
	}


/*******************************
* Methods for frontend login API
********************************
*/
	/**
	 * Login from front end by Wordpress
	 */
	function ajax_login( ) {

		header("Content-type: application/json");
		$user = wp_signon( );

		if ( !is_wp_error($user) ) {

			die(json_encode(array(
				"status" => 1,
				"user_id"=>$user->ID
			)));
		}
		die(json_encode(array(
				"status" => 0,
				"error" => $user->get_error_message()
			)));
	}

	/**
	 * Handles the Google+ OAuth type login.
	 */
	function handle_gplus_login () {
		header("Content-type: application/json");
		$resp = array(
			"status" => 0,
		);
		if (empty($this->options['google-client_id'])) die(json_encode($resp)); // Yeah, we're not equipped to deal with this

		$data = stripslashes_deep($_POST);
		$token = !empty($data['token']) ? $data['token'] : false;
		if (empty($token)) die(json_encode($resp));

		// Start verifying
		$page = wp_remote_get('https://www.googleapis.com/userinfo/v2/me', array(
			'sslverify' => false,
			'timeout' => 5,
			'headers' => array(
				'Authorization' => sprintf('Bearer %s', $token),
			)
		));
		if (200 != wp_remote_retrieve_response_code($page)) die(json_encode($resp));

		$body = wp_remote_retrieve_body($page);
		$response = json_decode($body, true); // Body is JSON
		if (empty($response['id'])) die(json_encode($resp));

		$first = !empty($response['given_name']) ? $response['given_name'] : '';
		$last = !empty($response['family_name']) ? $response['family_name'] : '';
		$email = !empty($response['email']) ? $response['email'] : '';

		if (empty($email) || (empty($first) && empty($last))) die(json_encode($resp)); // In case we're missing stuff

		$username = false;
		if (!empty($last) && !empty($first)) $username = "{$first}_{$last}";
		else if (!empty($first)) $username = $first;
		else if (!empty($last)) $username = $last;

		if (empty($username)) die(json_encode($resp)); // In case we're missing stuff

		$wordp_user = get_user_by('email', $email);

		if (!$wordp_user) { // Not an existing user, let's create a new one
			$password = wp_generate_password(12, false);
			$count = 0;
			while (username_exists($username)) {
				$username .= rand(0,9);
				if (++$count > 10) break;
			}

			$wordp_user = wp_create_user($username, $password, $email);
			if (is_wp_error($wordp_user))
				die(json_encode($resp)); // Failure creating user
			else {
				update_user_meta($wordp_user, 'first_name', $first);
				update_user_meta($wordp_user, 'last_name', $last);
			}
		}
		else {
			$wordp_user = $wordp_user->ID;
		}

		$user = get_userdata($wordp_user);
		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID); // Logged in with Google, yay
		do_action('wp_login', $user->user_login);

		die(json_encode(array(
			"status" => 1,
		)));
	}

	/**
	 * Handles Facebook user login and creation
	 * Modified from Events and Bookings by Ve
	 */
	function handle_facebook_login () {
		header("Content-type: application/json");
		$resp = array(
			"status" => 0,
		);
		$fb_uid = @$_POST['user_id'];
		$token = @$_POST['token'];
		if (!$token) die(json_encode($resp));

		$request = new WP_Http;
		$result = $request->request(
			'https://graph.facebook.com/me?oauth_token=' . $token,
			array('sslverify' => false) // SSL certificate issue workaround
		);
		if (200 != $result['response']['code']) die(json_encode($resp)); // Couldn't fetch info

		$data = json_decode($result['body']);
		if (!$data->email) die(json_encode($resp)); // No email, can't go further

		$email = is_email($data->email);
		if (!$email) die(json_encode($resp)); // Wrong email

		$wp_user = get_user_by('email', $email);

		if (!$wp_user) { // Not an existing user, let's create a new one
			$password = wp_generate_password(12, false);
			$username = @$data->name
				? preg_replace('/[^_0-9a-z]/i', '_', strtolower($data->name))
				: preg_replace('/[^_0-9a-z]/i', '_', strtolower($data->first_name)) . '_' . preg_replace('/[^_0-9a-z]/i', '_', strtolower($data->last_name))
			;

			$wp_user = wp_create_user($username, $password, $email);
			if (is_wp_error($wp_user)) die(json_encode($resp)); // Failure creating user
		} else {
			$wp_user = $wp_user->ID;
		}

		$user = get_userdata($wp_user);

		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID); // Logged in with Facebook, yay
		do_action('wp_login', $user->user_login);

		die(json_encode(array(
			"status" => 1,
			"user_id"=>$user->ID
		)));
	}

	/**
	 * Spawn a TwitterOAuth object.
	 */
	function _get_twitter_object ($token=null, $secret=null) {
		// Make sure options are loaded and fresh
		if ( !$this->options['twitter-app_id'] )
			$this->options = get_option( 'appointments_options' );
		if (!class_exists('TwitterOAuth'))
			include WP_PLUGIN_DIR . '/appointments/includes/twitteroauth/twitteroauth.php';
		$twitter = new TwitterOAuth(
			$this->options['twitter-app_id'],
			$this->options['twitter-app_secret'],
			$token, $secret
		);
		return $twitter;
	}

	/**
	 * Get OAuth request URL and token.
	 */
	function handle_get_twitter_auth_url () {
		header("Content-type: application/json");
		$twitter = $this->_get_twitter_object();
		$request_token = $twitter->getRequestToken($_POST['url']);
		echo json_encode(array(
			'url' => $twitter->getAuthorizeURL($request_token['oauth_token']),
			'secret' => $request_token['oauth_token_secret']
		));
		die;
	}

	/**
	 * Login or create a new user using whatever data we get from Twitter.
	 */
	function handle_twitter_login () {
		header("Content-type: application/json");
		$resp = array(
			"status" => 0,
		);
		$secret = @$_POST['secret'];
		$data_str = @$_POST['data'];
		$data_str = ('?' == substr($data_str, 0, 1)) ? substr($data_str, 1) : $data_str;
		$data = array();
		parse_str($data_str, $data);
		if (!$data) die(json_encode($resp));

		$twitter = $this->_get_twitter_object($data['oauth_token'], $secret);
		$access = $twitter->getAccessToken($data['oauth_verifier']);

		$twitter = $this->_get_twitter_object($access['oauth_token'], $access['oauth_token_secret']);
		$tw_user = $twitter->get('account/verify_credentials');

		// Have user, now register him/her
		$domain = preg_replace('/www\./', '', parse_url(site_url(), PHP_URL_HOST));
		$username = preg_replace('/[^_0-9a-z]/i', '_', strtolower($tw_user->name));
		$email = $username . '@twitter.' . $domain; //STUB email
		$wp_user = get_user_by('email', $email);

		if (!$wp_user) { // Not an existing user, let's create a new one
			$password = wp_generate_password(12, false);
			$count = 0;
			while (username_exists($username)) {
				$username .= rand(0,9);
				if (++$count > 10) break;
			}

			$wp_user = wp_create_user($username, $password, $email);
			if (is_wp_error($wp_user)) die(json_encode($resp)); // Failure creating user
		} else {
			$wp_user = $wp_user->ID;
		}

		$user = get_userdata($wp_user);
		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID); // Logged in with Twitter, yay
		do_action('wp_login', $user->user_login);

		die(json_encode(array(
			"status" => 1,
			"user_id"=>$user->ID
		)));
	}

	/**
	 * Get OAuth request URL and token.
	 */
	function handle_get_google_auth_url () {
		header("Content-type: application/json");

		$this->openid->returnUrl = $_POST['url'];

		echo json_encode(array(
			'url' => $this->openid->authUrl()
		));
		exit();
	}

	/**
	 * Login or create a new user using whatever data we get from Google.
	 */
	function handle_google_login () {
		header("Content-type: application/json");
		$resp = array(
			"status" => 0,
		);

		$cache = $this->openid->getAttributes();

		if (isset($cache['namePerson/first']) || isset($cache['namePerson/last']) || isset($cache['namePerson/friendly']) || isset($cache['contact/email'])) {
			$this->_google_user_cache = $cache;
		}

		// Have user, now register him/her
		if ( isset( $this->_google_user_cache['namePerson/friendly'] ) )
			$username = $this->_google_user_cache['namePerson/friendly'];
		else
			$username = $this->_google_user_cache['namePerson/first'];
		$email = $this->_google_user_cache['contact/email'];
		$wordp_user = get_user_by('email', $email);

		if (!$wordp_user) { // Not an existing user, let's create a new one
			$password = wp_generate_password(12, false);
			$count = 0;
			while (username_exists($username)) {
				$username .= rand(0,9);
				if (++$count > 10) break;
			}

			$wordp_user = wp_create_user($username, $password, $email);
			if (is_wp_error($wordp_user))
				die(json_encode($resp)); // Failure creating user
			else {
				update_user_meta($wordp_user, 'first_name', $this->_google_user_cache['namePerson/first']);
				update_user_meta($wordp_user, 'last_name', $this->_google_user_cache['namePerson/last']);
			}
		}
		else {
			$wordp_user = $wordp_user->ID;
		}

		$user = get_userdata($wordp_user);
		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID); // Logged in with Google, yay
		do_action('wp_login', $user->user_login);

		die(json_encode(array(
			"status" => 1,
		)));
	}


/*******************************
* User methods
********************************
*/
	/**
	 * Saves working hours from user profile
	 */
	function save_profile( $profileuser_id ) {
		global $current_user, $wpdb;

		// Copy key file to uploads folder
		if ( is_object( $this->gcal_api ) ) {
			$kff = $this->gcal_api->key_file_folder( ); // Key file folder
			$kfn = $this->gcal_api->get_key_file( $profileuser_id ). '.p12'; // Key file name
			if ( $kfn && is_dir( $kff ) && !file_exists( $kff . $kfn ) && file_exists( $this->plugin_dir . '/includes/gcal/key/' . $kfn ) )
				copy( $this->plugin_dir . '/includes/gcal/key/' . $kfn, $kff . $kfn );
		}

		// Only user himself can save his data
		if ( $current_user->ID != $profileuser_id )
			return;

		// Save user meta
		if ( isset( $_POST['app_name'] ) )
			update_user_meta( $profileuser_id, 'app_name', $_POST['app_name'] );
		if ( isset( $_POST['app_email'] ) )
			update_user_meta( $profileuser_id, 'app_email', $_POST['app_email'] );
		if ( isset( $_POST['app_phone'] ) )
			update_user_meta( $profileuser_id, 'app_phone', $_POST['app_phone'] );
		if ( isset( $_POST['app_address'] ) )
			update_user_meta( $profileuser_id, 'app_address', $_POST['app_address'] );
		if ( isset( $_POST['app_city'] ) )
			update_user_meta( $profileuser_id, 'app_city', $_POST['app_city'] );

		// Save Google API settings
		if ( isset( $_POST['gcal_api_mode'] ) )
			update_user_meta( $profileuser_id, 'app_api_mode', $_POST['gcal_api_mode'] );
		if ( isset( $_POST['gcal_service_account'] ) )
			update_user_meta( $profileuser_id, 'app_service_account', trim( $_POST['gcal_service_account'] ) );
		if ( isset( $_POST['gcal_key_file'] ) )
			update_user_meta( $profileuser_id, 'app_key_file', trim( str_replace( '.p12', '', $_POST['gcal_key_file'] ) ) );
		if ( isset( $_POST['gcal_selected_calendar'] ) )
			update_user_meta( $profileuser_id, 'app_selected_calendar', trim( $_POST['gcal_selected_calendar'] ) );
		if ( isset( $_POST['gcal_summary'] ) ) {
			if ( !trim( $_POST['gcal_summary'] ) )
				$summary = __('SERVICE Appointment','appointments');
			else
				$summary = $_POST['gcal_summary'];
			update_user_meta( $profileuser_id, 'app_gcal_summary', $summary );
		}
		if ( isset( $_POST['gcal_description'] ) ) {
			if ( !trim( $_POST['gcal_description'] ) ) {
				$gcal_description = __("Client Name: CLIENT\nTour Type: SERVICE\nService Provider Name: SERVICE_PROVIDER\n", "appointments");
			} else {
				$gcal_description = $_POST['gcal_description'];
			}
			update_user_meta( $profileuser_id, 'app_gcal_description', $gcal_description );
		}

		// Cancel appointment
		if ( isset( $this->options['allow_cancel'] ) && 'yes' == $this->options['allow_cancel'] &&
			isset( $_POST['app_cancel'] ) && is_array( $_POST['app_cancel'] ) && !empty( $_POST['app_cancel'] ) ) {
			foreach ( $_POST['app_cancel'] as $app_id=>$value ) {
				if ( $this->change_status( 'removed', $app_id ) ) {
					$this->log( sprintf( __('Client %s cancelled appointment with ID: %s','appointments'), $this->get_client_name( $app_id ), $app_id ) );
					$this->send_notification( $app_id, true );

					if (!empty($this->gcal_api) && is_object($this->gcal_api)) $this->gcal_api->delete($app_id); // Drop the cancelled appointment
					else if (!defined('APP_GCAL_DISABLE')) $this->log("Unable to issue a remote call to delete the remote appointment.");

					// Do we also do_action app-appointments-appointment_cancelled?
				}
			}
		}

		// Only user who is a worker can save the rest
		if ( !$this->is_worker( $profileuser_id ) )
			return;

		// Confirm an appointment using profile page
		if ( isset( $_POST['app_confirm'] ) && is_array( $_POST['app_confirm'] ) && !empty( $_POST['app_confirm'] ) ) {
			foreach ( $_POST['app_confirm'] as $app_id=>$value ) {
				if ( $this->change_status( 'confirmed', $app_id ) ) {
					$this->log( sprintf( __('Service Provider %s manually confirmed appointment with ID: %s','appointments'), $this->get_worker_name( $current_user->ID ), $app_id ) );
					$this->send_confirmation( $app_id );
				}
			}
		}

		// Save working hours table
		// Do not save these if we are coming from BuddyPress confirmation tab
		if ( isset($this->options["allow_worker_wh"]) && 'yes' == $this->options["allow_worker_wh"] && isset( $_POST['open'] ) && isset( $_POST['closed'] ) ) {
			$result = $result2 = false;
			$location = 0;
			foreach ( array( 'closed', 'open' ) as $stat ) {
				$count = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->wh_table} WHERE location=%d AND worker=%d AND status=%s",
					$location, $profileuser_id, $stat
				));

				if ( $count > 0 ) {
					$result = $wpdb->update( $this->wh_table,
						array( 'hours'=>serialize($_POST[$stat]), 'status'=>$stat ),
						array( 'location'=>$location, 'worker'=>$profileuser_id, 'status'=>$stat ),
						array( '%s', '%s' ),
						array( '%d', '%d', '%s' )
					);
				}
				else {
					$result = $wpdb->insert( $this->wh_table,
						array( 'location'=>$location, 'worker'=>$profileuser_id, 'hours'=>serialize($_POST[$stat]), 'status'=>$stat ),
						array( '%d', '%d', '%s', '%s' )
						);
				}
				// Save exceptions
				$count2 = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->exceptions_table} WHERE location=%d AND worker=%d AND status=%s",
					$location, $profileuser_id, $stat
				));

				if ( $count2 > 0 ) {
					$result2 = $wpdb->update( $this->exceptions_table,
						array(
								'days'		=> $_POST[$stat]["exceptional_days"],
								'status'	=> $stat
							),
						array(
							'location'	=> $location,
							'worker'	=> $profileuser_id,
							'status'	=> $stat ),
						array( '%s', '%s' ),
						array( '%d', '%d', '%s' )
					);
				}
				else {
					$result2 = $wpdb->insert( $this->exceptions_table,
						array( 'location'	=> $location,
								'worker'	=> $profileuser_id,
								'days'		=> $_POST[$stat]["exceptional_days"],
								'status'	=> $stat
							),
						array( '%d', '%d', '%s', '%s' )
						);
				}
			}
			if ( $result || $result2 ) {
				$message = sprintf( __('%s edited his working hours.', 'appointments'), $this->get_worker_name( $profileuser_id ) );
				$this->log( $message );
				// Employer can be noticed here
				do_action( "app_working_hour_update", $message, $profileuser_id );
				// Also clear cache
				$this->flush_cache();
			}
		}
	}

	/**
	 * Displays appointment schedule on the user profile
	 */
	function show_profile( $profileuser ) {
		global $current_user, $wpdb;

		// Only user or admin can see his data
		if ( $current_user->ID != $profileuser->ID && !App_Roles::current_user_can('list_users', CTX_STAFF) )
			return;

		// For other than user himself, display data as readonly
		if ( $current_user->ID != $profileuser->ID )
			$is_readonly = ' readonly="readonly"';
		else
			$is_readonly = '';

		$is_readonly = apply_filters( 'app_show_profile_readonly', $is_readonly, $profileuser );

		if ( isset( $this->options["gcal"] ) && 'yes' == $this->options["gcal"] )
			$gcal = ''; // Default is already enabled
		else
			$gcal = ' gcal="0"';
	?>
		<h3><?php _e("Appointments+", 'appointments'); ?></h3>

		<table class="form-table">
		<tr>
		<th><label><?php _e("My Name", 'appointments'); ?></label></th>
		<td>
		<input type="text" style="width:25em" name="app_name" value="<?php echo get_user_meta( $profileuser->ID, 'app_name', true ) ?>" <?php echo $is_readonly ?> />
		</td>
		</tr>

		<tr>
		<th><label><?php _e("My email for A+", 'appointments'); ?></label></th>
		<td>
		<input type="text" style="width:25em" name="app_email" value="<?php echo get_user_meta( $profileuser->ID, 'app_email', true ) ?>" <?php echo $is_readonly ?> />
		</td>
		</tr>

		<tr>
		<th><label><?php _e("My Phone", 'appointments'); ?></label></th>
		<td>
		<input type="text" style="width:25em" name="app_phone" value="<?php echo get_user_meta( $profileuser->ID, 'app_phone', true ) ?>"<?php echo $is_readonly ?> />
		</td>
		</tr>

		<tr>
		<th><label><?php _e("My Address", 'appointments'); ?></label></th>
		<td>
		<input type="text" style="width:50em" name="app_address" value="<?php echo get_user_meta( $profileuser->ID, 'app_address', true ) ?>" <?php echo $is_readonly ?> />
		</td>
		</tr>

		<tr>
		<th><label><?php _e("My City", 'appointments'); ?></label></th>
		<td>
		<input type="text" style="width:25em" name="app_city" value="<?php echo get_user_meta( $profileuser->ID, 'app_city', true ) ?>" <?php echo $is_readonly ?> />
		</td>
		</tr>

		<?php if ( !$this->is_worker( $profileuser->ID ) ) { ?>
		<tr>
		<th><label><?php _e("My Appointments", 'appointments'); ?></label></th>
		<td>
		<?php echo do_shortcode("[app_my_appointments allow_cancel=1 client_id=".$profileuser->ID." ".$gcal."]") ?>
		</td>
		</tr>
			<?php
			if ( isset( $this->options['allow_cancel'] ) && 'yes' == $this->options['allow_cancel'] ) { ?>
				<script type='text/javascript'>
				jQuery(document).ready(function($){
					$('#your-profile').submit(function() {
						if ( $('.app-my-appointments-cancel').is(':checked') ) {
							if ( !confirm('<?php echo esc_js( __("Are you sure to cancel the selected appointment(s)?","appointments") ) ?>') )
							{return false;}
						}
					});
				});
				</script>
			<?php
			}
		}
		else { ?>
		<tr>
		<th><label><?php _e("My Appointments as Provider", 'appointments'); ?></label></th>
		<td>
		<?php echo do_shortcode("[app_my_appointments status='pending,confirmed,paid' _allow_confirm=1 provider_id=".$profileuser->ID."  provider=1 ".$gcal."]") ?>
		</td>
		</tr>
		<?php
			if ( isset( $this->options['allow_worker_confirm'] ) && 'yes' == $this->options['allow_worker_confirm'] ) { ?>
				<script type='text/javascript'>
				jQuery(document).ready(function($){
					$('#your-profile').submit(function() {
						if ( $('.app-my-appointments-confirm').is(':checked') ) {
							if ( !confirm('<?php echo esc_js( __("Are you sure to confirm the selected appointment(s)?","appointments") ) ?>') )
							{return false;}
						}
					});
				});
				</script>
				<?php
			}
			if ( isset($this->options["allow_worker_wh"]) && 'yes' == $this->options["allow_worker_wh"] ) { ?>
			<?php
			// A little trick to pass correct lsw variables to the related function
			$_REQUEST["app_location_id"] = 0;
			$_REQUEST["app_provider_id"] = $profileuser->ID;

			$this->get_lsw();

			$result = array();
			$result_open = $this->get_exception( $this->location, $this->worker, 'open' );
			if ( $result_open )
				$result["open"] = $result_open->days;
			else
				$result["open"] = null;

			$result_closed = $this->get_exception( $this->location, $this->worker, 'closed' );
			if ( $result_closed )
				$result["closed"] = $result_closed->days;
			else
				$result["closed"] = null;
			?>
			<tr>
			<th><label><?php _e("My Working Hours", 'appointments'); ?></label></th>
			<td>
			<?php echo $this->working_hour_form('open') ?>
			</td>
			</tr>
			<tr>
			<th><label><?php _e("My Break Hours", 'appointments'); ?></label></th>
			<td>
			<?php echo $this->working_hour_form('closed') ?>
			</td>
			</tr>
			<tr>
			<th><label><?php _e("My Exceptional Working Days", 'appointments'); ?></label></th>
			<td>
			<input class="datepick" id="open_datepick" type="text" style="width:100%" name="open[exceptional_days]" value="<?php if (isset($result["open"])) echo $result["open"]?>" />
			</td>
			</tr>
			<tr>
			<th><label><?php _e("My Holidays", 'appointments'); ?></label></th>
			<td>
			<input class="datepick" id="closed_datepick" type="text" style="width:100%" name="closed[exceptional_days]" value="<?php if (isset($result["closed"])) echo $result["closed"]?>" />
			</td>
			</tr>
			<script type="text/javascript">
			jQuery(document).ready(function($){
				$("#open_datepick").datepick({dateFormat: 'yyyy-mm-dd',multiSelect: 999, monthsToShow: 2});
				$("#closed_datepick").datepick({dateFormat: 'yyyy-mm-dd',multiSelect: 999, monthsToShow: 2});
			});
			</script>
			<?php } ?>
		<?php } ?>
		<?php if ( isset($this->options["gcal_api_allow_worker"]) && 'yes' == $this->options["gcal_api_allow_worker"] && $this->is_worker( $profileuser->ID ) ) { ?>
			<tr>
			<th><label><?php _e("Appointments+ Google Calendar API", 'appointments'); ?></label></th>
			<td>
			</td>
			</tr>
			<tr>
			<td colspan="2">
			<?php
				if ( is_object( $this->gcal_api ) )
					$this->gcal_api->display_nag( $profileuser->ID ); ?>
			</td>
			</tr>
		<?php
			if ( is_object( $this->gcal_api ) )
				$this->gcal_api->display_settings( $profileuser->ID );
		 } ?>
		</table>
	<?php
	}

/****************************************
* Methods for integration with Membership
*****************************************
*/

	/**
	 * Check if Membership plugin is active
	 */
	function check_membership_plugin() {
		if( ( is_admin() && class_exists('membershipadmin') ) || ( !is_admin() && class_exists('membershippublic') ) )
			$this->membership_active = true;
	}

	/**
	* Finds if user is Membership member with sufficient level
	* @return bool
	*/
	function is_member( ) {
		if ( $this->membership_active && isset( $this->options["members"] ) ) {
			global $current_user;
			$meta = maybe_unserialize( $this->options["members"] );
			$member = new M_Membership($current_user->ID);
			if( is_array( $meta ) && $current_user->ID > 0 && $member->has_levels()) {
				// Load the levels for this member
				$levels = $member->get_level_ids( );
				if ( is_array( $levels ) && is_array( $meta["level"] ) ) {
					foreach ( $levels as $level ) {
						if ( in_array( $level->level_id, $meta["level"] ) )
							return true; // Yes, user has sufficent level
					}
				}
			}
		}
		return false;
	}

/*****************************************
* Methods for integration with Marketpress
******************************************
*/

	/**
	 * Check if Marketpress plugin is active
	 * @Since 1.0.1
	 */
	function check_marketpress_plugin() {
		global $mp;
		if ( class_exists('MarketPress') && is_object( $mp ) ) {
			$this->marketpress_active = true;
			// Also check if it is activated
			if ( isset( $this->options["use_mp"] ) && $this->options["use_mp"] ) {
				$this->mp = true;
				add_action( 'manage_posts_custom_column', array($this, 'edit_products_custom_columns'), 1 );
				add_action( 'wp_ajax_nopriv_mp-update-cart', array($this, 'pre_update_cart'), 1 );
				add_action( 'wp_ajax_mp-update-cart', array($this, 'pre_update_cart'), 1 );
				add_action( 'wp', array($this, 'remove_from_cart_manual'), 1 );
				add_filter( 'the_content', array($this, 'product_page'), 18 );
				add_action( 'mp_order_paid', array($this, 'handle_mp_payment'));
				add_filter( 'mp_product_list_meta', array($this, 'mp_product_list_meta'), 10, 2);
				add_filter( 'mp_order_notification_body', array($this, 'modify_email'), 10, 2 );
				add_filter( 'mp_product_name_display_in_cart', array($this, 'modify_name'), 10, 2 );
				add_filter( 'mp_buy_button_tag', array($this, 'mp_buy_button_tag'), 10, 3 );
				return true;
			}
		}
		return false;
	}

	/**
	 * Remove duplicate buttons on Product List page and modify button text, also replace form with a link
	 * @param $button, $product_id, $context: See MarketPress
	 * @return string
	 * @Since 1.2.5
	 */
	function mp_buy_button_tag( $button, $product_id, $context ) {

		$book_now = apply_filters( 'app_mp_book_now', __('Choose Option &raquo;','appointments') );

		$product = get_post( $product_id );
		if ( 'list' != $context || !$this->is_app_mp_page( $product ) )
			return $button;

		if ( isset($_REQUEST['order'] ) ) {
			$button = preg_replace(
				'%<input class="mp_button_buynow"(.*?)value="(.*?)" />%is',
				'<input class="mp_button_buynow" type="submit" name="buynow" value="'.$book_now.'" />',
				$button
			);
			$button = preg_replace(
				'%<input class="mp_button_addcart"(.*?)value="(.*?)" />%is',
				'<input class="mp_button_buynow" type="submit" name="buynow" value="'.$book_now.'" />',
				$button
			);
			$button = preg_replace(
				'%<form(.*?)></form>%is',
				'<a class="mp_link_buynow" href="'.get_permalink($product_id).'">'.$book_now.'</a>',
				$button
			);

			return $button;
		}
		else return '';
	}

	/**
	 * Determine if a page is A+ Product page from the shortcodes used
	 * @param $product custom post object
	 * @return bool
	 * @Since 1.0.1
	 */
	function is_app_mp_page( $product ) {
		$result = false;
		if ( is_object( $product ) && strpos( $product->post_content, '[app_' ) !== false )
			$result = true;
		// Maybe required for templates
		return apply_filters( 'app_is_mp_page', $result, $product );
	}

	/**
	 * Hide column details for A+ products
	 * @Since 1.0.1
	 */
	function edit_products_custom_columns( $column ) {
		global $post, $mp;
		if (!$this->is_app_mp_page($post)) return;
		$hook = version_compare($mp->version, '2.8.8', '<')
			? 'manage_posts_custom_column'
			: 'manage_product_posts_custom_column'
		;
		if ('variations' == $column || 'sku' == $column || 'pricing' == $column) {
			remove_action($hook, array($mp, 'edit_products_custom_columns'));
			echo '-';
		} else {
			add_action($hook, array($mp, 'edit_products_custom_columns'));
		}
	}

	/**
	 * Remove download link from confirmation email
	 * @Since 1.0.1
	 */
	function modify_email( $body, $order ) {

		if ( !is_object( $order ) || !is_array( $order->mp_cart_info ) )
			return $body;

		$order_id = $order->post_title; // Strange, but true :)

		foreach ( $order->mp_cart_info as $product_id=>$product_detail ) {
			$product = get_post( $product_id );
			// Find if this is an A+ product and change link if it is
			if ( $this->is_app_mp_page( $product ) )
				$body = str_replace( get_permalink( $product_id ) . "?orderid=$order_id", '-', $body );
		}

		// Addons may want to modify MP email
		return apply_filters( 'app_mp_email', $body, $order );
	}

	/**
	 * Modify display name in the cart
	 * @Since 1.0.1
	 */
	function modify_name( $name, $product_id ) {
		$product = get_post( $product_id );
		$var_names = get_post_meta( $product_id, 'mp_var_name', true );
		if ( !$this->is_app_mp_page( $product ) || !is_array( $var_names ) )
			return $name;

		list( $app_title, $app_id ) = split( ':', $name );
		if ( $app_id ) {
			global $wpdb;
			$result = $this->get_app( $app_id );
			if ( $result ) {
				$name = $name . " (". date_i18n( $this->datetime_format, strtotime( $result->start ) ) . ")";
				$name = apply_filters( 'app_mp_product_name_in_cart', $name, $this->get_service_name( $result->service ), $this->get_worker_name( $result->worker ), $result->start, $result );
			}
		}
		return $name;
	}

	/**
	 * Handle after a successful Marketpress payment
	 * @Since 1.0.1
	 */
	function handle_mp_payment( $order ) {

		if ( !is_object( $order ) || !is_array( $order->mp_cart_info ) )
			return;

		foreach ( $order->mp_cart_info as $product_id=>$product_detail ) {
			$product = get_post( $product_id );
			// Find if this is an A+ product
			if ( $this->is_app_mp_page( $product ) && is_array( $product_detail ) ) {
				foreach( $product_detail as $var ) {
					// Find variation = app id which should also be downloadable
					if ( isset( $var['name'] ) && isset( $var['download'] ) ) {
						list( $product_name, $app_id ) = split( ':', $var['name'] );
						$app_id = (int)trim( $app_id );
						if ( $this->change_status( 'paid', $app_id ) ) {
							do_action( 'app_mp_order_paid', $app_id, $order ); // FIRST do the action
							if (!empty($this->options["send_confirmation"]) && 'yes' == $this->options["send_confirmation"]) $this->send_confirmation($app_id);
						}
					}
				}
			}
		}
	}

	/**
	 * Add to array of product pages where we have A+ shortcodes
	 * @Since 1.0.1
	 */
	function add_to_mp( $post_id ) {
		$this->mp_posts[] = $post_id;
	}

	/**
	 * If this is an A+ product page add js codes to footer to hide some MP fields
	 * @param content: post content
	 * @Since 1.0.1
	 */
	function product_page( $content ) {

		global $post;
		if ( is_object( $post ) && in_array( $post->ID, $this->mp_posts ) )
			$this->add2footer( '$(".mp_quantity,.mp_product_price,.mp_buy_form,.mp_product_variations,.appointments-paypal").hide();' );

		return $content;
	}

	/**
	 * Hide meta (Add to chart button, price) for an A+ product
	 * @Since 1.0.1
	 */
	function mp_product_list_meta( $meta, $post_id) {

		if ( in_array( $post_id, $this->mp_posts ) )
			return '<a class="mp_link_buynow" href="' . get_permalink($post_id) . '">' . __('Choose Option &raquo;', 'mp') . '</a>';
		else
			return	$meta;
	}

	/**
	 * Adds and returns a variation to the app product
	 * @Since 1.0.1
	 */
	function add_variation( $app_id, $post_id, $service, $worker, $start, $end ) {

		$meta = get_post_meta( $post_id, 'mp_var_name', true );
		// MP requires at least 2 variations, so we add a dummy one	if there is none
		if ( !$meta || !is_array( $meta ) ) {
			add_post_meta( $post_id, 'mp_var_name', array( 0 ) );
			add_post_meta( $post_id, 'mp_sku', array( 0 ) );

			// Find minimum service price here:
			global $wpdb;
			$min_price = $wpdb->get_var( "SELECT MIN(price) FROM " . $this->services_table . " WHERE price>0 " );
			if ( !$min_price )
				$min_price = 0;

			add_post_meta( $post_id, 'mp_price', array( $min_price ) );
			// Variation ID
			$meta = array( 0 );
		}

		$max = count( $meta );
		$meta[$max] = $app_id;
		update_post_meta( $post_id, 'mp_var_name', $meta );

		$sku = get_post_meta( $post_id, 'mp_sku', true );
		$sku[$max] = $this->service;
		update_post_meta( $post_id, 'mp_sku', $sku );

		$price = get_post_meta( $post_id, 'mp_price', true );
		$price[$max] = apply_filters( 'app_mp_price', $this->get_price( true ), $service, $worker, $start, $end ); // Filter added at V1.2.3.1
		update_post_meta( $post_id, 'mp_price', $price );

		// Add a download link, so that app will be a digital product
		$file = get_post_meta($post_id, 'mp_file', true);
		if ( !$file )
			add_post_meta( $post_id, 'mp_file', get_permalink( $post_id ) );

		return $max;
	}

	/**
	 * If a pending app is removed automatically, also remove it from the cart
	 * @Since 1.0.1
	 */
	function remove_from_cart( $app ) {
		global $mp;
		$changed = false;
		$cart = $mp->get_cart_cookie();

		if ( is_array( $cart ) ) {
			foreach ( $cart as $product_id=>$product_detail ) {
				$product = get_post( $product_id );
				$var_names = get_post_meta( $product_id, 'mp_var_name', true );
				// Find if this is an A+ product
				if ( $this->is_app_mp_page( $product ) && is_array( $product_detail ) && is_array( $var_names ) ) {
					foreach( $product_detail as $var_id=>$var_val ) {
						// Find variation = app id
						if ( isset( $var_names[$var_id] ) && $var_names[$var_id] == $app->ID ) {
							unset( $cart[$product_id] );
							$changed = true;
						}
					}
				}
			}
		}
		// Update cart only if something has changed
		if ( $changed )
			$mp->set_cart_cookie($cart);
	}

	/**
	 * Clear appointment that is removed from the cart also from the database
	 * This is called before MP
	 * @Since 1.0.1
	 */
	function remove_from_cart_manual( ) {

		if (isset($_POST['update_cart_submit'])) {
			if (isset($_POST['remove']) && is_array($_POST['remove'])) {
				foreach ($_POST['remove'] as $pbid) {
					list($bid, $product_id, $var_id) = split(':', $pbid);
					$product = get_post( $product_id );
					// Check if this is an app product page
					if ( $this->is_app_mp_page( $product ) ) {
						// We need to find var name = app_id
						$var_names = get_post_meta( $product_id, 'mp_var_name', true );
						if ( isset( $var_names[$var_id] ) ) {
							$this->change_status( 'removed', (int)trim( $var_names[$var_id] ) );
						}
					}
				}
			}
		}
	}

	/**
	 * Add the appointment to the cart
	 * This is called before MP
	 * @Since 1.0.1
	 */
	function pre_update_cart( ) {
		global $mp;

		if ( isset( $_POST['product_id'] )  && isset( $_POST['variation'] ) && $_POST['product_id'] && $_POST['variation'] ) {
			$product_id = $_POST['product_id'];
			$product = get_post( $product_id );
			// Check if this is an app product page
			if ( $this->is_app_mp_page( $product ) ) {
				$variation = $_POST['variation'];

				$cart = $mp->get_cart_cookie();
				if ( !is_array( $cart ) )
					$cart = array();

				// Make quantity 0 so that MP can set it to 1
				$cart[$product_id][$variation] = 0;

				//save items to cookie
				$mp->set_cart_cookie($cart);

				// Set email to SESSION variables if not set before
				if ( !isset( $_SESSION['mp_shipping_info']['email'] ) && isset( $_COOKIE["wpmudev_appointments_userdata"] ) ) {
					$data = unserialize( stripslashes( $_COOKIE["wpmudev_appointments_userdata"] ) );
					if ( is_array( $data ) && isset( $data["e"] ) )
						@$_SESSION['mp_shipping_info']['email'] = $data["e"];
				}
			}
		}
	}

/*******************************
* Methods for inits, styles, js
********************************
*/

	/**
     * Find blogs and install tables for each of them
	 * @since 1.0.2
	 * @until 1.4.1 - omg no, please let's never do this again
     */
	function install() { do_action('app-core-doing_it_wrong', __METHOD__); }
	/**
     * Install database tables
     */
	function _install() { do_action('app-core-doing_it_wrong', __METHOD__); }
	/**
	 * Install tables for new blog
	 * @since 1.0.2
	 * @until 1.4.1
	 */
	function new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta ) { do_action('app-core-doing_it_wrong', __METHOD__); }
	/**
	 * Remove tables for a deleted blog
	 * @since 1.0.2
	 * @until 1.4.1
	 */
	function delete_blog( $blog_id, $drop )  { do_action('app-core-doing_it_wrong', __METHOD__); }

	/**
	 * Initialize widgets
	 */
	function widgets_init() {
		if ( !is_blog_installed() )
			return;

		register_widget( 'Appointments_Widget_Services' );
		register_widget( 'Appointments_Widget_Service_Providers' );
		register_widget( 'Appointments_Widget_Monthly_Calendar' );
	}

	/**
	 * Add a script to be used in the footer, checking duplicates
	 * In some servers, footer scripts were called twice. This function fixes it.
	 * @since 1.2.0
	 */
	function add2footer( $script='' ) {

		if ( $script && strpos( $this->script, $script ) === false )
			$this->script = $this->script . $script;
	}

	/**
	 * Load javascript to the footer
	 */
	function wp_footer() {
		$script = '';
		$this->script = apply_filters( 'app_footer_scripts', $this->script );

		if ( $this->script ) {
			$script .= '<script type="text/javascript">';
			$script .= "jQuery(document).ready(function($) {";
			$script .= $this->script;
			$script .= "});</script>";
		}

		echo $this->esc_rn( $script );
		do_action('app-footer_scripts-after');
	}

	/**
	 * Load style and script only when they are necessary
	 * http://beerpla.net/2010/01/13/wordpress-plugin-development-how-to-include-css-and-javascript-conditionally-and-only-when-needed-by-the-posts/
	 */
	function load_styles( $posts ) {
		if ( empty($posts) || is_admin() )
			return $posts;

		$this->shortcode_found = false; // use this flag to see if styles and scripts need to be enqueued
		foreach ( $posts as $post ) {
			if ( is_object( $post ) && stripos( $post->post_content, '[app_' ) !== false ) {
				$this->shortcode_found = true;
				$this->add_to_cache( $post->ID );
				// Don't go further if MP is not active, this may save some time for archive pages
				if ( !$this->mp )
					break;
				// Also add to A+ product posts
				if ( 'product' == $post->post_type )
					$this->add_to_mp( $post->ID );
			}
		}

		if ( $this->shortcode_found )
			$this->load_scripts_styles( );

		return $posts;
	}

	/**
	 * Function to load all necessary scripts and styles
	 * Can be called externally, e.g. when forced from a page template
	 */
	 
	function load_scripts_styles_backend( ) {
		wp_enqueue_script( 'bootstrap', $this->plugin_url . '/js/bootstrap-admin.js', array('jquery'), $this->version );
		wp_enqueue_style( 'bootstrap', $this->plugin_url . '/css/bootstrap-admin.css', array(), $this->version );
		wp_enqueue_script('bootstrap-datepicker.min',$this->plugin_url . '/js/bootstrap-datepicker.min.js');
		wp_enqueue_style('bootstrap-datepicker.min', $this->plugin_url . '/css/bootstrap-datepicker.standalone.min.css');
	}
	 
	function load_scripts_styles( ) {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-tablesorter', $this->plugin_url . '/js/jquery.tablesorter.min.js', array('jquery'), $this->version );
		wp_enqueue_script( 'bootstrap', $this->plugin_url . '/js/bootstrap.min.js', array('jquery'), $this->version );
		wp_enqueue_style( 'bootstrap', $this->plugin_url . '/css/bootstrap.min.css', array(), $this->version );
		
		add_action( 'wp_footer', array( &$this, 'wp_footer' ) );	// Publish plugin specific scripts in the footer

		// TODO: consider this
		wp_enqueue_script( 'app-js-check', $this->plugin_url . '/js/js-check.js', array('jquery'), $this->version);
		wp_localize_script( 'app-js-check', '_appointments_data',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'root_url' => plugins_url('appointments/images/')
				)
		);

		if ( !current_theme_supports( 'appointments_style' ) ) {
			wp_enqueue_style( "appointments", $this->plugin_url. "/css/front.css", array(), $this->version );
			add_action( 'wp_head', array( &$this, 'wp_head' ) );
		}

		// wpautop does strange things to cache content, so remove it first and add to output
		if ( $this->use_cache() ) {
			if ( has_filter( 'wpautop' ) ) {
				$this->had_filter = true;
			}
			remove_filter( 'the_content', 'wpautop' );
			remove_filter( 'the_excerpt', 'wpautop' );
		}

		do_action('app-scripts-general');

		// Prevent external caching plugins for this page
		if ( !defined( 'DONOTCACHEPAGE' ) )
			define( 'DONOTCACHEPAGE', true );
		// Prevent W3T Minify
		if ( !defined( 'DONOTMINIFY' ) )
			define( 'DONOTMINIFY', true );

		// Set up services support defaults
		$show_login_button = array('google', 'wordpress');
		if (!empty($this->options['facebook-app_id'])) $show_login_button[] = 'facebook';
		if (!empty($this->options['twitter-app_id']) && !empty($this->options['twitter-app_secret'])) $show_login_button[] = 'twitter';

		// Is registration allowed?
		$do_register = is_multisite()
			? in_array(get_site_option('registration'), array('all', 'user'))
			: (int)get_option('users_can_register')
		;

		// Load the rest only if API use is selected
		if (@$this->options['accept_api_logins']) {
			wp_enqueue_script('appointments_api_js', $this->plugin_url . '/js/appointments-api.js', array('jquery'), $this->version );
			wp_localize_script('appointments_api_js', 'l10nAppApi', apply_filters('app-scripts-api_l10n', array(
				'facebook' => __('Login with Facebook', 'appointments'),
				'twitter' => __('Login with Twitter', 'appointments'),
				'google' => __('Login with Google+', 'appointments'),
				'wordpress' => __('Login with WordPress', 'appointments'),
				'submit' => __('Submit', 'appointments'),
				'cancel' => _x('Cancel', 'Drop current action', 'appointments'),
				'please_wait' => __('Please, wait...', 'appointments'),
				'logged_in' => __('You are now logged in', 'appointments'),
				'error' => __('Login error. Please try again.', 'appointments'),
				'_can_use_twitter' => (!empty($this->options['twitter-app_id']) && !empty($this->options['twitter-app_secret'])),
				'show_login_button' => $show_login_button,
				'gg_client_id' => $this->options['google-client_id'],
				'register' => ($do_register ? __('Register', 'appointments') : ''),
				'registration_url' => ($do_register ? wp_registration_url() : ''),
			)));

			if (!empty($this->options['facebook-app_id'])) {
				if (!$this->options['facebook-no_init']) {
					add_action('wp_footer', create_function('', "echo '" .
					sprintf(
						'<div id="fb-root"></div><script type="text/javascript">
						window.fbAsyncInit = function() {
							FB.init({
							  appId: "%s",
							  status: true,
							  cookie: true,
							  xfbml: true
							});
						};
						// Load the FB SDK Asynchronously
						(function(d){
							var js, id = "facebook-jssdk"; if (d.getElementById(id)) {return;}
							js = d.createElement("script"); js.id = id; js.async = true;
							js.src = "//connect.facebook.net/en_US/all.js";
							d.getElementsByTagName("head")[0].appendChild(js);
						}(document));
						</script>',
						$this->options['facebook-app_id']
					) .
					"';"));
				}
			}
			do_action('app-scripts-api');
		}
	}

	/**
	 * css that will be added to the head, again only for app pages
	 */
	function wp_head() {

		?>
		<style type="text/css">
		<?php

		if ( isset( $this->options["additional_css"] ) && '' != trim( $this->options["additional_css"] ) ) {
			echo $this->options['additional_css'];
		}

		foreach ( $this->get_classes() as $class=>$name ) {
			if ( !isset( $this->options["color_set"] ) || !$this->options["color_set"] ) {
				if ( isset( $this->options[$class."_color"] ) )
					$color = $this->options[$class."_color"];
				else
					$color = $this->get_preset( $class, 1 );
			}
			else
				$color = $this->get_preset( $class, $this->options["color_set"] );

			echo 'td.'.$class.',div.'.$class.' {background: #'. $color .' !important;}';
		}

		// Don't show Google+ button if openid is not enabled
		if ( !@$this->openid )
			echo '.appointments-login_link-google{display:none !important;}';
		?>
		</style>
		<?php
	}

	/**
     * Localize the plugin
     */
	function localization() {
		// Load up the localization file if we're using WordPress in a different language
		// Place it in Appointments+'s "languages" folder and name it "appointments-[value in wp-config].mo"
		load_plugin_textdomain( 'appointments', false, '/appointments/languages/' );
	}

	/**
	 *	Add initial settings
	 *
	 */
	function init() {

		// Since wp-cron is not reliable, use this instead
		add_option( "app_last_update", time() );

		$confirmation_message = App_Template::get_default_confirmation_message();
		$reminder_message = App_Template::get_default_reminder_message();

		add_option('appointments_options', array(
			'min_time'					=> 30,
			'additional_min_time'		=> '',
			'admin_min_time'			=> '',
			'app_lower_limit'			=> 0,
			'app_limit'					=> 365,
			'clear_time'				=> 60,
			'spam_time'					=> 0,
			'auto_confirm'				=> 'no',
			'allow_worker_selection'	=> 'no',
			'allow_worker_confirm'		=> 'no',
			'allow_overwork'			=> 'no',
			'allow_overwork_break'		=> 'no',
			'dummy_assigned_to'			=> 0,
			'app_page_type'				=> 'monthly',
			'accept_api_logins'			=> '',
			'facebook-app_id'			=> '',
			'twitter-app_id'			=> '',
			'twitter-app_secret'		=> '',
			'show_legend'				=> 'yes',
			'gcal'						=> 'yes',
			'gcal_location'				=> '',
			'color_set'					=> 1,
			'free_color'				=> '48c048',
			'busy_color'				=> 'ffffff',
			'notpossible_color'			=> 'ffffff',
			'make_an_appointment'		=> '',
			'ask_name'					=> '1',
			'ask_email'					=> '1',
			'ask_phone'					=> '1',
			'ask_address'				=> '',
			'ask_city'					=> '',
			'ask_note'					=> '',
			'additional_css'			=> '.entry-content td{border:none;width:50%}',
			'payment_required'			=> 'no',
			'percent_deposit'			=> '',
			'fixed_deposit'				=> '',
			'currency'					=> 'USD',
			'mode'						=> 'sandbox',
			'merchant_email'			=> '',
			'return'					=> 1,
			'login_required'			=> 'no',
			'send_confirmation'			=> 'yes',
			'send_notification'			=> 'no',
			'send_reminder'				=> 'yes',
			'reminder_time'				=> '24',
			'send_reminder_worker'		=> 'yes',
			'reminder_time_worker'		=> '4',
			'confirmation_subject'		=> __('Confirmation of your Appointment','appointments'),
			'confirmation_message'		=> $confirmation_message,
			'reminder_subject'			=> __('Reminder for your Appointment','appointments'),
			'reminder_message'			=> $reminder_message,
			'log_emails'				=> 'yes',
			'use_cache'					=> 'no',
			'use_mp'					=> false,
			'allow_cancel'				=> 'no',
			'cancel_page'				=> 0
		));

		//  Run this code not before 10 mins
		if ( ( time() - get_option( "app_last_update" ) ) < apply_filters( 'app_update_time', 600 ) )
			return;
		$this->remove_appointments();
		$this->send_reminder();
		$this->send_reminder_worker();
		// Update Google API imports
		if ( is_object( $this->gcal_api ) )
			$this->gcal_api->import_and_update();
	}

/*******************************
* Methods for Confirmation
********************************

	/**
	 *	Send confirmation email
	 *  @param app_id: ID of the app whose confirmation will be sent
	 */
	function send_confirmation( $app_id ) {
		if ( !isset( $this->options["send_confirmation"] ) || 'yes' != $this->options["send_confirmation"] )
			return;
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->app_table} WHERE ID=%d", $app_id) );
		if ( $r != null ) {

			$_REQUEST["app_location_id"] = 0;
			$_REQUEST["app_service_id"] = $r->service;
			$_REQUEST["app_provider_id"] = $r->worker;

			// Why oh why didn't we do this all along?
			if (empty($r->email) && !empty($r->user) && (int)$r->user) {
				$wp_user = get_user_by('id', (int)$r->user);
				if ($wp_user && !empty($wp_user->user_email)) $r->email = $wp_user->user_email;
			}

			$body = apply_filters( 'app_confirmation_message', $this->add_cancel_link( $this->_replace( $this->options["confirmation_message"],
					$r->name, $this->get_service_name( $r->service), $this->get_worker_name( $r->worker), $r->start, $r->price,
					$this->get_deposit($r->price), $r->phone, $r->note, $r->address, $r->email, $r->city ), $app_id ), $r, $app_id );

			$mail_result = wp_mail(
						$r->email,
						$this->_replace( $this->options["confirmation_subject"], $r->name,
							$this->get_service_name( $r->service), $this->get_worker_name( $r->worker),
							$r->start, $r->price, $this->get_deposit($r->price), $r->phone, $r->note, $r->address, $r->email, $r->city ),
						$body,
						$this->message_headers( ),
						apply_filters( 'app_confirmation_email_attachments', '' )
					);

			if ( $r->email && $mail_result ) {
				// Log only if it is set so
				if ( isset( $this->options["log_emails"] ) && 'yes' == $this->options["log_emails"] )
					$this->log( sprintf( __('Confirmation message sent to %s for appointment ID:%s','appointments'), $r->email, $app_id ) );

				do_action( 'app_confirmation_sent', $body, $r, $app_id );

				// Allow disabling of confirmation email to admin
				$disable = apply_filters( 'app_confirmation_disable_admin', false, $r, $app_id );
				if ( $disable )
					return;

				//  Send a copy to admin and service provider
				$to = array( $this->get_admin_email( ) );

				$worker_email = $this->get_worker_email( $r->worker );
				if ( $worker_email )
					$to[]= $worker_email;

				$provider_add_text  = sprintf( __('A new appointment has been made on %s. Below please find a copy of what has been sent to your client:', 'appointments'), get_option( 'blogname' ) );
				$provider_add_text .= "\n\n\n";

				wp_mail(
						$to,
						$this->_replace( __('New Appointment','appointments'), $r->name, $this->get_service_name( $r->service), $this->get_worker_name( $r->worker),
							$r->start, $r->price, $this->get_deposit($r->price), $r->phone, $r->note, $r->address, $r->email, $r->city ),
						$provider_add_text . $body,
						$this->message_headers( )
					);
			}
		}
		return true;
	}

	/**
	 * Send notification email
	 * @param cancel: If this is a cancellation
	 * @since 1.0.2
	 */
	function send_notification( $app_id, $cancel=false ) {
		// In case of cancellation, continue
		if ( !$cancel && !isset( $this->options["send_notification"] ) || 'yes' != $this->options["send_notification"] )
			return;
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->app_table} WHERE ID=%d", $app_id) );
		if ( $r != null ) {

			$admin_email = apply_filters( 'app_notification_email', $this->get_admin_email( ), $r );

			if ( $cancel ) {
				$subject = __('An appointment has been cancelled', 'appointments');
				$body = sprintf( __('Appointment with ID %s has been cancelled by the client. You can see it clicking this link: %s','appointments'), $app_id, admin_url("admin.php?page=appointments&type=removed") );
			}
			else {
				$subject = __('An appointment requires your confirmation', 'appointments');
				$body = sprintf( __('The new appointment has an ID %s and you can edit it clicking this link: %s','appointments'), $app_id, admin_url("admin.php?page=appointments&type=pending") );
			}
			$body = apply_filters('app_notification_message',
				apply_filters(
					'app-messages-' . ($cancel ? 'cancellation' : 'notification') . '-body',
					$body, $r, $app_id
				),
				$r, $app_id
			);
			$subject = apply_filters(
				'app-messages-' . ($cancel ? 'cancellation' : 'notification') . '-subject',
				$subject, $r, $app_id
			);

			$mail_result = wp_mail(
				$admin_email,
				$subject,
				$body,
				$this->message_headers()
			);

			if ( $mail_result && isset( $this->options["log_emails"] ) && 'yes' == $this->options["log_emails"] ) {
				$this->log( sprintf( __('Notification message sent to %s for appointment ID:%s','appointments'), $admin_email, $app_id ) );
				do_action( 'app_notification_sent', $body, $r, $app_id );
			}

			// Also notify service provider if he is allowed to confirm it
			// Note that message itself is different from that of the admin
			// Don't send repeated email to admin if he is the provider
			if ( $r->worker &&  $admin_email != $this->get_worker_email( $r->worker ) && isset( $this->options['allow_worker_confirm'] ) && 'yes' == $this->options['allow_worker_confirm'] ) {

				if ( $cancel ) {
				/* Translators: First %s is for appointment ID and the second one is for date and time of the appointment */
					$body = sprintf(__('Cancelled appointment has an ID %s for %s.','appointments'), $app_id, date_i18n($this->datetime_format, strtotime($r->start)));
				}
				else {
					$body = sprintf(__('The new appointment has an ID %s for %s and you can confirm it using your profile page.','appointments'), $app_id, date_i18n($this->datetime_format, strtotime($r->start)));
				}
				$body = apply_filters(
					'app-messages-worker-' . ($cancel ? 'cancellation' : 'notification'),
					$body, $r, $app_id
				);
				$subject = apply_filters(
					'app-messages-worker-' . ($cancel ? 'cancellation' : 'notification') . '-subject',
					$subject, $r, $app_id
				);

				$mail_result = wp_mail(
					$this->get_worker_email($r->worker),
					$subject,
					$body,
					$this->message_headers()
				);

				if ( $mail_result && isset( $this->options["log_emails"] ) && 'yes' == $this->options["log_emails"] )
					$this->log( sprintf( __('Notification message sent to %s for appointment ID:%s','appointments'), $this->get_worker_email( $r->worker ), $app_id ) );
			}
		}
		return true;
	}

	/**
	 * Sends out a removal notification email.
	 * This email is sent out only on admin status change, *not* on appointment cancellation by user.
	 * The email will go out to the client and, perhaps, worker and admin.
	 */
	function send_removal_notification ($app_id) {
		if ( !isset( $this->options["send_removal_notification"] ) || 'yes' != $this->options["send_removal_notification"] ) return false;
		$app = $this->get_app($app_id);
		$log = isset($this->options["log_emails"]) && 'yes' == $this->options["log_emails"];
		$email = !empty($app->email) ? $app->email : false;
		if (empty($email) && !empty($app->user) && is_numeric($app->user)) {
			// If we don't have an email, try getting one if user ID is set
			$wp_user = get_user_by('id', (int)$app->user);
			if ($wp_user && !empty($wp_user->user_email)) $email = $wp_user->user_email;
		}
		if (empty($email)) {
			// No reason to carry on, we don't know how to notify the client
			if ($log) $this->log(sprintf(__('Unable to notify the client about the appointment ID:%s removal, stopping.', 'appointments'), $app_id));
			return false;
		}

		$subject = !empty($this->options['removal_notification_subject'])
			? $this->options['removal_notification_subject']
			: App_Template::get_default_removal_notification_subject()
		;
		$subject = $this->_replace($subject,
			$app->name,
			$this->get_service_name($app->service),
			$this->get_worker_name($app->worker),
			$app->start,
			$app->price,
			$this->get_deposit($app->price),
			$app->phone,
			$app->note,
			$app->address,
			$app->email,
			$app->city
		);
		$msg = !empty($this->options['removal_notification_message'])
			? $this->options['removal_notification_message']
			: App_Template::get_default_removal_notification_message()
		;
		$msg = $this->_replace($msg,
			$app->name,
			$this->get_service_name($app->service),
			$this->get_worker_name($app->worker),
			$app->start,
			$app->price,
			$this->get_deposit($app->price),
			$app->phone,
			$app->note,
			$app->address,
			$app->email,
			$app->city
		);
		$msg = apply_filters('app_removal_notification_message', $msg, $app, $app_id);
		$result = wp_mail(
			$email,
			$subject,
			$msg,
			$this->message_headers()
		);
		if ($result && $log) {
			$this->log(sprintf(__('Removal notification message sent to %s for appointment ID:%s', 'appointments'), $email, $app_id));
		}

		$disable = apply_filters( 'app_removal_notification_disable_admin', false, $app, $app_id );
		if ($disable) return false;

		//  Send a copy to admin and service provider
		$to = array($this->get_admin_email());

		$worker_email = $this->get_worker_email($app->worker);
		if ($worker_email) $to[]= $worker_email;

		$provider_add_text  = sprintf(__('An appointment removal notification for %s has been sent to your client:', 'appointments'), $app_id);
		$provider_add_text .= "\n\n\n";

		wp_mail(
			$to,
			__('Removal notification', 'appointments'),
			$provider_add_text . $msg,
			$this->message_headers()
		);


		return true;
	}

	/**
	 *	Check and send reminders to clients for appointments
	 *
	 */
	function send_reminder() {
		if ( !isset( $this->options["reminder_time"] ) || !$this->options["reminder_time"] || 'yes' != $this->options["send_reminder"] )
			return;

		$hours = explode( "," , trim( $this->options["reminder_time"] ) );

		if ( !is_array( $hours ) || empty( $hours ) )
			return;

		global $wpdb;

		$messages = array();
		foreach ( $hours as $hour ) {
			$rlike = esc_sql(like_escape(trim($hour)));
			$results = $wpdb->get_results( "SELECT * FROM " . $this->app_table . "
				WHERE (status='paid' OR status='confirmed')
				AND (sent NOT LIKE '%:{$rlike}:%' OR sent IS NULL)
				AND DATE_ADD('".date( 'Y-m-d H:i:s', $this->local_time )."', INTERVAL ".(int)$hour." HOUR) > start " );

			if ( $results ) {
				foreach ( $results as $r ) {
					$_REQUEST["app_location_id"] = 0;
					$_REQUEST["app_service_id"] = $r->service;
					$_REQUEST["app_provider_id"] = $r->worker;

					$messages[] = array(
								'ID'		=> $r->ID,
								'to'		=> $r->email,
								'subject'	=> $this->_replace( $this->options["reminder_subject"], $r->name, $this->get_service_name( $r->service),
									$this->get_worker_name( $r->worker), $r->start, $r->price, $this->get_deposit($r->price), $r->phone, $r->note, $r->address, $r->email, $r->city ),
								'message'	=> apply_filters( 'app_reminder_message', $this->add_cancel_link( $this->_replace( $this->options["reminder_message"],
									$r->name, $this->get_service_name( $r->service), $this->get_worker_name( $r->worker), $r->start,
									$r->price, $this->get_deposit($r->price), $r->phone, $r->note, $r->address, $r->email, $r->city ), $r->ID ), $r, $r->ID )
							);
					// Update "sent" field
					$wpdb->update( $this->app_table,
									array( 'sent'	=> rtrim( $r->sent, ":" ) . ":" . trim( $hour ) . ":" ),
									array( 'ID'		=> $r->ID ),
									array ( '%s' )
								);
				}
			}
		}
		// Remove duplicates
		$messages = $this->array_unique_by_ID( $messages );
		if ( is_array( $messages ) && !empty( $messages ) ) {
			foreach ( $messages as $message ) {
				$mail_result = wp_mail( $message["to"], $message["subject"], $message["message"], $this->message_headers(), apply_filters( 'app_reminder_email_attachments', '' ) );
				if ( $mail_result && isset( $this->options["log_emails"] ) && 'yes' == $this->options["log_emails"] )
					$this->log( sprintf( __('Reminder message sent to %s for appointment ID:%s','appointments'), $message["to"], $message["ID"] ) );
			}
		}
		return true;
	}

	/**
	 *	Remove duplicate messages by app ID
	 */
	function array_unique_by_ID( $messages ) {
		if ( !is_array( $messages ) || empty( $messages ) )
			return false;
		$idlist = array();
		// Save array to a temp area
		$result = $messages;
		foreach ( $messages as $key=>$message ) {
			if ( in_array( $message['ID'], $idlist ) )
				unset( $result[$key] );
			else
				$idlist[] = $message['ID'];
		}
		return $result;
	}

	/**
	 *	Check and send reminders to worker for appointments
	 */
	function send_reminder_worker() {
		if ( !isset( $this->options["reminder_time_worker"] ) || !$this->options["reminder_time_worker"] || 'yes' != $this->options["send_reminder_worker"] )
			return;

		$hours = explode( "," , $this->options["reminder_time_worker"] );

		if ( !is_array( $hours ) || empty( $hours ) )
			return;

		global $wpdb;

		$messages = array();
		foreach ( $hours as $hour ) {
			$rlike = esc_sql(like_escape(trim($hour)));
			$results = $wpdb->get_results( "SELECT * FROM " . $this->app_table . "
				WHERE (status='paid' OR status='confirmed')
				AND worker <> 0
				AND (sent_worker NOT LIKE '%:{$rlike}:%' OR sent_worker IS NULL)
				AND DATE_ADD('".date( 'Y-m-d H:i:s', $this->local_time )."', INTERVAL ".(int)$hour." HOUR) > start " );

			$provider_add_text  = __('You are receiving this reminder message for your appointment as a provider. The below is a copy of what may have been sent to your client:', 'appointments');
			$provider_add_text .= "\n\n\n";

			if ( $results ) {
				foreach ( $results as $r ) {
					$_REQUEST["app_location_id"] = 0;
					$_REQUEST["app_service_id"] = $r->service;
					$_REQUEST["app_provider_id"] = $r->worker;

					$messages[] = array(
								'ID'		=> $r->ID,
								'to'		=> $this->get_worker_email( $r->worker ),
								'subject'	=> $this->_replace( $this->options["reminder_subject"], $r->name, $this->get_service_name($r->service),
									$this->get_worker_name($r->worker), $r->start, $r->price, $this->get_deposit($r->price), $r->phone, $r->note, $r->address, $r->email ),
								'message'	=> $provider_add_text . $this->_replace( $this->options["reminder_message"], $r->name,
									$this->get_service_name( $r->service), $this->get_worker_name( $r->worker), $r->start, $r->price,
									$this->get_deposit($r->price), $r->phone, $r->note, $r->address, $r->email )
							);
					// Update "sent" field
					$wpdb->update( $this->app_table,
									array( 'sent_worker' => rtrim( $r->sent_worker, ":" ) . ":" . trim( $hour ) . ":" ),
									array( 'ID'		=> $r->ID ),
									array ( '%s' )
								);
				}
			}
		}
		// Remove duplicates
		$messages = $this->array_unique_by_ID( $messages );
		if ( is_array( $messages ) && !empty( $messages ) ) {
			foreach ( $messages as $message ) {
				$mail_result = wp_mail( $message["to"], $message["subject"], $message["message"], $this->message_headers() );
				if ( $mail_result && isset( $this->options["log_emails"] ) && 'yes' == $this->options["log_emails"] )
					$this->log( sprintf( __('Reminder message sent to %s for appointment ID:%s','appointments'), $message["to"], $message["ID"] ) );
			}
		}
	}

	/**
	 *	Replace placeholders with real values for email subject and content
	 */
	function _replace( $text, $user, $service, $worker, $datetime, $price, $deposit, $phone='', $note='', $address='', $email='', $city='' ) {
		/*
		return str_replace(
					array( "SITE_NAME", "CLIENT", "SERVICE_PROVIDER", "SERVICE", "DATE_TIME", "PRICE", "DEPOSIT", "PHONE", "NOTE", "ADDRESS", "EMAIL", "CITY" ),
					array( wp_specialchars_decode(get_option('blogname'), ENT_QUOTES), $user, $worker, $service, mysql2date( $this->datetime_format, $datetime ), $price, $deposit, $phone, $note, $address, $email, $city ),
					$text
				);
		*/
		$balance = !empty($price) && !empty($deposit)
			? (float)$price - (float)$deposit
			: (!empty($price) ? $price : 0.0)
		;
		$replacement = array(
			'SITE_NAME' => wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
			'CLIENT' => $user,
			'SERVICE_PROVIDER' => $worker,
			'SERVICE' => $this->escape_backreference($service),
			'DATE_TIME' => mysql2date($this->datetime_format, $datetime),
			'PRICE' => $price,
			'DEPOSIT' => $deposit,
			'BALANCE' => $balance,
			'PHONE' => $phone,
			'NOTE' => $note,
			'ADDRESS' => $address,
			'EMAIL' => $email,
			'CITY' => $city,
		);
		foreach($replacement as $macro => $repl) {
			$text = preg_replace('/' . preg_quote($macro, '/') . '/U', $repl, $text);
		}
		return $text;
	}

	/**
     *	Avoid back-reference collisions.
     *  http://us1.php.net/manual/en/function.preg-replace.php#103985
     */
    function escape_backreference($x)
    {
        return preg_replace('/\$(\d)/', '\\\$$1', $x);
    }

	/**
	 *	Email message headers
	 */
	function message_headers () {
		$admin_email = $this->get_admin_email();
		$blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
		$content_type = apply_filters('app-emails-content_type', 'text/plain');
		if (!(defined('APP_EMAIL_DROP_LEGACY_HEADERS') && APP_EMAIL_DROP_LEGACY_HEADERS)) {
			$message_headers = "MIME-Version: 1.0\n" . "From: {$blogname}" .  " <{$admin_email}>\n" . "Content-Type: {$content_type}; charset=\"" . get_option('blog_charset') . "\"\n";
		} else {
			$message_headers = "MIME-Version: 1.0\n" .
				"Content-Type: {$content_type}; charset=\"" . get_option('blog_charset') . "\"\n"
			;
			add_filter('wp_mail_from', create_function('', "return '{$admin_email}';"));
			add_filter('wp_mail_from_name', create_function('', "return '{$blogname}';"));
		}
		// Modify message headers
		$message_headers = apply_filters( 'app_message_headers', $message_headers );

		return $message_headers;
	}

	/**
	 *	Remove an appointment if not paid or expired
	 *	Clear expired appointments.
	 *	Change status to completed if they are confirmed or paid
	 *	Change status to removed if they are pending or reserved
	 */
	function remove_appointments( ) {

		global $wpdb;

		$process_expired = apply_filters('app-auto_cleanup-process_expired', true);

		$expireds = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$this->app_table} WHERE start<%s AND status NOT IN ('completed', 'removed')", date("Y-m-d H:i:s", $this->local_time)) );
		if ( $expireds && $process_expired ) {
			foreach ( $expireds as $expired ) {
				if ( 'pending' == $expired->status || 'reserved' == $expired->status ) {
					if ('reserved' == $expired->status && strtotime($expired->end) > $this->local_time) $new_status = $expired->status; // Don't shift the GCal apps until they actually expire (end time in past)
					else $new_status = 'removed';
				} else if ( 'confirmed' == $expired->status || 'paid' == $expired->status ) {
					$new_status = 'completed';
				} else {
					$new_status = $expired->status; // Do nothing ??
				}
				$update = $wpdb->update( $this->app_table,
								array( 'status'	=> $new_status ),
								array( 'ID'	=> $expired->ID )
							);
				if ( $update ) {
					do_action( 'app_remove_expired', $expired, $new_status );
				}
			}
		}

		// Clear appointments that are staying in pending status long enough
		if ( isset( $this->options["clear_time"] ) && $this->options["clear_time"] > 0 ) {
			$clear_secs = $this->options["clear_time"] * 60;
			$expireds = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$this->app_table} WHERE status='pending' AND created<%s", date("Y-m-d H:i:s", $this->local_time - $clear_secs)) );
			if ( $expireds ) {
				foreach ( $expireds as $expired ) {
					$update = $wpdb->update( $this->app_table,
									array( 'status'	=> 'removed' ),
									array( 'ID'	=> $expired->ID )
								);
					if ( $update ) {
						do_action( 'app_remove_pending', $expired );
						if ( $this->mp )
							$this->remove_from_cart( $expired );
					}
				}
			}
		}
		update_option( "app_last_update", time() );

		// Appointment status probably changed, so clear cache.
		// Anyway it is good to clear the cache in certain intervals.
		// This can be removed for pages with very heavy visitor traffic, but little appointments
		$this->flush_cache();
	}

	/**
	 * Handle cancellation of an appointment by the client
	 * @since 1.2.6
	 */
	function cancel() {
		if ( isset( $this->options['allow_cancel'] ) && 'yes' == $this->options['allow_cancel'] ) {

			/* Cancel by the link in email */
			// We don't want to break any other plugin's init, so these conditions are very strict
			if ( isset( $_GET['app_cancel'] ) && isset( $_GET['app_id'] ) && isset( $_GET['app_nonce'] ) ) {
				$app_id = $_GET['app_id'];
				$app = $this->get_app( $app_id );

				if( isset( $app->status ) )
					$stat = $app->status;
				else
					$stat = '';

				// Addons may want to add or omit some stats, but as default we don't want completed appointments to be cancelled
				$in_allowed_stat = apply_filters( 'app_cancel_allowed_status', ('pending' == $stat || 'confirmed' == $stat || 'paid' == $stat), $stat, $app_id );

				// Also the clicked link may belong to a formerly created and deleted appointment.
				// Another irrelevant app may have been created after cancel link has been sent. So we will check creation date
				if ( $in_allowed_stat && $_GET['app_nonce'] == md5( $_GET['app_id']. $this->salt . strtotime( $app->created ) ) ) {
					if ( $this->change_status( 'removed', $app_id ) ) {
						$this->log( sprintf( __('Client %s cancelled appointment with ID: %s','appointments'), $this->get_client_name( $app_id ), $app_id ) );
						$this->send_notification( $app_id, true );

						if (!empty($this->gcal_api) && is_object($this->gcal_api)) $this->gcal_api->delete($app_id); // Drop the cancelled appointment
						else if (!defined('APP_GCAL_DISABLE')) $this->log("Unable to issue a remote call to delete the remote appointment.");

						do_action('app-appointments-appointment_cancelled', $app_id);
						// If there is a header warning other plugins can do whatever they need
						if ( !headers_sent() ) {
							if ( isset( $this->options['cancel_page'] ) &&  $this->options['cancel_page'] ) {
								wp_redirect( get_permalink( $this->options['cancel_page'] ) );
								exit;
							}
							else {
								wp_redirect( home_url() );
								exit;
							}
						}
					}
					// Gracefully go to home page if appointment has already been cancelled, or do something here
					do_action( 'app_cancel_failed', $app_id );
				}
			}

			/* Cancel from my appointments table by ajax */
			if ( isset( $_POST['app_id'] ) && isset( $_POST['cancel_nonce'] ) ) {
				$app_id = $_POST['app_id'];

				// Check if user is the real owner of this appointment to prevent malicious attempts
				$owner = false;
				// First try to find from database
				if ( is_user_logged_in() ) {
					global $current_user;
					$app = $this->get_app( $app_id );
					if ( $app->user && $app->user == $current_user->ID )
						$owner = true;
				}
				// Then check cookie. Check is not so strict here, as he couldn't be seeing that cancel checkbox in the first place
				if ( !$owner && isset( $_COOKIE["wpmudev_appointments"] ) ) {
					$apps = unserialize( stripslashes( $_COOKIE["wpmudev_appointments"] ) );
					if ( is_array( $apps ) && in_array( $app_id, $apps ) )
						$owner = true;
				}
				// Addons may want to do something here
				$owner = apply_filters( 'app_cancellation_owner', $owner, $app_id );

				// He is the wrong guy, or he may have cleared his cookies while he is on the page
				if ( !$owner )
					die( json_encode( array('error'=>esc_js(__('There is an issue with this appointment. Please refresh the page and try again. If problem persists, please contact website admin.','appointments') ) ) ) );

				// Now we can safely continue for cancel
				if ( $this->change_status( 'removed', $app_id ) ) {
					$this->log( sprintf( __('Client %s cancelled appointment with ID: %s','appointments'), $this->get_client_name( $app_id ), $app_id ) );
					$this->send_notification( $app_id, true );

					if (!empty($this->gcal_api) && is_object($this->gcal_api)) $this->gcal_api->delete($app_id); // Drop the cancelled appointment
					else if (!defined('APP_GCAL_DISABLE')) $this->log("Unable to issue a remote call to delete the remote appointment.");

					do_action('app-appointments-appointment_cancelled', $app_id);
					die( json_encode( array('success'=>1)));
				}
				else
					die( json_encode( array('error'=>esc_js(__('Appointment could not be cancelled. Please refresh the page and try again.','appointments') ) ) ) );
			}
		}
		else if ( isset( $_POST['app_id'] ) && isset( $_POST['cancel_nonce'] ) )
			die( json_encode( array('error'=>esc_js(__('Cancellation of appointments is disabled. Please contact website admin.','appointments') ) ) ) );
	}

	/**
	 * Replace CANCEL placeholder with its link
	 * @param text: email text
	 * @param app_id: ID of the appointment to be cancelled
	 * @since 1.2.6
	 */
	function add_cancel_link( $text, $app_id ) {
		if ( isset( $this->options['allow_cancel'] ) && 'yes' == $this->options['allow_cancel'] && $app_id ) {

			$app = $this->get_app( $app_id );
			// The link to be clicked may belong to a formerly created and deleted appointment.
			// Another irrelevant app may have been created after cancel link has been sent. So we will add creation date for check
			if ( $app )
				return str_replace( 'CANCEL', add_query_arg( array( 'app_cancel'=>1, 'app_id'=>$app_id, 'app_nonce'=>md5( $app_id . $this->salt . strtotime( $app->created ) ) ), home_url() ), $text);
			else
				return str_replace( 'CANCEL', '', $text );
		}
		else
			return str_replace( 'CANCEL', '', $text );
	}

/*******************************
* Methods for Admin
********************************
*/
	/**
	 * Add app status counts in admin Right Now Dashboard box
	 * http://codex.wordpress.org/Plugin_API/Action_Reference/right_now_content_table_end
	 */
	function add_app_counts() {

		global $wpdb;

		$num_active = $wpdb->get_var("SELECT COUNT(ID) FROM " . $this->app_table . " WHERE status='paid' OR status='confirmed' " );

        $num = number_format_i18n( $num_active );
        $text = _n( 'Active Appointment', 'Active Appointments', intval( $num_active ) );
        if ( App_Roles::current_user_can( 'manage_options', App_Roles::CTX_DASHBOARD ) ) {
            $num = "<a href='admin.php?page=appointments'>$num</a>";
            $text = "<a href='admin.php?page=appointments'>$text</a>";
        }
        echo '<td class="first b b-appointment">' . $num . '</td>';
        echo '<td class="t appointment">' . $text . '</td>';

        echo '</tr>';

		$num_pending = $wpdb->get_var("SELECT COUNT(ID) FROM " . $this->app_table . " WHERE status='pending' " );

        if ( $num_pending > 0 ) {
            $num = number_format_i18n( $num_pending );
            $text = _n( 'Deposit Appointment', 'Deposit Appointments', intval( $num_pending ) );
            if ( App_Roles::current_user_can( 'manage_options', App_Roles::CTX_DASHBOARD ) ) {
                $num = "<a href='admin.php?page=appointments&type=pending'>$num</a>";
                $text = "<a href='admin.php?page=appointments&type=pending'>$text</a>";
            }
            echo '<td class="first b b-appointment">' . $num . '</td>';
            echo '<td class="t appointment">' . $text . '</td>';

            echo '</tr>';
        }
	}

	// Enqeue js on admin pages
	function admin_scripts() {
		$screen = get_current_screen();
		$title = sanitize_title(__('Appointments', 'appointments'));

		$allow_profile = !empty($this->options['allow_worker_wh']) && 'yes' == $this->options['allow_worker_wh'];

		if (empty($screen->base) || (
			!preg_match('/(^|\b|_)appointments($|\b|_)/', $screen->base)
			&&
			!preg_match('/(^|\b|_)' . preg_quote($title, '/') . '($|\b|_)/', $screen->base) // Super-weird admin screen base being translatable!!!
			&&
			(!$allow_profile || !preg_match('/profile/', $screen->base) || !(defined('IS_PROFILE_PAGE') && IS_PROFILE_PAGE))
		)) return false;

		wp_enqueue_script( 'jquery-colorpicker', $this->plugin_url . '/js/colorpicker.js', array('jquery'), $this->version);
		wp_enqueue_script( 'jquery-datepick', $this->plugin_url . '/js/jquery.datepick.min.js', array('jquery'), $this->version);
		wp_enqueue_script( 'jquery-multiselect', $this->plugin_url . '/js/jquery.multiselect.min.js', array('jquery-ui-core','jquery-ui-widget', 'jquery-ui-position'), $this->version);
		// Make a locale check to update locale_error flag
		$date_check = $this->to_us( date_i18n( $this->safe_date_format(), strtotime('today') ) );

		// Localize datepick only if not defined otherwise
		if (
			!(defined('APP_FLAG_SKIP_DATEPICKER_L10N') && APP_FLAG_SKIP_DATEPICKER_L10N)
			&&
			$file = $this->datepick_localfile()
		) {
			//if ( !$this->locale_error ) wp_enqueue_script( 'jquery-datepick-local', $this->plugin_url . $file, array('jquery'), $this->version);
			wp_enqueue_script( 'jquery-datepick-local', $this->plugin_url . $file, array('jquery'), $this->version);
		}
		if ( empty($this->options["disable_js_check_admin"]) )
			wp_enqueue_script( 'app-js-check', $this->plugin_url . '/js/js-check.js', array('jquery'), $this->version);

		wp_enqueue_script("appointments-admin", $this->plugin_url . "/js/admin.js", array('jquery'), $this->version);
		wp_localize_script("appointments-admin", "_app_admin_data", array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'strings' => array(
				'preparing_export' => __('Preparing for export, please hold on...', 'appointments'),
			),
		));
		do_action('app-admin-admin_scripts');
	}
	// Enqueue css on settings page
	/**
	 * @deprecated since v1.4.2-BETA-2
	 */
/*
	function admin_css_settings() {
		wp_enqueue_style( 'jquery-colorpicker-css', $this->plugin_url . '/css/colorpicker.css', false, $this->version);
	}
*/
	// Enqueue css for all admin pages
	function admin_css() {
		wp_enqueue_style( "appointments-admin", $this->plugin_url . "/css/admin.css", false, $this->version );

		$screen = get_current_screen();
		$title = sanitize_title(__('Appointments', 'appointments'));

		$allow_profile = !empty($this->options['allow_worker_wh']) && 'yes' == $this->options['allow_worker_wh'];

		if (empty($screen->base) || (
			!preg_match('/(^|\b|_)appointments($|\b|_)/', $screen->base)
			&&
			!preg_match('/(^|\b|_)' . preg_quote($title, '/') . '($|\b|_)/', $screen->base) // Super-weird admin screen base being translatable!!!
			&&
			(!$allow_profile || !preg_match('/profile/', $screen->base) || !(defined('IS_PROFILE_PAGE') && IS_PROFILE_PAGE))
		)) return false;

		wp_enqueue_style( 'jquery-colorpicker-css', $this->plugin_url . '/css/colorpicker.css', false, $this->version);
		wp_enqueue_style( "jquery-datepick", $this->plugin_url . "/css/jquery.datepick.css", false, $this->version );
		wp_enqueue_style( "jquery-multiselect", $this->plugin_url . "/css/jquery.multiselect.css", false, $this->version );
		wp_enqueue_style( "jquery-ui-smoothness", $this->plugin_url . "/css/smoothness/jquery-ui-1.8.16.custom.css", false, $this->version );
		do_action('app-admin-admin_styles');
	}

	// Return datepick locale file if it exists
	// Since 1.0.6
	function datepick_localfile() {
		$locale = preg_replace('/_/', '-', get_locale());
		$locale = apply_filters( 'app_locale', $locale );

		if (function_exists('glob') && !(defined('APP_FLAG_NO_GLOB') && APP_FLAG_NO_GLOB)) {
			$filename = false;
			$all = glob("{$this->plugin_dir}/js/jquery.datepick-*.js");
			$full_match = preg_quote("{$locale}.js", '/');
			$partial_match = false;
			if (substr_count($locale, '-')) {
				list($main_locale, $rest) = explode('-', $locale, 2);
				if (!empty($main_locale)) $partial_match = preg_quote("{$main_locale}.js", '/');
			}

			foreach ($all as $file) {
				if (preg_match('/' . $full_match . '$/', $file)) {
					$filename = $file;
					break;
				} else if ($partial_match && preg_match('/' . $partial_match . '$/', $file)) {
					$filename = $file;
				}
			}
			return !empty($filename)
				? "/js/" . basename($filename)
				: false
			;
		} else {
			$file = '/js/jquery.datepick-'.$locale.'.js';
			if ( file_exists( $this->plugin_dir . $file ) )
				return $file;

			if ( substr_count( $locale, '-' ) ) {
				$l = explode( '-', $locale );
				$locale = $l[0];
				$file = '/js/jquery.datepick-'.$locale.'.js';
				if ( file_exists( $this->plugin_dir . $file ) )
					return $file;
			}
		}

		return false;
	}

	// Read and return local month names from datepick
	// Since 1.0.6.1
	function datepick_local_months() {
		if ( !$file = $this->datepick_localfile() )
			return false;

		if ( !$file_content = @file_get_contents(  $this->plugin_dir . $file ) )
			return false;

		$file_content = str_replace( array("\r","\n","\t"), '', $file_content );

		if ( preg_match( '/monthNames:(.*?)]/s', $file_content, $matches ) ) {
			$months = str_replace( array('[',']',"'",'"'), '', $matches[1] );
			return explode( ',', $months );
		}
		return false;
	}


	// Read and return abbrevated local month names from datepick
	// Since 1.0.6.3
	function datepick_abb_local_months() {
		if ( !$file = $this->datepick_localfile() )
			return false;

		if ( !$file_content = @file_get_contents(  $this->plugin_dir . $file ) )
			return false;

		$file_content = str_replace( array("\r","\n","\t"), '', $file_content );

		if ( preg_match( '/monthNamesShort:(.*?)]/s', $file_content, $matches ) ) {
			$months = str_replace( array('[',']',"'",'"'), '', $matches[1] );
			return explode( ',', $months );
		}
		return false;
	}

	/**
	 * Track javascript errors
	 * @since 1.0.3
	 */
	function js_error() {
		// TODO: Activate this again in future releases
		if  ( false && isset( $_POST['url'] ) ) {
			$this->error_url = $_POST['url'];
			$this->log( __('Javascript error on : ', 'appointments') . $this->error_url );
			die( json_encode( array( 'message'	=> '<div class="error"><p>' .
				sprintf( __('<b>[Appointments+]</b> You have at least one javascript error on %s.<br />Error message: %s<br />File: %s<br />Line: %s', 'appointments'), $this->error_url, @$_POST['errorMessage'], @$_POST['file'], @$_POST['lineNumber']) .
			'</p></div>')
			)
			);
		}
		die();
	}

	/**
	 * Check if there are more than one shortcodes for certain shortcode types
	 * @since 1.0.5
	 * @return bool
	 */
	function has_duplicate_shortcode( $post_id ) {
		$post = get_post( $post_id );
		if ( is_object( $post) && $post && strpos( $post->post_content, '[app_' ) !== false ) {
			if ( substr_count( $post->post_content, '[app_services' ) > 1 || substr_count( $post->post_content, '[app_service_providers' ) > 1
				|| substr_count( $post->post_content, '[app_confirmation' ) > 1 || substr_count( $post->post_content, '[app_paypal' ) > 1
				|| substr_count( $post->post_content, '[app_login' ) > 1 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if confirmation shortcode missing
	 * @since 1.2.5
	 * @return bool
	 */
	function confirmation_shortcode_missing( $post_id ) {
		$post = get_post( $post_id );
		if ( is_object( $post) && $post && strpos( $post->post_content, '[app_' ) !== false ) {
			if ( !substr_count( $post->post_content, '[app_confirmation' )
				&& ( substr_count( $post->post_content, '[app_monthly' ) || substr_count( $post->post_content, '[app_schedule' ) ) )
				return true;
		}
		return false;
	}

	/**
	 *	Warn admin if no services defined or duration is wrong
	 */
	function admin_notices() {

		$this->dismiss();

		global $wpdb, $current_user;
		$r = false;
		$results = $this->get_services();
		if ( !$results ) {
			echo '<div class="error"><p>' .
				__('<b>[Appointments+]</b> You must define at least once service.', 'appointments') .
			'</p></div>';
			$r = true;
		}
		else {
			foreach ( $results as $result ) {
				if ( $result->duration < $this->get_min_time() ) {
					echo '<div class="error"><p>' .
						__('<b>[Appointments+]</b> One of your services has a duration smaller than time base. Please visit Services tab and after making your corrections save new settings.', 'appointments') .
					'</p></div>';
					$r = true;
					break;
				}
				if ( $result->duration % $this->get_min_time() != 0 ) {
					echo '<div class="error"><p>' .
						__('<b>[Appointments+]</b> One of your services is not divisible by the time base. Please visit Services tab and after making your corrections save new settings.', 'appointments') .
					'</p></div>';
					$r = true;
					break;
				}
				if ( $result->duration > 1440 ) {
					echo '<div class="error"><p>' .
						__('<b>[Appointments+]</b> One of your services has a duration greater than 24 hours. Appointments+ does not support services exceeding 1440 minutes (24 hours). ', 'appointments') .
					'</p></div>';
					$r = true;
					break;
				}
				$dismissed = false;
				$dismiss_id = get_user_meta( $current_user->ID, 'app_dismiss', true );
				if ( $dismiss_id && $dismiss_id == session_id() )
					$dismissed = true;
				if ( $this->get_workers() && !$this->get_workers_by_service( $result->ID ) && !$dismissed ) {
					echo '<div class="error"><p>' .
						__('<b>[Appointments+]</b> One of your services does not have a service provider assigned. Delete services you are not using.', 'appointments') .
					'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a title="'.__('Dismiss this notice for this session', 'appointments').'" href="' . $_SERVER['REQUEST_URI'] . '&app_dismiss=1"><small>'.__('Dismiss', 'appointments').'</small></a>'.
					'</p></div>';
					$r = true;
					break;
				}
			}
		}
		if ( !$this->db_version || version_compare( $this->db_version, '1.2.2', '<' ) ) {
			echo '<div class="error"><p>' .
				__('<b>[Appointments+]</b> Appointments+ database tables need to be updated. Please deactivate and reactivate the plugin (DO NOT DELETE the plugin). You will not lose any saved information.', 'appointments') .
			'</p></div>';
			$r = true;
		}
		// Warn if Openid is not loaded
		$dismissed_g = false;
		$dismiss_id_g = get_user_meta( $current_user->ID, 'app_dismiss_google', true );
		if ( $dismiss_id_g && $dismiss_id_g == session_id() )
			$dismissed_g = true;
		if ( @$this->options['accept_api_logins'] && !@$this->openid && !$dismissed_g ) {
			echo '<div class="error"><p>' .
				__('<b>[Appointments+]</b> Either php curl is not installed or HTTPS wrappers are not enabled. Login with Google+ will not work.', 'appointments') .
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a title="'.__('Dismiss this notice for this session', 'appointments').'" href="' . $_SERVER['REQUEST_URI'] . '&app_dismiss_google=1"><small>'.__('Dismiss', 'appointments').'</small></a>'.
			'</p></div>';
			$r = true;
		}
		// Check for duplicate shortcodes for a visited page
		if ( isset( $_GET['post'] ) && $_GET['post'] && $this->has_duplicate_shortcode( $_GET['post'] ) ) {
			echo '<div class="error"><p>' .
			__('<b>[Appointments+]</b> More than one instance of services, service providers, confirmation, Paypal or login shortcodes on the same page may cause problems.</p>', 'appointments' ).
			'</div>';
		}

		// Check for missing confirmation shortcode
		$dismissed_c = false;
		$dismiss_id_c = get_user_meta( $current_user->ID, 'app_dismiss_confirmation_lacking', true );
		if ( $dismiss_id_c && $dismiss_id_c == session_id() )
			$dismissed_c = true;
		if ( !$dismissed_c && isset( $_GET['post'] ) && $_GET['post'] && $this->confirmation_shortcode_missing( $_GET['post'] ) ) {
			echo '<div class="error"><p>' .
				__('<b>[Appointments+]</b> Confirmation shortcode [app_confirmation] is always required to complete an appointment.', 'appointments') .
			'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a title="'.__('Dismiss this notice for this session', 'appointments').'" href="' . $_SERVER['REQUEST_URI'] . '&app_dismiss_confirmation_lacking=1"><small>'.__('Dismiss', 'appointments').'</small></a>'.
			'</p></div>';
			$r = true;
		}
		return $r;
	}

	/**
	 *	Dismiss warning messages for the current user for the session
	 *	@since 1.1.7
	 */
	function dismiss() {
		global $current_user;
		if ( isset( $_REQUEST['app_dismiss'] ) ) {
			update_user_meta( $current_user->ID, 'app_dismiss', session_id() );
			?><div class="updated fade"><p><?php _e('Notice dismissed.', 'appointments'); ?></p></div><?php
		}
		if ( isset( $_REQUEST['app_dismiss_google'] ) ) {
			update_user_meta( $current_user->ID, 'app_dismiss_google', session_id() );
			?><div class="updated fade"><p><?php _e('Notice dismissed.', 'appointments'); ?></p></div><?php
		}
		if ( isset( $_REQUEST['app_dismiss_confirmation_lacking'] ) ) {
			update_user_meta( $current_user->ID, 'app_dismiss_confirmation_lacking', session_id() );
			?><div class="updated fade"><p><?php _e('Notice dismissed.', 'appointments'); ?></p></div><?php
		}
	}

	/**
	 *	Admin pages init stuff, save settings
	 *
	 */
	function admin_init() {

		if ( !session_id() )
			@session_start();

		$page = add_menu_page('Appointments', __('Appointments','appointments'), App_Roles::get_capability('manage_options', App_Roles::CTX_PAGE_APPOINTMENTS),  'appointments', array(&$this,'appointment_list'),'div');
		add_submenu_page('appointments', __('Transactions','appointments'), __('Transactions','appointments'), App_Roles::get_capability('manage_options', App_Roles::CTX_PAGE_TRANSACTIONS), "app_transactions", array(&$this,'transactions'));
		add_submenu_page('appointments', __('Settings','appointments'), __('Settings','appointments'), App_Roles::get_capability('manage_options', App_Roles::CTX_PAGE_SETTINGS), "app_settings", array(&$this,'settings'));
		add_submenu_page('appointments', __('Shortcodes','appointments'), __('Shortcodes','appointments'), App_Roles::get_capability('manage_options', App_Roles::CTX_PAGE_SHORTCODES), "app_shortcodes", array(&$this,'shortcodes_page'));
		add_submenu_page('appointments', __('FAQ','appointments'), __('FAQ','appointments'), App_Roles::get_capability('manage_options', App_Roles::CTX_PAGE_FAQ), "app_faq", array(&$this,'faq_page'));
		// Add datepicker to appointments page
		add_action( "admin_print_scripts-$page", array( &$this, 'admin_scripts' ) );

		do_action('app-admin-admin_pages_added', $page);

		if ( isset($_POST["action_app"]) && !wp_verify_nonce($_POST['app_nonce'],'update_app_settings') ) {
			add_action( 'admin_notices', array( &$this, 'warning' ) );
			return;
		}

		// Read Location, Service, Worker
		$this->get_lsw();
		global $wpdb;

		if ( isset($_POST["action_app"]) && 'save_general' == $_POST["action_app"] ) {
			$this->options["min_time"]					= $_POST["min_time"];
			$this->options["additional_min_time"]		= trim( $_POST["additional_min_time"] );
			$this->options["admin_min_time"]			= $_POST["admin_min_time"];
			$this->options["app_lower_limit"]			= trim( $_POST["app_lower_limit"] );
			$this->options["app_limit"]					= trim( $_POST["app_limit"] );
			$this->options["clear_time"]				= trim( $_POST["clear_time"] );
			$this->options["spam_time"]					= trim( $_POST["spam_time"] );
			$this->options["auto_confirm"]				= $_POST["auto_confirm"];
			$this->options["allow_worker_wh"]			= $_POST["allow_worker_wh"];
			$this->options["allow_worker_confirm"]		= $_POST["allow_worker_confirm"];
			$this->options["allow_overwork"]			= $_POST["allow_overwork"];
			$this->options["allow_overwork_break"]		= $_POST["allow_overwork_break"];
			$this->options["dummy_assigned_to"]			= !$this->is_dummy( @$_POST["dummy_assigned_to"] ) ? @$_POST["dummy_assigned_to"] : 0;

			$this->options["login_required"]			= $_POST["login_required"];
			$this->options["accept_api_logins"]			= isset( $_POST["accept_api_logins"] );
			$this->options["facebook-no_init"]			= isset( $_POST["facebook-no_init"] );
			$this->options['facebook-app_id']			= trim( $_POST['facebook-app_id'] );
			$this->options['twitter-app_id']			= trim( $_POST['twitter-app_id'] );
			$this->options['twitter-app_secret']		= trim( $_POST['twitter-app_secret'] );
			$this->options['google-client_id']			= trim( $_POST['google-client_id'] );

			$this->options["app_page_type"]				= $_POST["app_page_type"];
			$this->options["show_legend"]				= $_POST["show_legend"];
			$this->options["color_set"]					= $_POST["color_set"];
			foreach ( $this->get_classes() as $class=>$name ) {
				$this->options[$class."_color"]			= $_POST[$class."_color"];
			}
			$this->options["ask_name"]					= isset( $_POST["ask_name"] );
			$this->options["ask_email"]					= isset( $_POST["ask_email"] );
			$this->options["ask_phone"]					= isset( $_POST["ask_phone"] );
			$this->options["ask_phone"]					= isset( $_POST["ask_phone"] );
			$this->options["ask_address"]				= isset( $_POST["ask_address"] );
			$this->options["ask_city"]					= isset( $_POST["ask_city"] );
			$this->options["ask_note"]					= isset( $_POST["ask_note"] );
			$this->options["additional_css"]			= trim( stripslashes_deep($_POST["additional_css"]) );

			$this->options["payment_required"]			= $_POST["payment_required"];
			$this->options["percent_deposit"]			= trim( str_replace( '%', '', $_POST["percent_deposit"] ) );
			$this->options["fixed_deposit"]				= trim( str_replace( $this->options["currency"], '', $_POST["fixed_deposit"] ) );

			/*
			 * Membership plugin is replaced by Membership2. Old options are
			 * only saved when the depreacted Membership plugin is still active.
			 */
			if ( class_exists( 'M_Membership' ) ) {
				$this->options['members_no_payment']	= isset( $_POST['members_no_payment'] ); // not used??
				$this->options['members_discount']		= trim( str_replace( '%', '', $_POST['members_discount'] ) );
				$this->options['members']				= maybe_serialize( @$_POST["members"] );
			}

			$this->options['currency'] 					= $_POST['currency'];
			$this->options['mode'] 						= $_POST['mode'];
			$this->options['merchant_email'] 			= trim( $_POST['merchant_email'] );
			$this->options['return'] 					= $_POST['return'];
			$this->options['allow_free_autoconfirm'] 	= !empty($_POST['allow_free_autoconfirm']);

			$this->options["send_confirmation"]			= $_POST["send_confirmation"];
			$this->options["send_notification"]			= @$_POST["send_notification"];
			$this->options["confirmation_subject"]		= stripslashes_deep( $_POST["confirmation_subject"] );
			$this->options["confirmation_message"]		= stripslashes_deep( $_POST["confirmation_message"] );
			$this->options["send_reminder"]				= $_POST["send_reminder"];
			$this->options["reminder_time"]				= str_replace( " ", "", $_POST["reminder_time"] );
			$this->options["send_reminder_worker"]		= $_POST["send_reminder_worker"];
			$this->options["reminder_time_worker"]		= str_replace( " ", "", $_POST["reminder_time_worker"] );
			$this->options["reminder_subject"]			= stripslashes_deep( $_POST["reminder_subject"] );
			$this->options["reminder_message"]			= stripslashes_deep( $_POST["reminder_message"] );

			$this->options["send_removal_notification"] = $_POST["send_removal_notification"];
			$this->options["removal_notification_subject"] = stripslashes_deep( $_POST["removal_notification_subject"] );
			$this->options["removal_notification_message"] = stripslashes_deep( $_POST["removal_notification_message"] );

			$this->options["log_emails"]				= $_POST["log_emails"];

			$this->options['use_cache'] 				= $_POST['use_cache'];
			$this->options['disable_js_check_admin']	= isset( $_POST['disable_js_check_admin'] );
			$this->options['disable_js_check_frontend']	= isset( $_POST['disable_js_check_frontend'] );

			$this->options['use_mp']	 				= isset( $_POST['use_mp'] );
			$this->options["app_page_type_mp"]			= @$_POST["app_page_type_mp"];

			$this->options['allow_cancel'] 				= @$_POST['allow_cancel'];
			$this->options['cancel_page'] 				= @$_POST['cancel_page'];

			$this->options["records_per_page"]			= (int)trim( @$_POST["records_per_page"] );

			$this->options = apply_filters('app-options-before_save', $this->options);

			$saved = false;
			if ( update_option( 'appointments_options', $this->options ) ) {
				$saved = true;
				if ( 'yes' == $this->options['use_cache'] )
					add_action( 'admin_notices', array ( &$this, 'saved_cleared' ) );
				else
					add_action( 'admin_notices', array ( &$this, 'saved' ) );
			}

			// Flush cache
			if ( isset( $_POST["force_flush"] ) || $saved ) {
				$this->flush_cache();
				if ( isset( $_POST["force_flush"] ) )
					add_action( 'admin_notices', array ( &$this, 'cleared' ) );
			}

			if (isset($_POST['make_an_appointment']) || isset($_POST['make_an_appointment_product'])) {
				$this->_create_pages();
			}

			// Redirecting when saving options
			if ($saved) {
				wp_redirect(add_query_arg('saved', 1));
				die;
			}
		}

		$result = $updated = $inserted = false;
		// Save Working Hours
		if ( isset($_POST["action_app"]) && 'save_working_hours' == $_POST["action_app"] ) {
			$location = (int)$_POST['location'];
			foreach ( array( 'closed', 'open' ) as $stat ) {
				$query = $this->db->prepare(
					"SELECT COUNT(*) FROM {$this->wh_table} WHERE location=%d AND worker=%d AND status=%s",
					$location, $this->worker, $stat
				);

				$count = $wpdb->get_var($query);

				if ( $count > 0 ) {
					$r = $wpdb->update( $this->wh_table,
								array( 'hours'=>serialize($_POST[$stat]), 'status'=>$stat ),
								array( 'location'=>$location, 'worker'=>$this->worker, 'status'=>$stat ),
								array( '%s', '%s' ),
								array( '%d', '%d', '%s' )
							);
					if ( $r )
						$result = true;
				}
				else {
					$r = $wpdb->insert( $this->wh_table,
								array( 'location'=>$location, 'worker'=>$this->worker, 'hours'=>serialize($_POST[$stat]), 'status'=>$stat ),
								array( '%d', '%d', '%s', '%s' )
							);
					if ( $r )
						$result = true;

				}
				if ( $result )
					add_action( 'admin_notices', array ( &$this, 'saved' ) );
			}
		}
		// Save Exceptions
		if ( isset($_POST["action_app"]) && 'save_exceptions' == $_POST["action_app"] ) {
			$location = (int)$_POST['location'];
			foreach ( array( 'closed', 'open' ) as $stat ) {
				$count = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->exceptions_table} WHERE location=%d AND worker=%d AND status=%s",
					$location, $this->worker, $stat
				));

				if ( $count > 0 ) {
					$r = $wpdb->update( $this->exceptions_table,
								array(
										'days'		=> $this->_sort( $_POST[$stat]["exceptional_days"] ),
										'status'	=> $stat
									),
								array(
									'location'	=> $location,
									'worker'	=> $this->worker,
									'status'	=> $stat ),
								array( '%s', '%s' ),
								array( '%d', '%d', '%s' )
							);
					if ( $r )
						$result = true;
				}
				else {
					$r = $wpdb->insert( $this->exceptions_table,
								array( 'location'	=> $location,
										'worker'	=> $this->worker,
										'days'		=> $this->_sort( $_POST[$stat]["exceptional_days"] ),
										'status'	=> $stat
									),
								array( '%d', '%d', '%s', '%s' )
								);
					if ( $r )
						$result = true;
				}
				if ( $result )
					add_action( 'admin_notices', array ( &$this, 'saved' ) );
			}
		}
		// Save Services
		if ( isset($_POST["action_app"]) && 'save_services' == $_POST["action_app"] && is_array( $_POST["services"] ) ) {
			do_action('app-services-before_save');
			foreach ( $_POST["services"] as $ID=>$service ) {
				if ( '' != trim( $service["name"] ) ) {
					// Update or insert?
					$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(ID) FROM {$this->services_table} WHERE ID=%d", $ID));
					if ( $count ) {
						$r = $wpdb->update( $this->services_table,
									array(
										'name'		=> $service["name"],
										'capacity'	=> (int)$service["capacity"],
										'duration'	=> $service["duration"],
										'price'		=> preg_replace("/[^0-9,.]/", "", $service["price"]),
										'page'		=> $service["page"]
										),
									array( 'ID'		=> $ID ),
									array( '%s', '%d', '%d','%s','%d' )
								);
						if ( $r )
							$result = true;
					}
					else {
						//if ((int)$this->db->get_var("SELECT COUNT(ID) FROM {$this->services_table}") >= 2) { /* ... */ }
						$r = $wpdb->insert( $this->services_table,
									array(
										'ID'		=> $ID,
										'name'		=> $service["name"],
										'capacity'	=> (int)$service["capacity"],
										'duration'	=> $service["duration"],
										'price'		=> preg_replace("/[^0-9,.]/", "", $service["price"]),
										'page'		=> $service["page"]
										),
									array( '%d', '%s', '%d', '%d','%s','%d' )
									);
						if ( $r )
							$result = true;
					}
					do_action('app-services-service-updated', $ID);
				}
				else {
					// Entering an empty name means deleting of a service
					$r = $wpdb->query(
						$wpdb->prepare("DELETE FROM {$this->services_table} WHERE ID=%d LIMIT 1", $ID)
					);
					// Remove deleted service also from workers table
					$r1 = $wpdb->query(
						$wpdb->prepare("UPDATE {$this->workers_table} SET services_provided = REPLACE(services_provided,':%d:','') ", $ID)
						//"UPDATE ". $this->workers_table . " SET services_provided = REPLACE(services_provided,':".$ID.":','') "
					);
					if ( $r || $r1 )
						$result = true;
				}
			}
			if( $result )
				add_action( 'admin_notices', array ( &$this, 'saved' ) );
		}
		// Save Workers
		if ( isset($_POST["action_app"]) && 'save_workers' == $_POST["action_app"] && is_array( $_POST["workers"] ) ) {
			foreach ( $_POST["workers"] as $worker ) {
				$ID = $worker["user"];
				if ( $ID && !empty ( $worker["services_provided"] ) ) {
					$inserted = false;
					// Does the worker have already a record?
					$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->workers_table} WHERE ID=%d", $ID));
					if ( $count ) {
						if ( !$this->db_version )
							$r = $wpdb->update( $this->workers_table,
										array(
											'price'				=> preg_replace("/[^0-9,.]/", "", $worker["price"]),
											'services_provided'	=> $this->_implode( $worker["services_provided"] ),
											'page'				=> $worker["page"]
											),
										array( 'ID'				=> $worker["user"] ),
										array( '%s', '%s','%d' )
										);
						else
							$r = $wpdb->update( $this->workers_table,
										array(
											'price'				=> preg_replace("/[^0-9,.]/", "", $worker["price"]),
											'services_provided'	=> $this->_implode( $worker["services_provided"] ),
											'page'				=> $worker["page"],
											'dummy'				=> isset( $worker["dummy"] )
											),
										array( 'ID'				=> $worker["user"] ),
										array( '%s', '%s','%d', '%s' )
										);
						if ( $r )
							$updated = true;
					}
					else {
						if ( !$this->db_version ) {
							$r = $wpdb->insert(
								$this->workers_table,
								array(
									'ID'				=> $worker["user"],
									'price'				=> preg_replace("/[^0-9,.]/", "", $worker["price"]),
									'services_provided'	=> $this->_implode( $worker["services_provided"] ),
									'page'				=> $worker["page"]
								),
								array( '%d', '%s', '%s','%d' )
							);
						} else {
							$r = $wpdb->insert(
								$this->workers_table,
								array(
									'ID'				=> $worker["user"],
									'price'				=> preg_replace("/[^0-9,.]/", "", $worker["price"]),
									'services_provided'	=> $this->_implode( $worker["services_provided"] ),
									'page'				=> $worker["page"],
									'dummy'				=> isset ( $worker["dummy"] )
								),
								array( '%d', '%s', '%s', '%d', '%s' )
							);
						}
						if ( $r ) {
							// Insert the default working hours to the worker's working hours
							foreach ( array('open', 'closed') as $stat ) {
								$result_wh = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->wh_table} WHERE location=0 AND service=0 AND status=%s", $stat), ARRAY_A );
								if ( $result_wh != null ) {
									$result_wh["ID"] = 'NULL';
									$result_wh["worker"] = $ID;
									$wpdb->insert( $this->wh_table,
													$result_wh
												);
								}
							}
							// Insert the default holidays to the worker's holidays
							foreach ( array('open', 'closed') as $stat ) {
								$result_wh = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->exceptions_table} WHERE location=0 AND service=0 AND status=%s", $stat), ARRAY_A );
								if ( $result_wh != null ) {
									$result_wh["ID"] = 'NULL';
									$result_wh["worker"] = $ID;
									$wpdb->insert(
										$this->exceptions_table,
										$result_wh
									);
								}
							}
							$inserted = true;
						}
					}
					do_action('app-workers-worker-updated', $ID);
				}
				// Entering an empty service name means deleting of a worker
				else if ( $ID ) {
					//$r = $wpdb->query( "DELETE FROM " . $this->workers_table . " WHERE ID=".$ID." LIMIT 1 " );
					//$r1 = $wpdb->query( "DELETE FROM " . $this->wh_table . " WHERE worker=".$ID." " );
					//$r2 = $wpdb->query( "DELETE FROM " . $this->exceptions_table . " WHERE worker=".$ID." " );
					$r = $wpdb->query( $wpdb->prepare("DELETE FROM {$this->workers_table} WHERE ID=%d LIMIT 1", $ID) );
					$r1 = $wpdb->query( $wpdb->prepare("DELETE FROM {$this->wh_table} WHERE worker=%d", $ID) );
					$r2 = $wpdb->query( $wpdb->prepare("DELETE FROM {$this->exceptions_table} WHERE worker=%d", $ID) );
					if ( $r || $r1 || $r2 )
						$result = true;
				}
			}
			if( $result || $updated || $inserted )
				add_action( 'admin_notices', array ( &$this, 'saved' ) );
		}

		// Delete removed app records
		if ( isset($_POST["delete_removed"]) && 'delete_removed' == $_POST["delete_removed"]
			&& isset( $_POST["app"] ) && is_array( $_POST["app"] ) ) {
			$q = '';
			foreach ( $_POST["app"] as $app_id ) {
				$q .= " ID=". (int)$app_id. " OR";
			}
			$q = rtrim( $q, " OR" );
			$result = $wpdb->query( "DELETE FROM " . $this->app_table . " WHERE " . $q . " " );
			if ( $result ) {
				global $current_user;
				$userdata = get_userdata( $current_user->ID );
				add_action( 'admin_notices', array ( &$this, 'deleted' ) );
				do_action( 'app_deleted',  $_POST["app"] );
				$this->log( sprintf( __('Appointment(s) with id(s):%s deleted by user:%s', 'appointments' ),  implode( ', ', $_POST["app"] ), $userdata->user_login ) );
			}
		}

		// Bulk status change
		if ( isset( $_POST["app_status_change"] ) && $_POST["app_new_status"] && isset( $_POST["app"] ) && is_array( $_POST["app"] ) ) {
			$q = '';
			foreach ( $_POST["app"] as $app_id ) {
				$q .= " ID=". (int)$app_id. " OR";
			}
			$q = rtrim( $q, " OR" );

			// Make a new status re-check here - It should be in status map
			$new_status = esc_sql($_POST["app_new_status"]);
			if ( array_key_exists( $new_status, $this->get_statuses() ) ) {
				$result = $wpdb->query( "UPDATE " . $this->app_table . " SET status='".$new_status."' WHERE " . $q . " " );
				if ( $result ) {
					global $current_user;
					$userdata = get_userdata( $current_user->ID );
					add_action( 'admin_notices', array ( &$this, 'updated' ) );
					do_action( 'app_bulk_status_change',  $_POST["app"] );
					$this->log( sprintf( __('Status of Appointment(s) with id(s):%s changed to %s by user:%s', 'appointments' ),  implode( ', ', $_POST["app"] ), $new_status, $userdata->user_login ) );

					if ( is_object( $this->gcal_api ) ) {
						// If deleted, remove these from GCal too
						if ( 'removed' == $new_status ) {
							foreach ( $_POST["app"] as $app_id ) {
								$this->gcal_api->delete( $app_id );
								$this->send_removal_notification($app_id);
							}
						}
						// If confirmed or paid, add these to GCal
						else if (is_object($this->gcal_api) && $this->gcal_api->is_syncable_status($new_status)) {
							foreach ( $_POST["app"] as $app_id ) {
								$this->gcal_api->update( $app_id );
								// Also send out an email
								if (!empty($this->options["send_confirmation"]) && 'yes' == $this->options["send_confirmation"]) {
									$this->send_confirmation($app_id);
								}
							}
						}
					}
				}
			}
		}

		// Determine if we shall flush cache
		if ( ( isset( $_POST["action_app"] ) ) && ( $result || $updated || $inserted ) ||
			( isset( $_POST["delete_removed"] ) && 'delete_removed' == $_POST["delete_removed"] ) ||
			( isset( $_POST["app_status_change"] ) && $_POST["app_new_status"] ) )
			// As it means any setting is saved, lets clear cache
			$this->flush_cache();
	}

	private function _create_pages () {
		// Add an appointment page
		if ( isset( $_POST["make_an_appointment"] ) ) {
			$tpl = !empty($_POST['app_page_type']) ? $_POST['app_page_type'] : false;
			wp_insert_post(
					array(
						'post_title'	=> 'Make an Appointment',
						'post_status'	=> 'publish',
						'post_type'		=> 'page',
						'post_content'	=> App_Template::get_default_page_template($tpl)
					)
			);
		}

		// Add an appointment product page
		if ( isset( $_POST["make_an_appointment_product"] ) && $this->marketpress_active ) {
			$tpl = !empty($_POST['app_page_type_mp']) ? $_POST['app_page_type_mp'] : false;
			$post_id = wp_insert_post(
					array(
						'post_title'	=> 'Appointment',
						'post_status'	=> 'publish',
						'post_type'		=> 'product',
						'post_content'	=> App_Template::get_default_page_template($tpl)
					)
			);
			if ( $post_id ) {
				// Add a download link, so that app will be a digital product
				$file = get_post_meta($post_id, 'mp_file', true);
				if ( !$file ) add_post_meta( $post_id, 'mp_file', get_permalink( $post_id) );

				// MP requires at least 2 variations, so we add a dummy one
				add_post_meta( $post_id, 'mp_var_name', array( 0 ) );
				add_post_meta( $post_id, 'mp_sku', array( 0 ) );
				add_post_meta( $post_id, 'mp_price', array( 0 ) );
			}
		}
	}

	function shortcodes_page () {
		?>
<div class="wrap">
	<div class="icon32" style="margin:10px 0 0 0"><img src="<?php echo $this->plugin_url . '/images/general.png'; ?>" /></div>
	<h2><?php echo __('Appointments+ Shortcodes','appointments'); ?></h2>
	<div class="metabox-holder columns-2">
		<?php if (file_exists(APP_PLUGIN_DIR . '/includes/support/app-shortcodes.php')) include(APP_PLUGIN_DIR . '/includes/support/app-shortcodes.php'); ?>
	</div>
</div>
		<?php
	}

	function faq_page () {
		?>
<div class="wrap">
	<div class="icon32" style="margin:10px 0 0 0"><img src="<?php echo $this->plugin_url . '/images/general.png'; ?>" /></div>
	<h2><?php echo __('Appointments+ FAQ','appointments'); ?></h2>
	<?php if (file_exists(APP_PLUGIN_DIR . '/includes/support/app-faq.php')) include(APP_PLUGIN_DIR . '/includes/support/app-faq.php'); ?>
</div>
		<?php
	}

	/**
	 *	Sorts a comma delimited string
	 *	@since 1.2
	 */
	function _sort( $input ) {
		if ( strpos( $input, ',') === false )
			return $input;
		$temp = explode( ',', $input );
		sort( $temp );
		return implode( ',', $temp );
	}

	/**
	 *	Packs an array into a string with : as glue
	 */
	function _implode( $input ) {
		if ( !is_array( $input ) || empty( $input ) )
			return false;
		return ':'. implode( ':', array_filter( $input ) ) . ':';
	}

	/**
	 *	Packs a string into an array assuming : as glue
	 */
	function _explode( $input ){
		if ( !is_string( $input ) )
			return false;
		return array_filter( explode( ':' , ltrim( $input , ":") ) );
	}

	/**
	 * Deletes a worker's database records in case he is deleted
	 * @since 1.0.4
	 */
	function delete_user( $ID ) {
		if ( !$ID )
			return;

		global $wpdb;
		//$r = $wpdb->query( "DELETE FROM " . $this->workers_table . " WHERE ID=".$ID." LIMIT 1 " );
		//$r1 = $wpdb->query( "DELETE FROM " . $this->wh_table . " WHERE worker=".$ID." " );
		//$r2 = $wpdb->query( "DELETE FROM " . $this->exceptions_table . " WHERE worker=".$ID." " );
		$r = $wpdb->query( $wpdb->prepare("DELETE FROM {$this->workers_table} WHERE ID=%d LIMIT 1", $ID) );
		$r1 = $wpdb->query( $wpdb->prepare("DELETE FROM {$this->wh_table} WHERE worker=%d", $ID) );
		$r2 = $wpdb->query( $wpdb->prepare("DELETE FROM {$this->exceptions_table} WHERE worker=%d", $ID) );

		// Also modify app table
		$r3 = $wpdb->update(
			$this->app_table,
			array( 'worker'	=>	0 ),
			array( 'worker'	=> $ID )
		);

		if ( $r || $r1 || $r2 || $r3 )
			$this->flush_cache();
	}

	/**
	 * Removes a worker's database records in case he is removed from that blog
	 * @param ID: user ID
	 * @param blog_id: ID of the blog that user has been removed from
	 * @since 1.2.3
	 */
	function remove_user_from_blog( $ID, $blog_id ) {
		if ( !$ID || !$blog_id )
			return;

		global $wpdb;

		// Let's be safe
		if ( !method_exists( $wpdb, 'get_blog_prefix' ) )
			return;

		$prefix = $wpdb->get_blog_prefix( $blog_id );

		if ( !$prefix )
			return;

		//$r = $wpdb->query( "DELETE FROM " . $prefix . "app_workers WHERE ID=".$ID." LIMIT 1 " );
		//$r1 = $wpdb->query( "DELETE FROM " . $prefix . "app_working_hours WHERE worker=".$ID." " );
		//$r2 = $wpdb->query( "DELETE FROM " . $prefix . "app_exceptions WHERE worker=".$ID." " );
		$r = $wpdb->query( $wpdb->prepare("DELETE FROM {$prefix}app_workers WHERE ID=%d LIMIT 1", $ID) );
		$r1 = $wpdb->query( $wpdb->prepare("DELETE FROM {$prefix}app_working_hours WHERE worker=%d", $ID) );
		$r2 = $wpdb->query( $wpdb->prepare("DELETE FROM {$prefix}app_exceptions WHERE worker=%d", $ID) );

		// Also modify app table
		$r3 = $wpdb->update(
			$prefix . "app_appointments",
			array( 'worker'	=>	0 ),
			array( 'worker'	=> $ID )
		);

		if ( $r || $r1 || $r2 || $r3 )
			$this->flush_cache();
	}

	/**
	 * Prints "Cache cleared" message on top of Admin page
	 */
	function cleared( ) {
		echo '<div class="updated fade"><p><b>[Appointments+]</b> '. __('Cache cleared.','appointments').'</p></div>';
	}

	/**
	 * Prints "settings saved and cache cleared" message on top of Admin page
	 * @since 1.1.7
	 */
	function saved_cleared( ) {
		echo '<div class="updated fade"><p><b>[Appointments+]</b> '. __('Settings saved and cache cleared.','appointments').'</p></div>';
	}

	/**
	 * Prints "saved" message on top of Admin page
	 */
	function saved( ) {
		echo '<div class="updated fade"><p><b>[Appointments+]</b> '. __('Settings saved.','appointments').'</p></div>';
	}

	/**
	 * Prints "deleted" message on top of Admin page
	 */
	function deleted( ) {
		echo '<div class="updated fade"><p><b>[Appointments+]</b> '. __('Selected record(s) deleted.','appointments').'</p></div>';
	}

	/**
	 * Prints "updated" message on top of Admin page
	 */
	function updated( ) {
		echo '<div class="updated fade"><p><b>[Appointments+]</b> '. __('Selected record(s) updated.','appointments').'</p></div>';
	}

	/**
	 * Prints warning message on top of Admin page
	 */
	function warning( ) {
		echo '<div class="updated fade"><p><b>[Appointments+] '. __('You are not authorised to do this.','appointments').'</b></p></div>';
	}

	/**
	 * Admin settings HTML code
	 */
	function settings() {

		if (!App_Roles::current_user_can('manage_options', App_Roles::CTX_PAGE_SETTINGS)) {
			wp_die( __('You do not have sufficient permissions to access this page.','appointments') );
		}
		$this->get_lsw();
		global $wpdb;
	?>
		<div class="wrap">
		<div class="icon32" style="margin:10px 0 0 0"><img src="<?php echo $this->plugin_url . '/images/general.png'; ?>" /></div>
		<h2><?php echo __('Appointments+ Settings','appointments'); ?></h2>
		<h3 class="nav-tab-wrapper">
			<?php
			$tab = ( !empty($_GET['tab']) ) ? $_GET['tab'] : 'main';

			$tabs = array(
				'gcal'			=> __('Google Calendar', 'appointments'),
				'working_hours'	=> __('Working Hours', 'appointments'),
				//'line_three' =>  __('Working Hours For 3 line Provider', 'appointments'),
				'exceptions'	=> __('Exceptions', 'appointments'),
				'services'      => __('Services', 'appointments'),
				'workers' 	    => __('Service Providers', 'appointments'),
				//'shortcodes'    => __('Shortcodes', 'appointments'),
				'addons'		=> __('Add-ons', 'appointments'),
				'log'    		=> __('Logs', 'appointments'),
				//'faq'    		=> __('FAQ', 'appointments'),
			);

			$tabhtml = array();

			// If someone wants to remove or add a tab
			$tabs = apply_filters( 'appointments_tabs', $tabs );

			$class = ( 'main' == $tab ) ? ' nav-tab-active' : '';
			$tabhtml[] = '	<a href="' . admin_url( 'admin.php?page=app_settings' ) . '" class="nav-tab'.$class.'">' . __('General', 'appointments') . '</a>';

			foreach ( $tabs as $stub => $title ) {
				$class = ( $stub == $tab ) ? ' nav-tab-active' : '';
				$tabhtml[] = '	<a href="' . admin_url( 'admin.php?page=app_settings&amp;tab=' . $stub ) . '" class="nav-tab'.$class.'" id="app_tab_'.$stub.'">'.$title.'</a>';
			}

			echo implode( "\n", $tabhtml );
			?>
		</h3>
		<div class="clear"></div>
			<?php App_Template::admin_settings_tab($tab); ?>
		</div>
	<?php
	}

	function delete_log(){
		// check_ajax_referer( );
		if ( !unlink( $this->log_file ) )
			die( json_encode( array('error' => esc_js( __('Log file could not be deleted','appointments')))));
		die();
	}

	/**
	 *	Add a service
	 *  @param php: True if this will be used in first call, false if this is js
	 *  @param service: Service object that will be displayed (only when php is true)
	 */
	function add_service( $php=false, $service='' ) {
		if ( $php ) {
			if ( is_object($service)) {
				$n = $service->ID;
				$name = $service->name;
				$capacity = $service->capacity;
				$price = $service->price;
			 }
			 else return;
		}
		else {
			$n = "'+n+'";
			$name = '';
			$capacity = '0';
			$price = '';
		}

		$min_time = $this->get_min_time();

		$html = '';
		$html .= '<tr><td>';
		$html .= $n;
		$html .= '</td><td>';
		$html .= '<input style="width:100%" type="text" name="services['.$n.'][name]" value="'.stripslashes( $name ).'" />' . apply_filters('app-settings-services-service-name', '', $n);
		$html .= '</td><td>';
		$html .= '<input style="width:90%" type="text" name="services['.$n.'][capacity]" value="'.$capacity.'" />';
		$html .= '</td><td>';
		$html .= '<select name="services['.$n.'][duration]" >';
		$k_max = apply_filters( 'app_selectable_durations', min( 24, (int)(1440/$min_time) ) );
		for ( $k=1; $k<=$k_max; $k++ ) {
			if ( $php && is_object( $service ) && $k * $min_time == $service->duration )
				$html .= '<option selected="selected">'. ($k * $min_time) . '</option>';
			else
				$html .= '<option>'. ($k * $min_time) . '</option>';
		}
		$html .= '</select>';
		$html .= '</td><td>';
		$html .= '<input style="width:90%" type="text" name="services['.$n.'][price]" value="'.$price.'" />';
		$html .= '</td><td>';
		$pages = apply_filters('app-service_description_pages-get_list', array());
		if (empty($pages)) $pages = get_pages( apply_filters('app_pages_filter',array() ) );
		$html .= '<select name="services['.$n.'][page]" >';
		$html .= '<option value="0">'. __('None','appointments') .'</option>';
		foreach( $pages as $page ) {
			if ( $php )
				$title = esc_attr( $page->post_title );
			else
				$title = esc_js( $page->post_title );

			if ( $php && is_object( $service ) && $service->page == $page->ID )
				$html .= '<option value="'.$page->ID.'" selected="selected">'. $title . '</option>';
			else
				$html .= '<option value="'.$page->ID.'">'. $title . '</option>';
		}
		$html .= '</select>';
		$html .= '</td></tr>';
		return $html;
	}

	/**
	 *	Add a worker
	 *  @param php: True if this will be used in first call, false if this is js
	 *  @param worker: Worker object that will be displayed (only when php is true)
	 */
	function add_worker( $php=false, $worker='' ) {
		if ( $php ) {
			if ( is_object($worker)) {
				$k = $worker->ID;
				if ( $this->is_dummy( $worker->ID ) )
					$dummy = ' checked="checked"';
				else
					$dummy = "";
				$price = $worker->price;
				$workers = wp_dropdown_users( array( 'echo'=>0, 'show'=>'user_login', 'selected' => $worker->ID, 'name'=>'workers['.$k.'][user]', 'exclude'=>apply_filters('app_filter_providers', null) ) );
			}
			 else return;
		}
		else {
			$k = "'+k+'";
			$price = '';
			$dummy = '';
			$workers =str_replace( array("\t","\n","\r"), "", str_replace( array("'", "&#039;"), array('"', "'"), wp_dropdown_users( array ( 'echo'=>0, 'show'=>'user_login', 'include'=>0, 'name'=>'workers['.$k.'][user]', 'exclude'=>apply_filters('app_filter_providers', null)) ) ) );
		}
		global $wpdb;

		$html = '';
		$html .= '<tr><td>';
		$html .= $k;
		$html .= '</td><td>';
		$html .= $workers  . apply_filters('app-settings-workers-worker-name', '', (is_object($worker) ? $worker->ID : false), $worker);
		$html .= '</td><td>';
		$html .= '<input type="checkbox" name="workers['.$k.'][dummy]" '.$dummy.' />';
		$html .= '</td><td>';
		$html .= '<input type="text" name="workers['.$k.'][price]" style="width:80%" value="'.$price.'" />';
		$html .= '</td><td>';
		$services = $this->get_services();
		if ( $services ) {
			if ( $php && is_object( $worker ) )
				$services_provided = $this->_explode( $worker->services_provided );
			else
				$services_provided = false;
			$html .= '<select class="add_worker_multiple" style="width:280px" multiple="multiple" name="workers['.$k.'][services_provided][]" >';
			foreach ( $services as $service ) {
				if ( $php )
					$title = stripslashes( $service->name );
				else
					$title = esc_js( $service->name );

				if ( is_array( $services_provided ) && in_array( $service->ID, $services_provided ) )
					$html .= '<option value="'. $service->ID . '" selected="selected">'. $title . '</option>';
				else
					$html .= '<option value="'. $service->ID . '">'. $title . '</option>';
			}
			$html .= '</select>';
		}
		else
			$html .= __( 'No services defined', 'appointments' );
		$html .= '</td><td>';
		$pages = apply_filters('app-biography_pages-get_list', array());
		if (empty($pages)) $pages = get_pages( apply_filters('app_pages_filter',array() ) );
		$html .= '<select name="workers['.$k.'][page]" >';
		$html .= '<option value="0">'. __('None','appointments') .'</option>';
		foreach( $pages as $page ) {
			if ( $php )
				$title = esc_attr( $page->post_title );
			else
				$title = esc_js( $page->post_title );

			if ( $php && is_object( $worker ) && $worker->page == $page->ID )
				$html .= '<option value="'.$page->ID.'" selected="selected">'. $title . '</option>';
			else
				$html .= '<option value="'.$page->ID.'">'. $title . '</option>';
		}
		$html .= '</select>';
		$html .= '</td></tr>';
		return $html;
	}

	/**
	 *	Create a working hour form
	 *  Worker can be forced.
	 *  @param status: Open (working hours) or close (break hours)
	 */
	function working_hour_form( $status='open' ) {
		//$_old_time_format = $this->time_format;
		//$this->time_format = "H:i";
		$_required_format = "H:i";

		$this->get_lsw();

		if ( isset( $this->options["admin_min_time"] ) && $this->options["admin_min_time"] )
			$min_time = $this->options["admin_min_time"];
		else
			$min_time = $this->get_min_time();

		$min_secs = 60 * $min_time;

		$wb = $this->get_work_break( $this->location, $this->worker, $status );
		if ( $wb )
			$whours = maybe_unserialize( $wb->hours );
		else
			$whours = array();

		$form = '';
		$form .= '<table class="app-working_hours-workhour_form">';
		if ( 'open' == $status )
			$form .= '<tr><th>'.__('Day', 'appointments').'</th><th>'.__('Work?', 'appointments' ).'</th><th>'.__('Start', 'appointments').'</th><th>'.__('End', 'appointments').'</th></tr>';
		else
			$form .= '<tr><th>'.__('Day', 'appointments').'</th><th>'.__('Give break?','appointments').'</th><th>'.__('Start','appointments').'</th><th>'.__('End','appointments').'</th></tr>';
		foreach ( $this->weekdays() as $day_label => $day ) {
			if (!empty($whours[$day]['active']) && is_array($whours[$day]['active'])) {
				$total_whour_segments = count($whours[$day]['active']) - 1;
				// We have multiple breaks for today.
				foreach ($whours[$day]['active'] as $idx => $active) {
					$form .= '<tr ' . ($idx > 0 ? 'class="app-repeated"' : '') . '><td>';
					if (0 == $idx) $form .= $day_label;
					$form .= '</td>';
					$form .= '<td>';
					$form .= '<select name="'.$status.'['.$day.'][active][' . $idx . ']" autocomplete="off">';
					if ( 'yes' == $active )
						$s = " selected='selected'";
					else $s = '';
					$form .= '<option value="no">'.__('No', 'appointments').'</option>';
					$form .= '<option value="yes"'.$s.'>'.__('Yes', 'appointments').'</option>';
					$form .= '</select>';
					$form .= '</td>';
					$form .= '<td>';
					$form .= '<select name="'.$status.'['.$day.'][start][' . $idx . ']">';
					for ( $t=0; $t<3600*24; $t=$t+$min_secs ) {
						$dhours = esc_attr($this->secs2hours($t, $_required_format)); // Hours in 08:30 format - escape, because they're values now.
						$shours = $this->secs2hours($t);
						if ( isset($whours[$day]['start'][$idx]) && strtotime($dhours) == strtotime($whours[$day]['start'][$idx]) )
							$s = "selected='selected'";
						else $s = '';

						$form .= "<option {$s} value='{$dhours}'>";
						$form .= $shours;
						$form .= '</option>';
					}
					$form .= '</select>';
					$form .= '</td>';

					$form .= '<td>';
					$form .= '<select name="'.$status.'['.$day.'][end][' . $idx . ']" autocomplete="off">';
					for ( $t=$min_secs; $t<=3600*24; $t=$t+$min_secs ) {
						$dhours = esc_attr($this->secs2hours($t, $_required_format)); // Hours in 08:30 format - escape, because they're values now.
						$shours = $this->secs2hours($t);
						if ( isset($whours[$day]['end'][$idx]) && strtotime($dhours) == strtotime($whours[$day]['end'][$idx]) )
							$s = "selected='selected'";
						else $s = '';

						$form .= "<option {$s} value='{$dhours}'>";
						$form .= $shours;
						$form .= '</option>';
					}
					$form .= '</select>';
					if ('closed' == $status && $idx == 0 && 'yes' == $active) $form .= '&nbsp;<a href="#add_break" class="app-add_break" title="' . esc_attr(__('Add break', 'appointments')) . '"><span>' . __('Add break', 'appointments') . '</span></a>';
					$form .= '</td>';

					$form .= '</tr>';
				}
			} else {
				// Oh, it's just one break.
				$form .= '<tr><td>';
				$form .= $day_label;
				$form .= '</td>';
				$form .= '<td>';
				$form .= '<select name="'.$status.'['.$day.'][active]" autocomplete="off">';
				if ( isset($whours[$day]['active']) && 'yes' == $whours[$day]['active'] )
					$s = " selected='selected'";
				else $s = '';
				$form .= '<option value="no">'.__('No', 'appointments').'</option>';
				$form .= '<option value="yes"'.$s.'>'.__('Yes', 'appointments').'</option>';
				$form .= '</select>';
				$form .= '</td>';
				$form .= '<td>';
				$form .= '<select name="'.$status.'['.$day.'][start]" autocomplete="off">';
				for ( $t=0; $t<3600*24; $t=$t+$min_secs ) {
					$dhours = esc_attr($this->secs2hours($t, $_required_format)); // Hours in 08:30 format - escape, because they're values now.
					$shours = $this->secs2hours($t);
					if ( isset($whours[$day]['start']) && strtotime($dhours) == strtotime($whours[$day]['start']) )
						$s = "selected='selected'";
					else $s = '';

					$form .= "<option {$s} value='{$dhours}'>";
					$form .= $shours;
					$form .= '</option>';
				}
				$form .= '</select>';
				$form .= '</td>';

				$form .= '<td>';
				$form .= '<select name="'.$status.'['.$day.'][end]" autocomplete="off">';
				for ( $t=$min_secs; $t<=3600*24; $t=$t+$min_secs ) {
					$dhours = esc_attr($this->secs2hours($t, $_required_format)); // Hours in 08:30 format - escape, because they're values now.
					$shours = $this->secs2hours($t);
					if ( isset($whours[$day]['end']) && strtotime($dhours) == strtotime($whours[$day]['end']) )
						$s = " selected='selected'";
					else $s = '';

					$form .= "<option {$s} value='{$dhours}'>";
					$form .= $shours;
					$form .= '</option>';
				}
				$form .= '</select>';
				if ('closed' == $status && isset($whours[$day]['active']) && 'yes' == $whours[$day]['active']) $form .= '&nbsp;<a href="#add_break" class="app-add_break" title="' . esc_attr(__('Add break', 'appointments')) . '"><span>' . __('Add break', 'appointments') . '</span></a>';
				$form .= '</td>';

				$form .= '</tr>';
			}
		}

		$form .= '</table>';

		//$this->time_format = $_old_time_format;

		return $form;
	}

	/**
	 *	Return results for appointments
	 */
	function get_admin_apps($type, $startat, $num) {

		if( isset( $_GET['s'] ) && trim( $_GET['s'] ) != '' ) {
			$s = esc_sql(like_escape($_GET['s']));
			$add = " AND ( name LIKE '%{$s}%' OR email LIKE '%{$s}%' OR ID IN ( SELECT ID FROM {$this->db->users} WHERE user_login LIKE '%{$s}%' ) ) ";
		}
		else
			$add = "";

		if(isset($_GET['app_service_id']) && $_GET['app_service_id'] )
			$add .= $this->db->prepare(" AND service=%d", $_GET['app_service_id']);

		if(isset($_GET['app_provider_id']) && $_GET['app_provider_id'] )
			$add .= $this->db->prepare(" AND worker=%d", $_GET['app_provider_id']);

		if ( isset( $_GET['app_order_by']) && $_GET['app_order_by'] )
			$order_by = esc_sql(str_replace( '_', ' ', $_GET['app_order_by'] ));
		else
			$order_by = "ID DESC";

		switch($type) {

			case 'active':
						$sql = $this->db->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM {$this->app_appointments_custom} APP_ADD ORDER BY {$order_by} LIMIT %d, %d", $startat, $num);
						break;
			case 'pending':
						$sql = $this->db->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM {$this->app_appointments_custom} APP_ADD ORDER BY {$order_by} LIMIT %d, %d", $startat, $num);
						break;
			case 'completed':
						$sql = $this->db->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM {$this->app_appointments_custom} APP_ADD ORDER BY {$order_by} LIMIT %d, %d", $startat, $num);
						break;
			case 'removed':
						$sql = $this->db->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM {$this->app_appointments_custom} APP_ADD ORDER BY {$order_by} LIMIT %d, %d", $startat, $num);
						break;
			case 'reserved':
						$sql = $this->db->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM {$this->app_appointments_custom} APP_ADD ORDER BY {$order_by} LIMIT %d, %d", $startat, $num);
						break;
			default:
						$sql = $this->db->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM {$this->app_appointments_custom} APP_ADD ORDER BY {$order_by} LIMIT %d, %d", $startat, $num);
						break;
		}
		$sql = preg_replace('/\bAPP_ADD\b/', $add, $sql);

		return $this->db->get_results( $sql );

	}

	function get_apps_total() {
		return $this->db->get_var( "SELECT FOUND_ROWS();" );
	}

	/**
	 *	Creates the list for Appointments admin page
	 */
	function appointment_list() {
		App_Template::admin_appointments_list();

	}

	/**
	 * Save a CSV file of all appointments
	 * @since 1.0.9
	 */
	function export(){

		$sql = false;
		$type = !empty($_POST['export_type']) ? $_POST['export_type'] : 'all';
		if ('selected' == $type && !empty($_POST['app'])) {
			// selected appointments
			$ids = array_filter(array_map('intval', $_POST['app']));
			if ($ids) $sql = "SELECT * FROM {$this->app_table} WHERE ID IN(" . join(',', $ids) . ") ORDER BY ID";
		} else if ('type' == $type) {
			$status = !empty($_POST['status']) ? $_POST['status'] : false;
			if ('active' === $status) $sql = $this->db->prepare("SELECT * FROM {$this->app_table} WHERE status IN('confirmed','paid') ORDER BY ID", $status);
			else if ($status) $sql = $this->db->prepare("SELECT * FROM {$this->app_table} WHERE status=%s ORDER BY ID", $status);
		} else if ('all' == $type) {
			$sql = "SELECT * FROM {$this->app_table} ORDER BY ID";
		}
		if (!$sql) wp_die(__('Nothing to download!','appointments'));

		$apps = $this->db->get_results($sql, ARRAY_A);

		if ( !is_array( $apps ) || empty( $apps ) ) wp_die(__('Nothing to download!','appointments'));

		$file = fopen('php://temp/maxmemory:'. (12*1024*1024), 'r+');
		// Add field names to the file
		$columns = array_map('strtolower', apply_filters('app-export-columns', $this->db->get_col_info()));
		fputcsv( $file,  $columns );

		foreach ( $apps as $app ) {
			$raw = $app;
			array_walk( $app, array(&$this, 'export_helper') );
			$app = apply_filters('app-export-appointment', $app, $raw);
			if (!empty($app)) fputcsv( $file, $app );
		}

		$filename = "appointments_".date('F')."_".date('d')."_".date('Y').".csv";

		//serve the file
		rewind($file);
		ob_end_clean(); //kills any buffers set by other plugins
		header('Content-Description: File Transfer');
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		$output = stream_get_contents($file);
		//$output = $output . "\xEF\xBB\xBF"; // UTF-8 BOM
		header('Content-Length: ' . strlen($output));
		fclose($file);
		die($output);
	}

	/**
	 * Helper function for export
	 * @since 1.0.9
	 */
	function export_helper( &$value, $key ) {
		if ( 'created' == $key || 'start' == $key || 'end' == $key )
			$value = mysql2date( $this->datetime_format, $value );
		else if ( 'user' == $key && $value ) {
			$userdata = get_userdata( $value );
			if ( $userdata )
				$value = $userdata->user_login;
		}
		else if ( 'service' == $key )
			$value = $this->get_service_name( $value );
		else if ( 'worker' == $key )
			$value = $this->get_worker_name( $value );
	}

	/**
	 * Helper function for displaying appointments
	 *
	 */
	function myapps($type = 'active') {
		App_Template::admin_my_appointments_list($type);
	}

	/**
	 * Return a safe date format that datepick can use
	 * @return string
	 * @since 1.0.4.2
	 */
	function safe_date_format() {
		// Allowed characters
		$check = str_replace( array( '-', '/', ',', 'F', 'j', 'y', 'Y', 'd', 'M', 'm' ), '', $this->date_format );
		if ( '' == trim( $check ) )
			return $this->date_format;

		// Return a default safe format
		return 'F j Y';
	}

	/**
	 * Modify a date if it is non US
	 * Change d/m/y to d-m-y so that strtotime can behave correctly
	 * Also change local dates to m/d/y format
	 * @return string
	 * @since 1.0.4.2
	 */
	function to_us( $date ) {
		// Find the real format we are using
		$date_format = $this->safe_date_format();
		$date_arr = explode( '/', $date_format );
		if ( isset( $date_arr[0] ) && isset( $date_arr[1] ) && 'd/m' == $date_arr[0] .'/'. $date_arr[1] )
			return str_replace( '/', '-', $date );
		// Already US format
		if ( isset( $date_arr[0] ) && isset( $date_arr[1] ) && 'm/d' == $date_arr[0] .'/'. $date_arr[1] )
			return $date;

		global $wp_locale;
		if ( !is_object( $wp_locale )  || empty( $wp_locale->month ) ) {
			$this->locale_error = true;
			return $date;
		}

		$datepick_local_months = $this->datepick_local_months();
		$datepick_abb_local_months = $this->datepick_abb_local_months();

		$months = array( 'January'=>'01','February'=>'02','March'=>'03','April'=>'04','May'=>'05','June'=>'06',
						'July'=>'07','August'=>'08','September'=>'09','October'=>'10','November'=>'11','December'=>'12' );
		// A special check where locale is set, but language files are not loaded
		if ( strpos( $date_format, 'F' ) !== false || strpos( $date_format, 'M' ) !== false ) {
			$n = 0;
			$k = 0;
			foreach ( $months as $month_name => $month_no ) {
				$month_name_local = $wp_locale->get_month($month_no);
				$month_name_abb_local = $wp_locale->get_month_abbrev($month_name_local);
				if ( $month_name_local == $month_name )
					$n++;
				// Also check if any month will give a 1970 result
				if ( '1970-01-01' == date( 'Y-m-d', strtotime( $month_name . ' 1 2012' ) ) ) {
					$this->locale_error = true;
					return $date;
				}
				// Also check translation of datepick
				if ( strpos( $date_format, 'F' ) !== false ) {
					if ( $month_name_local != trim( $datepick_local_months[$k] ) ) {
						$this->locale_error = true;
						return $date;
					}
				}
				if ( strpos( $date_format, 'M' ) !== false ) {
					// Also check translation of datepick for short month names
					if ( $month_name_abb_local != trim( $datepick_abb_local_months[$k] ) ) {
						$this->locale_error = true;
						return $date;
					}
				}
				$k++;
			}
			if ( $n > 11 ) {
				// This means we shall use English
				$this->locale_error = true;
				return $date;
			}
		}

		// Check if F (long month name) is set
		if ( strpos( $date_format, 'F' ) !== false ) {
			foreach ( $months as $month_name => $month_no ) {
				$month_name_local = $wp_locale->get_month($month_no);
				if ( strpos( $date, $month_name_local ) !== false )
					return date( 'm/d/y', strtotime( str_replace( $month_name_local, $month_name, $date ) ) );
			}
		}

		if ( strpos( $date_format, 'M' ) !== false ) {
			// Check if M (short month name) is set
			$month_abb = array( );
			foreach ( $months as $month_name => $month_no ) {
				$month_name_local = $wp_locale->get_month($month_no);
				$month_name_abb_local = $wp_locale->get_month_abbrev($month_name_local);
				if ( strpos( $date, $month_name_abb_local ) !== false )
					return date( 'm/d/y', strtotime( str_replace( $month_name_abb_local, $month_name, $date ) ) );
			}
		}

		$this->locale_error = true;
		return $date;
	}
	




	function get_user_compare_with_app_worker($id, $service_id){
		global $wpdb;
		$get_user = $wpdb->get_row( "SELECT * FROM wp_app_workers WHERE ID = $id");
		$res = explode( ":", $get_user->services_provided );
		if(in_array($service_id, $res)){
			
			
			return true;
			
			}else{
			return false;	
			}
		}



	function get_service_on_option_group($id, $service_id){

			global $wpdb;
			$users = $wpdb->get_results( 'SELECT * FROM `wp_users`');
			if($service_id==1)
				 $html .='<optgroup label="3-Line" >';
				 else if($service_id==2)
				 $html .='<optgroup label="10-Line" >';
			foreach ( $users as $user ) {
				
				 if($this->get_user_compare_with_app_worker($user->ID, $service_id))
				 	{
						$html .= '<option value="'.$user->ID.'" ';
						if($id==$user->ID){
						$html .= 'selected';	
							}
						$html .='>' . $user->display_name  . '</option>';
					}
				 
			}
				 $html .='</optgroup>';	
				 
				 return  $html;
		
		}



	function check_if_hooker_is_available_that_day($user_id, $start, $end){
		global $wpdb; 
		$hour_min = date ("H:i", strtotime($start));
		//$hour_min = strtotime ($hour_min);
		$hour_min_end = date ("H:i", strtotime($end));
		$day = date ("d", strtotime($start));
		$month = date ("m", strtotime($start));
		$year = date ("Y", strtotime($start));
		$date= strtok($start,' ') ;
			
		$working_hours = $wpdb->get_row( "SELECT * FROM wp_app_hooker WHERE `date` = '$date%' and worker = $user_id ");
		//print_r($working_hours);
		//echo $working_hours->break_start;
		if(!empty($working_hours) or $working_hours !=NULL){
				if(strtotime ($working_hours->working_hours_start) <= strtotime ($hour_min)){
					if($working_hours->break_enable==='0'){
						if(strtotime ($worker_hours->break_start)<=strtotime ($hour_min) and strtotime ($hour_min) <= strtotime ($working_hours->break_end) ){
					return false;
							}else{
					return true;
						}
									
					}else{
					return true;
					}
					return true;
				}
			}else{
				return false;
				}
								
		}



	function status_bkand($status){
						$html .= '<option value="active" ';
						if($status=='active'){
						$html .= 'selected';	
							}
						$html .='>Active</option>';


/*						$html .= '<option value="pending" ';
						if($status=='pending'){
						$html .= 'selected';	
							}
						$html .='>Pending</option>';
*/


						$html .= '<option value="deposit" ';
						if($status=='deposit'){
						$html .= 'selected';	
							}
						$html .='>Deposit</option>';

			

			
						$html .= '<option value="completed" ';
						if($status=='completed'){
						$html .= 'selected';	
							}
						$html .='>Completed</option>';


						$html .= '<option value="reserved" ';
						if($status=='reserved'){
						$html .= 'selected';	
							}
						$html .='>Reserved</option>';

			
						$html .= '<option value="removed" ';
						if($status=='removed'){
						$html .= 'selected';	
							}
						$html .='>Cancel</option>';

			
			return $html;
		
		}

	function hooker_option_group($id, $start, $end){
			global $wpdb;
			$users = $wpdb->get_results( 'SELECT * FROM `wp_users`');
			$html .='<optgroup label="Hooker" >';
			foreach ( $users as $user ) {
				$get_userdata = get_userdata($user->ID);
				if(in_array('hooker', $get_userdata->roles)){
					if($this->check_if_hooker_is_available_that_day($user->ID, $start, $end)== true){
						$html .= '<option value="'.$user->ID.'" ';
						if($id==$user->ID){
						$html .= 'selected';	
							}
						$html .='>' . $user->display_name  . '</option>';
					}
				}
			}
			$html .='</optgroup>';	
			
			return $html;
		
		}




	function app_get_user_info($id, $service_id, $start, $end){
			//$users = get_users();
			// Array of WP_User objects.
			$html .= $this->get_service_on_option_group($id, $service_id);
			$html .= $this->hooker_option_group($id, $start, $end);
			return $html;
			
		}



	//delete worker entry which date is over (wp_three_line_appointment)
	
	function delete_entry_older_this_month(){
		global $wpdb;
		$wpdb->get_results( "DELETE FROM  `wp_three_line_appointment` WHERE  `date` < CURRENT_TIMESTAMP" );
		}

	function attendees_fields_form_backend($i, $name, $age, $weight, $insuran, $bk_select_provider, $service_id, $start, $end, $status, $cid){
			$output = '';
			$output .= '<a class="btn btn-primary" role="button" data-toggle="collapse" href="#collapse_'.$cid.'_'.$this->clean_for_class($name).'_'.$status.'_'.$i.'" aria-expanded="false" aria-controls="collapse_'.$cid.'_'.$this->clean_for_class($name).'_'.$status.'_'.$i.'" style="display:block; text-align:left">'.$name.'</a>';
			$output .= '<div class="collapse" id="collapse_'.$cid.'_'.$this->clean_for_class($name).'_'.$status.'_'.$i.'">';
			$output .= '<div class="well">';
			$output .= '<div>Name:</div><div><input type="text" name="bk_att_name_'.$cid.'_'.$status.'_'.$i.'" id="bk_att_name_'.$cid.'_'.$status.'_'.$i.'" value="'.$name.'" style="display: block;width: 100%;"></div>';
			$output .= '<div>Age:</div><div><input type="text" name="bk_att_age_'.$cid.'_'.$status.'_'.$i.'" id="bk_att_age_'.$cid.'_'.$status.'_'.$i.'" value="'.$age.'" style="display: block;width: 100%;"></div>';
			$output .= '<div>Weight:</div><div><input type="text" name="bk_att_weight_'.$cid.'_'.$status.'_'.$i.'" id="bk_att_weight_'.$cid.'_'.$status.'_'.$i.'" value="'.$weight.'" style="display: block;width: 100%;"></div>';
			$output .= '<div>Insurance Waiver:</div><div><input type="text" name="bk_insurance_waver_'.$cid.'_'.$status.'_'.$i.'" id="bk_insurance_waver_'.$cid.'_'.$status.'_'.$i.'" value="'.$insuran.'" style="display: block;width: 100%;"></div><div><a href="/insurance/"  target="_blank">Click here to generate your Insurance Waiver. </a></div>';
			$output .= '<div>Service Provider:</div><div><select name="bk_select_provider_'.$cid.'_'.$status.'_'.$i.'" id="bk_select_provider_'.$cid.'_'.$status.'_'.$i.'"  style="display: block;width: 100%;">'.$this->app_get_user_info($bk_select_provider, $service_id, $start, $end).'</select></div>';
			$output .= '<div>Status:</div><div><select name="bk_select_status_'.$cid.'_'.$status.'_'.$i.'" id="bk_select_status_'.$cid.'_'.$status.'_'.$i.'"  style="display: block;width: 100%;">'.$this->status_bkand($status).'</select></div>';
			$output .= '</div>';
			$output .= '</div>';
			return $output;
		}




	function rearrange_by_status_foratten_info($cid, $status, $name){
		global $wpdb;
		$query = $wpdb->get_results( "SELECT * FROM wp_app_appointments WHERE cid LIKE '$cid' AND status LIKE '$status'" );
		$i = 1;
		$ret .='<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
  <div class="panel panel-default">';
				$ret .='<div class="panel-heading" role="tab" style="padding: 5px 10px;" id="heading_'.$cid.'_'.$status.'">';
				$ret .='<h4 class="panel-title">';
				$ret .='<a style="display:block;" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse_'.$cid.'_'.$status.'" aria-expanded="false" aria-controls="collapse_'.$cid.'_'.$status.'">'.$name.'<sup style="text-transform: lowercase;    padding: 5px 10px;    margin-left: 10px;    background-color: #337AB7;    border-radius: 50%;    color: #fff;">'.count($query).'</sup></a>';
				$ret .='</h4>';
				$ret .='</div>';
				$ret .=' <div id="collapse_'.$cid.'_'.$status.'" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading_'.$cid.'_'.$status.'">';
				$ret .=' <div class="panel-body">';
		foreach	($query as $q){
				$ret.= $this->attendees_fields_form_backend($q->ID, $q->name, $q->age, $q->weight, $q->insurance_waver, $q->worker, $q->service, $q->start, $q->end, $q->status, $cid);
				$i++;
			}
				$ret .='</div>';		
				$ret .='</div>';		
			
		$ret .='</div></div>';		
			
			return $ret;
		
		}

	
	function get_attendance_info($cid){
			$ret = '';
			$ret .= $this->rearrange_by_status_foratten_info($cid, 'confirmed', 'Confirmed')	;		
			$ret .= $this->rearrange_by_status_foratten_info($cid, 'deposit', 'Deposit')	;		
			$ret .= $this->rearrange_by_status_foratten_info($cid, 'completed', 'Completed')	;		
			$ret .= $this->rearrange_by_status_foratten_info($cid, 'reserved', 'Reserved')	;		
			$ret .= $this->rearrange_by_status_foratten_info($cid, 'removed', 'Cancel')	;		
			return $ret;
		}
	

	// Edit or create appointments
	function inline_edit() {
		$safe_date_format = $this->safe_date_format();
		// Make a locale check to update locale_error flag
		$date_check = $this->to_us( date_i18n( $safe_date_format, strtotime('today') ) );

		global $wpdb;
		$app_id = $_POST["app_id"];
		$cid = $_POST["cid"];
		if ( $app_id ) {
			$app = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->app_appointments_custom} WHERE ID=%d", $app_id) );
			$start_date_timestamp = date("Y-m-d", strtotime($app->start));
			if ( $this->locale_error ) {
				$start_date = date( $safe_date_format, strtotime( $app->start ) );
			} else {
				$start_date = date_i18n( $safe_date_format, strtotime( $app->start ) );
			}

			$start_time = date_i18n( $this->time_format, strtotime( $app->start ) );
			$end_datetime = date_i18n( $this->datetime_format, strtotime( $app->end ) );
			// Is this a registered user?
			if ( $app->user ) {
				$name = get_user_meta( $app->user, 'app_name', true );
				if ( $name )
					$app->name = $app->name && !(defined('APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES') && APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES) ? $app->name : $name;

				$email = get_user_meta( $app->user, 'app_email', true );
				if ( $email )
					$app->email = $app->email && !(defined('APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES') && APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES) ? $app->email : $email;

				$phone = get_user_meta( $app->user, 'app_phone', true );
				if ( $phone )
					$app->phone = $app->phone && !(defined('APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES') && APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES) ? $app->phone : $phone;

				$address = get_user_meta( $app->user, 'app_address', true );
				if ( $address )
					$app->address = $app->address && !(defined('APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES') && APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES) ? $app->address : $address;

				$city = get_user_meta( $app->user, 'app_city', true );
				if ( $city )
					$app->city = $app->city && !(defined('APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES') && APP_USE_LEGACY_ADMIN_USERDATA_OVERRIDES) ? $app->city : $city;
			}
		} else {
			$app = new stdClass(); // This means insert a new app object
/*
//DO NOT DO THIS!!!!
//This is just begging for a race condition issue >.<
			// Get maximum ID
			$app_max = $wpdb->get_var( "SELECT MAX(ID) FROM " . $this->app_table . " " );
			// Check if nothing has saved yet
			if ( !$app_max )
				$app_max = 0;
			$app->ID = $app_max + 1 ; // We want to create a new record
*/
			$app->ID = 0;
			// Set other fields to default so that we don't get notice messages
			$app->user = $app->location = $app->worker = 0;
			$app->created = $app->end = $app->name = $app->email = $app->phone = $app->address = $app->city = $app->status = $app->sent = $app->sent_worker = $app->note = '';

			// Get first service and its price
			$app->service = $this->get_first_service_id();
			$_REQUEST['app_service_id'] = $app->service;
			$_REQUEST['app_provider_id'] = 0;
			$app->price = $this->get_price( );

			// Select time as next 1 hour
			$start_time = date_i18n( $this->time_format, intval(($this->local_time + 60*$this->get_min_time())/3600)*3600 );

			$start_date_timestamp = date("Y-m-d", $this->local_time + 60*$this->get_min_time());
			// Set start date as now + 60 minutes.
			if ( $this->locale_error ) {
				$start_date = date( $safe_date_format, $this->local_time + 60*$this->get_min_time() );
			}
			else {
				$start_date = date_i18n( $safe_date_format, $this->local_time + 60*$this->get_min_time() );
			}
		}

		$html = '';
		$html .= '<tr class="inline-edit-row inline-edit-row-post quick-edit-row-post">';
		if ( isset( $_POST["col_len"] ) )
			$html .= '<td colspan="'.$_POST["col_len"].'" class="colspanchange">';
		else
			$html .= '<td colspan="6" class="colspanchange">';

		$html .= '<fieldset class="inline-edit-col-left" style="width:33%">';
		$html .= '<div class="inline-edit-col">';
		$html .= '<h4>'.__('CLIENT', 'appointments').'</h4>';
		/* user */
		$html .= '<label>';
		$html .= '<span class="title">'.__('User', 'appointments'). '</span>';
		$html .= wp_dropdown_users( array( 'show_option_all'=>__('Not registered user','appointments'), 'show'=>'user_login', 'echo'=>0, 'selected' => $app->user, 'name'=>'user' ) );
		$html .= '</label>';
		/* Client name */
		$html .= '<label>';
		$html .= '<span class="title">'.$this->get_field_name('name'). '</span>';
		$html .= '<span class="input-text-wrap">';
		$html .= '<input type="text" name="cname" class="ptitle" value="'.stripslashes( $app->name ).'" />';
		$html .= '</span>';
		$html .= '</label>';
		/* Client email */
		$html .= '<label>';
		$html .= '<span class="title">'.$this->get_field_name('email'). '</span>';
		$html .= '<span class="input-text-wrap">';
		$html .= '<input type="text" name="email" class="ptitle" value="'.$app->email.'" />';
		$html .= '</span>';
		$html .= '</label>';
		/* Client Phone */
		$html .= '<label>';
		$html .= '<span class="title">'.$this->get_field_name('phone'). '</span>';
		$html .= '<span class="input-text-wrap">';
		$html .= '<input type="text" name="phone" class="ptitle" value="'.stripslashes( $app->phone ).'" />';
		$html .= '</span>';
		$html .= '</label>';
		/* Client Address */
		$html .= '<label>';
		$html .= '<span class="title">'.$this->get_field_name('address'). '</span>';
		$html .= '<span class="input-text-wrap">';
		$html .= '<input type="text" name="address" class="ptitle" value="'.stripslashes( $app->address ).'" />';
		$html .= '</span>';
		$html .= '</label>';
		/* Client City */
		$html .= '<label>';
		$html .= '<span class="title">'.$this->get_field_name('city'). '</span>';
		$html .= '<span class="input-text-wrap">';
		$html .= '<input type="text" name="city" class="ptitle" value="'.stripslashes( $app->city ).'" />';
		$html .= '</span>';
		$html .= '</label>';
		$html .= apply_filters('app-appointments_list-edit-client', '', $app);
		$html .= '</div>';
		$html .= '</fieldset>';

		$html .= '<fieldset class="inline-edit-col-center" style="width:28%">';
		$html .= '<div class="inline-edit-col">';
		$html .= '<h4>'.__('SERVICE', 'appointments').'</h4>';
		/* Services */
		$services = $this->get_services();
		$html .= '<label>';
		$html .= '<span class="title">'.__('Name', 'appointments'). '</span>';
		$html .= '<select name="service">';
		if ( $services ) {
			foreach ( $services as $service ) {
				if ( $app->service == $service->ID )
					$sel = ' selected="selected"';
				else
					$sel = '';
				$html .= '<option value="'.$service->ID.'"'.$sel.'>'. stripslashes( $service->name ) . '</option>';
			}
		}
		$html .= '</select>';
		$html .= '</label>';
		/* Workers */
		$workers = $wpdb->get_results("SELECT * FROM " . $this->workers_table . " " );
		$html .= '<label>';
		$html .= '<span class="title">'.__('Provider', 'appointments'). '</span>';
		$html .= '<select name="worker">';
		// Always add an "Our staff" field
		$html .= '<option value="0">'. __('No specific provider', 'appointments') . '</option>';
		if ( $workers ) {
			foreach ( $workers as $worker ) {
				if ( $app->worker == $worker->ID ) {
					$sel = ' selected="selected"';
				}
				else
					$sel = '';
				$html .= '<option value="'.$worker->ID.'"'.$sel.'>'. $this->get_worker_name( $worker->ID, false ) . '</option>';
			}
		}
		$html .= '</select>';
		$html .= '</label>';
		/* Price */
		$html .= '<label>';
		$html .= '<span class="title">'.__('Price', 'appointments'). '</span>';
		$html .= '<span class="input-text-wrap">';
		$html .= '<input type="text" name="price" style="width:50%" class="ptitle" value="'.$app->price.'" />';
		$html .= '</span>';
		$html .= '</label>';
		$html .= '</label>';
		$html .= apply_filters('app-appointments_list-edit-services', '', $app);
		/* Coupon */
		$html .= '<label>';
		$html .= '<span class="title">'.__('Coupon', 'appointments'). '</span>';
		$html .= '<span class="input-text-wrap">';
		$html .= '<input type="text" name="price" style="width:50%" class="ptitle" value="'.$app->coupon.'" />';
		$html .= '</span>';
		$html .= '</label>';
		
		$html .= '</div>';
		$html .= '</fieldset>';

		$html .= '<fieldset class="inline-edit-col-right" style="width:38%">';
		$html .= '<div class="inline-edit-col">';
		$html .= '<h4>'.__('APPOINTMENT', 'appointments').'</h4>';
		/* Created - Don't show for a new app */
		if ( $app_id ) {
			$html .= '<label>';
			$html .= '<span class="title">'.__('Created', 'appointments'). '</span>';
			$html .= '<span class="input-text-wrap" style="height:26px;padding-top:4px;">';
			$html .= date_i18n( $this->datetime_format, strtotime($app->created) );
			$html .= '</span>';
			$html .= '</label>';
		}
		/* Start */
		$html .= '<label style="float:left;width:65%">';
		$html .= '<span class="title">'.__('Start', 'appointments'). '</span>';
		$html .= '<span class="input-text-wrap" >';
		$html .= '<input type="text" name="date" class="datepicker" size="12" value="'.$start_date.'" data-timestamp="' . esc_attr($start_date_timestamp) . '"  />';
		$html .= '</label>';
		$html .= '<label style="float:left;width:30%; padding-left:5px;">';

		// Check if an admin min time (time base) is set. @since 1.0.2
		if ( isset( $this->options["admin_min_time"] ) && $this->options["admin_min_time"] )
			$min_time = $this->options["admin_min_time"];
		else
			$min_time = $this->get_min_time();

		$min_secs = 60 * apply_filters( 'app_admin_min_time', $min_time );
		$html .= '<select name="time" >';
		for ( $t=0; $t<3600*24; $t=$t+$min_secs ) {
			$dhours = $this->secs2hours( $t ); // Hours in 08:30 format
			if ( $dhours == $start_time )
				$s = " selected='selected'";
			else $s = '';

			$html .= '<option'.$s.'>';
			$html .= $dhours;
			$html .= '</option>';
		}
		$html .= '</select>';
		$html .= '</span>';
		$html .= '</label>';
		$html .= '<div style="clear:both; height:0"></div>';
		/* End - Don't show for a new app */
		if ( $app_id ) {
			$html .= '<label style="margin-top:8px">';
			$html .= '<span class="title">'.__('End', 'appointments'). '</span>';
			$html .= '<span class="input-text-wrap" style="height:26px;padding-top:4px;">';
			$html .= $end_datetime;
			$html .= '</span>';
			$html .= '</label>';
		}
		/* Note */
		$html .= '<label>';
		$html .= '<span class="title">'.$this->get_field_name('note'). '</span>';
		$html .= '<textarea cols="22" rows=1">';
		$html .= stripslashes( $app->note );
		$html .= '</textarea>';
		$html .= '</label>';
		/* Status */
		//$statuses = $this->get_statuses();
		$statuses = App_Template::get_status_names();
		$html .= '<label style="display:none;">';
		$html .= '<span class="title">'.__('Status', 'appointments'). '</span>';
		$html .= '<span class="input-text-wrap">';
		$html .= '<select name="status">';
		if ( $statuses ) {
			foreach ( $statuses as $status => $status_name ) {
				if ( $app->status == $status )
					$sel = ' selected="selected"';
				else
					$sel = '';
				if($status_name=='Pending'){	
				$html .= '<option value="'.$status.'"'.$sel.'>Pending Payment</option>';
				}else{
				$html .= '<option value="'.$status.'"'.$sel.'>'. $status_name . '</option>';
					}
			}
		}
		$html .= '</select>';
		$html .= '</span>';
		$html .= '</label>';
		/* Confirmation email */
		// Default is "checked" for a new appointment
		if ( $app_id ) {
			$c = '';
			$text = __('(Re)send confirmation email', 'appointments');
		}
		else {
			$c = ' checked="checked"';
			$text = __('Send confirmation email', 'appointments');
		}

		$html .= '<label>';
		$html .= '<span class="title">'.__('Confirm','appointments').'</span>';
		$html .= '<span class="input-text-wrap">';
		$html .= '<input type="checkbox" name="resend" value="1" '.$c.' />&nbsp;' .$text;
		$html .= '</span>';
		$html .= '</label>';

		$html .= '</div>';
		$html .= '</fieldset>';
		/* General fields required for save and cancel */
		$html .= '<p class="submit inline-edit-save">';
		$html .= '<a href="javascript:void(0)" title="'._x('Cancel', 'Drop current action', 'appointments').'" class="button-secondary cancel alignleft">'._x('Cancel', 'Drop current action', 'appointments').'</a>';
		if ( 'reserved' == $app->status ) {
			$js = 'style="display:none"';
			$title = __('GCal reserved appointments cannot be edited here. Edit them in your Google calendar.', 'appointments');
		}
		else {
			$js = 'href="javascript:void(0)"';
			$title = __('Click to save or update', 'appointments');
		}
		$html .= '<a '.$js.' title="'.$title.'" class="button-primary save alignright">'.__('Save / Update','appointments').'</a>';
		$html .= '<a '.$js.' title="'.$title.'" data-toggle="modal" data-target="#myModal_'.$app->ID.'" class="button-primary alignright" style="margin-right:20px;">'.__('Attendees Information','appointments').'</a>';
		$html .='<div class="modal fade" id="myModal_'.$app->ID.'" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalLabel">Attendees Information</h4>
      </div>
      <div class="modal-body">
	  	<h3>Price: $'.$app->price.'</h3>
	  	<h3>Start: '.$app->start.'<span style="float:right">End: '.$app->end.'</span></h3>
	  	
        '.$this->get_attendance_info($cid).'
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary sever_but">Save changes</button>
      </div>
    </div>
  </div>
</div>';
		$html .= '<script>';
		$html .= '	jQuery(document).ready(function($){';
		$html .= '$(".sever_but").click(function(){';
		$html .= $this->bk_attendees_fields_jquery($cid);
		$html .= 'var attendees_info = "'.$this->bk_attendees_fields_ajax($cid).'";';
		$html .= 'var bk_save_att_data = {action: "bk_save_att_data", cid: "'.$cid.'", attendees_info: attendees_info,  nonce: "'. wp_create_nonce() .'"};';
		
		$html .='$.post(ajaxurl, bk_save_att_data, function(response) {
				$(".add-new-waiting").hide();
				if ( response && response.error ){
					alert(response.error);
				}
				else if (response) {
					alert("ATTENDEES INFORMATION IS UPDATED");
					var redirection_url = "'.admin_url( 'admin.php?page=appointments&type='.$app->status.'', 'http' ).'";
					//window.location.href=redirection_url;
				}
				else {alert("'.esc_js(__("Unexpected error","appointments")).'");}
			},"json");
';	
		
		
		$html .= '})';
		$html .= '})';
		$html .= '</script>';



		$html .= '<img class="waiting" style="display:none;" src="'.admin_url('images/wpspin_light.gif').'" alt="">';
		$html .= '<input type="hidden" name="app_id" value="'.$app->ID.'">';
		$html .= '<input type="hidden" name="cid" value="'.$app->cid.'">';
		$html .= '<span class="error" style="display:none"></span>';
		$html .= '<br class="clear">';
		$html .= '</p>';
		

				

		$html .= '</td>';
		$html .= '</tr>';

		die( json_encode( array( 'result'=>$html)));

	}


	//Shimion startbackend update
	function bk_attendees_fields_form_jquery($i, $status, $cid){
		$script .= 'var bk_att_name_'.$cid.'_'.$status.'_'.$i.' = $("#bk_att_name_'.$cid.'_'.$status.'_'.$i.'").val();';
		$script .= 'var bk_att_age_'.$cid.'_'.$status.'_'.$i.' = $("#bk_att_age_'.$cid.'_'.$status.'_'.$i.'").val();';
		$script .= 'var bk_att_weight_'.$cid.'_'.$status.'_'.$i.' = $("#bk_att_weight_'.$cid.'_'.$status.'_'.$i.'").val();';
		$script .= 'var bk_insurance_waver_'.$cid.'_'.$status.'_'.$i.' = $("#bk_insurance_waver_'.$cid.'_'.$status.'_'.$i.'").val();';
		$script .= 'var bk_select_provider_'.$cid.'_'.$status.'_'.$i.' = $("#bk_select_provider_'.$cid.'_'.$status.'_'.$i.'").val();';
		$script .= 'var bk_select_status_'.$cid.'_'.$status.'_'.$i.' = $("#bk_select_status_'.$cid.'_'.$status.'_'.$i.'").val();';
		return $script;
		}



	function bk_attendees_fields_jquery($cid){
		global $wpdb;
		$query = $wpdb->get_results( "SELECT * FROM wp_app_appointments WHERE cid LIKE '$cid'" );	
		$i = 1;
		foreach	($query as $q){
				$ret.= $this->bk_attendees_fields_form_jquery($q->ID, $q->status, $cid);
				$i++;
			}
			
			return $ret;
		}
	


//remove all special characters
function clean_for_class($string) {
   $string = str_replace('', '-', $string); // Replaces all spaces with hyphens.
   return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
}




	function bk_attendees_fields_ajax($cid){
		global $wpdb;
		$query = $wpdb->get_results( "SELECT * FROM wp_app_appointments WHERE cid LIKE '$cid'" );	
		//$i = 1;
		foreach	($query as $q){
						$status = $q->status;
						$i = $q->ID;
					//if($i>1){
					$output .= '!';
					$output .= '"+bk_att_name_'.$cid.'_'.$status.'_'.$i.'+"|"+bk_att_age_'.$cid.'_'.$status.'_'.$i.'+"|"+bk_att_weight_'.$cid.'_'.$status.'_'.$i.'+"|"+bk_insurance_waver_'.$cid.'_'.$status.'_'.$i.'+"|"+bk_select_provider_'.$cid.'_'.$status.'_'.$i.'+"|"+bk_select_status_'.$cid.'_'.$status.'_'.$i.'+"|"+"'.$q->ID.'"+"';
				//	$output .= 'bk_att_name_'.$i.': bk_att_name_'.$i.', bk_att_age_'.$i.':bk_att_age_'.$i.', bk_att_weight_'.$i.': bk_att_weight_'.$i.', ';	;
					
						/*}else{
					//$output .= 'bk_att_name_'.$i.':bk_att_name_'.$i.', bk_att_age_'.$i.':bk_att_age_'.$i.', bk_att_weight_'.$i.': bk_att_weight_'.$i.', ';	;
					$output .= '"+bk_att_name_'.$cid.'_'.$status.'_'.$i.'+"|"+bk_att_age_'.$cid.'_'.$status.'_'.$i.'+"|"+bk_att_weight_'.$cid.'_'.$status.'_'.$i.'+"|"+bk_insurance_waver_'.$cid.'_'.$status.'_'.$i.'+"|"+bk_select_provider_'.$cid.'_'.$status.'_'.$i.'+"|"+bk_select_status_'.$cid.'_'.$status.'_'.$i.'+"|"+"'.$q->ID.'"+"';
					
						}
				$i++;*/
			}
			
			return $output;
		}




	//inner edit inner save
	function bk_save_att_data(){
		$cid = $_POST["cid"];
		$attendees_info = $_POST['attendees_info'];
		global $wpdb;
		//$query = $wpdb->get_results( "SELECT * FROM wp_app_appointments WHERE cid LIKE '$cid'" );	
		//$i = 1;
		/*foreach	($query as $q){
				$data['bk_att_name_'.$i.'']		= $_POST['bk_att_name_'.$i.''];
				$data['bk_att_age_'.$i.'']		= $_POST['bk_att_age_'.$i.''];
				$data['bk_att_weight_'.$i.'']		= $_POST['bk_att_weight_'.$i.''];
				$wpdb->update( $this->app_table, $data, array('ID' => $app_id) );
				$i++;
			}*/
	
	
			$attendees_info= explode( "!", $attendees_info );
			
			foreach($attendees_info as $arrays){
				$arrs= explode( "|", $arrays );
					$data['name'] = $arrs['0'];
					$data['age'] = $arrs['1'];
					$data['weight'] = $arrs['2'];
					$data['insurance_waver'] = $arrs['3'];
					$data['worker'] = $arrs['4'];
					$data['status'] = $arrs['5'];
					$app_id = $arrs['6'];
					$wpdb->update( $this->app_table, $data, array('ID' => $app_id) );
				}
			
			
			
			
			
			die( json_encode(
							array(
							'attendees_info'			=>$attendees_info,
							)
						)
					);
		
		
		}









	function inline_edit_save() {
		$app_id = $_POST["app_id"];
		$cid = $_POST["cid"];
		$email_sent = false;
		global $wpdb, $current_user;
		$app = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->app_appointments_custom} WHERE ID=%d", $app_id) );
		$app_reals = $wpdb->get_results("SELECT * FROM  `wp_app_appointments` WHERE  `cid` LIKE  '$app->cid'" );
		$data = array();
		if ( $app != null )
			$data['ID'] = $app_id;
		else {
			$data['created']	= date("Y-m-d H:i:s", $this->local_time );
			$data['ID'] 		= 'NULL';
		}
		$data['user']		= $_POST['user'];
		$data['email']		= $_POST['email'];
		$data['name']		= $_POST['name'];
		$data['phone']		= $_POST['phone'];
		$data['address'] 	= $_POST['address'];
		$data['city']		= $_POST['city'];
		$data['service']	= $_POST['service'];
		$service			= $this->get_service( $_POST['service'] );
		$data['worker']		= $_POST['worker'];
		$data['price']		= $_POST['price'];
		// Clear comma from date format. It creates problems for php5.2
		$data['start']		= date( 'Y-m-d H:i:s', strtotime( str_replace( ',','', $this->to_us( $_POST['date'] ) ). " " . $this->to_military( $_POST['time'] ) ) );
		$data['end']		= date( 'Y-m-d H:i:s', strtotime( str_replace( ',','', $this->to_us( $_POST['date'] ) ). " " . $this->to_military( $_POST['time'] ) ) + $service->duration *60 );
		$data['note']		= $_POST['note'];
		$data['status']		= $_POST['status'];
		$resend				= $_POST["resend"];



		$data2['price']		= $_POST['price'];
		// Clear comma from date format. It creates problems for php5.2
		$data2['start']		= date( 'Y-m-d H:i:s', strtotime( str_replace( ',','', $this->to_us( $_POST['date'] ) ). " " . $this->to_military( $_POST['time'] ) ) );
		$data2['end']		= date( 'Y-m-d H:i:s', strtotime( str_replace( ',','', $this->to_us( $_POST['date'] ) ). " " . $this->to_military( $_POST['time'] ) ) + $service->duration *60 );
		$data2['note']		= $_POST['note'];
		$data2['status']		= $_POST['status'];
		$resend				= $_POST["resend"];







		$data = apply_filters('app-appointment-inline_edit-save_data', $data);

		$update_result = $insert_result = false;
		if( $app != null ) {
			// Update
			$update_result = $wpdb->update( $this->app_appointments_custom, $data, array('ID' => $app_id) );
			foreach($app_reals as $real){
			$wpdb->update( $this->app_table, $data2, array('ID' => $real->ID) );
				}
			if ( $update_result ) {
				if ( ( 'pending' == $data['status'] || 'removed' == $data['status'] || 'completed' == $data['status'] ) && is_object( $this->gcal_api ) ) {
					$this->gcal_api->delete( $app_id );
				} else if (is_object($this->gcal_api) && $this->gcal_api->is_syncable_status($data['status'])) {
					$this->gcal_api->update( $app_id ); // This also checks for event insert
				}
				if ('removed' === $data['status']) $this->send_removal_notification($app_id);
			}
			if ($update_result && $resend) {
				if ('removed' == $data['status']) do_action( 'app_removed', $app_id );
				//else $this->send_confirmation( $app_id );
			}
		}
		else {
			// Insert
			$insert_result = $wpdb->insert( $this->app_table, $data );
			if ( $insert_result && $resend && empty($email_sent) ) {
				$email_sent = $this->send_confirmation( $wpdb->insert_id );
			}
			if ( $insert_result && is_object($this->gcal_api) && $this->gcal_api->is_syncable_status($data['status'])) {
				$this->gcal_api->insert( $app_id );
			}
		}

		do_action('app-appointment-inline_edit-after_save', ($update_result ? $app_id : $wpdb->insert_id), $data);

		if ($resend && 'removed' != $data['status'] && empty($email_sent) ) {
			$email_sent = $this->send_confirmation( $app_id );
		}

		if ( ( $update_result || $insert_result ) && $data['user'] && defined('APP_USE_LEGACY_USERDATA_OVERWRITING') && APP_USE_LEGACY_USERDATA_OVERWRITING ) {
			if ( $data['name'] )
				update_user_meta( $data['user'], 'app_name',  $data['name'] );
			if (  $data['email'] )
				update_user_meta( $data['user'], 'app_email', $data['email'] );
			if ( $data['phone'] )
				update_user_meta( $data['user'], 'app_phone', $data['phone'] );
			if ( $data['address'] )
				update_user_meta( $data['user'], 'app_address', $data['address'] );
			if ( $data['city'] )
				update_user_meta( $data['user'], 'app_city', $data['city'] );

			do_action( 'app_save_user_meta', $data['user'], $data );
		}

		do_action('app-appointment-inline_edit-before_response', ($update_result ? $app_id : $wpdb->insert_id), $data);

		$result = array(
			'app_id' => 0,
			'message' => '',
		);
		if ( $update_result ) {
			// Log change of status
			if ( $data['status'] != $app->status ) {
				$this->log( $this->log( sprintf( __('Status changed from %s to %s by %s for appointment ID:%d','appointments'), $app->status, $data["status"], $current_user->user_login, $app->ID ) ) );
			}
			$result = array(
				'app_id' => $app->ID,
				'message' => __('<span style="color:green;font-weight:bold">Changes saved.</span>', 'appointments'),
			);
		} else if ( $insert_result ) {
			$result = array(
				'app_id' => $wpdb->insert_id,
				'message' => __('<span style="color:green;font-weight:bold">Changes saved.</span>', 'appointments'),
			);
		} else {
			$message = $resend && !empty($data['status']) && $removed != $data['status']
				? sprintf('<span style="color:green;font-weight:bold">%s</span>', __('Confirmation message (re)sent', 'appointments'))
				: sprintf('<span style="color:red;font-weight:bold">%s</span>', __('Record could not be saved OR you did not make any changes!', 'appointments'))
			;
			$result = array(
				'app_id' => ($update_result ? $app_id : $wpdb->insert_id),
				'message' => $message,
			);
		}

		$result = apply_filters('app-appointment-inline_edit-result', $result, ($update_result ? $app_id : $wpdb->insert_id), $data);
		die(json_encode($result));
	}

	 // For future use
	function reports() {
	}


	function get_same_dat_and_client_at_one_row(){
		//$get_transactions = $this->get_transactions($type, $startat, $num);
		
		}

	/**
	 *	Get transaction records
	 *  Modified from Membership plugin by Barry
	 */
	function get_transactions($type, $startat, $num) {

		switch($type) {

			case 'past':
						$sql = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->transaction_table} WHERE transaction_status NOT IN ('Pending', 'Future') ORDER BY transaction_ID DESC  LIMIT %d, %d", $startat, $num );
						break;
			case 'pending':
						$sql = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->transaction_table} WHERE transaction_status IN ('Pending') ORDER BY transaction_ID DESC LIMIT %d, %d", $startat, $num );
						break;
			case 'future':
						$sql = $this->db->prepare( "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->transaction_table} WHERE transaction_status IN ('Future') ORDER BY transaction_ID DESC LIMIT %d, %d", $startat, $num );
						break;

		}

		return $this->db->get_results( $sql );

	}

	/**
	 *	Find if a Paypal transaction is duplicate or not
	 */
	function duplicate_transaction($app_id, $amount, $currency, $timestamp, $paypal_ID, $status, $note,$content=0) {
		$sql = $this->db->prepare( "SELECT transaction_ID FROM {$this->transaction_table} WHERE transaction_app_ID = %d AND transaction_paypal_ID = %s AND transaction_stamp = %d LIMIT 1 ", $app_id, $paypal_ID, $timestamp );

		$trans = $this->db->get_var( $sql );
		if(!empty($trans)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 *	Save a Paypal transaction to the database
	 */


function count_ids_for_sending_email($custom){
		if(!empty($custom)){
			//print_r($_POST['custom']);
			
			$values = explode( "|", $custom );
			$worker = $values[5];
			if(!empty($worker)){
				//$arrs= explode( "|", $worker );
				return  $worker;
			}
		}
	}	
	
	
function collect_email_address($custom){
		if(!empty($custom)){
			//print_r($_POST['custom']);
			$values= explode( "|", $custom );
			$email = $values[4];
				return  $email;
		}
	}	 

function mail_massage_name($custom){
			$value= explode( "|", $custom );
			$value = $value[12];
			return $value;
	}

	
function mail_massage($custom){
		if(!empty($custom)){
			//print_r($_POST['custom']);
			$values= explode( "|", $custom );
			$date = $values[6];
			$start = date('H:i:s', strtotime($values[7]));
			$end =  date('H:i:s', strtotime($values[8]));
			$num_att =  $values[0];
			$res_activity =  $values[13];
			$email_massage = '';
			$email_massage .= 'You have a appointment registered. Here is the information.'. '<br>';;
			$email_massage .= 'Date: '.  $date. '<br>';
			$email_massage .= 'Start: '.  $start. '<br>';
			$email_massage .= 'End: '.  $end. '<br>';
			$email_massage .= 'Number of attendees: '.  $num_att. '<br>';
			$email_massage .= 'Status: '.  $values[2] . '<br>';;
			if($res_activity==1){
			$email_massage .= 'Reservation Code: '.  $values[1] . '<br>';
		//	$email_massage .= 'Reservation Number: '.  $values[1] . '/n';
			}
			
			$email_massage .= 'Price : '.  $values[10] . '<br>';
			$email_massage .= 'Price Total: '.  $values[9] . '<br>';
			$email_massage .= ''. '<br>';
			$email_massage .= 'Thank You '. '<br>';
			return  $email_massage;
		}
	}	 
	
	
/*function collect_reservation_code($custom){
		if(!empty($custom)){
			//print_r($_POST['custom']);
			$values= explode( "|", $custom );
			$reservation_code = $values[5];
				return  $reservation_code;
		}
	}	 
*/	
	
	 

function set_uniquer_ids_for_sending_email($custom){
		if(!empty($custom)){
			//print_r($_POST['custom']);
			
			$values= explode( ",", $custom );
			$worker 		= $values[5];
			if(!empty($worker)){
				$arrs= explode( "|", $worker );
				//return  str_replace("|",",",$arrs);
				return  $arrs;
			}
		}
	}	 
//get the start time
function get_start_date_for_sending_email($custom){
		if(!empty($custom)){
			//print_r($_POST['custom']);
			
			$values		= explode( "|", $custom );
			$date 		= $values[6];
			$start		= date('H:i:s', strtotime($values[7]));
			return  $date .' '. $start;
		}
	}	 



/*function semt_email_service_provider_by_id($custom){
		if(!empty($custom)){
			//print_r($_POST['custom']);
			
			$values = explode( "|", $custom );
			//$worker = $values[0];
			$worker_id = $values[5];
			$date = $values[6];
			$time = $values[6];
			if(!empty($worker_id)){
				$arrs= explode( ",", $worker_id );
				foreach ($arrs as $arry){
				$user_mail = 	get_userdata( $arry );
				$massage = 'You have an Appointment at '.$date.' '.$time;
				wp_mail( $user_mail->email, 'You have an Appointment', $massage );
				//return  $worker;
			}
		}
	}	
*/





function receive_paypal_2($custom){
	
	if(!empty($custom)){
		//print_r($_POST['custom']);
		
		$values= explode( "|", $custom );
		$apps_num = $values[0];
		$reservation_code = $values[1];
		$status = $values[2];
		$status_standing = $values[3];
		$email = $values[4];
		global $wpdb;
		$result = $wpdb->query(
	"
	UPDATE wp_app_appointments 
	SET `status` =  '$status'
	WHERE `reservation_code` LIKE  '$reservation_code' AND `status` LIKE '$status_standing' 
	LIMIT $apps_num
	"
);


		if($result=== false){
			//wp_mail('shimion_b@yahoo.com', 'Heisezip problem with update after paypasl payment', 'This is problem with ');
		echo 'SOrry';
		}else{
		echo 'enjoy';
			}


/*for($x=1; $x<=$apps_num; $x++){
		global $wpdb;
		$wpdb->update( 
			'wp_app_appointments', 
			array( 
				'status' => $status,	// string
			), 
			array( 'reservation_code' => $reservation_code, 'status' => $status_standing ), 
			array( 
				'%s',	// value1
			), 
			array( '%s' ) 
		);
	}
*/

 update_option('check_value_custom', $custom);


	}
	
}





	 
	 
	 
	 
	function record_transaction($app_id, $amount, $currency, $timestamp, $paypal_ID, $status, $note) {

		$data = array();
		$data['transaction_app_ID'] = $app_id;
		$data['transaction_paypal_ID'] = $paypal_ID;
		$data['transaction_stamp'] = $timestamp;
		$data['transaction_currency'] = $currency;
		$data['transaction_status'] = $status;
		$data['transaction_total_amount'] = (int) round($amount * 100);
		$data['transaction_note'] = $note;

		$existing_id = $this->db->get_var( $this->db->prepare( "SELECT transaction_ID FROM {$this->transaction_table} WHERE transaction_paypal_ID = %s LIMIT 1", $paypal_ID ) );

		if(!empty($existing_id)) {
			// Update
			$this->db->update( $this->transaction_table, $data, array('transaction_ID' => $existing_id) );
		} else {
			// Insert
			$this->db->insert( $this->transaction_table, $data );
		}

	}

	function get_total() {
		return $this->db->get_var( "SELECT FOUND_ROWS();" );
	}

	function transactions() {

		global $page, $action, $type;

		wp_reset_vars( array('type') );

		if(empty($type)) $type = 'past';

		?>
		<div class='wrap'>
			<div class="icon32" style="margin:8px 0 0 0"><img src="<?php echo $this->plugin_url . '/images/transactions.png'; ?>" /></div>
			<h2><?php echo __('Transactions','appointments'); ?></h2>

			<ul class="subsubsub">
				<li><a href="<?php echo add_query_arg('type', 'past'); ?>" class="rbutton <?php if($type == 'past') echo 'current'; ?>"><?php  _e('Recent transactions', 'appointments'); ?></a> | </li>
				<li><a href="<?php echo add_query_arg('type', 'pending'); ?>" class="rbutton <?php if($type == 'pending') echo 'current'; ?>"><?php  _e('Pending transactions', 'appointments'); ?></a> | </li>
				<li><a href="<?php echo add_query_arg('type', 'future'); ?>" class="rbutton <?php if($type == 'future') echo 'current'; ?>"><?php  _e('Future transactions', 'appointments'); ?></a></li>
			</ul>

			<?php
				$this->mytransactions($type);

			?>
		</div> <!-- wrap -->
		<?php

	}

	function mytransactions($type = 'past') {

		if(empty($_GET['paged'])) {
			$paged = 1;
		} else {
			$paged = ((int) $_GET['paged']);
		}

		$startat = ($paged - 1) * 50;

		$transactions = $this->get_transactions($type, $startat, 50);
		$total = $this->get_total();

		$columns = array();

		$columns['subscription'] = __('App ID','appointments');
		$columns['user'] = __('User','appointments');
		$columns['date'] = __('Date/Time','appointments');
		$columns['service'] = __('Service','appointments');
		$columns['amount'] = __('Amount','appointments');
		$columns['transid'] = __('Transaction id','appointments');
		$columns['status'] = __('Status','appointments');

		$trans_navigation = paginate_links( array(
			'base' => add_query_arg( 'paged', '%#%' ),
			'format' => '',
			'total' => ceil($total / 50),
			'current' => $paged
		));

		echo '<div class="tablenav">';
		if ( $trans_navigation ) echo "<div class='tablenav-pages'>$trans_navigation</div>";
		echo '</div>';
		?>

			<table cellspacing="0" class="widefat fixed">
				<thead>
				<tr>
				<?php
					foreach($columns as $key => $col) {
						?>
						<th class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>" scope="col"><?php echo $col; ?></th>
						<?php
					}
				?>
				</tr>
				</thead>

				<tfoot>
				<tr>
				<?php
					reset($columns);
					foreach($columns as $key => $col) {
						?>
						<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>" scope="col"><?php echo $col; ?></th>
						<?php
					}
				?>
				</tr>
				</tfoot>

				<tbody>
					<?php
					if($transactions) {
						foreach($transactions as $key => $transaction) {
							?>
							<tr valign="middle" class="alternate">
								<td class="column-subscription">
									<?php
										echo $transaction->transaction_app_ID;
									?>

								</td>
								<td class="column-user">
									<?php
										echo $this->get_client_name( $transaction->transaction_app_ID );
									?>
								</td>
								<td class="column-date">
									<?php
										echo date_i18n($this->datetime_format, $transaction->transaction_stamp);

									?>
								</td>
								<td class="column-service">
								<?php
								$service_id = $this->db->get_var($this->db->prepare("SELECT service FROM {$this->app_table} WHERE ID=%d",$transaction->transaction_app_ID));
								echo $this->get_service_name( $service_id );
								?>
								</td>
								<td class="column-amount">
									<?php
										$amount = $transaction->transaction_total_amount / 100;

										echo $transaction->transaction_currency;
										echo "&nbsp;" . number_format($amount, 2, '.', ',');
									?>
								</td>
								<td class="column-transid">
									<?php
										if(!empty($transaction->transaction_paypal_ID)) {
											echo $transaction->transaction_paypal_ID;
										} else {
											echo __('None yet','appointments');
										}
									?>
								</td>
								<td class="column-status">
									<?php
										if(!empty($transaction->transaction_status)) {
											echo $transaction->transaction_status;
										} else {
											echo __('None yet','appointments');
										}
									?>
								</td>
							</tr>
							<?php
						}
					} else {
						$columncount = count($columns);
						?>
						<tr valign="middle" class="alternate" >
							<td colspan="<?php echo $columncount; ?>" scope="row"><?php _e('No Transactions have been found, patience is a virtue.','appointments'); ?></td>
						</tr>
						<?php
					}
					?>

				</tbody>
			</table>
		<?php
	}

	function reached_ceiling () {
		return false;
	}

}
}

define('APP_PLUGIN_DIR', dirname(__FILE__), true);
define('APP_PLUGIN_FILE', __FILE__, true);

require_once APP_PLUGIN_DIR . '/includes/default_filters.php';
require_once APP_PLUGIN_DIR . '/includes/class_app_install.php';
require_once APP_PLUGIN_DIR . '/includes/class_app_timed_abstractions.php';
require_once APP_PLUGIN_DIR . '/includes/class_app_roles.php';
require_once APP_PLUGIN_DIR . '/includes/class_app_codec.php';
require_once APP_PLUGIN_DIR . '/includes/class_app_shortcodes.php';
require_once APP_PLUGIN_DIR . '/includes/class_app_addon_helper.php';

App_Installer::serve();

App_AddonHandler::serve();
App_Shortcodes::serve();

global $appointments;
$appointments = new Appointments();

if (is_admin()) {
	require_once APP_PLUGIN_DIR . '/includes/support/class_app_tutorial.php';
	App_Tutorial::serve();

	require_once APP_PLUGIN_DIR . '/includes/support/class_app_admin_help.php';
	App_AdminHelp::serve();

	// Setup dashboard notices
	if (file_exists(APP_PLUGIN_DIR . '/includes/wpmudev-dash-notification.php')) {
		global $wpmudev_notices;
		if (!is_array($wpmudev_notices)) $wpmudev_notices = array();
		$wpmudev_notices[] = array(
			'id' => 679841,
			'name' => 'Appointments+',
			'screens' => array(
				'appointments_page_app_settings',
				'appointments_page_app_shortcodes',
				'appointments_page_app_faq',
			),
		);
		require_once APP_PLUGIN_DIR . '/includes/wpmudev-dash-notification.php';
	}
	// End dash bootstrap
}

/**
 * Find blogs and uninstall tables for each of them
 * @since 1.0.2
 * @until 1.4.1
 */
if ( !function_exists( 'wpmudev_appointments_uninstall' ) ) {
	function wpmudev_appointments_uninstall () { do_action('app-core-doing_it_wrong', __FUNCTION__); }
}

if ( !function_exists( '_wpmudev_appointments_uninstall' ) ) {
	function _wpmudev_appointments_uninstall () { do_action('app-core-doing_it_wrong', __FUNCTION__); }
	function wpmudev_appointments_rmdir ($dir) { do_action('app-core-doing_it_wrong', __FUNCTION__); }
}
