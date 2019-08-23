<?php
/* Copyright (C) 2019 fhcomplete.org
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307, USA.
 *
 * Authors: Paolo Bison <bison@technikum-wien.at>,
 *          Andreas Oesterreicher <andreas.oesterreicher@technikum-wien.at>
 */
/**
 * Cronjob that set Permissions of Leaders an Assistants to 
 * the DegreeProgram Course Categories
 */

require_once('../lib/LogicUsers.php');

// Checks if the user has the permissions to run this script
LogicUsers::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize categories script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));

// Studiensemester can be passed as Commandline Option
// zb php synchronizeCategories.php --stsem WS2019
$longopt = array(
	"stsem:",
);
$commandlineparams = getopt('', $longopt);
if (isset($commandlineparams['stsem']))
{
	$currentOrNextStudiensemester = $commandlineparams['stsem'];
}
else
{
	// Retrieves the current studiensemester
	$currentOrNextStudiensemester = LogicUsers::getCurrentOrNextStudiensemester();
}

Output::printInfo('Working studiensemester: '.$currentOrNextStudiensemester);

// Retrieves the courses to be synchronized from database (addon.tbl_moodle)
$dbMoodleCoursesIDsArray = LogicUsers::getDBMoodleCoursesIDsArray($currentOrNextStudiensemester);

Output::printInfo('Number of courses in the database: '.count($dbMoodleCoursesIDsArray));

// Retrieves the courses from moodle using the course ids retrieved from the database
$moodleCourses = LogicUsers::getMoodleCourses($dbMoodleCoursesIDsArray);

Output::printInfo('Number of courses in moodle: '.count($moodleCourses));

// If the amount of courses does not match between database and moodle
if (count($dbMoodleCoursesIDsArray) != count($moodleCourses))
{
	Output::printWarning('The number of courses in the database and those present in moodle does not match!');
}

LogicUsers::synchronizeCategories($moodleCourses); // All the magic happens here!

Output::printInfo('Ended synchronize categories script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));
Output::printLineSeparator();
