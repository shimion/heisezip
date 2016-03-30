<?php
/** Step 2 (from text above). */
add_action( 'admin_menu', 'working_shedule_function_declear' );

/** Step 1. */
function working_shedule_function_declear() {
	add_menu_page( 'Assign Tour Guides', 'Assign Tour Guides', 'manage_options', 'assign-tour-guides', 'working_shedule_function' );
}
function working_shedule_function(){
	
/*if ( !current_user_can( 'author' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}	
*/	
wp_enqueue_style( 'eventCalendar_theme', plugins_url('css/eventCalendar_theme.css', __FILE__) ); 
wp_enqueue_style( 'eventCalendar', plugins_url('css/eventCalendar.css', __FILE__) ); 
wp_enqueue_style( 'eventCalendar_theme_responsive', plugins_url('css/eventCalendar_theme_responsive.css', __FILE__) ); 
wp_enqueue_script( 'jquery.eventCalendar', plugins_url('js/jquery.eventCalendar.js', __FILE__), array(), '1.0.0', false );
wp_enqueue_script( 'moment', plugins_url('js/moment.js', __FILE__), array(), '1.0.0', false );
?>

<?php global $appointments, $wpdb; ?>

<?php _e( '<h2>Assign Tour Guides</h2>', 'appointments'); ?>
<?php _e( '<p>Please click on the caleder you want to view</p>', 'appointments'); ?>
<br />
<br />
<?php
//$workers = $wpdb->get_results( "SELECT * FROM " . $appointments->workers_table . " " );
?>
&nbsp;

<script type="text/javascript">
jQuery(document).ready(function($){
	$('#app_provider_id').change(function(){
		var app_provider_id = $('#app_provider_id option:selected').val();
		window.location.href = "<?php echo admin_url('admin.php?page=app_settings&tab=working_hours')?>" + "&app_provider_id=" + app_provider_id;
	});
});
</script>
<form method="post" action="" >
	<table class="widefat fixed " style="border:none;" >
	<td style="width:509px;">
    
    <div style="max-width:509px; width:100%;">
    
				<div id="eventCalendarInline"></div>
                
				<script>
					jQuery(document).ready(function($) {
						var eventsInline = [ <?php echo $appointments->get_event_dates_all(); ?> ];

						$("#eventCalendarInline").eventCalendar({
							jsonData: eventsInline,
							jsonDateFormat: 'human'  // 'YYYY-MM-DD HH:MM:SS'
						});
						//e.preventDefault();
						$( "#eventCalendarInline" ).on('click', '.eventCalendar-day', function() {
							//alert($(this).attr( "date" ));
							
							var date_each = $(this).attr( "date" );
								var three_data = {action: "check_allinfo", date: date_each, nonce: "<?php wp_create_nonce() ?>"};	
								$.post(ajaxurl, three_data, function(response) {
										
											if ( response && response.error ){
												alert(response.error);
												
											}else{
												//alert('THanks');
												//$("#add_date_cll").attr("date", response.data);
												//$("#add_date_cll").addClass("eventCalendar-day");
												$( ".content_section" ).closest( ".content_section" ).remove();
												var button_place = response.button_value;	
												$(".button-place").html(response.button_value);
												$(".eventCalendar-subtitle").attr("data", response.data);
												$( "#apply_form" ).append( response.form );
											}
									
									},'json');
									
									

						});						

						$( ".widefat" ).on('click', '#submit_3line_hours_delete', function() {
							var data = $(".eventCalendar-subtitle").attr( "data" );
							var delete_user_data = {action: "delete_user_app", data: data, nonce: "<?php wp_create_nonce() ?>"};	
								$.post(ajaxurl, delete_user_data, function(response) {
										
											if ( response && response.error ){
												alert(response.error);
												
											}else{
												alert(response.result);
												<?php $url =  admin_url('admin.php?page=working-calender'); ?>
												window.location.href='<?php echo $url; ?>';
												
											}
									},'json');
											
						});						

						$( ".widefat" ).on('click', '#submit_3line_hours', function() {
							
							var data = $(".eventCalendar-subtitle").attr( "data" );
							//alert(date_each);
							var start_start = $("#start_start :selected").val();
							var start_end = $("#start_end :selected").val();
							var break_start = $("#break_start :selected").val();
							var break_end = $("#break_end :selected").val();
							var enable_break = $("#enable_break :selected").val();
							var add_save_user_data = {action: "add_save_user", data: data, start_start: start_start, start_end: start_end, break_start: break_start, break_end: break_end, enable_break: enable_break, nonce: "<?php wp_create_nonce() ?>"};	
								$.post(ajaxurl, add_save_user_data, function(response) {
										
											if ( response && response.error ){
												alert(response.error);
												
											}else{
												alert(response.success);
												<?php $url =  admin_url('admin.php?page=working-calender'); ?>
												window.location.href='<?php echo $url; ?>';
												
											}
									},'json');
											
							
						});



						$(".button-place").click(function(){
							//alert('HELLO');
							
							var data = $(this).attr( "data" );
								var add_new = {action: "add_new_data", data: data, nonce: "<?php wp_create_nonce() ?>"};	
								$.post(ajaxurl, add_new, function(response) {
										
											if ( response && response.error ){
												alert(response.error);
												
											}else{
												//alert(response.button_value);
												
											}
									
									},'json');
							
							//$( "#apply_form" ).replaceWith( '' );
							
							});

						
						
						
					});
				</script>

	</div>
    
    
    
    </td>
    
    <td>
    <div id="apply_form"></div>
    </td>
	</tr>
	</table>

	<input type="hidden" name="worker" value="0" />
	<input type="hidden" name="location" value="0" />
	<input type="hidden" name="action_app" value="save_working_hours" />
	<?php wp_nonce_field( 'update_app_settings', 'app_nonce' ); ?>
	<!--<p class="submit">
	<input type="submit" class="button-primary" value="<?php _e('Save Working Hours', 'appointments') ?>" />
	</p>-->

</form>

<style>
.widefat, .widefat div{ font-size:14px;}
.widefat tr:nth-child(odd) {
    background-color: #f5f1f1;
}
.my_clicked{background-color: #728CEC;    color: #FFF;}
.widefat .bold{ font-weight:bold; color:#fff;}
.btn{ margin-right:5px;}
</style>


<?php
}
?>