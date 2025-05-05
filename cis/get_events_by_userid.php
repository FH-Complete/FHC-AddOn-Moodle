<?php

require_once(dirname(__DIR__).'/lib/MoodleAPI.php');

$moodleAPI = new MoodleAPI();
header('Content-Type: application/json');
//echo json_encode($result);
// Call the method
$events = $moodleAPI->fhcomplete_events_by_userid($username,$timestart,$timeend);
$tz = new DateTimeZone(date_default_timezone_get());

foreach($events as $event){
	$timestart = new DateTime("@".$event->timestart);
	$timestart->setTimezone($tz);
	$timestart->modify('-1 seconds');
	$event->timestart = $timestart->format(DateTime::ATOM);

	$timeend = clone $timestart;
	$timeend->modify('+'.$event->timeduration.' seconds');
	$event->timeend = $timeend->format(DateTime::ATOM);
}

$moodle_events = $events; 

?>