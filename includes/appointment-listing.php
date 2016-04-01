<?php
/** Step 2 (from text above). */
add_action( 'admin_menu', 'menu_appointment_listing' );

/** Step 1. */
function menu_appointment_listing() {
	add_menu_page( 'Tour Guide Availability', 'Tour Guide Availability', 'update_core', 'appointment-listing', 'appointment_listing' );
}


function appointment_listing(){


?>
<div class="wrap">
<h2>Tour Guide Availability	<a href="#" id="add_block" data-toggle="modal" data-target="#myModaledit" class="add-new-h2">Add New</a></h2>
<table cellspacing="0" class="widefat">
		<thead>
				<tr>
						<th style=""></th>
								<th>Provider</th>
								<th>Date/Time</th>
								<th>Hour Start</th>
								<th>Hour End</th>
								<th>Break Enable</th>
								<th>Break Start</th>
								<th>Break end</th>
								<th>Block</th>
				</tr>
		</thead>

		<tfoot>
				<tr>
						<th style=""></th>
								<th>Provider</th>
								<th>Date/Time</th>
								<th>Hour Start</th>
								<th>Hour End</th>
								<th>Break Enable</th>
								<th>Break Start</th>
								<th>Break end</th>
								<th>Block</th>
						</tr>

		</tfoot>

		<tbody>

			<?php 
			global $wpdb;
			$workers = $wpdb->get_results( "SELECT * FROM  `wp_three_line_appointment`  WHERE  `date` >= CURRENT_TIMESTAMP ORDER BY `date` ASC, `working_hours_start` ASC" );
			
			foreach($workers as $worker){ 
			$user = get_user_by( 'id', $worker->worker );
			if($worker->block=='0'){
				$blc = 'background-color: #32E007;    border: 1px solid #32E007;    padding: 2px 10px;    border-radius: 3px;    color: #FFF;';
				}elseif($worker->block=='1'){
				$blc = 'background-color: #FD4444;    border: 1px solid #FD4444;    padding: 2px 10px;    border-radius: 3px;    color: #FFF;';
					}
			?>
				<tr>
						<th style=""></th>
								<th><?php echo $user->first_name . ' ' . $user->last_name; ?></th>
								<th><?php echo $worker->date; ?></th>
								<th><?php echo $worker->working_hours_start; ?></th>
								<th><?php echo $worker->working_hours_end; ?></th>
								<th><?php if(!empty($worker->break_enable)=="0"){echo 'Yes'; }elseif(!empty($worker->break_enable)=="1"){ echo 'No';}; ?></th>
								<th><?php echo $worker->break_start; ?></th>
								<th><?php echo $worker->break_end; ?></th>
								<th><button id="block_<?php echo $worker->ID; ?>" data-loading-text="Loading..." style=" <?php echo $blc; ?> "  value="<?php echo $worker->ID; ?>" >Lock</button> <button style="background-color: #444AFD;border: 1px solid #444AFD;padding: 2px 10px;border-radius: 3px;color: #FFF;" data-loading-text="Loading..."  value="<?php echo $worker->ID; ?>" id="block_edit_<?php echo $worker->ID; ?>"  date="<?php echo $worker->date; ?>" data-toggle="modal" data-target="#myModaledit" >Edit</button>
                                </th>
                                
				</tr>
                
<script>
					jQuery(document).ready(function($) {
						$( "#block_<?php echo $worker->ID; ?>" ).click(function() {
							var data = $(this).attr( "value" );
								//alert(data);
							var block = {action: "block", data: data, nonce: "<?php wp_create_nonce() ?>"};	
								$.post(ajaxurl, block, function(response) {
										
											if ( response && response.error ){
												alert(response.error);
												
											}else{
												alert('Updated...');
												$(this).attr("style",response.result);
												<?php $url =  admin_url('admin.php?page=appointment-listing'); ?>
												window.location.href='<?php echo $url; ?>';
												
											}
									},'json');
											
						});
						
						$( "#block_edit_<?php echo $worker->ID; ?>" ).click(function() {
							$(".edit_body").empty();
							$(".edit_body").append( '<img class="waiting" src="http://heisezip.webimpakt-green.com/wp-admin/images/wpspin_light.gif" alt="">' )
							var id = $(this).attr( "value" );
							var date = $(this).attr( "date" );
								//alert(data);
							var edit_block = {action: "edit_block_function", id: id, date: date, nonce: "<?php wp_create_nonce() ?>"};	
								$.post(ajaxurl, edit_block, function(response) {
										
											if ( response && response.error ){
												alert(response.error);
												
											}else{
												//alert('Updated...');
												if(response.form ){
													
													$(".waiting").hide();
													$(".edit_body").append( response.form )
													}else{
													$(".waiting").show();	
														}
												
												
												//$(this).attr("style",response.result);
												<?php  $url =  admin_url('admin.php?page=appointment-listing'); ?>
												//window.location.href='<?php echo $url; ?>';
												
											}
									},'json');
											
						});						
						
						
												
				});						

</script>
                
            
            <?php } ?>

		</tbody>
	</table>
</div>
<div class="modal fade" id="myModaledit" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="display: none;">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
        <h4 class="modal-title" id="myModalLabel">Appointment Information</h4>
      </div>
      <div class="modal-body edit_body">
      
      
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary sever_but_block">Save changes</button>
      </div>
    </div>
  </div>
</div>


<script>
					jQuery(document).ready(function($) {
						
						$( ".sever_but_block" ).click(function() {
							//$(".edit_body").empty();
							//$(".edit_body").append( '<img class="waiting" src="http://heisezip.webimpakt-green.com/wp-admin/images/wpspin_light.gif" alt="">' )
							var id = $(".form_block").attr( "p_id" );
							//var date = $(this).attr( "date" );
								//alert(data);
								
							//var data = $(".eventCalendar-subtitle").attr( "data" );
							//alert(date_each);
							var date_time = $("#date_time").val();
							var select_provider = $("#select_provider").val();
							var start_start = $("#start_start :selected").val();
							var start_end = $("#start_end :selected").val();
							var break_start = $("#break_start :selected").val();
							var break_end = $("#break_end :selected").val();
							var enable_break = $("#enable_break :selected").val();
							var add_save_user_data_block = {action: "add_save_user_block", id: id,  start_start: start_start, start_end: start_end, break_start: break_start, break_end: break_end, enable_break: enable_break, date_time: date_time, select_provider: select_provider, nonce: "<?php wp_create_nonce() ?>"};	
								$.post(ajaxurl, add_save_user_data_block, function(response) {
										
											if ( response && response.error ){
												alert(response.error);
												
											}else{
												alert(response.success);
												<?php $url =  admin_url('admin.php?page=appointment-listing'); ?>
												window.location.href='<?php echo $url; ?>';
												
											}
									},'json');
								
											
						});						
						
						
												
				});						


//add New Tour Guide Availability
					jQuery(document).ready(function($) {
						$( "#add_block" ).click(function() {
							$(".edit_body").empty();
							$(".edit_body").append( '<img class="waiting" src="http://heisezip.webimpakt-green.com/wp-admin/images/wpspin_light.gif" alt="">' )
								//alert(data);
							var edit_block = {action: "add_block_function", nonce: "<?php wp_create_nonce() ?>"};	
								$.post(ajaxurl, edit_block, function(response) {
										
											if ( response && response.error ){
												alert(response.error);
												
											}else{
												//alert('Updated...');
												if(response.form ){
													
													$(".waiting").hide();
													$(".edit_body").append( response.form )
													}else{
													$(".waiting").show();	
														}
												
												
												//$(this).attr("style",response.result);
												<?php  $url =  admin_url('admin.php?page=appointment-listing'); ?>
												//window.location.href='<?php echo $url; ?>';
												
											}
									},'json');
											
						});						
						
						
												
				});						

</script>



	
<?php
}
?>