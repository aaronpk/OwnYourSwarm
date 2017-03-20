<?php

class ProcessCheckin {

  public static function run($checkin_id) {
    $checkin = ORM::for_table('checkins')->find_one($checkin_id);
    $user = ORM::for_table('users')->find_one($checkin->user_id);

    $ch = curl_init('https://api.foursquare.com/v2/checkins/'.$checkin->foursquare_checkin_id.'?v=20170319&oauth_token='.$user->foursquare_access_token);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $info = json_decode(curl_exec($ch), true);

    $entry = self::checkinToHEntry($info['response']['checkin'], $user);

    if(isset($entry['properties']['photo'])) {
      $checkin->photos = json_encode($entry['properties']['photo'], JSON_UNESCAPED_SLASHES);
    }

    $checkin->mf2_data = json_encode($entry, JSON_UNESCAPED_SLASHES);
    $checkin->save();

    $micropub_response = micropub_post($user, $entry);

    if(in_array($micropub_response['code'],[201,202]) && isset($micropub_response['headers']['Location'])) {
      $canonical_url = $micropub_response['headers']['Location'][0];
    } else {
      $canonical_url = false;
    }

    if($canonical_url) {
      $checkin->success = 1;
      $checkin->canonical_url = $canonical_url;
    }
    $checkin->pending = 0;

    $checkin->save();
  }

  public static function checkinScoreToHEntry($score, $checkin, &$user) {

  }

  public static function checkinToHEntry($checkin, &$user) {
    $date = DateTime::createFromFormat('U', $checkin['createdAt']);
    $tz = new DateTimeZone(sprintf('%+d', $checkin['timeZoneOffset'] / 60));
    $date->setTimeZone($tz);

    $entry = [
      'type' => ['h-entry'],
      'properties' => [
        'published' => [$date->format('c')],
        'syndication' => ['https://www.swarmapp.com/user/'.$checkin['user']['id'].'/checkin/'.$checkin['id']],
      ]
    ];

    if(isset($checkin['shout'])) {
      $entry['properties']['content'] = [$checkin['shout']];
    }

    if(isset($checkin['photos'])) {
      $photos = [];
      foreach($checkin['photos']['items'] as $p) {
        $photos[] = $p['prefix'].'original'.$p['suffix'];
      }
      $entry['properties']['photo'] = $photos;
    }

    $venue = $checkin['venue'];

    // Include person tags
    if(isset($checkin['with'])) {
      $entry['properties']['category'] = [];
      foreach($checkin['with'] as $with) {
        // Check our users table to find the person's website if they use OwnYourSwarm
        $person_urls = ['https://foursquare.com/user/'.$with['id']];
        $person = ORM::for_table('users')->where('foursquare_user_id', $with['id'])->find_one();
        if($person) {
          array_unshift($person_urls, $person->url); // canonical URL is first in the list
        }
        $entry['properties']['category'][] = [
          'type' => ['h-card'],
          'properties' => [
            'url' => $person_urls,
            'name' => [$with['firstName']],
            'photo' => [$with['photo']['prefix'].'300x300'.$with['photo']['suffix']]
          ]
        ];
      }
    }

    $hcard = [
      'type' => ['h-card'],
      'properties' => [
        'name' => [$venue['name']],
        'url' => ['https://foursquare.com/v/'.$venue['id']],
      ]
    ];

    if(isset($venue['url']) && $venue['url']) {
      $hcard['properties']['url'][] = $venue['url'];
    }

    if(isset($venue['contact'])) {
      if(isset($venue['contact']['twitter'])) {
        $hcard['properties']['url'][] = 'https://twitter.com/'.$venue['contact']['twitter'];
      }
      if(isset($venue['contact']['formattedPhone'])) {
        $hcard['properties']['tel'] = [$venue['contact']['formattedPhone']];
      }
    }

    # Map Foursquare properties to h-card properties
    $props = [
      'latitude' => 'lat',
      'longitude' => 'lng',
      'street-address' => 'address',
      'locality' => 'city',
      'region' => 'state',
      'country-name' => 'country',
    ];

    foreach($props as $k=>$v) {
      if(isset($venue['location'][$v])) {
        $hcard['properties'][$k] = [$venue['location'][$v]];
      }
    }

    $entry['properties']['checkin'] = [$hcard];

    return $entry;
  }

}
