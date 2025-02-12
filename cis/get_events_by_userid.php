<?php

require_once('../lib/MoodleAPI.php');

$timestart = time() - 432000;
$timeend = time() + 5184000;



$userid = intval($_GET['userid']);
$timestart = intval($_GET['timestart']);
$timeend = intval($_GET['timeend']);

$moodleAPI = new MoodleAPI();

$result = new stdClass();
$result->userid = $userid;
$result->timestart = $timestart;
$result->timeend = $timeend;

header('Content-Type: application/json');
//echo json_encode($result);
// Call the method
$events = $moodleAPI->fhcomplete_events_by_userid($userid, $timestart, $timeend);
echo json_encode($events); 

?>