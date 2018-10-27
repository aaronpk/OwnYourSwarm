<?php
chdir(dirname(__FILE__).'/..');
include('vendor/autoload.php');

$users = ORM::for_table('users')
  ->where_lt('date_next_poll', date('Y-m-d H:i:s'))
  ->where('micropub_success', 1)
  ->where_not_equal('foursquare_access_token', '')
  ->where_not_equal('micropub_access_token', '')
  ->find_many();
foreach($users as $user) {
  Backfeed::run($user->id);
}
