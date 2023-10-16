<?php
/**
 * Description of FHCMoodleUsernameMapper
 *
 * maps FH-Complete UIDs to Moodle Usernames
 * 
 * default Implementation when FHC UID and Moodle Username
 * are identical and no mapping is needed
 * 
 * can be implemented in custom addon and configured in config.php 
 * of this FHC-Addon-Moodle via ADDON_MOODLE_USERNAME_MAPPER_INCLUDE_FILE 
 * constant
 * 
 * @author harald.bamberger@technikum-wien.at
 */
class FHCMoodleUsernameMapper
{
	public static function MoodleUsernameToFHCUid($moodleusername)
	{
		return $moodleusername;
	}
	
	public static function FHCUidToMoodleUsername($fhcuid)
	{
		return $fhcuid;
	}
}
