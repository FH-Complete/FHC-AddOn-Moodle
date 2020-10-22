<?php

require_once('../../../config/cis.config.inc.php');
require_once('../../../include/basis_db.class.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/phrasen.class.php');
require_once('../../../include/lehreinheit.class.php');
require_once('../config.inc.php');
require_once('../include/moodle_course.class.php');

require_once(dirname(__FILE__) . '/../config.inc.php');
require_once(dirname(__FILE__) . '/../include/moodle_course.class.php');

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
	$moodle_course = new moodle_course();
	$lvId = $course['lvId'];
	$leId = $course['leId'];

	if ($moodle_course->course_exists_for_le($leId))
	{
		$moodle_course->getCourseByLeId($leId);
	}
	elseif ($moodle_course->course_exists_for_lv($lvId, $stsem))
	{
		$moodle_course->getAll($lvId, $stsem);
	}

	array_push($moodle_courses, $moodle_course->result);

}

echo json_encode($moodle_courses);

?>
