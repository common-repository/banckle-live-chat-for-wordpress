<?php
/*
  Plugin Name: Banckle Chat for Wordpress
  Plugin URI: http://banckle.com
  Description: The most innovative and feature-complete online live support tool ever made. Banckle Chat can help business increase sales, boost productivity, and cut costs of support and customer service. Get more happier customers.
  Version: 1.3.9
  Author: Imran Anwar
  Author URI: http://banckle.com

  Copyright 2012  banckle.com  (email : imran.anwar@banckle.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details at <http://www.gnu.org/licenses/>.
 */

$dir = get_option('siteurl') . '/' . PLUGINDIR . '/banckle-live-chat-for-wordpress/';
$chat_server="https://chat.banckle.com";
$widgetTextMessages = array(
		"title" => array("label" => "Title", "value" => "Banckle Chat"),
		"poweredBy" => array("label" => "Copyright Text:", "value" => "Powered by Banckle"),
		"welcomeMessage" => array("label" => "Welcome:", "value" => "Welcome to Banckle Chat."),
		"inviteMessage" => array("label" => "Invite:", "value" => "How may I help you today?"),
		"waitingMessage" => array("label" => "Waiting:", "value" => "Please stand by while we connect you to the next available operator..."),
		"changeToOffline" => array("label" => "Unavailable:", "value" => "Our operators are all busy, please leave an offline message."),
		"invalidEmail" => array("label" => "Invalid Email:", "value" => "Please enter a valid email address."),
		"offlineMessageSent" => array("label" => "Offline message sent:", "value" => "Your offline message is successfully sent. Someone from our team will contact you when we see your message."),
		"enterEmailToSend" => array("label" => "Enter email:", "value" => "Enter email to send transcript."),
		"finalMessage" => array("label" => "Exit: ", "value" =>"Thank you for choosing Banckle.")
);
function widgetText($texts, $field) {
	global $widgetTextMessages;
	if(array_key_exists($field, $texts))
		return $texts[$field];
	$f = $widgetTextMessages[$field];
	return $f["value"];
}



load_plugin_textdomain('bancleLiveChat', 'wp-content/plugins/banckle-live-chat-for-wordpress');


add_action('init', 'BanckleLiveChatInit');
add_action('wp_footer', 'BanckleLiveChatFooterScript');
//add_action('wp_header', 'BanckleLiveChatFooterScript');
add_action('admin_notices', 'BanckleLiveChatAdminNotice');

/**
 * Banckle Chat Widget
 */
class BanckleLiveChatWidget extends WP_Widget {

	 /** constructor */
	 function BanckleLiveChatWidget() {
		  parent::WP_Widget(false, $name = 'Banckle Chat Widget');
	 }

	 /** @see WP_Widget::widget */
	 function widget($args, $instance) {
         global $chat_server;

		  if (get_option('BLCScript') && get_option('blcLocation')==="banckleLiveChatWidget") {
				$loc = get_option('blcLocation') === "" ? "banckleLiveChatBottomRight" : get_option('blcLocation');
				echo("\n<!-- Banckle Chat Code Start -->\n");
				echo("<script type='text/javascript' src='$chat_server/chat/visitor.do?dep=" . get_option('BLCScript') . "'></script>");
				echo("<a href='#' onclick='blc_startChat();return false;'><img id='blc_chatImg' style='border:0px;' src='$chat_server/chat/onlineImg.do?d=" . get_option('BLCScript') . "'></a>");
				echo("\n<!-- Banckle Chat Code End -->\n");
		  }
	 }

	 /** @see WP_Widget::update */
	 function update($new_instance, $old_instance) {
		  $instance = $old_instance;
		  return $instance;
	 }

	 /** @see WP_Widget::form */
	 function form($instance) {
        if (!get_option('BLCScript'))
            echo('<strong>' . sprintf(__('Banckle Chat is disabled. Please go to <a href="%s"> page</a> to enable it.'), admin_url('options-general.php?page=banckle-live-chat-for-wordpress')) . '</strong>');
	 }

}

add_action('widgets_init', create_function('', 'return register_widget("BanckleLiveChatWidget");'));


function banckleLiveChatRequest($url, $method="GET", $postdata = "") {
    global $chat_server;
    $url = $chat_server . $url;

	 $method = strtoupper($method);
	 $session = curl_init();
	 curl_setopt($session, CURLOPT_URL, $url);
	 curl_setopt($session, CURLOPT_REFERER, "https://chat.banckle.com");
	 if ($method == "GET") {
		  curl_setopt($session, CURLOPT_HTTPGET, 1);
	 } else {
		  curl_setopt($session, CURLOPT_POST, 1);
		  curl_setopt($session, CURLOPT_POSTFIELDS, $postdata);
		  curl_setopt($session, CURLOPT_CUSTOMREQUEST, $method);
	 }
	 curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
	 curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
	 if (preg_match("/^(https)/i", $url))
		  curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
	 $result = curl_exec($session);
	 curl_close($session);
     if($result)
         return json_decode($result, true);
	 return $result;
}
function authenticate($loginId, $password) {
    $wpurl = get_bloginfo('wpurl');
    //authenticate by BA module
    $ret = banckleLiveChatRequest("/api/authenticate?userid=$loginId&password=$password&sourceSite=$wpurl&platform=wordpress", "GET", "");
    if(!$ret)
        return null;
    //authenticate by BC
    $r = $ret["return"];
    $token = $r['token'];
    if($token) {
        $resp = banckleLiveChatRequest("/chat/v3/connect?token=$token");
        if($resp["@status_code"] == 200) {
            $ret["connectionId"] = $resp["return"]["connectionId"];
            $ret["resource"] = $resp["return"]["resource"];
        }
    }
    return $ret;
}
    

