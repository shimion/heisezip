<?php
/** Step 2 (from text above). */
add_action( 'admin_menu', 'menu_ture_time' );

/** Step 1. */
function menu_ture_time() {
	add_menu_page( 'Tour Time', 'Tour Time', 'update_core', 'ture-time', 'ture_time' );
}


function ture_time(){


?>
<div class="wrap">
<?php
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

<?php _e( '<h2>Ture Time</h2>', 'appointments'); ?>

<?php 
if($_REQUEST['update']=='yes')
_e( '<p>Updated</p>', 'appointments'); ?>
<?php print_r($_REQUEST); ?>
<br />
<br />
<?php
$workers = $wpdb->get_results( "SELECT * FROM " . $appointments->workers_table . " " );
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
	<table class="widefat fixed">
	<td>
    
    <div style="max-width:509px; width:100%;">
    
				<div id="eventCalendarInline"></div>
                <div id="apply_form"></div>
				<script>
					jQuery(document).ready(function($) {
						var eventsInline = [ <?php echo $appointments->get_event_dates(); ?> ];

						$("#eventCalendarInline").eventCalendar({
							jsonData: eventsInline,
							jsonDateFormat: 'human'  // 'YYYY-MM-DD HH:MM:SS'
						});
						//e.preventDefault();
						$( "#eventCalendarInline" ).on('click', '.eventCalendar-day', function() {
							//alert($(this).attr( "date" ));
							
							var date_each = $(this).attr( "date" );
						//	$(".eventCalendar-subtitle").attr("date", date_each);
								var three_data = {action: "check_time_frame", date: date_each, nonce: "<?php wp_create_nonce() ?>"};	
								$.post(ajaxurl, three_data, function(response) {
										
											if ( response && response.error ){
												alert(response.error);
												
											}else{
												//alert('THanks');
												$( "#form" ).closest( "#form" ).remove();
												var button_place = response.button_value;	
												$(".button-place").html(response.button_value);
												$(".eventCalendar-subtitle").attr("data", response.data);
												$( "#apply_form" ).append( response.form );
											}
									
									},'json');
									
									

						});		
						
						


						$( ".widefat" ).on('click', '.add_more_timeframe', function() {
							var counting = $('.add_more_timeframe').attr("counting");
							var counting = parseInt(counting) + 1;
							
							var add_element ='<tr id="counting_tr"><td><p>Start:</p><select name="hours['+counting+'][start]" id="start_start" autocomplete="off"><option value="00:00" selected="selected">12:00 am</option><option value="01:00">1:00 am</option><option value="02:00">2:00 am</option><option value="03:00">3:00 am</option><option value="04:00">4:00 am</option><option value="05:00">5:00 am</option><option value="06:00">6:00 am</option><option value="07:00">7:00 am</option><option value="08:00">8:00 am</option><option value="09:00">9:00 am</option><option value="10:00">10:00 am</option><option value="11:00">11:00 am</option><option value="12:00">12:00 pm</option><option value="13:00">1:00 pm</option><option value="14:00">2:00 pm</option><option value="15:00">3:00 pm</option><option value="16:00">4:00 pm</option><option value="17:00">5:00 pm</option><option value="18:00">6:00 pm</option><option value="19:00">7:00 pm</option><option value="20:00">8:00 pm</option><option value="21:00">9:00 pm</option><option value="22:00">10:00 pm</option><option value="23:00">11:00 pm</option></select></td><td><p>End:</p><select id="start_end" name="hours['+counting+'][end]" autocomplete="off"><option value="00:00" selected="selected">12:00 am</option><option value="01:00">1:00 am</option><option value="02:00">2:00 am</option><option value="03:00">3:00 am</option><option value="04:00">4:00 am</option><option value="05:00">5:00 am</option><option value="06:00">6:00 am</option><option value="07:00">7:00 am</option><option value="08:00">8:00 am</option><option value="09:00">9:00 am</option><option value="10:00">10:00 am</option><option value="11:00">11:00 am</option><option value="12:00">12:00 pm</option><option value="13:00">1:00 pm</option><option value="14:00">2:00 pm</option><option value="15:00">3:00 pm</option><option value="16:00">4:00 pm</option><option value="17:00">5:00 pm</option><option value="18:00">6:00 pm</option><option value="19:00">7:00 pm</option><option value="20:00">8:00 pm</option><option value="21:00">9:00 pm</option><option value="22:00">10:00 pm</option><option value="23:00">11:00 pm</option></select></td><td></td></tr>';
							 $("#table_wapper").append(add_element);
							 $(".add_more_timeframe").attr("counting",counting);
							 	
								
							// alert(counting);							
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
</div>
<?php
}
?>