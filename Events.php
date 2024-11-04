<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

use CI3_Events as Events;


//require_once(__DIR__.'/config/config.php');
Events::on('lvMenuBuild', function ($menu_reference,$params) {

	// extracts all key=>value pairs of the associative array as variables in the current scope 
	//extract($params);
	$lv = $params['lv'];
	$lvid = $params['lvid'];
	$angezeigtes_stsem = $params['angezeigtes_stsem'];
	$angemeldet = $params['angemeldet'];
	$is_lector = $params['is_lector'];
	$p = $params['p'];
	$user = $params['user'];

	require_once(__DIR__ . '/lib/MoodleClientConstants.php');
	require_once(__DIR__ . '/config/config.php');
	$GLOBALS['connection'] = $connection;
	$GLOBALS['activeConnection'] = $activeConnection;

	$menu =& $menu_reference();
	$addon_lvinfo_col = array();
	require_once(dirname(__FILE__).'/cis/menu_lv.inc.php');
	
});  

