<?php
/*
Plugin Name: Private4time
Plugin URI: http://private4time.blog-me.de
Description: Administrators can set option to make the blog private. Works as well in single blogs as in Multiple Blog installation or former wpmu. The two following methodes are nescessary for the German law "JMStV": 1.) A blog-wide flag for allowing / disallowing Userregistration can be set and can overwrite the allowance (not the veto) from Site Administrator. 2.) The Blog can be set private for a time period.
Version: 1.0
Author: Peter Gross
Author URI: http://software-regensburg.de/

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

######################################################
# Main Program
######################################################
define('PRIVAT_OPTION' , 'private4time');
define('PRIVAT_FROM_OPTION' , 'private4time_from');
define('PRIVAT_TO_OPTION' , 'private4time_to');
define('PRIVAT_MESSAGE_OPTION' , 'private4time_message');
define('USERS_CAN_REGISTER_OPTION' , 'private4time_users_can_register');

define('USERS_CAN_REGISTER' , 'users_can_register');

define('FORM_PRIVAT_OPTION' , 'frm_private4time' );
define('FORM_MESSAGE_OPTION' , 'frm_message_option' );
define('FORM_USERS_CAN_REGISTER' , 'frm_users_can_register' );
$old_timezone = '';

######################################################
# Setup the option if it doesn't exist
if( get_option(PRIVAT_OPTION) == null) { add_option( PRIVAT_OPTION, 'false' ); }
if( get_option(PRIVAT_FROM_OPTION) == null) { add_option( PRIVAT_FROM_OPTION, '00:00' ); }
if( get_option(PRIVAT_TO_OPTION) == null) { add_option( PRIVAT_TO_OPTION, '00:00' ); }
if( get_option(PRIVAT_MESSAGE_OPTION) == null) { add_option( PRIVAT_MESSAGE_OPTION, '' ); }
if( get_option(USERS_CAN_REGISTER_OPTION) == null) { add_option( USERS_CAN_REGISTER_OPTION, 'false' ); }



######################################################
# load language files
function private4time_add_language_files() {
	load_plugin_textdomain('private4time', 'wp-content/plugins/private4time'  );
}
add_action('init', 'private4time_add_language_files');

######################################################
# remove filter from wpmu-functions.php, which always returns the get_site_option('registration')
remove_filter('option_users_can_register', 'users_can_register_signup_filter');

# install new filter: if site-Administrator allows registration, the blog option defines the behavior
function option_users_can_register_private4time_filter() {
	$registration = get_site_option('registration');
	if ( $registration == 'all' || $registration == 'user' ) {
    remove_filter('option_users_can_register', 'option_users_can_register_private4time_filter');
	  $value = get_option('users_can_register');
    add_filter('option_users_can_register', 'option_users_can_register_private4time_filter');
		return $value;
	} else {
		return false;
	}
}
add_filter('option_users_can_register', 'option_users_can_register_private4time_filter');

#
#add_filter('login_message', 'private4time_login_message');



######################################################
# is the blog at this time set to private or to public?
if( get_option(PRIVAT_OPTION) == 'true' && is_private4time()) 
{
  update_option (USERS_CAN_REGISTER, '0');                         // during private blog registering is forbidden!
  if( trim(get_option(PRIVAT_MESSAGE_OPTION)) != '' )  
    add_filter('login_message', 'private4time_login_message');     // add custom login message
  add_action('get_header', 'private4time');                        // rewrite the header
}
else
{ # in public blog, the USERS_CAN_REGISTER_OPTION overwrites the last setting
	update_option (USERS_CAN_REGISTER, get_option(USERS_CAN_REGISTER_OPTION)); // give the set option to the official flag 'users_can_register'
}


######################################################
# Start the Admin Menu dialog
if ( is_admin() ) {	add_action('admin_menu', 'private4time_menu'); }

######################################################
# End of main
######################################################

######################################################
# insert Menu 'Private4Time' in settings
function private4time_menu() {
	global $wp_version;

	if( function_exists('add_submenu_page') ) 
  {
		$menutitle = __('Private4Time','private4time');
		$pagehook = add_submenu_page('options-general.php', $menutitle, $menutitle, 'manage_options', __FILE__, 'private4time_options');
	}
}

function private4time()                        // im Header zur login Maske umlenken
{
  //  if (!is_user_logged_in()) => not the right way for multible blogs or wpmu because then users from other blogs can enter => is not allowed for JMStV
  if (!(is_blog_user() || get_current_user_id()==1)) 
  {
    auth_redirect();
  }
}

function private4time_login_message()
{ 
  return '<p style="padding: 10px;">'.get_option(PRIVAT_MESSAGE_OPTION).'</p>'; 
}

######################################################
# Option Dialog function
######################################################
function private4time_options() 
{
    $err = private4time_fix_time_zone();
		
		if (!is_admin()) {
			print "Where do you come from?!";
			return false;
		}

		
		# Process post
		if ($_POST['saved'] == 'true') 
    {
      if ($_POST[FORM_PRIVAT_OPTION]) update_option (PRIVAT_OPTION, $_POST[FORM_PRIVAT_OPTION]);
			else                           update_option (PRIVAT_OPTION, 'false');
       if ($_POST[FORM_USERS_CAN_REGISTER]== '1') update_option (USERS_CAN_REGISTER_OPTION, $_POST[FORM_USERS_CAN_REGISTER]);
			else                           update_option (USERS_CAN_REGISTER_OPTION, '0');
			update_option (PRIVAT_FROM_OPTION, $_POST['from_hours'].':'.$_POST['from_minutes']);
			update_option (PRIVAT_TO_OPTION, $_POST['to_hours'].':'.$_POST['to_minutes']);
			update_option (PRIVAT_MESSAGE_OPTION, htmlspecialchars($_POST[FORM_MESSAGE_OPTION]));
			print '<p style="color: #FF0000;">'.__('Options saved','private4time').'</p>';
		}
		
		# timeformat hh:mm
    $arr = explode(':', get_option (PRIVAT_FROM_OPTION));
		$from_hours = $arr[0]; $from_minutes = $arr[1];
		$arr = explode(':', get_option (PRIVAT_TO_OPTION));
		$to_hours = $arr[0]; $to_minutes = $arr[1];

 	  $registration = get_site_option('registration');
   	if (!( $registration == 'all' || $registration == 'user' )) 
       update_option (USERS_CAN_REGISTER_OPTION, '0'); // if registration is forbidden side-width, it will also be not allowed for blog
		
		update_option (USERS_CAN_REGISTER, get_option(USERS_CAN_REGISTER_OPTION)); // give the set option to the official flag 'users_can_register'
		
    print '<div class="wrap"><div id="icon-options-general" class="icon32"><br /></div><h2>'.__('Settings').' &rsaquo; '.__('Private4Time','private4time').'</h2>';		
    print '<h4>'.__('Set your blog private for a time periode','private4time').'</h4>';

	  print '<p style="color: #FF0000;">'.$err.'</p>';

		// Start Form
		print '<form action="" method="post" name="form_private4time_option">';	
		
    printf('<table border="0" cellspacing="0" cellpadding="2" width="650px">');
    printf('<tbody>');
    printf('<tr style="background-color: #c0c0c0; text-align: center;" >');
    printf('<td style="text-align: right">'.__('time:','private4time').date('H:i').__('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;time zone: ','private4time').'</td><td style="text-align: left">'.private4time_get_time_zone().'</td><td>hh</td><td>:</td><td>mm</td><td></td><td></td><td>hh</td><td>:</td><td>mm</td><td></td>');
    printf('</tr>');

    printf('<tr style="background-color: #c0c0c0; color: #E00000" >');
		if (get_option(PRIVAT_OPTION) == 'false')
		  print '<td width="170px" style="padding:6px;"><input type="checkbox" name="' . FORM_PRIVAT_OPTION . '" value="true">'.__(' Set Blog &quot;Private&quot;','private4time').'</td>';
    else
		  print '<td width="170px" style="padding:6px;"><input type="checkbox" name="' . FORM_PRIVAT_OPTION . '" value="true" checked>'.__(' Set Blog &quot;Private&quot;','private4time').'</td>';

    print '<td width="90px" align="right" style="padding:5px;">'.__(' from: ','private4time').'</td>';
    printf('<td width="1px" style="padding:5px;"><select name="from_hours" id="from_hours">');
	  for ($i=0; $i<=23; $i++) 
    {
      if (intval($from_hours) == $i) $selected = 'selected'; else $selected = '';
      printf('<option %s value="%02d" >%02d</option>', $selected, $i, $i);
    }
    print '<td width="1px" style="padding:1px;"> : </td>';
    printf('<td width="1px" style="padding:5px;"><select name="from_minutes" id="from_minutes">');
	  for ($i=0; $i<=59; $i++) 
    {
      if (intval($from_minutes) == $i) $selected = 'selected'; else $selected = '';
      printf('<option %s value="%02d" >%02d</option>', $selected, $i, $i);
    }
    print '<td width="1px" style="padding:2px;"></td>';
    print '<td width="1px" style="padding-left:10px;">'.__(' to: ','private4time').'</td>';
    printf('<td width="1px" style="padding:5px;"><select name="to_hours" id="to_hours">');
	  for ($i=0; $i<=23; $i++) 
    {
      if (intval($to_hours) == $i) $selected = 'selected'; else $selected = '';
      printf('<option %s value="%02d" >%02d</option>', $selected, $i, $i);
    }
    print '<td width="1px" style="padding:1px;"> : </td>';
    printf('<td width="1px" style="padding:5px;"><select name="to_minutes" id="to_minutes">');
	  for ($i=0; $i<=59; $i++) 
    {
      if (intval($to_minutes) == $i) $selected = 'selected'; else $selected = '';
      printf('<option %s value="%02d" >%02d</option>', $selected, $i, $i);
    }
    print '<td style="padding:2px;"></td>';
    printf('</select></td></tr>');
    printf('</tbody>');
    printf('</table>');

    printf('<table border="0" cellspacing="0" cellpadding="2" width="650px">');
    printf('<tbody>');
    printf('<tr style="background-color: #c0c0c0; color: #E00000"; text-align: left">');
    print '<td style="padding:6px;">'.__('Login Message: ').'</td><td><textarea name="'.FORM_MESSAGE_OPTION.'" rows="2" cols="50" value="'.get_option(PRIVAT_MESSAGE_OPTION).'">'.get_option(PRIVAT_MESSAGE_OPTION).'</textarea></td><td>'.__('(optional)').'</td>';
    //print '<td style="padding:6px;">'.__('Login Message: ').'</td><td><input type="text" name="'.FORM_MESSAGE_OPTION.'" size="53" value="'.get_option(PRIVAT_MESSAGE_OPTION).'"></td><td>'.__('(optional)').'</td>';
    printf('</tr></tbody>');
    printf('</table><br />');


    print __("<br />With this option, you can set your whole blog permanently or periodically to &quot;private&quot;.<br />From 00:00 to 00:00 sets permanent to &quot;private&quot;!<br />Only blog-registered users can access to your blog.",'private4time');

    print "<br /><br /><hr><br />";

    printf('<table border="0" cellspacing="0" cellpadding="2" width="650px">');
    printf('<tbody>');
    printf('<tr style="background-color: #c0c0c0; color: #E00000" >');
	  if (get_option(USERS_CAN_REGISTER_OPTION) == '0')
	    print '<td style="padding:6px;"><input type="checkbox" name="' . FORM_USERS_CAN_REGISTER . '" value="1">'.__(' Allow User Registration for your blog (not during &quot;private&quot; period).','private4time').'</td>';
    else
	    print '<td style="padding:6px;"><input type="checkbox" name="' . FORM_USERS_CAN_REGISTER . '" value="1" checked>'.__(' Allow User Registration for your blog (not during &quot;private&quot; period).','private4time').'</td>';
    printf('</tr></tbody>');
    printf('</table><br />');
    print __("With this option, you can allow or disallow user registration for your blog.<br />On multible blog Wordpress installations (or wpmu) this option works only if site-wide registration is allowed.<br /><br />",'private4time');
	  
    printf('<table border="0" cellspacing="0" cellpadding="2" width="650px">');
    printf('<tbody>');
    printf('<tr>');
    print "<input type='hidden' name='saved' value='true'>";
		print "<td><input type='submit' name='submit' class='button-primary' value='" . __('Save Changes') . "'></td>";	 
	  print '<td style="text-align: right; color: #0000FF;"><i>'.__('Private4Time','private4time').__(' is powered by ','private4time').'<a href="http://blog-me.de" title="'.__('Create your free Wordpress Blog!', 'private4time').'"><em>Blog-Me.de</em></a>'.' & <a href="http://software-regensburg.de" title="Software & web development"><em>Software-Regensburg.de</em></a></i></td>';
    printf('</tr></tbody>');
    printf('</table>');

		print "</form>";	
    print '</div>';

    private4time_reset_time_zone();

}

######################################################
# the time check function
function is_private4time()
{
  private4time_fix_time_zone();
  
	# Zeitformat hh:mm
	$arr = explode(':', get_option (PRIVAT_FROM_OPTION));
	$from_hours = $arr[0]; $from_minutes = $arr[1];
	$arr = explode(':', get_option (PRIVAT_TO_OPTION));
	$to_hours = $arr[0]; $to_minutes = $arr[1];
 	$arr = explode(':', date('H:i'));              # get actual date()
	$act_hours = $arr[0]; $act_minutes = $arr[1];

  # calculate Unix timestamp as integer
  $from = mktime(intval($from_hours), intval($from_minutes), 0, 1, 1, 1997);
  $to   = mktime(intval($to_hours), intval($to_minutes), 0, 1, 1, 1997);
  $act  = mktime(intval($act_hours), intval($act_minutes), 0, 1, 1, 1997);
  //mktime ([ int $hour = date("H") [, int $minute = date("i") [, int $second = date("s") [, int $month = date("n") [, int $day = date("j") [, int $year = date("Y") [, int $is_dst = -1 ]]]]]]] )

  if ($from == $to) 
    $ret = true;
  else 
  {
    if ($from > $to)  $to   = mktime(intval($to_hours), intval($to_minutes), 0, 2, 1, 1997); // one day later

    if ($from <= $act && $act <= $to) $ret = true;
    else $ret = false;
  }
  
  private4time_reset_time_zone();
  return $ret;
}

######################################################
# fix time zone
function private4time_fix_time_zone() 
{
  global $old_timezone;
  if ( !function_exists( 'date_default_timezone_get' )) { 
    return sprintf(__('Warning: This plugin needs PHP5 for timezone setting. <br />%s is used instead.','private4time'), date_default_timezone_get());
  }
  $old_timezone = date_default_timezone_get(); 

  $current_offset = get_option('gmt_offset');
  $tzstring = get_option('timezone_string');
  $utc = $tzstring;

  if ( empty($tzstring) ) 
  { // Create a Etc/GMT from gmt_offset
	  $check_zone_info = false;
	  if ( 0 == $current_offset )
      $tzstring = 'Etc/GMT+0'; 
	  elseif ($current_offset < 0) {
		  $tzstring = 'Etc/GMT+' . abs($current_offset);
		  $utc = 'UTC-' . abs($current_offset);
		}
	  else {
		  $tzstring = 'Etc/GMT-' . abs($current_offset);
		  $utc = 'UTC+' . abs($current_offset);
    }
  }
  if ( !empty($tzstring) ) 
  {
    date_default_timezone_set($tzstring);
    if (date_default_timezone_get() != $tzstring) {
      return sprintf(__('Warning: The timezone %s cannot be set. <strong>%s</strong> is used instead.<br />To avoid this, please select a timezone city in the common settings.','private4time'), $utc, date_default_timezone_get());
    }
    return ''; // the only way to return no error...
  } 
  return sprintf(__('Warning: The actual timezone is empty! Please check your Wordpress configuration.<br /><strong>%s</strong> is used instead.','private4time'), date_default_timezone_get());
}

function private4time_get_time_zone()
{
  $tzstring = date_default_timezone_get();
  if (strpos($tzstring,'Etc/GMT+')!==false) $tzstring = str_replace('Etc/GMT+', 'UTC-', $tzstring);
  if (strpos($tzstring,'Etc/GMT-')!==false) $tzstring = str_replace('Etc/GMT-', 'UTC+', $tzstring);
  //echo $tzstring;
  return $tzstring;
}

function private4time_reset_time_zone() 
{
  global $old_timezone;
  if (function_exists( 'date_default_timezone_set' ))
    date_default_timezone_set($old_timezone);
}

?>