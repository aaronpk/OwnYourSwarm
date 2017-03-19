<?php
namespace Controllers;

use ORM;

class Controller {

  protected $user;

  protected function currentUser(&$response) {
    if(!array_key_exists('user_id', $_SESSION)) {
      $response->headers->set('Location', '/');
      $response->setStatusCode(302);
      return false;
    } else {
      $this->user = ORM::for_table('users')->find_one($_SESSION['user_id']);
      return $this->user;
    }
  }

}
