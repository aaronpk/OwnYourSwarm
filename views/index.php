<?php $this->layout('layout', ['title' => $title]); ?>

<?php if(!Config::$newUsersAllowed): ?>
  <div class="ui message error">
    We have hit Swarm's API rate limits and can no longer support new users. If you already have an account you can continue using the service, but new users will not be able to sign up here. This project is <a href="https://github.com/aaronpk/OwnYourSwarm">open source</a>, and you can run this on your own server if you'd like.
  </div>
<?php endif; ?>

<h2>OwnYourSwarm</h2>

<p>Post your Swarm checkins to your website via Micropub.</p>

