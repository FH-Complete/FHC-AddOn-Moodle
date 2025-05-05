<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

use CI3_Events as Events;

//require_once(__DIR__.'/config/config.php');
Events::on('lvMenuBuild', function ($menu_reference,$params) {

	// extracts all key=>value pairs of the associative array as variables in the current scope 
	extract($params);

	require_once(FHCPATH.'include/phrasen.class.php');

	if(!isset($p))
	{
		$p = new phrasen($sprache);
	}

	require_once(__DIR__ . '/lib/MoodleClientConstants.php');
	require_once(__DIR__ . '/config/config.php');
	$GLOBALS['connection'] = $connection;
	$GLOBALS['activeConnection'] = $activeConnection;

	$menu =& $menu_reference();
	$addon_lvinfo_col = array();
	require_once(dirname(__FILE__).'/cis/menu_lv.inc.php');
});


Events::on('moodleCalendarEvents', function ($moodle_events_reference, $params) {

	// extracts all key=>value pairs of the associative array as variables in the current scope 
	extract($params);

	require_once(__DIR__ . '/lib/MoodleClientConstants.php');
	require_once(__DIR__ . '/config/config.php');
	$GLOBALS['connection'] = $connection;
	$GLOBALS['activeConnection'] = $activeConnection;
	$moodle_events =& $moodle_events_reference();
	require_once(__DIR__ . '/cis/get_events_by_userid.php');
});

