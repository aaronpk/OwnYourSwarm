<?php
namespace Controllers;

use Config, ORM, Log;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use DateTime, DateTimeZone;

class FoursquarePermalink extends Controller {

  private static function hashForResponse($checkinData, $type, $response) {
    if($type == 'coin')
      return md5(json_encode($response));
    if(!isset($response['id']))
      return null;
    if($type == 'like')
      return md5($checkinData['id'].':'.$response['id']);
    if($type == 'comment')
      return $response['id'];
    return null;
  }

  private static function urlForResponseHash($userID, $checkinID, $type, $hash) {
    return Config::$baseURL . '/user/'. $userID . '/checkin/' . $checkinID . '/' . $type . '/' . $hash;
  }

  private static function getCheckinData($user, $userID, $checkinID) {
    $cacheKey = 'swarm/checkin/'.$userID.'/'.$checkinID;
    $checkinData = redis()->get($cacheKey);
    if(!$checkinData) {
      $ch = curl_init('https://api.foursquare.com/v2/checkins/'.$checkinID.'?v=20170319&oauth_token='.$user->foursquare_access_token);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $data = curl_exec($ch);

      Log::fsq($user->id, 'checkins/'.$checkinID, 'web', ['http'=>curl_getinfo($ch, CURLINFO_RESPONSE_CODE)]);

      $info = json_decode($data, true);
      if($info && !empty($info['response']['checkin'])) {
        $checkinData = $info['response']['checkin'];
        redis()->setex($cacheKey, 7200, json_encode($checkinData));
      } else {
        return false;
      }
    } else {
      $checkinData = json_decode($checkinData, true);
    }
    return $checkinData;
  }

  public function checkin(Request $request, Response $response, $args) {
    $user = ORM::for_table('users')
      ->where('foursquare_user_id', $args['userid'])
      ->find_one();

    if(!$user) {
      $response->setStatusCode(404);
      return $response;
    }

    $checkinData = self::getCheckinData($user, $args['userid'], $args['checkin']);

    if(!$checkinData) {
      $response->setStatusCode(404);
      $response->headers->set('Content-Type', 'application/json');
      $response->setContent($data);
      return $response;
    }

    $hEntry = \ProcessCheckin::checkinToHEntry($checkinData, $user);

    // Add coins, likes and comments
    if($checkinData['likes']['count'] > 0) {
      foreach($checkinData['likes']['groups'] as $group) {
        $hEntry['properties']['like'] = [];
        foreach($group['items'] as $like) {
          $hash = self::hashForResponse($checkinData, 'like', $like);
          $hEntry['properties']['like'][] = self::urlForResponseHash($user->foursquare_user_id, 
            $checkinData['id'], 'like', $hash);
        }
      }
    }
    if($checkinData['comments']['count'] > 0) {
      $hEntry['properties']['comment'] = [];
      foreach($checkinData['comments']['items'] as $comment) {
        $hash = self::hashForResponse($checkinData, 'comment', $comment);
        $hEntry['properties']['comment'][] = self::urlForResponseHash($user->foursquare_user_id, 
          $checkinData['id'], 'comment', $hash);
      }
    }
    if(isset($checkinData['score']['scores']) && count($checkinData['score']['scores']) > 0) {
      if(!isset($hEntry['properties']['comment']))
        $hEntry['properties']['comment'] = [];
      foreach($checkinData['score']['scores'] as $score) {
        $hash = self::hashForResponse($checkinData, 'coin', $score);
        $hEntry['properties']['comment'][] = self::urlForResponseHash($user->foursquare_user_id, 
          $checkinData['id'], 'coin', $hash);
      }
    }

    $response->headers->set('Content-Type', 'application/json');
    $response->setContent(json_encode($hEntry));

    return $response;
  }

  public function response(Request $request, Response $response, $args) {
    $user = ORM::for_table('users')
      ->where('foursquare_user_id', $args['userid'])
      ->find_one();

    if(!$user) {
      $response->setStatusCode(404);
      return $response;
    }

    $checkinData = self::getCheckinData($user, $args['userid'], $args['checkin']);

    if(!$checkinData) {
      $response->setStatusCode(404);
      return $response;
    }

    $checkin = new \StdClass;
    $checkin->canonical_url = Config::$baseURL . '/user/' . $args['userid'] . '/checkin/' . $args['checkin'];

    $date = DateTime::createFromFormat('U', $checkinData['createdAt']);
    $tz = offset_to_timezone($checkinData['timeZoneOffset']*60);
    $date->setTimeZone($tz);

    // Find the comment data given the hash
    switch($args['type']) {
      case 'coin':
        $responses = $checkinData['score']['scores']; 
        break;
      case 'like':
        $responses = [];
        foreach($checkinData['likes']['groups'] as $group) {
          $responses = array_merge($responses, $group['items']);
        }
        break;
      case 'comment':
        $responses = $checkinData['comments']['items']; 
        break;
      default:
        $responses = [];        
    }

    $webmention = false;
    foreach($responses as $r) {
      $hash = self::hashForResponse($checkinData, $args['type'], $r);
      if($hash == $args['hash']) {
        $webmention = new \StdClass;
        $webmention->type = $args['type'];
        switch($args['type']) {
          case 'comment':
            $date = DateTime::createFromFormat('U', $r['createdAt']);
            $date->setTimeZone($tz);
            $webmention->author_photo = $r['user']['photo']['prefix'].'300x300'.$r['user']['photo']['suffix'];
            $webmention->author_name = $r['user']['firstName'].(isset($r['user']['lastName']) ? ' '.$r['user']['lastName'] : '');
            $webmention->author_url = url_for_user($r['user']['id']);
            $webmention->content = htmlspecialchars($r['text']);

            if(isset($r['sticker'])) {
              $sticker_url = $r['sticker']['image']['prefix']
                . $r['sticker']['image']['sizes'][count($r['sticker']['image']['sizes'])-1]
                . $r['sticker']['image']['name'];
              if($webmention->content)
                $webmention->content .= "<br>\n";
              $webmention->content .= '<img src="'.$sticker_url.'" alt="'.htmlspecialchars($r['sticker']['name']).'">';
            }
            break;
          case 'coin': 
            $webmention->author_photo = $r['icon'];
            $webmention->author_name = null;
            $webmention->author_url = null;
            $webmention->content = htmlspecialchars($r['message']);
            $webmention->coins = $r['points'];
            break;
          case 'like':
            $webmention->author_photo = $r['photo']['prefix'].'300x300'.$r['photo']['suffix'];
            $webmention->author_name = $r['firstName'].(isset($r['lastName']) ? ' '.$r['lastName'] : '');
            $webmention->author_url = url_for_user($r['id']);
            break;
        }
      }
    }

    if(!$webmention) {
      $response->setStatusCode(404);
      return $response;
    }


    $response->setContent(view('foursquare/comment', [
      'title' => 'Swarm Checkin',
      'checkin' => $checkin,
      'comment' => $webmention,
      'published' => $date,
      'simple' => $user->micropub_style == 'simple',
    ]));
    return $response;
  }

}
