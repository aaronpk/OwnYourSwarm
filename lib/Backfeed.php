<?php

class Backfeed {

  public static $tiers = [
    #1,5,10,15,30,60
    #30,120,300,1800,86400,86400*2,86400*7,86400*14,86400*30
    30, 120, 600, 7200, 86400, 86400*7,
  ];

  public static function nextTier($tier) {
    foreach(self::$tiers as $t) {
      if($t > $tier)
        return $t;
    }
    return $tier;
  }

  private static function scheduleNext(&$user) {
    $next = self::nextTier($user->backfeed_poll_interval);
    if($next) {
      $user->backfeed_poll_interval = $next;
      $user->date_next_backfeed_poll = date('Y-m-d H:i:s', time()+$next);
    } else {
      $user->backfeed_poll_interval = 0;
      $user->date_next_backfeed_poll = null;
    }
    $user->save();
    return $next;
  }

  public static function likeHash($checkin, $like) {
    // hash of the checkin ID and the user ID
    return md5($checkin->foursquare_checkin_id.':'.$like['id']);
  }

  public static function run($user_id) {
    $user = ORM::for_table('users')->find_one($user_id);
    if(!$user) {
      echo "User not found\n";
      return;
    }

    // Skip running for users who have disabled backfeed entirely
    if(!$user->send_responses_other_users && !$user->send_responses_swarm) {
      return;
    }

    if(import_disabled($user))
      return;

    echo "=============================================\n";
    echo date('Y-m-d H:i:s') . "\n";
    echo "User: " . $user->url . "\n";

    $info = ProcessCheckin::getFoursquareCheckins($user, [], 'Backfeed::run');

    if(!isset($info['response']['checkins'])) {
      echo "No checkins found\n";
    } else {
      foreach($info['response']['checkins']['items'] as $checkin_data) {
        self::processBackfeedForSwarmCheckin($user, $checkin_data);
      }
    }

    self::scheduleNext($user);
  }

  public static function runForCheckin($checkin_id) {
    $checkin = ORM::for_table('checkins')->find_one($checkin_id);
    if(!$checkin) {
      echo "Checkin not found: $checkin_id\n";
      return;
    }

    $user = ORM::for_table('users')->find_one($checkin->user_id);
    if(!$user) {
      echo "User not found\n";
      return;
    }

    echo "=============================================\n";
    echo date('Y-m-d H:i:s') . "\n";
    echo "User: " . $user->url . "\n";
    echo "Checkin: " . $checkin->foursquare_checkin_id . "\n";
    echo $checkin->canonical_url . "\n";

    $info = ProcessCheckin::getFoursquareCheckin($user, $checkin->foursquare_checkin_id, 'Backfeed::runForCheckin');

    if(!isset($info['response']['checkin'])) {
      echo "Foursquare API returned invalid data for checkin: ".$checkin->foursquare_checkin_id."\n";
      print_r($info);
      echo "\n";
      return;
    }

    self::processBackfeedForSwarmCheckin($user, $info['response']['checkin']);
  }

  public static function processBackfeedForSwarmCheckin($user, $checkin_data) {
    if(!$user->send_responses_other_users)
      return;

    $checkin = ORM::for_table('checkins')
      ->where('user_id', $user->id)
      ->where('foursquare_checkin_id', $checkin_data['id'])
      ->find_one();
    if($checkin) {

      $cur_num_likes = $checkin_data['likes']['count'];
      if($cur_num_likes != $checkin->num_likes) {
        self::processLikes($user, $checkin, $checkin_data);
      }
      $checkin->num_likes = $cur_num_likes;

      $cur_num_comments = $checkin_data['comments']['count'];
      if($cur_num_comments != $checkin->num_comments) {
        self::processComments($user, $checkin, $checkin_data);
      }
      $checkin->num_comments = $cur_num_comments;

      $checkin->save();
    } else {
      #echo "Checkin not found in database: ".$checkin_data['id']."\n";
    }
  }

  public static function processLikes(&$user, &$checkin, $data) {
    $groups = $data['likes']['groups'];
    foreach($groups as $group) {
      foreach($group['items'] as $like_data) {
        $hash = self::likeHash($checkin, $like_data);
        $wm = ORM::for_table('webmentions')
          ->where('foursquare_checkin', $checkin->foursquare_checkin_id)
          ->where('hash', $hash)
          ->find_one();
        if(!$wm) {
          $wm = ORM::for_table('webmentions')->create();
          $wm->date_created = $checkin->published;
          $wm->type = 'like';
          $wm->checkin_id = $checkin->id;
          $wm->foursquare_checkin = $checkin->foursquare_checkin_id;
          $wm->hash = $hash;
          $wm->author_photo = $like_data['photo']['prefix'].'300x300'.$like_data['photo']['suffix'];
          $wm->author_url = url_for_user($like_data['id']);
          $wm->author_name = $like_data['firstName'].(isset($like_data['lastName']) ? ' '.$like_data['lastName'] : '');
          $wm->save();

          echo "New like from ".$wm->author_name."\n";

          q()->queue('SendWebmentions', 'send', [$wm->id]);
        }
      }
    }
  }

  public static function processComments(&$user, &$checkin, $data) {
    $info = ProcessCheckin::getFoursquareCheckin($user, $checkin->foursquare_checkin_id, 'processComments');

    if(!isset($info['response']['checkin'])) {
      echo "Checkin not found\n";
      return;
    }

    // Replace the foursquare data with what we got from the API
    // Will be saved after processComments returns
    $checkin->foursquare_data = json_encode($info['response']['checkin'], JSON_UNESCAPED_SLASHES);

    foreach($info['response']['checkin']['comments']['items'] as $comment) {
      $hash = $comment['id'];
      $wm = ORM::for_table('webmentions')
          ->where('foursquare_checkin', $checkin->foursquare_checkin_id)
          ->where('hash', $hash)
          ->find_one();
      if(!$wm) {
        $wm = ORM::for_table('webmentions')->create();
        $wm->date_created = date('Y-m-d H:i:s', $comment['createdAt']);
        $wm->type = 'comment';
        $wm->checkin_id = $checkin->id;
        $wm->foursquare_checkin = $checkin->foursquare_checkin_id;
        $wm->hash = $hash;
        $wm->author_photo = $comment['user']['photo']['prefix'].'300x300'.$comment['user']['photo']['suffix'];
        $wm->author_url = url_for_user($comment['user']['id']);
        $wm->author_name = $comment['user']['firstName'].(isset($comment['user']['lastName']) ? ' '.$comment['user']['lastName'] : '');

        $wm->content = htmlspecialchars($comment['text']);
        if(isset($comment['sticker'])) {
          $sticker_url = $comment['sticker']['image']['prefix']
            . $comment['sticker']['image']['sizes'][count($comment['sticker']['image']['sizes'])-1]
            . $comment['sticker']['image']['name'];
          if($wm->content)
            $wm->content .= "<br>\n";
          $wm->content .= '<img src="'.$sticker_url.'" alt="'.htmlspecialchars($comment['sticker']['name']).'">';
        }

        $wm->save();

        $source_url = Config::$baseURL . '/checkin/' . $wm->foursquare_checkin . '/' . $wm->hash;
        echo "New comment from ".$wm->author_name.": ".$source_url."\n";

        q()->queue('SendWebmentions', 'send', [$wm->id]);
      }
    }
  }

}