function BanckleLiveChatInit() {
	 if (function_exists('current_user_can') && current_user_can('manage_options')) {
		  add_action('admin_menu', 'BanckleLiveChatSettings');
		  add_action('admin_menu', 'BanckleCreateMenu');
	 }
}

function BanckleCreateMenu() {

	 //create new top-level menu
	 add_menu_page('Banckle Settings', 'Banckle Chat', 'administrator', 'BanckleLiveChatSettings', 'BanckleLiveChatSettings_page');
	 add_submenu_page('BanckleLiveChatSettings', 'Banckle Chat', 'Settings', 'administrator', 'BanckleLiveChatSettings', 'BanckleLiveChatSettings_page');
	 add_submenu_page('BanckleLiveChatSettings', 'Dashboard', 'Dashboard', 'administrator', 'banckleLiveChatDashboard', 'banckleLiveChatDashboard');
}

function banckleLiveChatDashboard() {
    global $chat_server;

	 echo "<div id='dashboarddiv'><iframe id='dashboardiframe' src='$chat_server' height=800 width=98% scrolling='no'></iframe></div>      <a href='$server' target='_newWindow' onClick='javascript:document.getElementById(\'dashboarddiv\').innerHTML=\'\'; '>Dashboard in a new window!</a>.
      ";
}

function BanckleLiveChatCustomError($errno, $errstr, $errfile, $errline) {
	 echo '<div class="wp-lc-login-form-error"> Oops, ' . $errstr . ' @ ' . $errline . '</div>';
}

function BanckleLiveChatFooterScript() {
?>
	 <style type="text/css">
	 	 #banckleLiveChatButton{position: fixed;vertical-align: middle;text-align: center;z-index:999999999 !important;}
	 	 .banckleLiveChatBottomLeft{bottom: 0px;left: 5px;}
	 	 .banckleLiveChatBottomRight{bottom: 0px;right: 22px;}
	 	 .banckleLiveChatTopLeft{top: 5px;left: 5px;}
	 	 .banckleLiveChatTopRight{top: 5px;right: 5px;}
	 	 #banckleLiveChatButton img{}
	 </style>
<?php
	 global $current_user;
     global $chat_server;
	 if (get_option('BLCScript') && get_option('blcLocation')!=="banckleLiveChatWidget") {
		  $loc = get_option('blcLocation') === "" ? "banckleLiveChatBottomRight" : get_option('blcLocation');
		  echo("<div id='banckleLiveChatButton' class='" . $loc . "'>");
		  echo("\n<!-- Banckle Chat Code Start -->\n");
                  echo("<script type='text/javascript' async='async' defer='defer' src='$chat_server/chat/visitor.do?dep=" . get_option('BLCScript') . "'></script>");
		  echo("<a href='#' onclick='blc_startChat();return false;'><img id='blc_chatImg' style='border:0px;' src='$chat_server/chat/onlineImg.do?d=" . get_option('BLCScript') . "'></a>");
		  echo("\n<!-- Banckle Chat Code End -->\n");
		  echo("</div>");
	 }
}

function BanckleLiveChatAdminNotice() {
	 if (!get_option('BLCScript'))
		  echo('<div class="error"><p><strong>' . sprintf(__('Banckle Chat is disabled. Please go to <a href="%s"> page</a> to enable it.'), admin_url('options-general.php?page=banckle-live-chat-for-wordpress')) . '</strong></p></div>');
}

function BanckleLiveChatDeploymentForm($deptId, $deptName, $resource, $loginId, $password) {
?>

	 <form method="post" action="options-general.php?page=banckle-live-chat-for-wordpress&action=deploy">
	 	 <div class="wp-lc-login-form">
	 		  <div class="wp-lc-login-form-btm">
	 				<div style="width: 580px; padding: 5px; margin: 0px auto; line-height:26px;">
	 					 <fieldset>
	 						  <legend>Widget</legend>
	 						  <div class="row">
	 								<span class="label"><label>Widget:</label></span>
	 								<span class="formw"><input value="New Widget" type="text" name="deployment" id="deployment" style="width:275px"/></span>
	 						  </div>
	 					 </fieldset>
	 					 <fieldset>
	 						  <legend>Messages</legend>
	 						  <?php 
	 						  global $widgetTextMessages;
	 						  foreach($widgetTextMessages as $name => $field)
	 						  {
	 						  	$label = $field['label'];
	 						  	$value = $field['value'];
	 						  	echo "<div class='row'>
	 						  	<span class='label'><label>$label</label></span>
	 						  	<span class='formw'><textarea name='$name' id='$name' style='width:275px'>$value</textarea></span>
	 						  	</div>";
	 						  }
	 						  ?>
	 						  <div class="row">
	 								<span class="label"><label>Department:</label></span>
	 								<span class="formw"><input type="hidden" name="departments" id="departments" value="<?php echo $deptId; ?>" />
	 									 <input type="hidden" name="loginId" id="loginId" style="width:275px" value="<?php echo $loginId ?>"/>
	 									 <input type="hidden" name="password" id="password"  style="width:275px" value="<?php echo $password ?>"/>
	 									 <input type="hidden" name="resource" id="resource" value="<?php echo $resource; ?>" style="width:275px"/>
	 									 <input disabled="true" type="text" name="deptName" id="deptName" value="<?php echo $deptName; ?>" style="width:275px"/>
	 								</span>
	 						  </div>
	 					 </fieldset>
	 					 <fieldset>
	 						  <legend>Appearance</legend>
	 						  <div class="row">
	 								<span class="label"><label>Position</label></span>
	 								<span class="formw"><select name="blcLocation" id="blcLocation"><option value="banckleLiveChatWidget">Widget</option><option value="banckleLiveChatTopLeft">Top Left</option><option value="banckleLiveChatTopRight">Top Right</option><option value="banckleLiveChatBottomLeft">Bottom Left</option><option value="banckleLiveChatBottomRight">Bottom Right</option></select> </span>
	 						  </div>
	 					 </fieldset>

	 					 <div class="row">
	 						  <span class="label">&nbsp;</span>
	 						  <span class="formw"><input type="submit" class="button-primary" value="Create Widget" /></span>
	 					 </div>

	 				</div><!--width 580px; -->
	 		  </div><!--only for bottom bg -->
	 	 </div><!--wp-lc-login-form -->
	 </form>



<?php
}

