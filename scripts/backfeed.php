<?php
chdir(dirname(__FILE__).'/..');
include('vendor/autoload.php');

$users = ORM::for_table('users')
  ->where_lt('date_next_poll', date('Y-m-d H:i:s'))
  ->find_many();
foreach($users as $user) {
  Backfeed::run($user->id);
}
