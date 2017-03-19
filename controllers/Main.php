<?php
namespace Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Main extends Controller {

  public function index(Request $request, Response $response) {
    $response->setContent(view('index', [
      'title' => 'OwnYourSwarm'
    ]));
    return $response;
  }

  public function dashboard(Request $request, Response $response) {
    if(!$this->currentUser($response))
      return $response;



    $response->setContent(view('dashboard', [
      'title' => 'OwnYourSwarm'
    ]));
    return $response;
  }

}
