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

/**
 * Definiert ob das Personenkennzeichen ins Moodle Uebertragen wird
 * Wenn aktiviert, wird dieses ins CustomField "pkz" geschrieben
 *
 * Mögliche Werte:
 * true
 * false (default)
 */
define('ADDON_MOODLE_SYNC_PERSONENKENNZEICHEN', false);

/**
 * Definiert die Anzahl der Themen die bei neuen Kursen
 * standardmaeßig vorhanden sein sollen.
 *
 * Mögliche Werte:
 * null (Option wird nicht gesetzt bei der Neuanlage)
 * ganze Zahl (1, 5, 10, ...)
 */
define('ADDON_MOODLE_NUM_SECTIONS',null);

/**
 * Definiert die Default Sprache von neuen Usern im Moodle
 *
 * Mögliche Werte:
 * en
 * de
 */
define('ADDON_MOODLE_DEFAULT_LANGUAGE','en');

/**
 * Wenn gesetzt, wird das Ende Datum des Kurses gesetzt mit Semesterende
 * (erst ab Moodle Version 3.3 verfuegbar)
 *
 * Mögliche Werte:
 * true
 * false
 */
define('ADDON_MOODLE_SET_END_DATE', true);
?>
