<?php

// WARNING: Do not edit this file. Copy or rename it as config.php before change it

// ------------------------------------------------------------------------------------------------------------
// Courses synching options

// STSEM-STG-SEM (default) or DEP-STG-JG-STSEM
define('ADDON_MOODLE_COURSE_SCHEMA', 'STSEM-STG-SEM');

// Format des Kursnamens
// %1$s Studiengangskuerzel
// %2$s Organisationsform
// %3$s Ausbildungssemester
// %4$s Studiensemester
// %5$s Lehrveranstaltungsbezeichnung
define('ADDON_MOODLE_COURSE_NAME', '%1$s-%2$s-%3$s-%4$s - %5$s');

// Gibt an ob bei der Kursbezeichnung automatisch der Lektor angehaengt werden soll
// Possible values: true | false
define('ADDON_MOODLE_COURSE_NAME_LEKTOR', false);

// Gibt an ob bei der Kurserstellung Gruppenuebernahme auswählbar ist
define('ADDON_MOODLE_COURSE_GRUPPEN', false);

// Defines the number of topics for new courses
// Possible values: 0 (default), 1, 5, 10, ...
define('ADDON_MOODLE_NUMSECTIONS_VALUE', 0);
define('ADDON_MOODLE_NUMSECTIONS_NAME', 'numsections');

// Used to set or not the end date parameter when creating a new course
define('ADDON_MOODLE_VERSION_SET_END_DATE', '3.3');
define('ADDON_MOODLE_VERSION_LENGTH', 5);

// Default category root ID
define('ADDON_MOODLE_ROOT_CATEGORY_ID', '0');

// Default course format
define('ADDON_MOODLE_COURSE_FORMAT', 'topics');

define('ADDON_MOODLE_JAHRGANG_CATEGORY_NAME' , 'Jahrgang ');
define('ADDON_MOODLE_INSERTVON' , 'Courses synchronization job');

// Groups to be added to the table addon.tbl_moodle
define('ADDON_MOODLE_GROUPS_TO_SYNCH_DELIMITER', ';');
define('ADDON_MOODLE_GROUPS_TO_SYNCH', 'TW_ITSERVICE;TW_FIX_LKT');
// Course name that will contains all users from groups
define('ADDON_MOODLE_GROUPS_COURSE_SHORTNAME', 'TW_FIX');
define('ADDON_MOODLE_GROUPS_COURSE_FULLNAME', 'TW - FIX');

// By default only updates moodle from addon.tbl_moodle,
// otherwise generates recors in addon.tbl_moodle and then moodle
define('ADDON_MOODLE_JUST_MOODLE', true);

// Lector is able to Create Course
define('ADDON_MOODLE_LECTOR_CREATE_COURSE', true);

// Enable/disable grades from moodle
define('ADDON_MOODLE_ENABLE_GRADES', true);

// ------------------------------------------------------------------------------------------------------------
// Users synching options

// Default language
define('ADDON_MOODLE_DEFAULT_LANGUAGE', 'en');

// If the matrikelnummer should be synchronized
define('ADDON_MOODLE_SYNC_PERSONENKENNZEICHEN', false);

// If the management staff should be synchronized
define('ADDON_MOODLE_SYNC_FACHBEREICHSLEITUNG', false);

// Roles IDs in moodle
define('ADDON_MOODLE_LEKTOREN_ROLEID', 3);
define('ADDON_MOODLE_STUDENT_ROLEID', 5);
define('ADDON_MOODLE_FACHBEREICHSLEITUNG_ROLEID', 10);
define('ADDON_MOODLE_LV_ANGERECHNET_ROLEID', 11);
define('ADDON_MOODLE_KOMPETENZFELDLEITUNG_ROLEID', 19);
define('ADDON_MOODLE_DEPARTMENTLEITUNG_ROLEID', 20);
define('ADDON_MOODLE_STUDIENGANGSLEITUNG_ROLEID', 17);
define('ADDON_MOODLE_ASSISTENT_ROLEID', 30);
define('ADDON_MOODLE_FACULTY_LEADER_ROLEID', 40);

// Organisation unit types deifinition
define('ADDON_MOODLE_DEPARTMENT', 'Department');
define('ADDON_MOODLE_KOMPETENZFELD', 'Kompetenzfeld');

// Organisation unit types
define('ADDON_MOODLE_OUTYPES', '\'Department\', \'Kompetenzfeld\'');
define('ADDON_MOODLE_OUTYPES_CATEGORIES', '\'Fakultaet\', \'Studiengang\', \'Lehrgang\'');
define('ADDON_MOODLE_OUTYPE_COURSE', 'Lehrgang');
define('ADDON_MOODLE_OUTYPE_FACULTY', 'Fakultaet');
define('ADDON_MOODLE_OUTYPE_DEGREE', 'Studiengang');

// User functions used to retrieve users from database
define('ADDON_MOODLE_COURSE_FUNCTIONS', '\'Leitung\'');
define('ADDON_MOODLE_CATEGORY_FUNCTIONS', '\'Leitung\', \'ass\', \'stvLtg\'');

// User functions values
define('ADDON_MOODLE_CATEGORY_FUNCTION_ASSISTENT', 'ass');
define('ADDON_MOODLE_CATEGORY_FUNCTION_LEADER', 'Leitung');

// Parameters used to create a new user in moodle
define('ADDON_MOODLE_USER_MANUAL_AUTH', 'manual');
define('ADDON_MOODLE_USER_PWD_PREFIX', 'FHCv!A2');
define('ADDON_MOODLE_USER_LDAP_AUTH', 'ldap');
define('ADDON_MOODLE_USER_PKZ_TYPE', 'pkz');

// To avoid incurring the limitation of parameters that can be posted imposed by apache + php + moodle
define('ADDON_MOODLE_POST_PARAMS_NUMBER', 300);

// Defines the separator for the courses id posted in vilesci usersSynchronization page
define('ADDON_MOODLE_VILESCI_COURSES_IDS_SEPARATOR', ';');
define('ADDON_MOODLE_VILESCI_MAX_NUMBER_COURSES', 42);

// ------------------------------------------------------------------------------------------------------------
// Running options

// Enable or disable debug messages
define('ADDON_MOODLE_DEBUG_ENABLED', false);

// Perform a dry run (= do NOT write in moodle or database)
define('ADDON_MOODLE_DRY_RUN', false);

define('ADDON_MOODLE_START_END_DATE_FORMAT', 'Y-m-d H:i:s');

// ------------------------------------------------------------------------------------------------------------
// Connection configuration

$activeConnection = 'DEFAULT'; // the used configuration set of the chosen connection

// Example of a configuration set, all parameters are required!
$connection = array(
	'DEFAULT' => array(
		PROTOCOL => 'https', // ssl by default... better!
	    HOST => 'hostname', // moodle server name
	    PATH => 'webservice/rest/server.php', // usually this is the path for REST API
		TOKEN => '123456', // activated token
		WS_FORMAT => 'json' // default JSON
	)
);
