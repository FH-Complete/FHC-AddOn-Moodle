<?php

/**
 * This script adds all the users (lectors, students and management staff) present on FHComplete to moodle
 * It also adds all the users from groups linked to a moodle course, into the linked moodle course
 * The database table used to link users to moodle courses is addon.tbl_moodle
 */

require_once('../lib/LogicUsers.php');

// Checks if the user has the permissions to run this script
LogicUsers::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize new users script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));

$days = 3;

// Retrieves the new users from the database
$newUsers = LogicUsers::getNewUsers($days);

Output::printInfo('Number of new users added in the last '.$days.' days: '.Database::rowsNumber($newUsers));

LogicUsers::synchronizeNewUsers($newUsers); // All the magic happens here!

Output::printInfo('Ended synchronize new users script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));
Output::printLineSeparator();
