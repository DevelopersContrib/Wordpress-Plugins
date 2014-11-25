<?php
/*
Plugin Name:  Contrib Widget
Plugin URI:https://github.com/DevelopersContrib/Wordpress-Plugins/tree/master/contrib-widget
Description: Integrates contrib widget that will allow users to contribute time, content, distribution, apps.
Author: Contrib.com
Version: 1.0
Author URI:Contrib.com
*/
?>
<?php
add_action('wp_head','head_func');
function head_func()
{
  $domain = $_SERVER["HTTP_HOST"];
  $domain = str_replace("http://","",$domain);
  $domain = str_replace("www.","",$domain);
  echo '<script type="text/javascript" src="http://tools.contrib.com/cwidget?d='.$domain.'&p=ur&c=f"></script>';
}