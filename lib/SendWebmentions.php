<?php

class SendWebmentions {

  public static function send($id) {
    $webmention = ORM::for_table('webmentions')->find_one($id);
    if(!$webmention) {
      echo "Not found: $id\n";
      return;
    }

    $checkin = ORM::for_table('checkins')->find_one($webmention->checkin_id);

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

    echo "code: ".$response['code']."\n";
    if(isset($response['headers']['Location']))
      echo "status: ".$response['headers']['Location']."\n";
  }

}

