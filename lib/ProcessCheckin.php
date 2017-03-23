<?php

class ProcessCheckin {

  public static $tiers = [
    # 1,2,3,5,10,15
    1,15,30,60,120,300,600,1800,3600
  ];

  public static function nextTier($tier) {
    $index = array_search((int)$tier, self::$tiers);
    if(array_key_exists($index+1, self::$tiers))
      return self::$tiers[$index+1];
    else
      return false;
  }

  public static function scheduleNext(&$checkin) {
    $next = self::nextTier($checkin->poll_interval);
    if($next) {
      q()->queue('ProcessCheckin', 'run', [$checkin->id], [
        'delay' => $next
      ]);
      $checkin->poll_interval = $next;
      $checkin->date_next_poll = date('Y-m-d H:i:s', time()+$next);
    } else {
      $checkin->poll_interval = 0;
      $checkin->date_next_poll = null;
    }
    $checkin->save();
    return $next;
  }

  public static function run($checkin_id) {
    $checkin = ORM::for_table('checkins')->find_one($checkin_id);
    if(!$checkin) {
      echo "Checkin $checkin_id not found\n";
      return;
    }
    $user = ORM::for_table('users')->find_one($checkin->user_id);
    if(!$user) {
      echo "User not found\n";
      return;
    }

    echo date('Y-m-d H:i:s') . "\n";
    echo "User: " . $user->url . "\n";
    echo "Checkin: " . $checkin->foursquare_checkin_id . "\n";

    $ch = curl_init('https://api.foursquare.com/v2/checkins/'.$checkin->foursquare_checkin_id.'?v=20170319&oauth_token='.$user->foursquare_access_token);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $info = json_decode(curl_exec($ch), true);

    if(!isset($info['response']['checkin'])) {
      echo "Checkin not found: ".$checkin->foursquare_checkin_id."\n";
      return;
    }

    // New checkin
    if(!$checkin->canonical_url) {
      $entry = self::checkinToHEntry($info['response']['checkin'], $user);

      $num_photos = 0;
      if(isset($entry['properties']['photo'])) {
        $checkin->photos = json_encode($entry['properties']['photo'], JSON_UNESCAPED_SLASHES);
        $checkin->num_photos = $num_photos = count($entry['properties']['photo']);
      }

      echo "Checkin has $num_photos photos\n";

      $checkin->foursquare_data = json_encode($info['response']['checkin'], JSON_UNESCAPED_SLASHES);
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
        echo "Success! ".$canonical_url."\n";
      } else {
        echo "Micropub post failed\n";
      }
      $checkin->pending = 0;

      $checkin->save();

    } else {
      // Updated checkin (a photo was added)
      $canonical_url = $checkin->canonical_url;

      $existing_photos = json_decode($checkin->photos, true);

      $data = $info['response']['checkin'];

      $num_photos = 0;
      if(isset($data['photos'])) {
        $num_photos = count($data['photos']['items']);
        $photos = [];
        foreach($data['photos']['items'] as $p) {
          $photos[] = $p['prefix'].'original'.$p['suffix'];
        }

        $new_photos = array_diff($photos, $existing_photos);
        echo "Found ".count($new_photos)." new photos\n";

        if(count($new_photos)) {
          $update = [
            'action' => 'update',
            'url' => $canonical_url,
            'add' => [
              'photo' => $new_photos
            ]
          ];
          $micropub_response = micropub_post($user, $update);
          if(in_array($micropub_response['code'],[200,201,202,204])) {
            echo "Update of ".$canonical_url." was successful\n";
          } else {
            echo "Failed to update checkin\n";
            echo $micropub_response['response']."\n";
          }
        }

        $checkin->photos = json_encode($photos, JSON_UNESCAPED_SLASHES);
        $checkin->num_photos = $num_photos;
        $checkin->save();
      }

    }

    // If there was no photo, queue another polling task to check for the photo later
    if($canonical_url && $num_photos == 0) {
      $next = self::scheduleNext($checkin);
      if($next) {
        echo "No photo found. Scheduling another check in $next seconds\n";
      } else {
        echo "No photo found. Reached max poll interval, giving up.\n";
      }
    }
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
      $text = $checkin['shout'];
      $html = $checkin['shout'];

      if($checkin['entities']) {
        // Replace from right to left so the offsets aren't messed up
        foreach(array_reverse($checkin['entities']) as $entity) {
          if($entity['type'] == 'user') {
            $person = ORM::for_table('users')->where('foursquare_user_id', $entity['id'])->find_one();
            if($person)
              $person_url = $person->url;
            else
              $person_url = 'https://foursquare.com/user/'.$entity['id'];

            $new_html = mb_substr($html, 0, $entity['indices'][0])
              . '<a href="' . $person_url . '">'
              . mb_substr($html, $entity['indices'][0], $entity['indices'][1]-$entity['indices'][0])
              . '</a>'
              . mb_substr($html, $entity['indices'][1]);
            $html = $new_html;
          }
        }
      }

      if($text == $html) {
        $entry['properties']['content'] = [$text];
      } else {
        $entry['properties']['content'] = [
          ['value'=>$text, 'html'=>$html]
        ];
      }
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
