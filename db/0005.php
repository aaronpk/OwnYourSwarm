<?php
chdir(dirname(__FILE__).'/..');
include('vendor/autoload.php');

$checkins = ORM::for_table('checkins')->find_many();
foreach($checkins as $checkin) {
  $data = json_decode($checkin->foursquare_data, true);
  if($data) {
    $checkin->tzoffset = $data['timeZoneOffset']*60;
    $checkin->save();
  }
}
