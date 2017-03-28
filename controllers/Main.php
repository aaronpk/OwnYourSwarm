<?php
namespace Controllers;

use ProcessCheckin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ORM;

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

    if($canonical_url) {
      $this->user->micropub_success = 1;
      $this->user->micropub_failures = 0;
      $this->user->save();
    }

    $response->headers->set('Content-Type', 'application/json');
    $response->setContent(json_encode($info));
    return $response;
  }

  public function import_checkin(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    if(!$request->get('checkin'))
      return $response;

    q()->queue('ProcessCheckin', 'run', [$this->user->id, $request->get('checkin'), true]);

    $response->headers->set('Content-Type', 'application/json');
    $response->setContent(json_encode(['result'=>'queued']));
    return $response;
  }

  public function reset_checkin(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    if(!$request->get('checkin'))
      return $response;

    $checkin = ORM::for_table('checkins')
      ->where('user_id', $this->user->id)
      ->where('foursquare_checkin_id', $request->get('checkin'))
      ->find_one();

    if($checkin) {
      ORM::for_table('webmentions')
        ->where('checkin_id', $checkin->id)
        ->delete_many();
      $checkin->delete();
    }

    $response->headers->set('Content-Type', 'application/json');
    $response->setContent(json_encode(['result'=>'deleted']));
    return $response;
  }

}
