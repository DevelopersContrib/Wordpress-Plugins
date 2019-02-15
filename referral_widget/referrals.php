<?php
/**
 * Plugin Name: Referrals
 * Plugin URI: 
 * Description: Referrals Plugin
 * Version: 1.0.0
 * Author: Contrib.com
 * Author URI: 
 */
 
class Referrals {
	private static $instance;
	
	public static function get_instance() {
		if (!session_id()) {
			session_start();
		}

		if ( null == self::$instance ) {
			self::$instance = new Referrals();
		} 
		return self::$instance;
	}
	
	private function __construct() {
		$user_id = get_current_user_id();
		if ($_SERVER['REQUEST_METHOD'] === 'POST') { // POST Login with existing API Key
			if(!empty($_POST['api_key'])){
				$redirect = get_site_url()."/wp-admin/admin.php?page=referrals_main_menu";
				$cancel = get_site_url()."/wp-admin/admin.php?page=referrals_login";
				$login_url = "https://www.referrals.com/signin/referralslogin?redirect_url=$redirect&client=".$_POST['api_key']."&cancel_url=$cancel";
				header('Location: '.$login_url);
				exit;
			}
		}
		
		if(isset($_GET['code'])){
			$token = $_GET['code'];
			$token = json_decode(base64_decode($token));
			if(!empty($token->access_token)) $_SESSION['referrals_token'] = $token->access_token;

			$referrals_api_key = get_user_meta( $user_id, 'referrals_api_key', true ); 
			
			if(empty($referrals_api_key)){ //create api key
				$headers = array('Accept: application/json');
		    	$url = "http://www.api.referrals.com/member/createapi";
		    	$param = array('token'=>$token->access_token);
			    $result =  $this->createApiCall($url, 'POST', $headers, $param);
		   	    $res = json_decode($result,true);
				if($res['success']===true){
					$referrals_api_key = $res['api_key'];
					add_user_meta( $user_id, 'referrals_api_key', $referrals_api_key);
				}
			}
			
			header('Location: '.get_site_url()."/wp-admin/admin.php?page=referrals_main_menu");
			exit;
		}	
		
		add_shortcode( 'referralswidget', array($this,'widget_func' ));
		add_action('wp_logout', array($this,'clear_referral_session'));
		
		if(!empty($_SESSION['referrals_token'])){
			add_action('admin_menu', array($this,'referrals_main_menu'));
		}else{
			add_action('admin_menu', array($this,'referrals_login'));
		}
	}
	
	public function widget_func( $atts ) {
		extract( shortcode_atts( array( 'code' => ''), $atts ) );

		$str = '<script id="referral-script" src="https://www.referrals.com/extension/widget.js?key='.$code.'" type="text/javascript"></script>';
		return $str;
	}
	
	public function clear_referral_session() {
		$_SESSION['referrals_token'] = null;
	}
	
	public function referrals_login() {
		add_menu_page(__('Referrals'), __('Referrals'), 'read', 'referrals_login', array($this,'referrals_login_page'), '', 7);
	}
		
