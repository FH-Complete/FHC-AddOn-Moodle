<?php

require_once('../lib/MoodleAPI.php');
require_once('../config/config.php');


$username = $_GET['username'];
$timestart = intval($_GET['timestart']);
$timeend = intval($_GET['timeend']);

$moodleAPI = new MoodleAPI();
header('Content-Type: application/json');
//echo json_encode($result);
// Call the method
$events = $moodleAPI->fhcomplete_events_by_userid($username,$timestart,$timeend);
echo json_encode($events); 

?>