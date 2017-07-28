<?php
// URL of Moodle installation
define('ADDON_MOODLE_PATH','https://localhost/moodle/');

// Moodle Webservice Token
define('ADDON_MOODLE_TOKEN','');

// Lector is able to Create Course
define('ADDON_MOODLE_LECTOR_CREATE_COURSE',true);

// Debug Level
define('ADDON_MOODLE_DEBUGLEVEL', 0);

/**
 * Strukturierung der Kursbereich im Moodle
 *
 * Mögliche Werte:
 * STSEM-STG-SEM (default)
 * DEP-STG-JG-STSEM
 */
define('ADDON_MOODLE_COURSE_SCHEMA','STSEM-STG-SEM');

/**
 * Synchronisierung der Fachbereichsleitungen in die Moodle Kurse
 * Dazu muss im Moodle ein Rolle angelegt werden
 * Diese Rolle bekommen die FBL zugeteilt
 *
 * Mögliche Werte:
 * true
 * false (default)
 */
define('ADDON_MOODLE_SYNC_FACHBEREICHSLEITUNG', false);

/**
 * Definiert die Rollen ID für Fachbereichsleitungen
 */
define('ADDON_MOODLE_FACHBEREICHSLEITUNG_ROLEID', 11);
?>
