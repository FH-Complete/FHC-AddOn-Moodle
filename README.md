# FHC-AddOn-Moodle
FH-Complete AddOn for Moodle integration

# Features

* Automatically create Moodle course for each FHComplete Course
* Automatically assign Students and Teacher to courses
* Transfer grades and points from moodle to CIS
* Moodle Addon to add additional Webservice functions

# Installation

* Install FHComplete Addon in Moodle
* Create Webservice Token in Moodle

* Copy the Repository to the FHComplete Subfolder /addon/moodle/
* Copy config-default.inc.php to config.inc.php and adapt ADDON_MOODLE_PATH and ADDON_MOODLE_TOKEN
* Add the Addon to the ACTIVE_ADDONS FHComplete Config
* Run dbcheck.php to create tables an migrate existing data
* enable cronjobs
