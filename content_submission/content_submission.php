<?php
/*
Plugin Name: Content Submission Plugin
Plugin URI: https://github.com/DevelopersContrib/Wordpress-Plugins/tree/master/content_submission
Version: 1.0
Author: contrib.com
*/

class ContentSubmission {
	private static $instance;
	protected $templates;
	
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new ContentSubmission();
		} 
		return self::$instance;
	}
	
	private function __construct() {
		$this->templates = array();
		add_action( 'wp_ajax_test_cs', array($this,'test' ));
		add_action( 'wp_ajax_approve_cs', array($this,'approve_cs' ));
		add_action( 'wp_ajax_delete_cs', array($this,'delete_cs' ));
		add_action( 'wp_ajax_disapprove_cs', array($this,'disapprove_cs' ));
		add_action('admin_menu', array($this,'content_submission_main_menu'));

		add_shortcode( 'contentsubmission', array($this,'content_submission_shortcode' ));
		
		add_action('wp_head', array($this,'jquery_no_conflict'));
		
		
		//--------content submission
		add_action( 'pre_get_posts', 'add_my_post_types_to_query' ); 
		function add_my_post_types_to_query( $query ) {
			if ( is_home() && $query->is_main_query() )
				$query->set( 'post_type', array( 'post', 'content_submission' ) );
			return $query;
		}
		function create_posttype() {		 
			register_post_type( 'content_submission',
				array(
					'labels' => array(
						'name' => __( 'Content Submission' ),
						'singular_name' => __( 'content_submission' )
					),
					'public' => true,
					'has_archive' => true,
					'rewrite' => array('slug' => 'content_submission'),
					'show_in_rest' => true,
					'show_ui'=>false
		 
				)
			);
		}
		add_action( 'init', 'create_posttype' );
		add_filter('kses_allowed_protocols', function ($protocols) { //allow data base64 images
			$protocols[] = 'data';
			return $protocols;
		});
		//----------
	}
	
	public function delete_cs()
	{
		$cs_post_id = $_POST['value'];
		echo json_encode(array('deleted'=>wp_delete_post( $cs_post_id, true)));
		die();
	}
	
	public function test()
	{
		echo 'here';
		$this->Generate_Featured_Image('https://i0.wp.com/instadesk.com/wp-content/uploads/2020/05/instagram-tricks-scaled.jpg',1);
		die();
	}
	
	public function disapprove_cs()
	{
		$cs_post_id = $_POST['value'];
		$val = update_post_meta($cs_post_id, 'status', 'disapproved');
		if($val){
			$author_name = ucwords(get_post_meta($cs_post_id, 'author_name',true ));
			$to = get_post_meta($cs_post_id, 'author_email',true );
			$cs_post = get_post( $cs_post_id );
			
			$subject = 'Your article "'.$cs_post->post_title.'" has been disapproved';
			$body = "$author_name, ";
			$body .= '<br> As much as we would like to accept your contributed post/article, we feel it can still be improved upon.';
			$body .= '<br> Please resubmit or let us know if you feel that you deserve a spot in our site :)';
			$headers = array('Content-Type: text/html; charset=UTF-8');
			 
			wp_mail( $to, $subject, $body, $headers );
		}
		$status = get_post_meta($cs_post_id, 'status',true );
		echo json_encode(array('status'=>ucwords($status),'post_id'=>$cs_post_id,'disapprove'=>$val));
		die();
	}
	
	public function approve_cs()
	{
		$cs_post_id = $_POST['value'];
		$cs_post = get_post( $cs_post_id );
		if(!empty($cs_post)){

			$post_id = wp_insert_post(array (
				'post_type' => 'post',
				'post_author' => $cs_post->post_author,
				'post_title' => $cs_post->post_title,
				'post_content' => $cs_post->post_content,
				'post_status' => 'publish',
			));
			
			if($post_id){
				update_post_meta($cs_post_id, 'status', 'approved');
				add_post_meta($cs_post_id, 'post_id',$post_id);
				
				$meta = get_post_meta( $cs_post_id );
				
				add_post_meta($post_id, 'author_name', $meta['author_name'][0]);
				add_post_meta($post_id, 'author_email', $meta['author_email'][0]);
				add_post_meta($post_id, 'author_bio', $meta['author_bio'][0]);
				add_post_meta($post_id, 'author_website', $meta['author_website'][0]);
				
				if(!empty($meta['featured_image'][0])){
					$this->Generate_Featured_Image($meta['featured_image'][0],$post_id);
				}
				
				$user_id = $cs_post->post_author;
				$reset_pass = get_user_meta( $user_id, 'reset_pass', true );
				
				if($reset_pass){
					$random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
					wp_set_password( $random_password, $user_id );
				}
				
				$author_name = $meta['author_name'][0];
				$to = $meta['author_email'][0];
				$cs_post = get_post( $cs_post_id );
				
				$subject = 'Congratulations! your article "'.$cs_post->post_title.'" has been approved';
				$body = "Congratulations $author_name,";
				$body .= '<br><br>Your article "'.$cs_post->post_title.'" has been approved <br>';
				//$body .='<br> Your Login Account: '.$meta['author_email'][0]; 
				//$body .='<br> Your Temporary Password: '.$random_password ;
				//$body .='<br> <a href="'.wp_login_url().'">Click here to login</a>';
				$body .='<br> Click <a href="'.get_permalink($post_id).'">here</a> to view ';
				$headers = array('Content-Type: text/html; charset=UTF-8');
				 
				wp_mail( $to, $subject, $body, $headers );
				
				$url = "https://e7lq80c199.execute-api.us-west-2.amazonaws.com/api1";
			
				$title = $cs_post->post_title;
				$slug = $this->slugify($title);
				$param = array(
					"title"=>$title,
					"slug"=>$slug,
					"post_url"=>get_permalink($post_id),
					"post_id"=>$post_id,
					"content"=>$cs_post->post_content,
					"author_name"=>$meta['author_name'][0],
					"author_email"=>$meta['author_email'][0],
					"author_bio"=>$meta['author_bio'][0],
					"author_website"=>$meta['author_website'][0],
					"image"=>$meta['featured_image'][0],
					"domain"=>$_SERVER['SERVER_NAME'],
					"type"=>"article",
					"key"=>"5c1bde69a9e783c7edc2e603d8b25023",
					"request"=>"wp-contentsubmission"
				);
				
				$header = array(
					'Accept: application/json',
					'Content-Type: application/json'
				);
							
				$pload = array(
					'method' => 'POST',
					'headers' => $headers,
					'body' => json_encode($param),
					'timeout' => 25,
				);

				$res = wp_remote_post($url, $pload);
				add_post_meta($post_id, 'crypto_response', $res['body']);
				
				echo json_encode(array('url'=>get_permalink($post_id),'status'=>'approved','post_id'=>$post_id));
			}else{
				echo json_encode(array('url'=>get_permalink($cs_post_id),'status'=>'pending','post_id'=>$cs_post_id));
			}
		}
		die();
	}
	
	public function content_submission_main_menu() {
		add_menu_page(__('Content Submission'), __('Content Submission'), 'manage_options', 'content_submission_main_menu', array($this,'content_submission_main_page'), '', 7);
	}
	
	public function content_submission_main_page(){

	?>
	
		<style>
		@import url("https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css");
		/* 1 column: 320px */
		.vnoc-custom-plugin {
		  margin: 30px auto 0;
			width: 98%;
		   font-family:arial !important;
		}
		.vnoc-custom-plugin .vcp-module {
		  background-color: fafafa;
		  border-radius: .25rem;
		  margin-bottom: 1rem;
		  text-align: center;
		}
		.vnoc-custom-plugin .vcp-box {
		   padding: 30px;
		   background: #fafafa;
		   border: 1px solid #ddd;
		   border-radius: 3px;
		   display: block;
		   text-decoration: none;
		   color: #121212;
		}
		.vnoc-custom-plugin .vcp-box:hover {
		   border:1px solid #aaa;
		   background: #fff;
		}
		.vnoc-custom-plugin .vcp-box h4 {
		   margin: 0px;
		}
		.vnoc-custom-plugin .vcp-box h3 {
		   margin-top: 10px;
		   color:#666;
		}
		.vnoc-custom-plugin .vcp-box img {
		   width: 80px;
		   height: 80px;
		}
		/* 2 columns: 600px */
		@media only screen and (min-width: 600px) {
		  .vnoc-custom-plugin .vcp-module {
				float: left;
				margin-right: 2.564102564102564%;
				width: 48.717948717948715%;
			}
			.vnoc-custom-plugin .vcp-module:nth-child(2n+0) {
				margin-right: 0;
			}
		}
		/* 3 columns: 768px */
		@media only screen and (min-width: 768px) {
		  .vnoc-custom-plugin .vcp-module {
				width: 31.623931623931625%;
			}
			.vnoc-custom-plugin .vcp-module:nth-child(2n+0) {
				margin-right: 2.564102564102564%;
			}
			.vnoc-custom-plugin .vcp-module:nth-child(3n+0) {
				margin-right: 0;
			}
		}
		/* 4 columns: 992px and up */
		@media only screen and (min-width: 992px) {
		  .vnoc-custom-plugin .vcp-module {
				width: 23.076923076923077%;
			}
			.vnoc-custom-plugin .vcp-module:nth-child(3n+0) {
				margin-right: 2.564102564102564%;
			}
			.vnoc-custom-plugin .vcp-module:nth-child(4n+0) {
				margin-right: 0;
			}
		}

		/***** tabs ******/
		.vnoc-custom-plugin .clear-section {
		   clear:both;
		}
		.vcp-tab-main {
		  width: auto;
		  min-width: 320px;
		  padding: 20px 50px 50px;
		  margin: 0 auto;
		  background: #fff;
		  
		}

		.vcp-tab-main section {
		  /*display: none;*/
		  padding: 20px 15px 20px;
		  border: 1px solid #ddd;
		}

		.vcp-tab-main input {
		  display: none;
		}

		.vcp-tab-main label {
		  display: inline-block;
		  margin: 0 0 -1px;
		  padding: 15px 25px;
		  font-weight: 600;
		  text-align: center;
		  color: #bbb;
		  border: 1px solid transparent;
		}

		.vcp-tab-main label:before {
		  font-family: arial;
		  font-weight: normal;
		  margin-right: 10px;
		}

		.vcp-tab-main label:hover {
		  color: #888;
		  cursor: pointer;
		}

		.vcp-tab-main input + label {
		  color: #555;
		  border: 1px solid #ddd;
		  border-top: 2px solid orange;
		  border-bottom: 1px solid #fff;
		}

		.vcp-tab-main #tab1:checked ~ #content1,
		.vcp-tab-main #tab2:checked ~ #content2,
		.vcp-tab-main #tab3:checked ~ #content3,
		.vcp-tab-main #tab4:checked ~ #content4,
		.vcp-tab-main #tab6:checked ~ #content6,
		.vcp-tab-main #tab5:checked ~ #content5 {
		  display: block;
		}

		@media screen and (max-width: 650px) {
		  .vcp-tab-main label {
			font-size: 0;
		  }
		  .vcp-tab-main label:before {
			margin: 0;
			font-size: 18px;
		  }
		}

		@media screen and (max-width: 400px) {
		  .vcp-tab-main label {
			padding: 15px;
		  }
		}
		.vcp-tab-main .vtab {
		   padding: 0px 20px 20px 20px;
		}
		.vcp-tab-main .vtab h3 {
		   font-size: 16px;
		   border-bottom: 1px dashed #ddd;
		   padding-bottom: 10px;
		   margin-bottom: 10px;
		}
		.vtab .manage-footer-img {
		   width:100%;
		}
		.vcp-footer-container {
		   background: #333;
		   color: #fff;
		   padding: 10px 0px 20px;
		}
		.vcp-footer-container .col-group > div {
		  padding: none;
		}
		.vcp-footer-container .col-group .footer-in {
		   padding: 15px;
		}
		.vcp-footer-container .col-group .footer-in h3 {
		   border:none;
		   text-transform: uppercase;
		   color:#fff;
		}
		.vcp-footer-container .footer-in .v-domain-desc {
		   font-size: 13px;
		}
		.vcp-footer-container .footer-in .p-link {
		   line-height: 6px;
		}
		.vcp-footer-container .footer-in .p-link a {
		   text-decoration:none;
		   color:#999;
		   font-size: 13px;
		}
		.vcp-footer-container .v-socials img {
		   width:40px;
		}
		.vcp-footer-container .col-group .footer-in .p-link a:hover {
		   color:#fff;
		}
		@media screen and (min-width: 44em) {
		  .vcp-footer-container .col-group {
			overflow: hidden;
		  }
		  .vcp-footer-container .col-group > div {
			float: left;
			width: 50%;
		  }
		  .vcp-footer-container .col-group > div:nth-child(odd) {
			clear: left;
		  }
		}
		@media screen and (min-width: 64em) {
		  .vcp-footer-container .col-group > div {
			width: 25%;
		  }
		  .vcp-footer-container .col-group > div:nth-child(odd) {
			clear: none;
		  }
		}

		.vcp-footer-container-bottom {
		   background: #222;
		   color: #fff;
		   padding: 10px 0px 5px;
		   font-size: 13px;
		}
		.vcp-footer-container-bottom p a {
		   color:#999;
		   text-decoration: none;
		   font-size: 13px;
		   margin-right: 5px;
		}
		.vcp-footer-container-bottom p a:hover {
		   color:#fff;
		}
		.vcp-footer-container-bottom .lcol {
		   float:left;
		}
		.vcp-footer-container-bottom .rcol {
		   float:right;
		}
		.scw {
		   margin-bottom: 2px;
		}
		.scw input:checked + label {
			color: #fff;
			border: none;
			padding: 6px;
			background: orange;
		}
		.scw label {
			display: inline-block;
			margin: 0 0 -1px;
			padding: 6px;
			font-weight: 600;
			text-align: center;
			color: #bbb;
			border: none;
			background: #fafafa;
		}
		.vtab .col-md-3, .vtab .col-md-4 {
		   border-bottom: 1px solid #ddd;
		   padding-bottom: 10px;
		   height:560px;
		}
		.vtab .mpages .mpages-in {
		   background: #fafafa;
		   border: 1px solid #ddd;
		   padding: 10px 10px 20px 10px;
		   margin-bottom: 25px;
		   border-radius: 3px;
		}
		</style>

		<div class="vnoc-custom-plugin">
		   <!-- tab section -->
		   <div class="clear-section"></div>
		   <div class="vcp-tab-main">
			
			 <input id="tab6" type="radio" name="tabs">
			 <label id="contentsubmission" for="tab6">Content Submission</label>
			
			 <section id="content6">
				<div class="vtab">
					<h3>Short Code</h3>
					<div class="col-md-12">		
						<p>
						[contentsubmission]						
						</p>
						<p>&nbsp;</p>
					</div>
				   <h3>Articles</h3>
					<div class="col-md-12">						
						
						<style>
							#tbl_content_submission {
							  border-collapse: collapse;
							  border-spacing: 0;
							  width: 100%;
							  border: 1px solid #ddd;
							}

							#tbl_content_submission th, #tbl_content_submission th td {
							  text-align: left;
							  padding: 8px;
							}

							#tbl_content_submission tr:nth-child(even){background-color: #f2f2f2}
						</style>
						<div style="overflow-x:auto;">
						  <table id="tbl_content_submission">
							<tr>
							  <th>Title</th>
							  <th>Author Name</th>
							  <th>Author Email</th>
							  <th>Author Website</th>
							  <th>Status</th>
							  <th>Action</th>
							</tr>
							<?php
								$args = array(  
									'post_type' => 'content_submission',
									'post_status' => 'draft',
									'orderby' => 'post_date', 
									'order' => 'DESC', 
								);

								$loop = new WP_Query( $args ); 
									
								while ( $loop->have_posts() ) : $loop->the_post();
									$_cs_post_id = get_the_ID();
									//$featured_img = wp_get_attachment_image_src( $post->ID );
									$meta = get_post_meta( $_cs_post_id );
									$cs_link = get_permalink( $_cs_post_id );
									?>
										<tr id="tr_cs_<?=$_cs_post_id?>">
											<td><?php echo the_title()?></td>
											<td><?php echo $meta['author_name'][0]?></td>
											<td><?php echo $meta['author_email'][0]?></td>
											<td><?php echo $meta['author_website'][0]?></td>
											<?php
												$status = $meta['status'][0];
												$post_id = $meta['post_id'][0];
												$link = get_permalink($post_id);
												if(!$link){ //post is deleted
													$link = $cs_link;
													$status = 'Post Deleted';
												}
											?>
											<td class="status"><?php echo ucwords($status)?></td>
											<td class="td_status_cs">
												<?php 
													if($status=='pending'){
												?>
												<a class="view" target="_blank" href="<?=$cs_link?>">View</a> &nbsp; 
												<a data-id="<?=$_cs_post_id?>" class="approve_cs" href="javascript:;">Approve</a> &nbsp; 
												<a data-id="<?=$_cs_post_id?>" class="disapprove_cs" href="javascript:;">Disapprove</a> &nbsp; 
												<a data-id="<?=$_cs_post_id?>" class="delete_cs" href="javascript:;">Delete</a>
												<!--<a data-id="<?=$_cs_post_id?>" class="test_cs" href="javascript:;">test</a>-->
												<?php
													}else{
												?>
													<a class="view" target="_blank" href="<?=$link?>">View</a> &nbsp; 
													<a data-id="<?=$_cs_post_id?>" class="delete_cs" href="javascript:;">Delete</a>
												<?php
													}
												?>
												
											</td>
										</tr>
									<?php
								endwhile;
								wp_reset_postdata(); 
							?>
						  </table>
						</div>
					</div>
					<br style="clear: both">
				</div>
			 </section>
		  </div>
		   <!-- end tab-->
		</div>
		<br style="clear:both;">
		<script>
			jQuery(document).ready(function(){
				jQuery('.test_cs').click(function(){
					showLoader('Loading...');
					var btn = jQuery(this);
					jQuery.post(
						"<?=get_site_url()?>/wp-admin/admin-ajax.php", 
						{
							action: 'test_cs',
							value: btn.attr('data-id'),
						},
						function(response){
							hideLoader();
						}
					);
				});
				//---content submission---
				jQuery('.approve_cs').click(function(){
					showLoader('Loading...');
					var btn = jQuery(this);
					jQuery.post(
						"<?=get_site_url()?>/wp-admin/admin-ajax.php", 
						{
							action: 'approve_cs',
							value: btn.attr('data-id'),
						},
						function(response){
							hideLoader();
							response = jQuery.parseJSON(response);
							var tr = btn.parents('tr');
							tr.find('.approve_cs').remove();
							tr.find('.disapprove_cs').remove();
							tr.find('.view').attr('href',response.url);
							tr.find('.status').html(response.status);
						}
					);
				});
				jQuery('.delete_cs').click(function(){
					if(confirm("Are you sure you want to delete?")){
						var btn = jQuery(this);
						showLoader('Deleting...');
						jQuery.post(
							"<?=get_site_url()?>/wp-admin/admin-ajax.php", 
							{
								action: 'delete_cs',
								value: btn.attr('data-id'),
							},
							function(response){
								hideLoader();
								response = jQuery.parseJSON(response);
								if(response.deleted){
									btn.parents('tr').remove();
								}
							}
						);
					}
				});
				jQuery('.disapprove_cs').click(function(){
					if(confirm("Are you sure you want to disapprove?")){
						var btn = jQuery(this);
						showLoader('Loading...');
						jQuery.post(
							"<?=get_site_url()?>/wp-admin/admin-ajax.php", 
							{
								action: 'disapprove_cs',
								value: btn.attr('data-id'),
							},
							function(response){
								hideLoader();
								response = jQuery.parseJSON(response);
								if(response.disapprove){
									var tr = btn.parents('tr');
									tr.find('.status').html(response.status);
									tr.find('.approve_cs').remove();
									tr.find('.disapprove_cs').remove();
								}
							}
						);
					}
				});
				//--------
			});
			function showLoader(msg,msgid){
				var msgid = msgid==undefined?'loadervnoc':msgid;
				msg = msg==undefined?"Loading...":msg;
				jQuery('body').append('<div id="'+msgid+'" style="display: none;background: none repeat scroll 0 0 #000000;border-radius: 10px;bottom: 40px;color: #FFFFFF;'+
					'height: 100px;left: 45%;opacity: 0.8;padding: 10px;position: fixed;text-align: center;top: 50%;width: 250px;z-index: 999999;">'+
					'<img src="https://www.contrib.com/images/loading0.gif" alt="...">'+
					'<div><em>'+msg+'</em></div></div>');
				jQuery('body').find('#'+msgid).show();
			}
			function hideLoader(msgid){
				var msgid = msgid==undefined?'loadervnoc':msgid;
				jQuery('body').find('#'+msgid).remove();
			}
		</script>
	<?php
	}
	
	function slugify ($string) {
		$string = utf8_encode($string);
		$string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);   
		$string = preg_replace('/[^a-z0-9- ]/i', '', $string);
		$string = str_replace(' ', '-', $string);
		$string = trim($string, '-');
		$string = strtolower($string);

		if (empty($string)) {
			return 'n-a';
		}

		return $string;
	}
	
	public function content_submission_shortcode() {
		ob_start();
		echo '<style>
			.custw-article-form {
				background: #f5f0f0;
				padding: 10px;
				border: 2px solid #ccc;
				border-radius: 4px;
				margin: 10px 5px;
			}
			.custw-form-control {
				font-family: inherit;
				background-color: #f8f8f8;
				width: 100%;	
				font-size: 1.1rem;
				box-sizing: border-box;
				border: 2px solid #ccc;
				border-radius: 4px;
				margin-bottom: 1rem;
			}
			select.custw-form-control {
				padding: 12px 20px;
			}
			input[type=text].custw-form-control {	
				background-image: url("https://image.flaticon.com/icons/png/128/1508/1508794.png");
				background-size: 21px 21px;
				background-position: 10px 10px; 
				background-repeat: no-repeat;
				padding: 12px 20px 12px 40px;
			}
			input[type=url].custw-form-control {	
				background-image: url("https://image.flaticon.com/icons/png/128/1508/1508794.png");
				background-size: 21px 21px;
				background-position: 10px 10px; 
				background-repeat: no-repeat;
				padding: 12px 20px 12px 40px;
			}
			input[type=email].custw-form-control {	
				background-image: url("https://image.flaticon.com/icons/png/128/1508/1508794.png");
				background-size: 21px 21px;
				background-position: 10px 10px; 
				background-repeat: no-repeat;
				padding: 12px 20px 12px 40px;
			}
			textarea.custw-form-control {
				height: 120px;
				background-image: url("https://image.flaticon.com/icons/png/128/1508/1508794.png");
				background-size: 21px 21px;
				background-position: 10px 10px; 
				background-repeat: no-repeat;
				padding: 12px 20px 12px 40px;
				resize: none;
			}
			input[type=submit].custw-form-button {
				font-family: inherit;
				background-color: #4CAF50;
				border: none;
				color: white;
				font-size: 1.1rem;
				padding: 16px 32px;
				text-decoration: none;
				border-radius: 2px;
				margin: 4px 2px;
				cursor: pointer;
			}
			input[type=file].custw-form-control {
				width: auto;
				padding: 0px 10px 10px;
			}
			.custw-image-display {
				margin-bottom: 1rem;
			}
			.custw-success-alert {
				background: #d4edda;
				box-sizing: border-box;
				border: 2px solid #c3e6cb;
				border-radius: 4px;
				padding: 30px;
				margin-bottom: 1rem;	
			}
			.custw-success-alert h2 {
				margin-top: 0px;
				margin-bottom: 0px;
			}
			.custw-success-alert h4 {
				margin-top: 5px;
				margin-bottom: 0px;
			}
			.custw-error-alert {
				background: #f8d7da;
				box-sizing: border-box;
				border: 2px solid #f5c6cb;
				border-radius: 4px;
				padding: 15px;
				margin-bottom: 1rem;	
			}
			.custw-error-alert h4 {
				margin-top: 0px;
				margin-bottom: 0px;
			}
			#editor {
			  height: 200px;
			  background: #fff;
			}
		</style>';
		
		if ( isset($_POST['submit_content'] ) ) {
			
			$user_id = 0;
			$author_email = $_POST['author_email'];
			$author_name = $_POST['author_name1'];
			$user_name = preg_replace('/\s+/', '', $author_name);
			
			if (!email_exists( $author_email ) ) {
				$random_password = wp_generate_password( $length = 12, $include_standard_special_chars = false );
				$userdata = array(
					'user_login' =>  $user_name,
					'user_email' =>  $author_email,
					'user_url'   =>  $_POST['author_website'],
					'user_pass'  =>  $random_password,
					'description' => $_POST['author_bio']
				);
				 
				$user_id = wp_insert_user( $userdata ) ;
				add_user_meta( $user_id, 'reset_pass', true);
			} else {
				$random_password = 'User already exists.  Password inherited.';
				$user = get_user_by( 'email',$author_email);
				$user_id = $user->ID;
			}

			$post_id = wp_insert_post(array (
				'post_author'=>$user_id,
				'post_type' => 'content_submission',
				'post_title' => $_POST['title'],
				'post_content' => $_POST['content_text'],
				'post_status' => 'draft',
			));
			
			//if(!empty($res['body']) && json_decode($res['body'])->success){
			if($post_id){
				add_post_meta($post_id, 'author_name', $_POST['author_name1']);
				add_post_meta($post_id, 'author_email', $author_email);
				add_post_meta($post_id, 'author_bio', $_POST['author_bio']);
				add_post_meta($post_id, 'author_website', $_POST['author_website']);
				
				add_post_meta($post_id, 'status', 'pending');
				
				if(!empty($_POST['featured_image'])){
					add_post_meta($post_id, 'featured_image', $_POST['featured_image']);
					$this->Generate_Featured_Image($_POST['featured_image'],$post_id);
				}
				
				$subject = $_POST['title'].' has been submitted successfully';
				$body = 'Thank You!!';
				$body .= '<br> Your article has been submitted successfully.';
				$headers = array('Content-Type: text/html; charset=UTF-8');
				 
				wp_mail( $author_email, $subject, $body, $headers );
				
				echo '<div id="submit_success" class="custw-article-form">	
				<div class="custw-article-inner">
					<div class="custw-article-group">
						<div class="custw-success-alert">
							<h2>Thank You!!</h2>
							<h4>Your article has been submitted successfully.</h4>
						</div>
					</div>
				</div>
				<script>
					var el = document.getElementById("submit_success");
					el.scrollIntoView();
				</script>
				';
			}else{
				echo '<div id="submit_success" class="custw-article-form">	
				<div class="custw-article-inner">
					<div class="custw-article-group">
						<div id="error_content" class="custw-error-alert">				
							<h4>An error occurred please try again later</h4>
						</div>
					</div>
				</div>
				<script>
					var el = document.getElementById("submit_success");
					el.scrollIntoView();
				</script>
				';
			}
			
		}else{	 
			$html= '
				<link href="'.esc_url( plugins_url( 'css/quill.snow.css', __FILE__ ) ).'" rel="stylesheet">
				<script src="'.esc_url( plugins_url( 'js/quill.js', __FILE__ ) ).'"></script>			
			<form action="' . $_SERVER['REQUEST_URI'] . '" method="post" onsubmit="return validateForm()">
			<div class="custw-article-form">	
				<div class="custw-article-inner">
					<div class="custw-article-group">
						<div id="error_content" class="custw-error-alert" style="display:none;">				
							<h4>Please provide <span>content</span>!</h4>
						</div>
						<div class="custw-article-group">
							<input value="" id="title" type="text" class="custw-form-control" name="title" placeholder="Article Title.." required>
						</div>						
						<div class="custw-article-group">
							<div id="editor"></div>
							<textarea style="display:none;" id="content_text" name="content_text"></textarea>
						</div>						
						<div class="custw-article-group" style="margin-top:10px;">
							<input type="file" class="custw-form-control" name="fileInput" id="fileInput" accept="image/*">	
							<span>Upload a feature image.</span>
							<div class="custw-image-display">
								<img style="display:none;" id="featured_img" src="https://via.placeholder.com/500" height="500">
								<input id="featured_image" name="featured_image" type="hidden" />
							</div>
						</div>	
						<div class="custw-article-group">
							<input value="" type="text" class="custw-form-control" name="author_name1" placeholder="Author Name" required>
						</div>						
						<div class="custw-article-group">
							<textarea class="custw-form-control" name="author_bio" placeholder="Author Bio"></textarea>
						</div>						
						<div class="custw-article-group">
							<input value="" type="email" class="custw-form-control" name="author_email" placeholder="Author Email" required>
						</div>						
						<div class="custw-article-group">
							<input type="url" pattern="https://.*" class="custw-form-control" name="author_website" placeholder="Author Website (https://www.website.com)">
						</div>						
						<div class="custw-article-group" style="float: right;margin-bottom:1rem;">
							<input  name="submit_content" type="submit" class="custw-form-button" value="Submit">
						</div>
						<div style="clear:both;"></div>
					</div>
				</div>
			</div>			
			</form>
			'."
			<script>
				var quill;
				function validateForm(){
					var error_content = document.getElementById('error_content');
					error_content.style.display = 'none';
					if(quill.getText().trim().length===0){						
						error_content.style.display = 'block';
						scrollTo('error_content');
						return false;
					}else{
						var content = document.getElementById('content_text');
						content.value = quill.root.innerHTML;
					}
					return true;
				}
				function scrollTo(el){
					var el = document.getElementById(el);
					el.scrollIntoView();
				}
				window.onload = function() {
					quill = new Quill('#editor', {
						modules: {
							toolbar: [
							[{ header: [1, 2, false] }],
							['bold', 'italic', 'underline'],
							['code-block'],
							['link', 'blockquote', 'code-block'],
							[{ list: 'ordered' }, { list: 'bullet' }, 'image']
							]
						},
						placeholder: 'Compose an epic...',
						theme: 'snow'
					});
					
					var fileInput = document.getElementById('fileInput');
					var output = document.getElementById('featured_img');
					var buff = document.getElementById('buff');
					fileInput.addEventListener('change', function(e) {
						output.src = 'https://cdn.vnoc.com/widgets/widget-spinner.gif';
						output.style.display = 'block';
						var file = fileInput.files[0];
						var imageType = /image.*/;
						if (file.type.match(imageType)) {
							var reader = new FileReader();
							reader.onload = function(e) {
								var data = new FormData();
								data.append('image', reader.result);
								var IMGUR_API_URL = 'https://www.contrib.com/image/upload';
								var xhr = new XMLHttpRequest();
								xhr.open('POST', IMGUR_API_URL, true);
								xhr.onreadystatechange = function() {
									if (xhr.readyState === 4) {
										var response = JSON.parse(xhr.responseText);
										if (response.status === 200 && response.success) {
											output.src = response.data.link;											
											var featured_image = document.getElementById('featured_image');
											featured_image.value = response.data.link;
										} else {
											alert('an error occurred please try again later');
										}
									}
								}
								xhr.send(data);
							}
							reader.readAsDataURL(file);             
						}
					else{
						output.innerHTML = 'File not supported!';
					}
					});
				}
			</script>
			";
			echo $html;
		}
		return ob_get_clean();
	}

	function Generate_Featured_Image( $image_url, $post_id  ){
		$upload_dir = wp_upload_dir();
		$image_data = wp_remote_get($image_url);
		$image_data = $image_data['body'];
		$filename = basename($image_url);
		if(wp_mkdir_p($upload_dir['path']))
		  $file = $upload_dir['path'] . '/' . $filename;
		else
		  $file = $upload_dir['basedir'] . '/' . $filename;
		file_put_contents($file, $image_data);

		$wp_filetype = wp_check_filetype($filename, null );
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => sanitize_file_name($filename),
			'post_content' => '',
			'post_status' => 'inherit'
		);
		$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
		$res1= wp_update_attachment_metadata( $attach_id, $attach_data );
		$res2= set_post_thumbnail( $post_id, $attach_id );
	}

	
	
	public function jquery_no_conflict()
	{
		echo ' <script>try{var $ = jQuery.noConflict();}catch(e){};'.
		''.
		" if (typeof jQuery === 'undefined') {".
		' var c=document.createElement("script"); '.
		' c.type="text/javascript", '.
		' c.readyState ? c.onreadystatechange=function(){ '.
		' 		"loaded"!=c.readyState&&"complete"!=c.readyState||(c.onreadystatechange=null,b()) '.
		' 	}: '.
		' 	c.onload=function(){}, '.
		' c.src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js", '.
		' document.getElementsByTagName("head")[0].appendChild(c) '.
		" }  ".
		'</script>  ';
	}
}

add_action( 'plugins_loaded', array( 'ContentSubmission', 'get_instance' ) );
