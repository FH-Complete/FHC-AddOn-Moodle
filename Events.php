<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

use CI3_Events as Events;
Events::on('lvMenuBuild', function ($menu_reference,$params) {
	require_once(__DIR__ . '/lib/MoodleClientConstants.php');
	require_once(__DIR__ . '/config/config.php');

	// extracts all key=>value pairs of the associative array as variables in the current scope 
	extract($params);

	require_once(FHCPATH.'include/phrasen.class.php');

	if(!isset($p))
	{
		$p = new phrasen($sprache);
	}

	$GLOBALS['connection'] = $connection;
	$GLOBALS['activeConnection'] = $activeConnection;

	$menu =& $menu_reference();
	$addon_lvinfo_col = array();
	require_once(dirname(__FILE__).'/cis/menu_lv.inc.php');
});


Events::on('moodleCalendarEvents', function ($moodle_events_reference, $params) {
	require_once(__DIR__ . '/lib/MoodleClientConstants.php');
	require_once(__DIR__ . '/config/config.php');
	
	if(CIS_LVPLAN_MOODLE_INTEGRATION){
		// extracts all key=>value pairs of the associative array as variables in the current scope 
		extract($params);
	
		$GLOBALS['connection'] = $connection;
		$GLOBALS['activeConnection'] = $activeConnection;
		$moodle_events =& $moodle_events_reference();
		require_once(__DIR__ . '/cis/get_events_by_userid.php');
	}
});

Events::on('loadRenderers', function ($renderers) {
	require_once(__DIR__ . '/lib/MoodleClientConstants.php');
	require_once(__DIR__ . '/config/config.php');
	if(CIS_LVPLAN_MOODLE_INTEGRATION){
		$moodle_renderers =& $renderers();
		$moodle_renderers["moodle"] = array(
			'calendarEvent' => APP_ROOT.'addons/moodle/renderers/calendarEvent.js',
			'modalTitle' => APP_ROOT.'addons/moodle/renderers/modalTitle.js',
			'modalContent' => APP_ROOT.'addons/moodle/renderers/modalContent.js',
			'calendarEventStyles' => APP_ROOT.'addons/moodle/renderers/moodleStyles.css'
		);
	}
});

Events::on('lehreinheit_delete_check', function($addError, $lehreinheit_id) {
	require_once(__DIR__ . '/lib/LogicCourses.php');
	$res = LogicCourses::coursesLehreinheitExists($lehreinheit_id);
	$course4LeExists = Database::fetchRow($res);

	if($course4LeExists->count < 1)
	{
		return;
	}

	if(!isset($p))
	{
		require_once(FHCPATH.'include/phrasen.class.php');
		$sprache = getSprache();
		$p = new phrasen($sprache);
	}

	$addError($p->t('moodle/course4LeExists'));
});
