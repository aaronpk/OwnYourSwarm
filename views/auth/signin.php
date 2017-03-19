<?php $this->layout('layout', ['title' => $title]); ?>

<div class="ui middle aligned center aligned grid">
  <div class="column">

    <h2 class="ui teal header">
      <div class="content">
        Sign in with your domain
      </div>
    </h2>

    <form class="ui large form" action="/auth/start" method="get">
      <div class="field">
        <div class="ui left icon input">
          <i class="globe icon"></i>
          <input type="url" name="me" placeholder="https://you.example.com">
        </div>
      </div>

      <button class="ui fluid large teal submit button">Login</button>
    </form>
  </div>
</div>
