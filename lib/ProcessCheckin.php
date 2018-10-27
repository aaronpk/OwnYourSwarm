<?php

class ProcessCheckin {

  public static $tiers = [
    1,15,30,60,120,300,600,1800,3600,14400
  ];

  public static function nextTier($tier) {
    foreach(self::$tiers as $t) {
      if($t > $tier)
        return $t;
    }
    return $tier;
  }

  private static function scheduleNext(&$checkin) {
    $next = self::nextTier($checkin->poll_interval);
    if($next) {
      q()->queue('ProcessCheckin', 'run', [$checkin->user_id, $checkin->foursquare_checkin_id], [
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

  public static function scheduleWebmentionJobForCoins(&$checkin, $delay=5) {
    q()->queue('ProcessCheckin', 'sendCoins', [$checkin->id], [
      'delay' => $delay
    ]);
  }

  public static function getFoursquareCheckin($user, $checkin_id, $context=null) {
    $params['v'] = '20170319';
    $params['oauth_token'] = $user->foursquare_access_token;

    $ch = curl_init('https://api.foursquare.com/v2/checkins/'.$checkin_id.'?'.http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'User-Agent: '.Foursquare::userAgent()
    ]);
    $info = json_decode(curl_exec($ch), true);
    Log::fsq($user->id, 'checkins/'.$checkin_id, $context, ['http'=>curl_getinfo($ch, CURLINFO_RESPONSE_CODE)]);
    return $info;
  }

  public static function getFoursquareCheckins($user, $params=[], $context=null) {
    $params['v'] = '20170319';
    $params['oauth_token'] = $user->foursquare_access_token;

    $ch = curl_init('https://api.foursquare.com/v2/users/self/checkins?'.http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'User-Agent: '.Foursquare::userAgent()
    ]);
    $info = json_decode(curl_exec($ch), true);
    Log::fsq($user->id, 'users/self/checkins', $context, ['http'=>curl_getinfo($ch, CURLINFO_RESPONSE_CODE)]);
    return $info;
  }

  private static function _addSyndicateTo(&$properties, $syndicateTo) {
    if(isset($properties['syndicate-to'])) {
      $properties['syndicate-to'][] = $syndicateTo;
    } else {
      $properties['syndicate-to'] = [$syndicateTo];
    }
  }

  public static function run($user_id, $checkin_id, $is_import=false) {
    $user = ORM::for_table('users')->find_one($user_id);
    if(!$user) {
      echo "User not found\n";
      return;
    }

    echo date('Y-m-d H:i:s') . "\n";
    echo "User: " . $user->url . "\n";
    echo "Checkin: " . $checkin_id . "\n";

    // Load checkin if already written to the DB
    $checkin = ORM::for_table('checkins')
      ->where('user_id', $user->id)
      ->where('foursquare_checkin_id', $checkin_id)
      ->find_one();

    $info = self::getFoursquareCheckin($user, $checkin_id, 'ProcessCheckin::run');

    if(isset($info['meta']['errorDetail']) && $info['meta']['errorDetail'] == 'Invalid checkin id') {
      if($checkin) {
        // If the checkin was deleted, mark as not pending
        $checkin->pending = 0;
        $checkin->success = 0;
        $checkin->poll_interval = 0;
        $checkin->date_next_poll = null;
        $checkin->save();
        echo "Checkin was deleted\n";
        return;
      }
    }

    if(!isset($info['response']['checkin'])) {
      echo "Foursquare API returned invalid data for checkin: ".$checkin_id."\n";
      print_r($info);
      echo "\n";
      return;
    }

    if(!$checkin) {
      $checkin = ORM::for_table('checkins')->create();
      $checkin->user_id = $user->id;
      $checkin->foursquare_checkin_id = $checkin_id;
      $checkin->published = date('Y-m-d H:i:s', $info['response']['checkin']['createdAt']);
      $checkin->tzoffset = $info['response']['checkin']['timeZoneOffset'] * 60;
      $checkin->success = 0;
    }
    $checkin->foursquare_data = json_encode($info['response']['checkin'], JSON_UNESCAPED_SLASHES);
    $checkin->save();

    // New checkin
    if(!$checkin->canonical_url) {
      $entry = self::checkinToHEntry($info['response']['checkin'], $user);

      $num_photos = 0;
      if(isset($entry['properties']['photo'])) {
        $checkin->photos = json_encode($entry['properties']['photo'], JSON_UNESCAPED_SLASHES);
        $checkin->num_photos = $num_photos = count($entry['properties']['photo']);
      }

      echo "Checkin has $num_photos photos\n";

      $checkin->mf2_data = json_encode($entry, JSON_UNESCAPED_SLASHES);
      $checkin->save();

      $rules = ORM::for_table('syndication_rules')
        ->where('user_id', $user->id)
        ->find_many();
      foreach($rules as $rule) {
        switch($rule->type) {
          case 'keyword': 
            if(isset($entry['properties']['content'])) {
              $content = $entry['properties']['content'][0];
              if(is_array($content))
                $content = $content['value'];
              if(stripos($content, $rule->match) !== false) {
                self::_addSyndicateTo($entry['properties'], $rule->syndicate_to);
              }
            }
            break;
          case 'photo': 
            if(isset($entry['properties']['photo'])) {
              self::_addSyndicateTo($entry['properties'], $rule->syndicate_to);
            }
            break;
          case 'shout':
            if(isset($entry['properties']['content'])) {
              self::_addSyndicateTo($entry['properties'], $rule->syndicate_to);
            }
            break;
        }
      }

      list($params, $content_type) = self::buildPOSTPayload($user, $entry);

      $micropub_response = micropub_post($user, $params, $content_type);

      if(in_array($micropub_response['code'],[201,202]) && isset($micropub_response['headers']['Location'])) {
        $canonical_url = $micropub_response['headers']['Location'][0];
      } else {
        $canonical_url = false;
      }

      if($canonical_url) {
        $checkin->success = 1;
        $checkin->canonical_url = $canonical_url;
        echo "Success! ".$canonical_url."\n";
        $user->micropub_success = 1;
        $user->micropub_failures = 0;

        // Reset backfeed schedule
        $user->poll_interval = 30;
        $user->date_next_poll = date('Y-m-d H:i:s', time()+30);

        $user->save();
      } else {
        echo "Micropub post failed\n";
        echo $micropub_response['response']."\n";
        $user->micropub_failures++;
        $user->save();
      }
      $checkin->pending = 0;

      $checkin->save();

      if($canonical_url && $user->send_responses_swarm) {
        self::scheduleWebmentionJobForCoins($checkin);
      }
    } else {
      // Updated checkin (a photo was added)
      // TODO: if a user is checked in by someone else, and then they check themselves in with a shout, the shout will be new information needing to be sent as well
      $canonical_url = $checkin->canonical_url;

      $existing_photos = json_decode($checkin->photos, true);
      if(!$existing_photos)
        $existing_photos = [];

      $data = $info['response']['checkin'];

      $updated = false;
      $add = [];
      $replace = [];

      // Check for new photos
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
          $add['photo'] = array_values($new_photos);
          $updated = true;
        }

        $checkin->photos = json_encode($photos, JSON_UNESCAPED_SLASHES);
        $checkin->num_photos = $num_photos;
        $checkin->save();
      }

      // Check if a shout was added
      // Only for checkins imported after launching this code since the DB "shout" column is blank for old checkins
      if(strtotime($checkin->published) > strtotime('2017-08-19T16:00:00-0700')) {
        if(!$checkin->shout && !empty($data['shout'])) {
          $replace['content'] = self::_buildHEntryContent($data, true);
          $updated = true;
          $checkin->shout = $data['shout'];
          $checkin->save();
        }
      }

      // Check syndication rules again to see if any changes matched some rules
      $rules = ORM::for_table('syndication_rules')
        ->where('user_id', $user->id)
        ->find_many();
      foreach($rules as $rule) {
        switch($rule->type) {
          case 'keyword': 
            // Only check this if there was previously no shout
            if(isset($replace['content'])) {
              if(stripos($data['shout'], $rule->match) !== false) {
                self::_addSyndicateTo($add, $rule->syndicate_to);
              }
            }
            break;
          case 'photo':
            // If the checkin previously did not have any photos but does now, add this syndication
            if(isset($add['photo'])) {
              self::_addSyndicateTo($add, $rule->syndicate_to);
            }
            break;
          case 'shout':
            // Only trigger this if there was previously no shout
            if(isset($replace['content'])) {
              self::_addSyndicateTo($add, $rule->syndicate_to);
            }
            break;
        }
      }

      if($updated) {
        $update = [
          'action' => 'update',
          'url' => $canonical_url,
          'add' => $add,
          'replace' => $replace
        ];
        $micropub_response = micropub_post($user, json_encode($update));
        if(in_array($micropub_response['code'],[200,201,202,204])) {
          print_r($update);
          echo "Update of ".$canonical_url." was successful\n";

          $user->micropub_update_success = 1;
          $user->save();
        } else {
          echo "Failed to update checkin\n";
          echo $micropub_response['response']."\n";
        }

        if($user->send_responses_swarm)
          self::scheduleWebmentionJobForCoins($checkin, 30);
      } else {
        echo "No changes found\n";
      }
    }

    // If this was not an import, queue another polling task to check for updates later
    if(!$is_import && $canonical_url) {
      $next = self::scheduleNext($checkin);
      if($next) {
        echo "Scheduling another check in $next seconds\n";
      } else {
        echo "Reached max poll interval, giving up.\n";
      }
    } else {
      $checkin->poll_interval = 0;
      $checkin->date_next_poll = null;
      $checkin->save();
    }

    if($canonical_url) {
      // There probably won't be any feedback for new checkins, but this also catches
      // backfeed on manually imported and offline checkins
      Backfeed::processBackfeedForSwarmCheckin($user, json_decode($checkin->foursquare_data, true));
    }
  }

