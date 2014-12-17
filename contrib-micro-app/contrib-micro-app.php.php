<?php
/*
Plugin Name: Contrib Micro App
Plugin URI:
Description: Contrib Micro App for Discussion FORUM
Author: Zipsite
Version: 1
Author URI:
*/
add_action('init','init_micro_app');
function init_micro_app(){
	global $current_user;

	$user_roles = $current_user->roles;
	$user_role = array_shift($user_roles);
	
	if($user_role=='administrator'){
		add_action('admin_menu', 'micro_app_menu');
		function micro_app_menu() {
			add_menu_page(__('Micro App'), __('Micro App'), 'read', 'micro_app_menu');
			add_submenu_page('micro_app_menu', __('Template'), __('Template'), 'read', 'micro_app_menu', 'manage_micro_app');
		}		
	}
	
	//widget----------------------------------------------------------------------------------
	
	function micro_app_widget_control() {
        global $wpdb;
        $options = $newoptions = get_option('micro_app_widget_title');
        if ( $_POST['micro_app-widget-submit'] ) {
            $newoptions = $_POST['micro_app-widget-title'];
           
        }
        if ( $options != $newoptions ) {
            $options = $newoptions;
            update_option('micro_app_widget_title', $options);
        }
		
        ?>
        <div style="text-align:left">
			
			<label for="micro_app-widget-title" style="line-height:35px;display:block;"><?php _e('Title'); ?>:<br />
			<input id="micro_app-widget-title" name="micro_app-widget-title" value="<?php echo (!empty($options)?$options:'') ; ?>" type="text" style="width:95%;">
			<input type="hidden" name="micro_app-widget-submit" id="micro_app-widget-submit" value="1" />
        </div>
        <?php
    }
	
	function micro_app_widget($args) {
        global $wpdb, $current_site;
        extract($args);

        $micro_app_widget_title = get_option('micro_app_widget_title');

		echo $before_widget;
		echo $before_title;
		echo ($micro_app_widget_title);
		echo $after_title;
		
		$code = '<script type="text/javascript" src="http://tools.contrib.com/cwidget/forum?f=all&l=10"></script>';
		$micro_app_shortcode = get_option('microapp_shortcode');
		$micro_app_shortcode = empty($micro_app_shortcode)?$code:stripslashes($micro_app_shortcode);
		
		echo $micro_app_shortcode;        
        echo $after_widget;
    }

    register_sidebar_widget(array(__('Contrib Micro App'), 'widgets'), 'micro_app_widget');
    register_widget_control(array(__('Contrib Micro App'), 'widgets'), 'micro_app_widget_control');
	//------------------------------------------------------------------------------------------
	
}

