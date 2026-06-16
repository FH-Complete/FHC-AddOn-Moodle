<?php

/**
 * This script creates and synchronizes users in FHComplete Groups to Moodle categories
 */

require_once('../lib/LogicFhcGroupsToCategories.php');

// Checks if the user has the permissions to run this script
LogicFhcGroupsToCategories::isExecutionAllowed();

Output::printLineSeparator();
Output::printInfo('Starting synchronize FhcGroupsToCategories script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));

if( isset($fhc_synchronize_fhcgroups_to_categories) )
{
	foreach ($fhc_synchronize_fhcgroups_to_categories as $options)
	{
		LogicFhcGroupsToCategories::synchronizeFhcGroupToCategory($options);
	}
}

Output::printInfo('Ended synchronize FhcGroupsToCategories  script on '.date(ADDON_MOODLE_START_END_DATE_FORMAT));
Output::printLineSeparator();
