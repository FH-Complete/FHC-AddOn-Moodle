<?php

require_once(dirname(__FILE__).'/../lib/LogicCourses.php'); // A lot happens here!

require_once('../../../include/functions.inc.php');
require_once('../../../include/phrasen.class.php');
require_once('../../../include/lehreinheit.class.php');

if (!$db = new basis_db())
{
	die('Fehler beim Herstellen der Datenbankverbindung');
}

$user = get_uid();

if (isset($_GET['stsem']))
{
	$stsem = $_GET['stsem'];
}
if (isset($_GET['courses']))
{
	$courses = $_GET['courses'];
}

$moodle_courses = array();

foreach ($courses as $course)
{
	$lvId = $course['lvId'];
	$leId = $course['leId'];

	if (LogicCourses::coursesLehreinheitExists($leId))
	{
		$moodle_course = LogicCourses::getCoursesFromLehreinheit($leId);
	}
	elseif (LogicCourses::coursesLehrveranstaltungStudiensemesterExists($lvId, $stsem))
	{
		$moodle_course = LogicCourses::getCoursesByLehrveranstaltungStudiensemester($lvId, $stsem);
	}
	array_push($moodle_courses, $moodle_course);
}

echo json_encode($moodle_courses);

?>
