<?php
namespace Controllers;

use Config, ORM;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use DateTime, DateTimeZone;

class Foursquare extends Controller {

  public function index(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    $response->setContent(view('foursquare', [
      'title' => 'Connect Foursquare'
    ]));
    return $response;
  }

  public function auth(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    $params = [
      'client_id' => Config::$foursquareClientID,
      'response_type' => 'code',
      'redirect_uri' => Config::$baseURL . '/foursquare/callback'
    ];

    $response->headers->set('Location', 'https://foursquare.com/oauth2/authenticate?'.http_build_query($params));
    $response->setStatusCode(302);
    return $response;
  }

  public function disconnect(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    $this->user->foursquare_url = '';
    $this->user->foursquare_user_id = '';
    $this->user->foursquare_access_token = '';
    $this->user->save();

    $response->headers->set('Location', '/foursquare');
    $response->setStatusCode(302);
    return $response;
  }

  public function callback(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    if(!$request->get('code')) {
      $response->setContent(view('auth/error', [
        'title' => 'OwnYourSwarm',
        'error' => 'Missing authorization code',
        'description' => 'No authorization code was returned from Foursquare.'
      ]));
      return $response;
    }

    // Get a Foursquare access token
    $ch = curl_init('https://foursquare.com/oauth2/access_token');
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
      'client_id' => Config::$foursquareClientID,
      'client_secret' => Config::$foursquareClientSecret,
      'grant_type' => 'authorization_code',
      'redirect_uri' => Config::$baseURL . '/foursquare/callback',
      'code' => $request->get('code')
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    $token = json_decode($data, true);
    if($token && array_key_exists('access_token', $token)) {

      $this->user->foursquare_access_token = $token['access_token'];

      // Retrieve user information
      $ch = curl_init('https://api.foursquare.com/v2/users/self?v=20170319&oauth_token='.$token['access_token']);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $data = curl_exec($ch);
      $info = json_decode($data, true);
      if($info && array_key_exists('response', $info) && array_key_exists('user', $info['response'])) {
        $this->user->foursquare_user_id = $info['response']['user']['id'];
        $this->user->foursquare_url = $info['response']['user']['canonicalUrl'];

        // Remove this foursquare user from other accounts
        ORM::for_table('users')->raw_execute('
          UPDATE users set foursquare_user_id="", foursquare_url=""
          WHERE foursquare_user_id=:u', ['u'=>$info['response']['user']['id']]);
      }

      $this->user->save();

      $response->headers->set('Location', '/dashboard');
      $response->setStatusCode(302);
    } else {
      $response->setContent(view('auth/error', [
        'title' => 'OwnYourSwarm',
        'error' => 'Error authorizing Foursquare',
        'description' => 'No access token was returned from Foursquare.'
      ]));
    }

    return $response;
  }

  public function push(Request $request, Response $response) {
    $payload = $request->get('checkin');

    $data = json_decode($payload, true);
    $user_id = $data['user']['id'];

    $user = ORM::for_table('users')->where('foursquare_user_id', $user_id)->find_one();
    if($user) {
      $user->last_checkin_payload = $payload;
      $user->save();

      $checkin = ORM::for_table('checkins')
        ->where('user_id', $user->id)
        ->where('foursquare_checkin_id', $data['id'])
        ->find_one();
      if(!$checkin) {
        $checkin = ORM::for_table('checkins')->create();
        $checkin->user_id = $user->id;
        $checkin->foursquare_checkin_id = $data['id'];
        $checkin->published = date('Y-m-d H:i:s', $data['createdAt']);
        $checkin->tzoffset = $data['timeZoneOffset'] * 60;
        $checkin->success = 0;
      }
      $checkin->foursquare_data = $payload;

      if(!import_disabled($user)) {
        $checkin->pending = 1;
        $checkin->date_next_poll = date('Y-m-d H:i:s');
        $checkin->poll_interval = 1;
      }

      $checkin->save();

      // Don't try to process the checkin if they've had more than N micropub requests fail
      if(!import_disabled($user)) {
        q()->queue('ProcessCheckin', 'run', [$checkin->user_id, $checkin->foursquare_checkin_id], [
          'delay' => 15
        ]);
      }
    }

    return $response;
  }

  public function comment(Request $request, Response $response, $args) {
    $webmention = ORM::for_table('webmentions')
      ->where('foursquare_checkin', $args['checkin'])
      ->where('hash', $args['hash'])
      ->find_one();

    if(!$webmention) {
      $response->setStatusCode(404);
      return $response;
    }

    $checkin = ORM::for_table('checkins')->find_one($webmention->checkin_id);

    $date = new DateTime($webmention->date_created);
    $date->setTimeZone(offset_to_timezone($checkin->tzoffset));

    $response->setContent(view('foursquare/comment', [
      'title' => 'Swarm Checkin',
      'checkin' => $checkin,
      'comment' => $webmention,
      'published' => $date
    ]));
    return $response;
  }

}