  public static function sendCoins($checkin_id) {
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

    if(!$user->send_responses_swarm)
      return;

    $data = json_decode($checkin->foursquare_data, true);
    if(!empty($data['score']) && !empty($data['score']['scores'])) {
      echo date('Y-m-d H:i:s') . "\n";
      echo "Sending webmentions for coins\n";
      echo "User: " . $user->url . "\n";
      echo "Checkin: " . $checkin->foursquare_checkin_id . "\n";
      echo "\n";

      foreach($data['score']['scores'] as $score) {
        $hash = md5(json_encode($score));

        echo "\t+".$score['points']." ".$score['message']."\n";

        $wm = ORM::for_table('webmentions')
          ->where('foursquare_checkin', $checkin->foursquare_checkin_id)
          ->where('hash', $hash)
          ->find_one();
        if(!$wm) {
          $wm = ORM::for_table('webmentions')->create();
          $wm->date_created = $checkin->published;
          $wm->checkin_id = $checkin->id;
          $wm->foursquare_checkin = $checkin->foursquare_checkin_id;
          $wm->hash = $hash;
          $wm->type = 'coin';
        }
        $wm->author_photo = $score['icon'];
        $wm->coins = $score['points'];
        $wm->content = htmlspecialchars($score['message']);
        $wm->save();

        q()->queue('SendWebmentions', 'send', [$wm->id]);
      }
    }
  }

