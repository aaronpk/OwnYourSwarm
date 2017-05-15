<?php
namespace Controllers;

use ProcessCheckin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ORM;
use DateTime, DateTimeZone;
use p3k\Multipart;

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

    if($this->user->last_checkin_payload) {
      $hentry = ProcessCheckin::checkinToHEntry(json_decode($this->user->last_checkin_payload, true), $user);
      list($hentry, $content_type) = ProcessCheckin::buildPOSTPayload($this->user, $hentry, true);
    } else {
      $hentry = false;
    }

    $response->setContent(view('dashboard', [
      'title' => 'OwnYourSwarm',
      'user' => $this->user,
      'hentry_checkin' => $hentry
    ]));
    return $response;
  }

  public function save_user_preferences(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    if($request->get('micropub_style')) {
      $this->user->micropub_style = $request->get('micropub_style');
      $this->user->save();
    }

    $response->headers->set('Content-Type', 'application/json');
    $response->setContent(json_encode(['result'=>'ok']));
    return $response;
  }

  public function get_recent_checkins(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    $info = ProcessCheckin::getFoursquareCheckins($this->user, [
      'limit' => 10,
      'sort' => 'newestfirst'
    ]);

    $checkins = [];
    if(isset($info['response']['checkins']['items']) && count($info['response']['checkins']['items'])) {
      foreach($info['response']['checkins']['items'] as $checkin) {
        $date = DateTime::createFromFormat('U', $checkin['createdAt']);
        $tz = offset_to_timezone($checkin['timeZoneOffset'] * 60);
        $date->setTimeZone($tz);

        $checkins[] = [
          'id' => $checkin['id'],
          'venue' => $checkin['venue']['name'],
          'date' => $date->format('c'),
          'date_short' => $date->format('M j, g:ia')
        ];
      }
    }

    $response->headers->set('Content-Type', 'application/json');
    $response->setContent(json_encode(['checkins'=>$checkins]));
    return $response;
  }

  public function test_post_checkin(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    $hentry = ProcessCheckin::checkinToHEntry(json_decode($this->user->last_checkin_payload, true), $user);

    list($params, $content_type) = ProcessCheckin::buildPOSTPayload($this->user, $hentry);

    $info = micropub_post($this->user, $params, $content_type);

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

  public function preview_checkin(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;

    if(!$request->get('checkin')) {
      if($this->user->last_checkin_payload) {
        $tmp = json_decode($this->user->last_checkin_payload, true);
        $checkin_id = $tmp['id'];
      } else {
        return $response;
      }
    } else {
      $checkin_id = $request->get('checkin');
    }

    $swarm = ProcessCheckin::getFoursquareCheckin($this->user, $checkin_id);

    if(!isset($swarm['response']['checkin'])) {
      $swarm = false;
      $micropub = false;
    } else {
      $swarm = $swarm['response']['checkin'];
      $hentry = ProcessCheckin::checkinToHEntry($swarm, $user);
      list($micropub, $content_type) = ProcessCheckin::buildPOSTPayload($this->user, $hentry, true);
    }

    $response->headers->set('Content-Type', 'application/json');
    $response->setContent(json_encode([
      'swarm' => json_encode($swarm, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES),
      'micropub' => $micropub
    ]));
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