function manage_micro_app(){
	global $current_user;

	$user_roles = $current_user->roles;
	$user_role = array_shift($user_roles);
	
	if($user_role=='administrator'){
		if(isset($_POST['save'])){
			
			$options = get_option('vertical');				
			$newoptions = $_POST['vertical'];				
			if ( $options != $newoptions )update_option('vertical', $newoptions);
			
			$options = get_option('limit');				
			$newoptions = $_POST['limit'];				
			if ( $options != $newoptions )update_option('limit', $newoptions);
			
			$options = get_option('microapp_shortcode');				
			$newoptions = $_POST['microapp_shortcode'];				
			if ( $options != $newoptions )update_option('microapp_shortcode', $newoptions);
			
		}
		?>
			<style>
				form input.txt {
					width:100px;
					text-align: right;
				}
				h3{
					margin-bottom:1px;
				}
			</style>
			<h1>Contrib Micro App</h1>
			
			<form method="post" action="">
				<div class="update-nag">
					<label><b>Vertical</b></label>&nbsp;&nbsp;
						<?php
							$url = "http://www.contrib.com/api/getCategories";
							$curl = curl_init();
							curl_setopt ($curl, CURLOPT_URL, $url);
							curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
							$result = curl_exec ($curl);
							curl_close ($curl); 
							
							$result = (json_decode($result));
							
							$vertical = get_option('vertical');
						?>
						<select id="appforumcat" name="vertical" style="width:400px">
							<option <?=$vertical=="all"?"selected":"";?> value="all">All</option>
							<?php								
								foreach($result->result as $item){
							?>
								<option value="<?=$item->id?>" <?=$vertical==$item->id?"selected":"";?> ><?=$item->name?></option>
							<?php
								}
							?>
						</select><br>
						<?php
							$code = '<script type="text/javascript" src="http://tools.contrib.com/cwidget/forum?f=all&l=10"></script>';
							$micro_app_shortcode = get_option('microapp_shortcode');
							$micro_app_shortcode = empty($micro_app_shortcode)?$code:stripslashes($micro_app_shortcode);
						?>
						<label><b>Limit</b></label>&nbsp;&nbsp;
						<input style="width:400px" type="text" value="<?php echo get_option('limit');?>" id="appforumlimit" name="limit" class=""><br>
				</div>
				
				<div class="update-nag">
					<h3><b>Shortcode</b></h3>
					<label><i style="color:red;">copy/paste the shortcode in your page/post.</i></label><br>
					<textarea autocomplete=false; id="shortcode" name="shortcode" cols="80" rows="4" class="">[micro_app]</textarea><br>
					<input id="appforum-code" name="microapp_shortcode" type='hidden' value='<?php echo $micro_app_shortcode;?>' />
				</div>
				
				<br>
				<br>
				
				<input id="save" name="save" type="submit" value="Save" />
			</form>
			
			<script type="text/javascript">
			jQuery(document).ready(function(){
				jQuery('#shortcode').focus(function(){
					setTimeout(function(){
						jQuery('#shortcode').select();
					},100);
				});
				
				jQuery('#shortcode').keydown(function(e){
					if (e.ctrlKey === true) {
						 return;
					} 
					e.preventDefault();
				});
				
				jQuery("#appforumlimit").keydown(function (e) {
					// Allow: backspace, delete, tab, escape, enter and .
					if (jQuery.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
						 // Allow: Ctrl+A
						(e.keyCode == 65 && e.ctrlKey === true) || 
						 // Allow: home, end, left, right, down, up
						(e.keyCode >= 35 && e.keyCode <= 40)) {
							 // let it happen, don't do anything
							 return;
					}
					// Ensure that it is a number and stop the keypress
					if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
						e.preventDefault();
					}
				});
				
				jQuery('#appforumcat').change(function(){
					var cat = jQuery(this).val();
					var limit = jQuery('#appforumlimit').val();
					if (cat == "all"){
					   code = '<script type="text/javascript" src="http://tools.contrib.com/cwidget/forum?f=all&l='+limit+'"><\/script>';
					}else {
					  code = '<script type="text/javascript" src="http://tools.contrib.com/cwidget/forum?f=category&c='+cat+'&l='+limit+'"><\/script>';
					}
					jQuery('#appforum-code').val(code);
				});		
				
				jQuery('#appforumlimit').keyup(function(){
					var limit = jQuery(this).val();
					var cat = jQuery('#appforumcat').val();
					if (cat == "all"){
					   code = '<script type="text/javascript" src="http://tools.contrib.com/cwidget/forum?f=all&l='+limit+'"><\/script>';
					}else {
					  code = '<script type="text/javascript" src="http://tools.contrib.com/cwidget/forum?f=category&c='+cat+'&l='+limit+'"><\/script>';
					}
					jQuery('#appforum-code').val(code);
				});		
			});
			</script>
		<?php
	}
}
add_shortcode('micro_app', 'micro_app');

function micro_app()
{
	$code = '<script type="text/javascript" src="http://tools.contrib.com/cwidget/forum?f=all&l=10"></script>';
	$micro_app_shortcode = get_option('microapp_shortcode');
	$micro_app_shortcode = empty($micro_app_shortcode)?$code:stripslashes($micro_app_shortcode);
	return $micro_app_shortcode;
}
?>