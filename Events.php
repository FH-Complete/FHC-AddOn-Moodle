<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

use CI3_Events as Events;



Events::on('lvMenuBuild', function ($menu_reference,$params) {

	// extracts all key=>value pairs of the associative array as variables in the current scope 
	extract($params);

	$menu =& $menu_reference();
	$addon_lvinfo_col = array();
	require_once(dirname(__FILE__).'/cis/menu_lv.inc.php');
	
});  