	public function referrals_login_page(){
		$API_KEY= "a088239f8263dc8f";
		$user_id = get_current_user_id();
		$current_user = wp_get_current_user();
		
		?>
		<style>
			
		</style>
		<link href="<?=plugins_url('custom.css',__FILE__ )?>" rel="stylesheet" type="text/css" />
		<div class="Referrals-custom-plugin">
			<!-- tab section -->
			<div class="clear-section"></div>
			<div class="vcp-tab-main">
				<input id="tab1" type="radio" name="tabs" checked="">
				<label id="lcustomfooter" for="tab1">Referrals Settings</label>
				
				<section id="content1">
					<div class="vtab">
						<div class="row">
							<div class="col-md-12 text-center">
								<a href="http://referrals.com/">
									<img class="logo-img-ss" height="90" width="200" src="https://d1p6j71028fbjm.cloudfront.net/logos/logo-new-referral-1.png">
								</a>
								<h1>Is a Plugin for Referrals</h1>
							</div>
					   </div>
					</div>
					<?php
						$error = '';
						$login = false;
						$name = $current_user->user_firstname.(!empty($current_user->user_lastname)?' '.$current_user->user_lastname:'');
						$email = $current_user->user_email;
						$website = '';
						if ($_SERVER['REQUEST_METHOD'] === 'POST') {
							$name = $_POST['signup_name'];
							$email = $_POST['signup_email'];
							$password = $_POST['signup_password'];
							$website = $_POST['signup_website'];
							$error = '';
							if(empty($name)){
								$error = 'Name is required';
							}else if(empty($email)){
								$error = 'Invalid Email Address';
							}else if(empty($password)){
								$error = 'Password is required';
							}else if(empty($website)){
								$error = 'Website is required';
							}
							if(empty($error)){
								$headers = array('Accept: application/json');
								
								$url = "http://www.api.referrals.com/member/add?api_key=".$API_KEY;
								$param = array('name'=>$name,'email'=>$email,'name'=>$name,'password'=>$password,'website'=>$website);
								$result =  $this->createApiCall($url, 'POST', $headers, $param);
								$res = json_decode($result,true);
								
								if($res['success']==true){
									$login = true;
								}else{
									$error = $res['message'];
								}
							}
						}
					?>
					<div class="section-signup">
						<div class="container register">
							<div class="row justify-content-center">
								<div class="col-md-3 register-left">
									<img src="https://image.flaticon.com/icons/png/512/117/117992.png" alt="Brand.com"/>
									<h3>Welcome!</h3>
									<?php
										$referrals_api_key = get_user_meta( $user_id, 'referrals_api_key', true ); 
										$redirect = get_site_url()."/wp-admin/admin.php?page=referrals_main_menu";
										$cancel = get_site_url()."/wp-admin/admin.php?page=referrals_login";
										if(!$login){
											if(empty($referrals_api_key)){
												?>
												<div class="ahaa">Already have an account?</div>
												<form method="POST">
													<input style="width:100%" type="text" class="form-control" placeholder="Please enter api key" value="" id="api_key" name="api_key" required="">
													<button style="width:100%" class="btn btn-primary" type="submit">Login</button>
												</form>
												<?php
											}else{
												$login_url = "https://www.referrals.com/signin/referralslogin?redirect_url=$redirect&client=$referrals_api_key&cancel_url=$cancel";
									?>
												<div class="ahaa">Already have an account?</div>
												<a href="<?=$login_url?>" style="width:100%" class="btn btn-primary login">Login</a>
									<?php
											}
										}
									?>
								</div>
								<div class="col-md-6 register-right">	
									<?php
										if(!$login){
									?>
									<h3 class="register-heading">SIGN UP FOR FREE</h3>
									<?php
										}else{
									?>
									<h3 class="register-heading">Login to your account</h3>
									<?php
										}
									?>
									<div class="row register-form">
										<div class="col-md-12" id="signup-not-div" style="display:none">
											<div class="alert-error alert alert-danger" id="signup-not-msg">
												This is an error message!
											</div>
										</div>
										<div class="col-md-12">
											<?php
												if(!$login){
											?>
												<form id="form-reg" method="POST" action="">
													<div class="form-group">
														<input type="text" class="form-control" oninput="checkName();" placeholder="Your Name *" value="<?=$name;?>" id="signup_name" name="signup_name" value="" required />
													</div>	
													<div class="form-group">
														<input type="email" class="form-control" placeholder="Your Email *" value="<?=$email;?>" id="signup_email" name="signup_email" value="" required />
													</div>
													<div class="form-group">
														<input type="password" class="form-control" oninput="checkPasscode();" placeholder="Password *" id="signup_password" name="signup_password"  value="" required />
													</div>
													<div class="form-group">
														<input type="password" class="form-control" oninput="checkPasscode();" placeholder="Confirm Password *" id="signup_password2" value="" required />
													</div>
													<div class="form-group">
														<input name="signup_website" id="signup_website" type="url" class="form-control" placeholder="e.g. http://" value="<?=$website?>" required />
													</div>
													<span style="color:red;"><?=$error?></span>
													<button type="submit" class="btnRegister">Signup</button>
												</form>
											<?php
												}else{
													$login_url = "https://www.referrals.com/signin/referralslogin?redirect_url=$redirect&client=$API_KEY&cancel_url=$cancel";
											?>
												<a href="<?=$login_url?>" style="width:100%" class="btn btn-primary login">Login</a>
											<?php
												}
											?>
										</div>						
									</div>
								</div>
							</div>
						</div>
						<!-- -->
					</div>
				</section>
			</div>
		   <!-- end tab-->
		</div>
		<br style="clear:both;">
		<script type="text/javascript">
			function checkName(){
				var format = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]+/;
				var signup_name = document.querySelector("#signup_name");
				
				if(signup_name.value==""){
					signup_name.setCustomValidity("Name is required");
				}else if(format.test(signup_name.value)){
					signup_name.setCustomValidity("Invalid name input.");
				}else{
					signup_name.setCustomValidity("");
				}
			}
			
