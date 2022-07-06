<?php

/**
 * This script adds all the students present on FHComplete to moodle
 * The database table used to link users to moodle courses is addon.tbl_moodle
 */

require_once('../lib/LogicCohorts.php');

// Checks if the user has the permissions to run this script
LogicCohorts::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize cohorts script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));

// Studiensemester can be passed as commandline option or automatically retrieved
// ex: php <this script> --stsem WS2019
$currentOrNextStudiensemester = LogicCohorts::getCliOrCurrentOrNextStudiensemester();

Output::printInfo('Working studiensemester: '.$currentOrNextStudiensemester);

if( isset($fhc_synchronize_cohorts) )
{
	foreach ($fhc_synchronize_cohorts as $options)
	{
		LogicCohorts::synchronizeCohorts($currentOrNextStudiensemester , $options);
	}	
}

Output::printInfo('Ended synchronize cohorts script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));
Output::printLineSeparator();