  private static function replaceLinkedEntities($text, $entities) {
    // Encode the string as JSON, which turns it into a string like
    // "Snack \ud83d\udc69\ud83c\udffb\u200d\ud83c\udfa4 with Asha"
    $json = json_encode($text);
    // Split the JSON-encoded string to separate all the \uXXXX characters
    if(preg_match_all('/(\\\u[a-h0-9]{4}|\\\"|.)/', trim($json,'"'), $matches)) {
      $chars = $matches[0];
    }

    $offsets = []; // Keep track of which offsets have been modified
    foreach($entities as $entity) {
      if($entity['type'] == 'user') {
        $s = $entity['indices'][0];
        $e = $entity['indices'][1];

        $offsets[] = $s;
        $offsets[] = $e-1;

        // Replace the text at the start and end offset with a hyperlink
        $chars[$s] = json_encode('<a href=\"' . url_for_user($entity['id']) . '\">' . $chars[$s]);
        $chars[$e-1] = json_encode($chars[$e-1] . '</a>');
      }
    }

    $json = '';
    // Put the JSON string back together
    foreach($chars as $i=>$c) {
      if(in_array($i, $offsets)) {
        $json .= json_decode($c);
      } else {
        $json .= $c;
      }
    }

    // JSON decode the string to get the final result
    $html = json_decode('"'.$json.'"');

    return $html;
  }