			function checkPasscode() {
				var passcode_input = document.querySelector("#signup_password");
				var passcode_input2 = document.querySelector("#signup_password2");
				if(passcode_input.value.length<8){
					passcode_input.setCustomValidity("password must contain 8 characters.");
				}else if (passcode_input.value != passcode_input2.value) {
					passcode_input.setCustomValidity("Passwords must match.");
				} else {
					passcode_input.setCustomValidity("");
				}
			}
		</script>
		<?php
	}
	
	public function referrals_main_menu() {
		add_menu_page(__('Referrals'), __('Referrals'), 'read', 'referrals_main_menu', array($this,'referrals_main_page'), '', 7);
	}
		
	public function referrals_main_page(){
		$user_id = get_current_user_id();
		$current_user = wp_get_current_user();
		
		$referrals_token = $_SESSION['referrals_token'];
		$referrals_api_key = get_user_meta( $user_id, 'referrals_api_key', true );
		$brands = array();
		$brand_id ='';
		$campaigns = array();
		
		if(!empty($referrals_api_key)){
			$headers = array('Accept: application/json');
			$url = "http://www.api.referrals.com/brand/getbrands?api_key=".$referrals_api_key;
			$param = array('token'=>$referrals_token);
			$result =  $this->createApiCall($url, 'POST', $headers, $param);
			$res = json_decode($result,true);
			$brands = $res['brands'];
			
			if(!empty($_POST['brand'])){
				$brand_id = $_POST['brand'];
				$headers = array('Accept: application/json');
				$url = "http://www.api.referrals.com/campaign/getcampaigns?api_key=".$referrals_api_key;
				$param = array('token'=>$referrals_token,'brand_id'=>$_POST['brand']);
				$result =  $this->createApiCall($url, 'POST', $headers, $param);
				$res = json_decode($result,true);
				$campaigns = $res['campaigns'];
			}
		}
		
		?>
		<style>
			
		</style>
		<link href="<?=plugins_url('custom.css',__FILE__ )?>" rel="stylesheet" type="text/css" />
		<div class="Referrals-custom-plugin">
			<!-- tab section -->
			<div class="clear-section"></div>
			<div class="vcp-tab-main">
				<input id="tab1" type="radio" name="tabs" checked="">
				<label id="lcustomfooter" for="tab1">Referrals Settings</label>
				
				<section id="content1">
					<div class="vtab">
						<div class="row">
							<div class="col-md-12 text-center">
								<a href="http://referrals.com/">
									<img class="logo-img-ss" height="90" width="200" src="https://d1p6j71028fbjm.cloudfront.net/logos/logo-new-referral-1.png">
								</a>
								<h1>Is a Plugin for Referrals</h1>
							</div>
					   </div>
					</div>
					<div class="section-signup">
						<div class="container register">
							<div class="row">
								<div class="">
									<form id="form-brand" method="post" class="form-inline" action="<?=get_site_url()."/wp-admin/admin.php?page=referrals_main_menu"?>">
										<div class="form-group">
											<label >Campaigns</label>
											<select class="form-control" id="brand" name="brand">
												<option value="0">Select Brand</option>
												<?php
														foreach($brands as $brand){
													?>
													<option value="<?=$brand['id']?>"><?=$brand['domain']?></option>
													<?php
														}
													?>
											</select>
										</div>
									</form>
									
									<?php
										if(!empty($brand_id)){
									?>
									<div class="panel panel-default">
										<div class="panel-heading">											
											<a target="_blank" href="https://www.referrals.com/campaign/create/<?=$brand_id?>?id=<?=base64_encode($current_user->user_email)?>" class="btn btn-primary">Create Campaign</a>											
										</div>
										<table class="table">
											<thead>
												<tr> 
													<th>#</th> 
													<th>Campaign Name</th>
													<th>Short Code</th>
												</tr> 
											</thead> 
											<tbody> 
												<?php
													$x = 1;
													foreach($campaigns as $campaign){
												?>
												<tr> 
													<th scope="row"><?=$x?></th> 
													<td><?=$campaign['name']?></td>
													<td>[referralswidget code="<?=$campaign['id']?>"]</td>
												</tr> 
												<?php
													$x++;
													}
												?>
											</tbody>
										</table> 
									</div>
									<?php
										}
									?>
								</div>
							</div>
						</div>
					</div>
				</section>
			</div>
		   <!-- end tab-->
		</div>
		<br style="clear:both;">
		<script type="text/javascript">		
			jQuery(document).ready(function(){
				jQuery('#brand').val(<?=$brand_id?>);
				jQuery('#brand').change(function(){
					jQuery('#form-brand').submit();
				});
			});
		});
		</script>
	<?php
	}
	
	public function createApiCall($url, $method, $headers, $data = array(),$user=null,$pass=null)
	{
		if (($method == 'PUT') || ($method=='DELETE'))
		{
			$headers[] = 'X-HTTP-Method-Override: '.$method;
		}

		$handle = curl_init();
		curl_setopt($handle, CURLOPT_URL, $url);
		curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
			  
		if ($user){
		 curl_setopt($handle, CURLOPT_USERPWD, $user.':'.$pass);
		} 

		switch($method)
		{
			case 'GET':
				break;
			case 'POST':
				curl_setopt($handle, CURLOPT_POST, true);
				curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($data));
				break;
			case 'PUT':
				curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'PUT');
				curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($data));
				break;
			case 'DELETE':
				curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
		}
		$response = curl_exec($handle);
		return $response;
	}
}

add_action( 'plugins_loaded', array( 'Referrals', 'get_instance' ) );


