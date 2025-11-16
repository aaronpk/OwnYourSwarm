<?php
namespace Controllers;

use Config;
use IndieAuth;
use ORM;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Auth extends Controller {

  private static function buildClientID() {
    return Config::$baseURL . '/client.json';
  }

  private static function buildRedirectURI() {
    return Config::$baseURL . '/auth/callback';
  }

  public function signin(Request $request, Response $response) {
    $response->setContent(view('auth/signin', [
      'title' => 'OwnYourSwarm'
    ]));
    return $response;
  }

  public function signout(Request $request, Response $response) {
    unset($_SESSION['me']);
    unset($_SESSION['auth']);
    unset($_SESSION['user_id']);
    session_destroy();
    $response->headers->set('Location', '/');
    $response->setStatusCode(302);
    return $response;
  }
  
  public function client_metadata(Request $request, Response $response) {
    $response->headers->set('Content-type', 'application/json');
    $response->setContent(json_encode([
      'client_id' => self::buildClientID(),
      'client_name' => 'OwnYourSwarm',
      'client_uri' => Config::$baseURL,
      'redirect_uris' => [self::buildRedirectURI()],
    ], JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES));
    return $response;
  }

  public function start(Request $request, Response $response) {
    IndieAuth\Client::$clientID = self::buildClientID();
    IndieAuth\Client::$redirectURL = self::buildRedirectURI();
    
    list($authorizationURL, $error) = IndieAuth\Client::begin($_POST['url'], 'create update');

    if($error) {
      $response->setContent(view('auth/error', [
        'title' => 'OwnYourSwarm',
        'error' => $error['error'],
        'description' => $error['error_description']
      ]));
      return $response;      
    }

    $response->headers->set('Location', $authorizationURL);
    $response->setStatusCode(302);
    return $response;
  }

  public function callback(Request $request, Response $response) {
    IndieAuth\Client::$clientID = self::buildClientID();
    IndieAuth\Client::$redirectURL = self::buildRedirectURI();

    list($r, $error) = IndieAuth\Client::complete($_GET);
    
    if($error) {
      $response->setContent(view('auth/error', [
        'title' => 'OwnYourSwarm',
        'error' => $error['error'],
        'description' => $error['error_description']
      ]));
      return $response;      
    }

    $me = $r['me'];

    $micropubEndpoint = IndieAuth\Client::discoverMicropubEndpoint($me);

    if($r['response'] && k($r['response'], array('me','access_token','scope'))) {
      $_SESSION['auth'] = $r['response'];
      $_SESSION['me'] = $me;

      $user = ORM::for_table('users')->where('url', $me)->find_one();
      if($user) {
        // Already logged in, update the last login date
        $user->last_login = date('Y-m-d H:i:s');
      } else {

        if(Config::$newUsersAllowed) {
          // New user! Store the user in the database
          $user = ORM::for_table('users')->create();
          $user->url = $me;
          $user->date_created = date('Y-m-d H:i:s');
        } else {
          $response->setContent(view('auth/error', [
            'title' => 'OwnYourSwarm',
            'error' => 'Registration Disabled',
            'description' => 'We\'re sorry, new user registration is currently not allowed.'
          ]));
          return $response;
        }

      }
      $user->micropub_endpoint = $micropubEndpoint;
      $user->micropub_access_token = $r['response']['access_token'];
      $user->micropub_response = $r['response'];

      $user->save();
      $_SESSION['user_id'] = $user->id();
    }

    // If they have not yet connected a Swarm account, show that prompt now
    if($user->foursquare_user_id || $user->micropub_success) {
      $response->headers->set('Location', '/dashboard');
    } else {
      $response->headers->set('Location', '/foursquare');
    }
    $response->setStatusCode(302);
    return $response;
  }

}