  private static function _buildHEntryContent($checkin, $json=true) {
    $text = $checkin['shout'];

    if($json && isset($checkin['with'])) {
      // Remove "with X" if that is the only text in the shout
      $withStr = 'with ';
      foreach($checkin['with'] as $i=>$with) {
        $withStr .= ($i == 0 ? $with['firstName'] : ', '.$with['firstName']);
      }
      if(trim($text) == trim($withStr)) {
        $text = '';
      }
    }

    $html = $text;
    if($text && $checkin['entities']) {
      $html = self::replaceLinkedEntities($html, $checkin['entities']);
    }

    if($text == $html) {
      $content = [$text];
    } else {
      $content = [
        ['value'=>$text, 'html'=>$html]
      ];
    }

    return $content;
  }

  public static function checkinToHEntry($checkin, &$user) {
    $json = $user->micropub_style == 'json';

    $date = DateTime::createFromFormat('U', $checkin['createdAt']);
    $tz = offset_to_timezone($checkin['timeZoneOffset'] * 60);
    $date->setTimeZone($tz);

    $entry = [
      'type' => ['h-entry'],
      'properties' => [
        'published' => [$date->format('c')],
        'syndication' => ['https://www.swarmapp.com/user/'.$checkin['user']['id'].'/checkin/'.$checkin['id']],
      ]
    ];

    if(!empty($checkin['shout'])) {
      $text = $checkin['shout'];

      $entry['properties']['content'] = self::_buildHEntryContent($checkin, $json);

      if($entry['properties']['content'] == ['']) {
        unset($entry['properties']['content']);
      }

      // Include hashtags
      if(preg_match_all('/\B\#(\p{L}+\b)/u', $text, $matches)) {
        $entry['properties']['category'] = $matches[1];
      }
    }

    if(!empty($checkin['photos']['items'])) {
      $photos = [];
      foreach($checkin['photos']['items'] as $p) {
        $photos[] = $p['prefix'].'original'.$p['suffix'];
      }
      $entry['properties']['photo'] = $photos;
    }

    $venue = $checkin['venue'];

    // Include person tags
    if(isset($checkin['with'])) {
      if(!isset($entry['properties']['category']))
        $entry['properties']['category'] = [];

      foreach($checkin['with'] as $with) {
        // Check our users table to find the person's website if they use OwnYourSwarm
        $withHCard = self::foursquareUserToHCard($with);
        $entry['properties']['category'][] = $withHCard;
      }
    }

    $hcard = [
      'type' => ['h-card'],
      'properties' => [
        'name' => [$venue['name']],
        'url' => ['https://foursquare.com/v/'.$venue['id']],
      ]
    ];
    $hcard['value'] = $hcard['properties']['url'][0];

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
      'postal-code' => 'postalCode',
    ];

    foreach($props as $k=>$v) {
      if(isset($venue['location'][$v])) {
        $hcard['properties'][$k] = [$venue['location'][$v]];
      }
    }

    $entry['properties']['checkin'] = [$hcard];

    # Include a location property with h-adr with everything except venue information
    if(isset($hcard['properties']['latitude'])) {
      $hadr = $hcard;
      $hadr['type'] = ['h-adr'];
      unset($hadr['properties']['name']);
      unset($hadr['properties']['url']);
      unset($hadr['properties']['tel']);
      unset($hadr['value']);
      $entry['properties']['location'] = [$hadr];
    }

    # Add the event the user checked in to.
    # For now, this is only for the plaintext fallback mode, until we standardize around
    # how this should be marked up in Microformats. This will be removed when building the
    # JSON post request later, and used by the form-encoded format.
    if(isset($checkin['event'])) {
      $event = [
        'type' => ['h-event'],
        'properties' => [
          'name' => $checkin['event']['name']
        ],
        'value' => $checkin['event']['name']
      ];
      $entry['properties']['checkin'][] = $event;
    }

    # If someone else checked you in, add that as a new property
    if(isset($checkin['createdBy']) && $checkin['createdBy']['id'] != $checkin['user']['id']) {
      $entry['properties']['checked-in-by'] = [self::foursquareUserToHCard($checkin['createdBy'])];
    }

