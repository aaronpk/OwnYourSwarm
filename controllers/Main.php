<?php
namespace Controllers;

use ProcessCheckin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Main extends Controller {

  public function index(Request $request, Response $response) {
    $response->setContent(view('index', [
      'title' => 'OwnYourSwarm'
    ]));
    return $response;
  }

  public function docs(Request $request, Response $response) {
    $response->setContent(view('docs', [
      'title' => 'OwnYourSwarm Docs'
    ]));
    return $response;
  }

  public function dashboard(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    if($this->user->last_checkin_payload)
      $hentry = ProcessCheckin::checkinToHEntry(json_decode($this->user->last_checkin_payload, true), $user);
    else 
      $hentry = false;

    $response->setContent(view('dashboard', [
      'title' => 'OwnYourSwarm',
      'user' => $this->user,
      'hentry_checkin' => $hentry
    ]));
    return $response;
  }

  public function test_post_checkin(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    $hentry = ProcessCheckin::checkinToHEntry(json_decode($this->user->last_checkin_payload, true), $user);

    $info = micropub_post($this->user, $hentry);

    if($info['code'] == 201 && isset($info['headers']['Location'])) {
      $canonical_url = $info['headers']['Location'][0];
    } else {
      $canonical_url = false;
    }



    $response->headers->set('Content-Type', 'application/json');
    $response->setContent(json_encode($info));
    return $response;
  }

}