function BanckleLiveChatSettings() {

	 function BanckleLiveChatSettings_page() {
		  global $dir;
		  global $widgetTextMessages;
		  set_error_handler("banckleLiveChatCustomError");
		  try {
?>

				<style>

					 .banckle-lc-wp-container{
						  border:1px solid #DBDBDB;
						  float:left;
						  padding:0px;
						  margin:0px;
						  width:798px;
						  position:relative;
					 }

					 .banckle-lc-wp-container IMG{ border:0px;}

					 .banckle-lc-wp-container h3{
						  clear:both;
						  display:block;
						  float:left;
						  padding: 10px 0px 0px 0px ;
						  margin:0px;
						  background: url(<?php echo($dir) ?>images/banckle-lc-wp-top1.gif) top left repeat-x;
						  height:42px;
						  text-indent:15px;
						  font-size:16px;
						  color:#333;
						  width:100%;
						  font-weight:bold;
					 }

					 .banckle-lc-wp-container-left{
						  display:inline;
						  float:left;
						  padding:10px;
						  margin:0px;
						  width:130px;
					 }

					 .banckle-lc-wp-container-right{
						  display:inline;
						  float:right;
						  width:620px;
						  padding:10px;
						  margin:0px;
						  _font:12px/20px Georgia, "Times New Roman", Times, serif;
						  color:#666;
					 }

					 OL{ margin:10px 0px;}
					 li{margin-left:30px;}

					 .banckle-lc-wp-container-right TEXTAREA{
						  border:1px inset #eee;
						  background:#F8F8F8;
						  padding:5px;
						  margin-left:0px;
						  margin-bottom:5px;
						  _height:100px;
						  _width:600px;
					 }
					 div.row {
						  clear: both;
						  padding-top: 5px;
					 }

					 div.row span.label {
						  float: left;
						  width: 240px;
						  text-align: right;
					 }

					 div.row span.formw {
						  float: left;
						  width: 235px;
						  text-align: left;
					 }

					 .steps3{
						  display:block;
						  clear:both;
						  float:left;
						  background:url(<?php echo($dir) ?>images/3steps.png) top left no-repeat #ccc;
						  width:646px;
						  height:148px;
						  margin-top:20px;
					 }
					 .steps3 UL{ padding:0px; margin:0px; margin-top:55px; font:12px/18px Arial, Helvetica, sans-serif}
					 .steps3 UL LI{ padding:0px; margin:0px; display:inline; float:left; width:190px; padding:0px 12px;  }

					 .postbox table.form-table{ margin-bottom:10px;}

					 .wp-lc-mesg-success{ display:block; clear:both; float:left; width:646px; padding:78px 0px 0px 0px; text-align: center; height:70px; background:url(<?php echo($dir) ?>images/bg_success.png) top left no-repeat #ccc; font:bold 12px arial}

					 .wp-lc-login{ display:block; clear:both; float:left; width:646px; padding:14px 0px 14px 0px; text-align: center; height:116px; background:url(<?php echo($dir) ?>images/bg_login.png) top left no-repeat #ccc; font:bold 12px arial}

					 .wp-lc-error{ display:block; clear:both; float:left; width:646px; padding:78px 0px 0px 0px; text-align: center; height:70px; background:url(<?php echo($dir) ?>images/bg_error.png) top left no-repeat #ccc; font:bold 12px arial}

					 .wp-lc-deploy{ display:block; clear:both; float:left; width:646px; padding:24px 0px 24px 0px; text-align: center; height:70px; background:url(<?php echo($dir) ?>images/bg_deployment.png) top left no-repeat #ccc; font:bold 12px arial}

					 .wp-lc-login-form{ display:block; clear:both; float:left; width:646px; padding:14px 0px 0px 0px; text-align: center; background:url(<?php echo($dir) ?>images/bg_logon_top.png) top left no-repeat #FEE180; font:bold 12px arial}
					 .wp-lc-login-form-btm{background:url(<?php echo($dir) ?>images/bg_logon_btm.png) bottom left no-repeat; float:left; padding-bottom:20px; width:100%}
					 .wp-lc-login-form-error{ display:block; clear:both; float:left; width:624px; padding:2px 10px; margin:4px 0px 4px 0px; text-align:left; background:#FFEBE8; border:1px solid #CC0000}

					 .wp-lc-login-form legend{ border-bottom: 1px solid #FEF3CC; clear: both; display: block; float: left; font: bold 14px arial;  margin: 15px 0px 5px 0px;  text-align: left;  width: 100%; background:#FEE9A5; padding:5px;}

					 fieldset {
						  margin: 1.5em 0 1.5em 0;padding: 0; /*background-color:#DBDBDB*/}
					 legend {
						  margin-left: 1em;
						  color: #000000;
						  font-weight: bold;
					 }



				</style>
				<div class="wrap">
	 <?php screen_icon() ?>
		  	 <script src='<?php echo($dir) ?>jquery.json-2.2.min.js'></script>
		  	 <h2>Banckle Chat</h2>
		  	 <div class="metabox-holder meta-box-sortables pointer">
		  		  <div class="postbox" style="float:left;width:57em;margin-right:20;">
		  				<h3 class="hndle" style="background: url(<?php echo($dir) ?>images/banckle-lc-wp-top1.gif) top left repeat-x; line-height: 30px;"><img style=" vertical-align: bottom;" src="<?php echo($dir) ?>images/live-chat_32.png" alt="<?php _e('Banckle Chat', 'Banckle Chat') ?>" /><span> Banckle Chat for WordPress</span></h3>

		  				<div class="inside" style="padding: 0 10px">
		  					 <table class="form-table">
		  						  <tr><td>
									 <?php
									 if (!function_exists('curl_init')) {
										  throw new Exception('Banckle Chat needs the CURL PHP extension.');
									 }
									 if (!function_exists('json_decode')) {
										  throw new Exception('Banckle Chat needs the JSON PHP extension.');
									 }


									 if (!get_option('BLCScript')) {
									 ?>
	 								</td></tr>
	 						  <tr valign="top">
	 								<td>
	 									 <div style="font-size:12px;">Banckle Chat Plugin has been successfully installed into your WordPress. To activate it, please visit <a target="_blank" href="http://banckle.com/wiki/display/livechat/integration-with-banckle-live-chat-wordpress-plugin-v.1.2.html">Integration with WordPress</a> page to follow a step-by-step tutorial with screenshots.</div>
	 								</td>
	 						  </tr>

	 						  <tr valign="top" style="padding-bottom:30px;">
	 								<td style="background:url(<?php echo($dir) ?>images/powered-by-banckle.gif) bottom right no-repeat; padding-bottom:60px;">
									 <?php
										  if (isset($_REQUEST['action']) && $_REQUEST['action'] === "deploy") {
												if ($_SERVER['REQUEST_METHOD'] == 'POST') {
													 $loginId = $_POST['loginId'];
                                                                                                         //echo "loginid:" . $loginId;
													 $password = $_POST['password'];
													 $resource = $_POST['resource'];
													 $auth = authenticate($loginId, $password);
													 $resource = $auth['resource'];
													 $deptId = $_POST['departments'];

													 $location = $_POST['blcLocation'];

													 $deployment = $_POST['deployment'];
													 $deployment = $deployment === "" ? "New Widget" : $deployment;

													 $title = $_POST['title'];
													 $title = $title === "" ? "Live Chat" : $title;
				
													 $texts = array();
													 
													 foreach($widgetTextMessages as $key => $field)
													 {
														if($_POST[$key])
															$texts[$key] = $_POST[$key];
													 }
													 $widget = array("name" => $deployment, "departments" => array($deptId), "textCustomization" => $texts);
													 $xmlDeploy = banckleLiveChatRequest("/chat/v3/widgets?resource=$resource", "POST", json_encode($widget));
									 ?>

													 <form method="post" action="options.php">
										  <?php wp_nonce_field('update-options'); ?>
		  										  <div class="wp-lc-deploy">
		  												<div style="width: 580px; padding: 5px; margin: 0px auto; line-height:26px">
		  													 <div class="row" style=" font-weight: normal;">
		  														  <b>Congratulations!</b> Banckle Chat Widget is <b>successfully</b> created.
		  													 </div>

		  													 <div class="row">
		  														  <span class="label">
		  																<input type="hidden" name="BLCScript" id="BLCScript" value="<?php echo $xmlDeploy['return']['id']; ?>"/>
		  																<input type="hidden" name="loginId" id="loginId" value="<?php echo $loginId ?>"/>
		  																<input type="hidden" name="password" id="password"  value="<?php echo $password ?>"/>
		  																<input type="hidden" name="blcLocation" id="blcLocation" value="<?php echo $location ?>"/>
		  																<input type="hidden" name="action" value="update" />
		  																<input type="hidden" name="page_options" value="BLCScript,loginId,password,blcLocation" />&nbsp;
		  														  </span>
		  														  <span class="formw"><input style="margin-top:8px" type="submit" class="button-primary" value="<?php _e('Activate Live Chat') ?>" /></span>
		  													 </div>
		  												</div>
		  										  </div>
		  									 </form>
									 <?php
												}
										  }
										  if (isset($_REQUEST['action']) && $_REQUEST['action'] === "dept") {
												if ($_SERVER['REQUEST_METHOD'] == 'POST') {
													 $loginId = $_POST['loginId'];
                                                                                                         //echo "loginid: " . $loginId;
													 $password = $_POST['password'];
													 $dept = $_POST['deptName'];
													 $deptId = isset($_POST['deptId']) ? $_POST['deptId'] : null;
													 $dept = $dept === "" ? "New Department" : $dept;
													 $resource = '';

													 $arr = authenticate($loginId, $password);

													 //echo $content . '<br><br>';
													 if ($arr) {
														  if (array_key_exists('error', $arr)) {
									 ?><div class="wp-lc-error">Oops, <?php echo $arr['error']['details']; ?>.<br/><input style="margin-top:8px;" type="submit" class="button-primary" onclick="parent.location='options-general.php?page=banckle-live-chat-for-wordpress'" value="<?php _e('Try Again') ?>" /></div><?php
														  } else {
																$resource = $arr['resource'];
																if (!isset($deptId)) {
																	 $jsonDept = banckleLiveChatRequest("/chat/v3/departments?resource=$resource", "POST", '{displayName : "' . $dept . '", members : ["' . $loginId . '"]}');
																	 $jsonDeptArray = $jsonDept["return"];
																	 if (array_key_exists('id', $jsonDeptArray)) {
																		  $deptId = $jsonDeptArray['id'];
																	 }
																}
																if ($deptId === "") {
									 ?><div class="wp-lc-error">Oops <?php echo $jsonDept; ?>, Department is not created, Please <br/><input style="margin-top:8px;" type="submit" class="button-primary" onclick="parent.location='options-general.php?page=banckle-live-chat-for-wordpress&action=dept'" value="<?php _e('Try Again') ?>" /></div><?php
																} else {
																	 BanckleLiveChatDeploymentForm($deptId, $dept, $resource, $loginId, $password);
																}
														  }
													 }
												}//end action dept post
										  }//end action dept
										  if (isset($_REQUEST['action']) && $_REQUEST['action'] === "signup") {
												if ($_SERVER['REQUEST_METHOD'] == 'POST') {
													 $loginId = $_POST['email'];
													 $password = $_POST['password'];
													 $email = $_POST['email'];

													 $arr;
													 $arr = banckleLiveChatRequest("/api/registeruser?password=$password&email=$email", "GET", "");
													 if ($arr) {
														  //var_dump($arr);
														  if (array_key_exists('error', $arr)) {
																//
									 ?>
					 									 <div class="wp-lc-error">
					 																														Oops, <?php echo $arr['error']['details']; ?>.<br/>
					 										  <input style="margin-top:8px;" type="submit" class="button-primary" onclick="parent.location='options-general.php?page=banckle-live-chat-for-wordpress&action=signup'" value="<?php _e('Try Again') ?>" />
					 									 </div>
									 <?php
														  } else {
									 ?>
						  									 <div class="wp-lc-login-form-error">Your Banckle account has been created successfully.</div>
						  									 <form method="post" action="options-general.php?page=banckle-live-chat-for-wordpress&action=dept">
						  										  <div class="wp-lc-login">
						  												<div style="width: 580px; padding: 5px; margin: 0px auto; line-height:26px;">
						  													 <div class="row">
						  														  <span class="label"><label>Department:</label></span>
						  														  <span class="formw"><input type="text" name="deptName" id="deptName" style="width:275px"/></span>
						  														  <input type="hidden" name="loginId" id="loginId" style="width:275px" value="<?php echo $loginId ?>"/>
						  														  <input type="hidden" name="password" id="password"  style="width:275px" value="<?php echo $password ?>"/>
						  													 </div>
						  													 <div class="row">
						  														  <span class="label">&nbsp;</span>
						  														  <span class="formw"><input type="submit" class="button-primary" value="Create Department" /></span>
						  													 </div>
						  												</div>

						  										  </div>
						  									 </form>
									 <?php
														  }
													 }
												} else {
													 //}
									 ?>
													 <form method="post" action="options-general.php?page=banckle-live-chat-for-wordpress&action=signup">
														  <div class="wp-lc-login">
																<div style="width: 580px; padding: 5px; margin: 0px auto; line-height:26px;">
																	 <!--div class="row">
																		  <span class="label"><label>Banckle Login ID:</label></span>
																		  <span class="formw"><input type="text" name="loginId" id="loginId" style="width:275px"/></span>
																	 </div-->
																	 <div class="row">
																		  <span class="label"><label>Email:</label></span>
																		  <span class="formw"><input type="text" name="email" id="email"  style="width:275px"/></span>
																	 </div>                                                                                                                                         
																	 <div class="row">
																		  <span class="label"><label>Password:</label></span>
																		  <span class="formw"><input type="password" name="password" id="password"  style="width:275px"/></span>
																	 </div>
																	 <div class="row">
																		  <span class="label">&nbsp;</span>
																		  <span class="formw"><input type="submit" class="button-primary" value="Register" /> <span><a href="options-general.php?page=banckle-live-chat-for-wordpress">Sign In</a></span></span>
																	 </div>
																</div>

														  </div>
													 </form>
									 <?php
												}
										  } else {
												if (!isset($_REQUEST['action']))
													 if ($_SERVER['REQUEST_METHOD'] == 'POST') {

														  $loginId = $_POST['loginId'];
														  $password = $_POST['password'];
														  $resource = '';
														  $arr = authenticate($loginId, $password);

														  //echo $content . '<br><br>';
														  if (true) {
																//var_dump($arr);

																if ($arr === null || array_key_exists('error', $arr)) {
																	 if($arr === null){
																		  echo '<div class="wp-lc-error">Oops! This user ID does not exist. <br/><input style="margin-top:8px;" type="submit" class="button-primary" onclick="parent.location=\'options-general.php?page=banckle-live-chat-for-wordpress\'" value="Try Again" /></div>';
																	 }else{
									 ?>
																		  <div class="wp-lc-error">Oops! <?php echo $arr['error']['details']; ?><br/><input style="margin-top:8px;" type="submit" class="button-primary" onclick="parent.location='options-general.php?page=banckle-live-chat-for-wordpress'" value="<?php _e('Try Again') ?>" /></div>


									 <?php
																	 }
																} else {
																	 //var_dump($arr);
																	 $resource = $arr['resource'];

																	 //Get the XML document loaded into a variable
																	 $xmlDept = banckleLiveChatRequest("/chat/v3/departments?resource=" . $resource, "GET", "");


																	 //if ($xmlDept->count() <= 0 || $xmlDept->department[0]->id == "") {
																	 if (count($xmlDept["return"]) == 0) {
									 ?>

									 									 <form method="post" action="options-general.php?page=banckle-live-chat-for-wordpress&action=dept">
									 										  <div class="wp-lc-login">
									 												<div style="width: 580px; padding: 5px; margin: 0px auto; line-height:26px;">
									 													 <div class="row" style=" font-weight: normal; text-align: left;margin-left: 75px;">
									 														  <b>It seems you have not created a department yet.</b> Its easy! So lets create a department now.
									 													 </div>
									 													 <div class="row">
									 														  <span class="label"><label>Department:</label></span>
									 														  <span class="formw"><input type="text" name="deptName" id="deptName" style="width:275px"/></span>
									 														  <input type="hidden" name="loginId" id="loginId" style="width:275px" value="<?php echo $loginId ?>"/>
									 														  <input type="hidden" name="password" id="password"  style="width:275px" value="<?php echo $password ?>"/>
									 													 </div>
									 													 <div class="row">
									 														  <span class="label">&nbsp;</span>
									 														  <span class="formw"><input type="submit" class="button-primary" value="Create Department" /></span>
									 													 </div>
									 												</div>

									 										  </div>
									 									 </form>
									 <?php
																	 } else {


																		  //Get the XML document loaded into a variable
																		  $xmlDeploy = banckleLiveChatRequest("/chat/v3/widgets?resource=" . $resource, "GET", "");

																		  //$xmlDeploy = new SimpleXMLElement(utf8_encode($xmlDeploy));
																		  if (count($xmlDeploy["return"]) > 0) {
									 ?>


										  									 <form method="post" action="options.php">
										  <?php wp_nonce_field('update-options'); ?>
									 										  <div class="wp-lc-deploy">

									 												<div style="width: 580px; padding: 5px; margin: 0px auto; line-height:26px">
									 													 <div class="row">
									 														  <span class="label"><label>Select Widget:</label></span>
									 														  <span class="formw">
									 																<select id="BLCScript" name="BLCScript"  style="width:140px">
																	 <?php
                                                                                $widgets = $xmlDeploy["return"];
																				for ($i = 0; $i < count($widgets); $i++) {
																					$widget = $widgets[$i];
																					 echo '<option value="' . $widget["id"] . '">' . $widget["name"] . '</option>';
																				}
																	 ?>
		  																</select>
		  														  </span>
		  													 </div>
		  													 <div class="row">
		  														  <span class="label">
		  															  <!--input type="hidden" name="BLCScript" id="BLCScript" value="<?php echo $widgets[0]["id"]; ?>"/-->
		  																<input type="hidden" name="loginId" id="loginId" value="<?php echo $loginId ?>"/>
		  																<input type="hidden" name="password" id="password"  value="<?php echo $password ?>"/>
		  																<input type="hidden" name="deptId" id="deptId"  value="<?php echo $widgets[0]["departments"][0] ?>"/>
		  																<input type="hidden" name="action" value="update" />
		  																<input type="hidden" name="page_options" value="BLCScript,loginId,password,deptId" />&nbsp;
		  														  </span>
		  														  <span class="formw"><input style="margin-top:8px" type="submit" class="button-primary" value="<?php _e('Activate Live Chat') ?>" /></span>
		  													 </div>
		  												</div>

		  										  </div>
		  									 </form>
									 <?php
																		  } else {
									 ?>
										  									 <form method="post" action="options-general.php?page=banckle-live-chat-for-wordpress&action=dept">
										  										  <div class="wp-lc-login">
										  												<div style="width: 580px; padding: 5px; margin: 0px auto; line-height:26px;">
										  													 <div class="row" style=" font-weight: normal; text-align: left;margin-left: 75px;">
										  														  <b>It seems you have not added a widget yet.</b> Its easy! So lets add a widget.
										  													 </div>
										  													 <div class="row">
										  														  <input type="hidden" name="deptId" id="deptId" style="width:275px" value="<?php echo $xmlDept['return'][0]['id'] ?>"/>
										  														  <input type="hidden" name="deptName" id="deptName" style="width:275px" value="<?php echo $xmlDept['return'][0]['displayName'] ?>"/>
										  														  <input type="hidden" name="loginId" id="loginId" style="width:275px" value="<?php echo $loginId ?>"/>
										  														  <input type="hidden" name="password" id="password"  style="width:275px" value="<?php echo $password ?>"/>
										  													 </div>
										  													 <div class="row">
										  														  <span class="label">&nbsp;</span>
										  														  <span class="formw"><input type="submit" class="button-primary" value="Create Widget" /></span>
										  													 </div>
										  												</div>
										  										  </div>
										  									 </form>
									 <?php
																		  }//ends deployments
																	 }// ends departments
																	 //echo '<br><b>' . $token . '</b><br>';
																}// ends user auth.
														  }// end if contents got from banclke
														  else {
																echo "Network Error";
														  }
													 } else {
									 ?>

					 									 <form method="post" action="options-general.php?page=banckle-live-chat-for-wordpress">
					 										  <div class="wp-lc-login">


					 												<div style="width: 580px; padding: 5px; margin: 0px auto; line-height:26px;">
					 													 <div class="row">
					 														  <span class="label"><label>Banckle Login ID:</label></span>
					 														  <span class="formw"><input type="text" name="loginId" id="loginId" style="width:275px"/></span>
					 													 </div>
					 													 <div class="row">
					 														  <span class="label"><label>Password:</label></span>
					 														  <span class="formw"><input type="password" name="password" id="password"  style="width:275px"/></span>
					 													 </div>
					 													 <div class="row">
					 														  <span class="label">&nbsp;</span>
					 														  <span class="formw"><input type="submit" class="button-primary" value="Continue" /></span>
					 													 </div>
					 													 <div class="row">
                                                                            <span>Don&apos;t have a Banckle account? Please <a href="options-general.php?page=banckle-live-chat-for-wordpress&action=signup">Sign up.</a></span>
					 													 </div>
					 												</div>

					 										  </div>
					 									 </form>
									 <?php
													 }// ends ispostback check






													 
										  }// ends action check
									 ?>
	 								</td></tr>
						  <?php
									 } else {
										  if (isset($_REQUEST['action']) && $_REQUEST['action'] === "updateDeploy") {
												$loginId = $_POST['loginId'];
												$password = $_POST['password'];
												$resource = $_POST['resource'];
												$deployId = $_POST['deployId'];
												$deployment = $_POST['deployment'];
												$departments = $_POST['departments'];
												$location = $_POST['blcLocation'];

												$widget = array();
												$texts = array();
												foreach($widgetTextMessages as $name => $field)
												{
													$s = $_POST[$name];
													if($s)
														$texts[$name] = $s;
												}
												$xmlDeploy = banckleLiveChatRequest("/chat/v3/widgets/" . $deployId . '?resource=' . $resource, "PUT", json_encode(array("textCustomization" =>  $texts)));



												update_option('blcLocation', $location);
												update_option('BLCScript', $deployId);

												echo '<div class="wp-lc-mesg-success">Deployment is updated!<br><input type="button" class="button-primary" value="OK" onclick="parent.location=\'options-general.php?page=banckle-live-chat-for-wordpress\'" /></div>';

												/*
												  ?>

												  <form method="post" action="options.php">
												  <?php wp_nonce_field('update-options'); ?>
												  <div class="wp-lc-deploy">
												  <div style="width: 580px; padding: 5px; margin: 0px auto; line-height:26px">
												  <div class="row">
												  Deployment is updated successfully!
												  </div>

												  <div class="row">
												  <span class="label">
												  <input type="hidden" name="blcLocation" id="blcLocation" value="<?php echo $location ?>"/>
												  <input type="hidden" name="action" value="update" />
												  <input type="hidden" name="page_options" value="blcLocation" />&nbsp;
												  </span>
												  <span class="formw"><input style="margin-top:8px" type="submit" class="button-primary" value="<?php _e('Ok') ?>" /></span>
												  </div>
												  </div>
												  </div>
												  </form>
												  <?php
												 */
										  }
										  if (isset($_REQUEST['action']) && $_REQUEST['action'] === "custom") {
												$loginId = get_option('loginId');
												$password = get_option('password');
												if(isset($_REQUEST['deployid']) && !empty($_REQUEST['deployid']) )
												{
													$deployId = $_REQUEST['deployid'];
												}
												else
												{
													$deployId = get_option('BLCScript');
												}
												
												$arr = authenticate($loginId, $password);

												if ($arr) {
													 if (array_key_exists('error', $arr)) {
						  ?>
														  <div class="wp-lc-error">Oops, <?php echo $arr['error']['details']; ?>.<br/><input style="margin-top:8px;" type="submit" class="button-primary" onclick="parent.location='options-general.php?page=banckle-live-chat-for-wordpress'" value="<?php _e('Try Again') ?>" /></div>

						  <?php
													 } else {
														  //var_dump($arr);		
                                                          $resource = $arr['resource'];                              
														  $xmlDeploy = banckleLiveChatRequest("/chat/v3/widgets?resource=$resource", "GET", "");
														  $widgets = $xmlDeploy['return'];
														  
														  
														  $blcLocationWidget = ""; 
														  $blcLocationTopLeft = ""; 
														  $blcLocationTopRight = "";
														  $blcLocationBottomLeft = "";
														  $blcLocationBottomRight = "";
                                                                                                                  
                                                                                                                 														  
														  if(strcmp(get_option('blcLocation'),"banckleLiveChatWidget") == 0 ){
														      $blcLocationWidget = "selected=\"selected\"";
														  }
                                                          if(strcmp(get_option('blcLocation'), "banckleLiveChatTopLeft") == 0){
														      $blcLocationTopLeft = "selected=\"selected\"";
											  			  }
                                                          if(strcmp(get_option('blcLocation'), "banckleLiveChatTopRight") == 0){
														      $blcLocationTopRight = "selected=\"selected\"";
														  }
                                                          if(strcmp(get_option('blcLocation'), "banckleLiveChatBottomLeft") == 0){
														      $blcLocationBottomLeft = "selected=\"selected\"";
														  }
                                                          if(strcmp(get_option('blcLocation'), "banckleLiveChatBottomRight") == 0){
														      $blcLocationBottomRight = "selected=\"selected\"";
														  }		

						  ?>
														
														  <form method="post" action="options-general.php?page=banckle-live-chat-for-wordpress&action=updateDeploy">
																<div class="wp-lc-login-form">
																	 <div class="wp-lc-login-form-btm">
																		  <div style="width: 580px; padding: 5px; margin: 0px auto; line-height:26px;">
																				<fieldset>
																					 <legend>Deployment</legend>
																					 <div class="row">
																						  <span class="label"><label>Deployment:</label></span>																						  
                                                                                          <span class="formw"><select name="deployment" id="deployment">
                                                                                          <?php
                                                                                          	  $widget = $widgets[0];
                                                                                              foreach($widgets as $w){
																								  if($w['id'] == $deployId) {
																									 $selected = 'selected="selected"';
																									 $widget = $w; 
																								  } else { 
																									 $selected = ''; 
																								  }
                                                                                                  echo "<option $selected value='".$w['name']."' id='".$w['id']."' >".$w['name']."</option>";
                                                                                              }
                                                                                              $texts = $widget['textCustomization'];
                                                                                           ?>
                                                                                           </select></span>
                                                                                                                                                                                  
                                                                                            <!--span class="formw"><input type="text" name="deployment" id="deployment" style="width:275px" value="<?php echo $xmlDeploy->deployment[0]->name; ?>"/-->
                                                                                            <!--span class="formw"><select name="deployment" id="deployment"><option selected="<?php echo $blcLocationWidget ?>" value="banckleLiveChatWidget">Widget</option><option selected="<?php echo $blcLocationTopLeft ?>" value="banckleLiveChatTopLeft">Top Left</option><option  selected="<?php echo $blcLocationTopRight ?>" value="banckleLiveChatTopRight">Top Right</option><option selected="<?php echo $blcLocationBottomLeft ?>" value="banckleLiveChatBottomLeft">Bottom Left</option><option  selected="<?php echo $blcLocationBottomRight ?>" value="banckleLiveChatBottomRight">Bottom Right</option></select> </span-->
																					 </div>
																				</fieldset>
																				<fieldset>
																					 <legend>Messages</legend>
									<?php
										global $widgetTextMessages;
									 	foreach($widgetTextMessages as $name => $field)
									 	{
											$label = $field['label'];
											$message = widgetText($texts, $name);
											echo "<div class='row'>
												  <span class='label'><label>$label</label></span>
												  <span class='formw'><textarea name='$name' id='$name' style='width:275px'>$message</textarea></span>
												 </div>";
										}
																					 ?>
																				</fieldset>
																				<fieldset>
																					 <legend>Appearance</legend>
																					 <div class="row">
																						  <span class="label"><label>Position</label></span>
																						  <span class="formw">
																						  	<select name="blcLocation" id="blcLocation">
																						  		<option <?php echo $blcLocationWidget ?> value="banckleLiveChatWidget">Widget</option>
																						  		<option <?php echo $blcLocationTopLeft ?> value="banckleLiveChatTopLeft">Top Left</option>
																						  		<option  <?php echo $blcLocationTopRight ?> value="banckleLiveChatTopRight">Top Right</option>
																						  		<option <?php echo $blcLocationBottomLeft ?> value="banckleLiveChatBottomLeft">Bottom Left</option>
																						  		<option <?php echo $blcLocationBottomRight ?> value="banckleLiveChatBottomRight">Bottom Right</option>
																						  	</select> </span>
																					 </div>
																				</fieldset>
																				<div class="row">
																					 <!--span class="label"><label>Department:</label></span-->
																					 <span class="formw">
																						  <input type="hidden" name="departments" id="departments" value="<?php echo $widget['departments'][0]; ?>" />
																						  <input type="hidden" name="loginId" id="loginId" style="width:275px" value="<?php echo $loginId; ?>"/>
																						  <input type="hidden" name="password" id="password"  style="width:275px" value="<?php echo $password; ?>"/>
																						  <input type="hidden" name="deployId" id="deployId"  style="width:275px" value="<?php echo $widget['id']; ?>"/>
																						  <input type="hidden" name="resource" id="resource" value="<?php echo $resource; ?>" style="width:275px"/>
                                                                                          <input type="hidden" name="page_options" value="BLCScript,loginId,password,deployId, blcLocation" />&nbsp;																	 
                                                                                      </span>
																				</div>
																				<div class="row">
																					 <span class="label">&nbsp;</span>
																					 <span class="formw"><input type="submit" class="button-primary" value="Update Deployment" /><input type="button" class="button-primary" value="Cancel" onclick="parent.location='options-general.php?page=banckle-live-chat-for-wordpress'" /></span>
																				</div>

																		  </div><!--width 580px; -->
																	 </div><!--only for bottom bg -->
																</div><!--wp-lc-login-form -->
														  </form>
														  <script>
														  jQuery(document).ready(function(){
															jQuery('#deployment').change(function(){ 
																var deployId = jQuery('#deployment option:selected').attr('id');
																window.location = 'options-general.php?page=banckle-live-chat-for-wordpress&action=custom&deployid='+deployId;
															});
														  });
														  </script>
						  <?php
													 }
												}
										  }
										  if (!isset($_REQUEST['action']) || ((isset($_REQUEST['action']) && $_REQUEST['action'] === "deploy"))) {
						  ?>
					 						  <tr valign="top">
					 								<td style="background:url(<?php echo($dir) ?>images/powered-by-banckle.gif) bottom right no-repeat ; padding-bottom:60px">
					 									 <div class="wp-lc-mesg-success">
					 											Congratulations! Banckle Chat Plugin has been successfully integrated and activated for your WordPress website. Please sign into <a target="_blank" href="https://apps.banckle.com/livechat">Banckle Chat</a> to start chatting with your visitors.<br>
					 										  <form method="post" action="options.php" style="display:inline;">
<?php wp_nonce_field('update-options'); ?>
												<input type="hidden" name="BLCScript" id="BLCScript" value=""/>
												<input type="hidden" name="loginId" id="loginId" value=""/>
												<input type="hidden" name="password" id="password" value=""/>
												<input type="hidden" name="blcLocation" id="blcLocation" value=""/>
												<input type="hidden" name="action" value="update" />
												<input type="hidden" name="page_options" value="BLCScript,loginId,password,blcLocation" />
												<input style="margin-top:8px" type="submit" class="button-primary" value="<?php _e('Deactivate Live Chat') ?>" />
										  </form>
										  <form method="post" action="options-general.php?page=banckle-live-chat-for-wordpress&action=custom" style="display:inline;">
												<input type="hidden" name="loginId" id="loginId" value="<?php echo get_option('loginId') ?>"/>
												<input type="hidden" name="password" id="password" value="<?php echo get_option('password') ?>"/>
												<input type="hidden" name="deployId" id="deployId" value="<?php echo get_option('BLCScript') ?>"/>
												<input style="margin-top:8px" type="submit" class="button-primary" value="<?php _e('Customize Live Chat') ?>" />
										  </form>
									 </div>
								</td></tr>
						  <?php
										  } else {
												//if (isset($_REQUEST['action']) && $_REQUEST['action'] === "deploy") {
												//	header("location:".get_bloginfo ('wpurl')."/wp-admin/options-general.php?page=banckle-live-chat-for-wordpress");
												//}
										  }
									 }
								} catch (Exception $e) {
									 //echo 'Exception: ' . $e->getMessage();
									 if ($e->getMessage() === "String could not be parsed as XML") {
										  echo '<div class="wp-lc-login-form-error">Oops, Banckle Chat server is currently unavailable, Please try again later</div>';
									 } else {
										  if ($e->getMessage() === "String could not be parsed as XML") {

										  }else{
											echo '<div class="wp-lc-login-form-error"> Oops, [' . $e->getCode() . '] ' . $e->getMessage() . ' @ Line: ' . $e->getLine() . '</div>';
											}
									 }
								}
						  ?>

	 					 </table>
	 				</div>

	 		  </div>
	 	 </div>

	 </div>

<?php
								restore_error_handler();
						  }

						  add_submenu_page('options-general.php', 'Banckle Chat', '', 'manage_options', 'banckle-live-chat-for-wordpress', 'BanckleLiveChatSettings_page');
					 }
?>
