<?php

class SendWebmentions {

  public static function send($id) {
    $webmention = ORM::for_table('webmentions')->find_one($id);
    if(!$webmention) {
      echo "Webmention not found: $id\n";
      return;
    }

    $checkin = ORM::for_table('checkins')->find_one($webmention->checkin_id);

    if(!$checkin) {
      echo "Checkin not found: $webmention->checkin_id\n";
      return;
    }

    $source_url = Config::$baseURL . '/checkin/' . $webmention->foursquare_checkin . '/' . $webmention->hash;

    echo "Sending webmention\n";
    echo "s: ".$source_url."\n";
    echo "t: ".$checkin->canonical_url."\n";
    $client = new IndieWeb\MentionClient();
    $response = $client->sendWebmention($source_url, $checkin->canonical_url);

    $webmention->response_date = date('Y-m-d H:i:s');
    $webmention->response_code = $response['code'];
    $webmention->response_body = $response['body'];
    if(isset($response['headers']['Location']))
      $webmention->response_location = $response['headers']['Location'];
    $webmention->save();

    $user = ORM::for_table('users')->find_one($checkin->user_id);
    if(!in_array($response['code'],[200,201,202])) {
      // No webmention endpoint found for this checkin, or webmention failed
      $user->failed_webmentions = $user->failed_webmentions + 1;
    } else {
      $user->failed_webmentions = 0;
    }
      
    // After 10 failed webmentions, disable checking backfeed completely
    if($user->failed_webmentions > 10) {
      $user->send_responses_swarm = 0;
      $user->send_responses_other_users = 0;
    }
    
    $user->save();

    echo "code: ".$response['code']."\n";
    if(isset($response['headers']['Location']))
      echo "status: ".$response['headers']['Location']."\n";
  }

}

