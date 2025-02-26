<?php

require_once('../lib/MoodleAPI.php');
require_once('../config/config.php');
require_once(dirname(__DIR__).'/../../config/cis.config.inc.php');


$username = $_GET['username'];
$timestart = intval($_GET['timestart']);
$timeend = intval($_GET['timeend']);

$moodleAPI = new MoodleAPI();
header('Content-Type: application/json');
//echo json_encode($result);
// Call the method
$events = $moodleAPI->fhcomplete_events_by_userid($username,$timestart,$timeend);
$tz = new DateTimeZone(date_default_timezone_get());
foreach($events->events as $event){
	$timestart = new DateTime("@".$event->timestart);
	$timestart->setTimezone($tz);
	$event->timestart = $timestart->format(DateTime::ATOM);

	$timeend = clone $timestart;
	$timeend->modify('+'.$event->timeduration.' seconds');
	$event->timeend = $timeend->format(DateTime::ATOM);
}

echo json_encode($events); 

?>