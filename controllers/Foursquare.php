<?php
namespace Controllers;

use Config, ORM;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

}
