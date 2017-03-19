<?php
namespace Controllers;

use Config;
use IndieAuth;
use ORM;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Auth extends Controller {

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

  public function start(Request $request, Response $response) {
    if(!$request->get('me') || !($me = IndieAuth\Client::normalizeMeURL($request->get('me')))) {
      $response->setContent(view('auth/error', [
        'title' => 'OwnYourSwarm',
        'error' => 'Invalid "me" Parameter',
        'description' => 'The URL you entered, "' . $request->get('me') . '" is not valid.'
      ]));
      return $response;
    }

    $authorizationEndpoint = IndieAuth\Client::discoverAuthorizationEndpoint($me);
    $tokenEndpoint = IndieAuth\Client::discoverTokenEndpoint($me);
    $micropubEndpoint = IndieAuth\Client::discoverMicropubEndpoint($me);

    if($tokenEndpoint && $micropubEndpoint && $authorizationEndpoint) {
      // Generate a "state" parameter for the request
      $state = IndieAuth\Client::generateStateParameter();
      $_SESSION['auth_state'] = $state;
      $_SESSION['auth_me'] = $me;

      $scope = 'create';
      $authorizationURL = IndieAuth\Client::buildAuthorizationURL($authorizationEndpoint, $me, self::buildRedirectURI(), Config::$baseURL, $state, $scope);
    } else {
      $authorizationURL = false;
    }

    // If the user has already signed in before and has a micropub access token, skip
    // the debugging screens and redirect immediately to the auth endpoint.
    // This will still generate a new access token when they finish logging in.
    $user = ORM::for_table('users')->where('url', $me)->find_one();
    if($user && $user->micropub_access_token && !$request->get('restart')) {
      $user->micropub_endpoint = $micropubEndpoint;
      $user->authorization_endpoint = $authorizationEndpoint;
      $user->token_endpoint = $tokenEndpoint;
      $user->save();

      $response->headers->set('Location', $authorizationURL);
      $response->setStatusCode(302);
      return $response;
    } else {
      if(!$user)
        $user = ORM::for_table('users')->create();
      $user->url = $me;
      $user->date_created = date('Y-m-d H:i:s');
      $user->micropub_endpoint = $micropubEndpoint ?: '';
      $user->authorization_endpoint = $authorizationEndpoint ?: '';
      $user->token_endpoint = $tokenEndpoint ?: '';
      $user->save();

      $response->setContent(view('auth/start', [
        'title' => 'Sign In - OwnYourSwarm',
        'me' => $me,
        'meParts' => parse_url($me),
        'authorizing' => $me,
        'tokenEndpoint' => $tokenEndpoint,
        'micropubEndpoint' => $micropubEndpoint,
        'authorizationEndpoint' => $authorizationEndpoint,
        'authorizationURL' => $authorizationURL
      ]));
      return $response;
    }
  }

  public function callback(Request $request, Response $response) {
    // Restart the login if no state is in the session
    if(!array_key_exists('auth_state', $_SESSION) || !array_key_exists('auth_me', $_SESSION)) {
      $response->headers->set('Location', '/auth/start?me='.urlencode($_SESSION['auth_me']));
      $response->setStatusCode(302);
      return $response;
    }

    $me = $_SESSION['auth_me'];

    if(!$request->get('code')) {
      $response->setContent(view('auth/error', [
        'title' => 'OwnYourSwarm',
        'error' => 'Missing authorization code',
        'description' => 'No authorization code was returned from the authorization endpoint.'
      ]));
      return $response;
    }

    if(!$request->get('state')) {
      $response->setContent(view('auth/error', [
        'title' => 'OwnYourSwarm',
        'error' => 'Missing state',
        'description' => 'No state was returned from the authorization endpoint.'
      ]));
      return $response;
    }

    if($request->get('state') != $_SESSION['auth_state']) {
      $response->setContent(view('auth/error', [
        'title' => 'OwnYourSwarm',
        'error' => 'Invalid state',
        'description' => 'The state parameter returned from the authorization endpoint did not match. This is most likely caused by a malicious authorization attempt, or by attempting to sign in in two different browser tabs simultaneously.'
      ]));
      return $response;
    }

    $micropubEndpoint = IndieAuth\Client::discoverMicropubEndpoint($me);
    $tokenEndpoint = IndieAuth\Client::discoverTokenEndpoint($me);

    if($tokenEndpoint) {
      $token = IndieAuth\Client::getAccessToken($tokenEndpoint, $request->get('code'), $me, self::buildRedirectURI(), Config::$baseURL, $request->get('state'), true);

    } else {
      $token = array('auth'=>false, 'response'=>false);
    }

    if($token['auth'] && k($token['auth'], array('me','access_token','scope'))) {
      $_SESSION['auth'] = $token['auth'];
      $_SESSION['me'] = $token['auth']['me'];

      $user = ORM::for_table('users')->where('url', $me)->find_one();
      if($user) {
        // Already logged in, update the last login date
        $user->last_login = date('Y-m-d H:i:s');
      } else {
        // New user! Store the user in the database
        $user = ORM::for_table('users')->create();
        $user->url = $me;
        $user->date_created = date('Y-m-d H:i:s');
      }
      $user->micropub_endpoint = $micropubEndpoint;
      $user->micropub_access_token = $token['auth']['access_token'];
      $user->micropub_response = $token['response'];

      // If polling was disabled, enable it again at the lowest tier
      if($user->tier == 0) {
        $user->tier = 1;
      }

      $user->save();
      $_SESSION['user_id'] = $user->id();
    }

    unset($_SESSION['auth_state']);
    unset($_SESSION['auth_me']);

    // If they have not yet connected a Swarm account, show that prompt now
    if($user->foursquare_username || $user->micropub_success) {
      $response->headers->set('Location', '/dashboard');
    } else {
      $response->headers->set('Location', '/foursquare');
    }
    $response->setStatusCode(302);
    return $response;
  }

}