    return $entry;
  }

  public static function foursquareUserToHCard($user) {
    $person_urls = ['https://foursquare.com/user/'.$user['id']];
    $person = ORM::for_table('users')->where('foursquare_user_id', $user['id'])
      ->order_by_desc('last_login')
      ->find_one();
    if($person) {
      // put canonical URL first in the list
      array_unshift($person_urls, $person->url); 
    }
    return [
      'type' => ['h-card'],
      'properties' => [
        'url' => $person_urls,
        'name' => [$user['firstName']],
        'photo' => [$user['photo']['prefix'].'300x300'.$user['photo']['suffix']]
      ],
      'value' => $person_urls[0]
    ];
  }

  public static function jsonToFormEncoded($json) {
    // Convert a Micropub JSON request to form-encoded parameters
    $params = [];

    $params['h'] = str_replace('h-', '', $json['type'][0]);

    foreach($json['properties'] as $key=>$val) {
      if(count($val) > 1) {
        $params[$key] = [];
        foreach($val as $v)
          $params[$key][] = self::getPlaintextValue($v);
      } else {
        $params[$key] = self::getPlaintextValue($val[0]);
      }
    }

    if(is_array($params['checkin']) && isset($params['checkin'][1])) {
      $event = $params['checkin'][1];
      unset($params['checkin'][1]);
      $params['checkin'] = $params['checkin'][0];
    } else {
      $event = false;
    }

    unset($params['checked-in-by']);

    if(!isset($params['content']))
      $params['content'] = '';

    // Include event info in the content
    $params['content'] = 'Checked in at '.$json['properties']['checkin'][0]['properties']['name'][0] 
      . ($event ? ' for '.$event : '')
      . ($params['content'] ? '. ' . $params['content'] : '');

    // Add a Geo URI with the location
    if(isset($json['properties']['checkin'][0]['properties']['latitude'])) {
      $params['location'] = 'geo:'.$json['properties']['checkin'][0]['properties']['latitude'][0].','.$json['properties']['checkin'][0]['properties']['longitude'][0];
    } else {
      unset($params['location']);
    }

    return $params;
  }

  public static function buildPOSTPayload($user, $params, $prettyprint=false) {
    if($user->micropub_style == 'json') {
      # Remove the event checkin if set. See `checkinToHEntry` above for details.
      unset($params['properties']['checkin'][1]);
      $payload = json_encode($params, JSON_UNESCAPED_SLASHES+($prettyprint ? JSON_PRETTY_PRINT : 0));
      $content_type = 'json';
    } else {
      $payload = ProcessCheckin::jsonToFormEncoded($params);
      if(isset($params['properties']['photo'])) {
        $multipart = new p3k\Multipart();

        if($prettyprint == false) {
          // Download photos to temp file and add to the request
          $finfo = finfo_open(FILEINFO_MIME_TYPE);
          $prop = count($params['properties']['photo']) > 1 ? 'photo[]' : 'photo';
          foreach($params['properties']['photo'] as $photo) {
            $file_path = tempnam(sys_get_temp_dir(), 'fsq').'.jpg';
            file_put_contents($file_path, file_get_contents($photo));
            $mimetype = finfo_file($finfo, $file_path);
            $multipart->addFile($prop, $file_path, $mimetype);
          }
        }

        unset($payload['photo']);
        $multipart->addArray($payload);
        $payload = $multipart->data();
        $content_type = $multipart->contentType();
      } else {
        $payload = http_build_query($payload);
        $payload = preg_replace('/%5B[0-9]+%5D/', '%5B%5D', $payload); // change [0] to []
        $content_type = 'form';
      }
      if($prettyprint) {
        $payload = str_replace('&', "&\n", $payload);
        $payload = urldecode($payload);
      }
    }

    return [$payload, $content_type];
  }

  private static function getPlaintextValue($val) {
    if(is_string($val))
      return $val;

    if(is_array($val)) {
      if(isset($val['properties']['url']))
        return $val['properties']['url'][0];
      elseif(isset($val['value']))
        return $val['value'];
      else
        return $val;
    }

    return null;
  }

}
