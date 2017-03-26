<?php $this->layout('layout', ['title' => $title]); ?>

<div id="authorization_endpoint" class="ui container">
  <h3>Authorization Endpoint</h3>

  <p><i>The authorization endpoint tells this app where to direct your browser to sign you in.</i></p>

  <?php if($authorizationEndpoint): ?>
    <div class="ui positive message">Found your authorization endpoint: <code><?= $authorizationEndpoint ?></code></div>
  <?php else: ?>
    <div class="ui negative message">Could not find your authorization endpoint!</div>
    <p>You need to set your authorization endpoint in a <code>&lt;link&gt;</code> tag on your home page or in an HTTP header.</p>
    <p>You can create your own authorization endpoint, but it's easier to use an existing service such as <a href="https://indieauth.com/">IndieAuth.com</a>. To delegate to IndieAuth.com, you can use the code provided below.</p>
    <pre><code>&lt;link rel="authorization_endpoint" href="https://indieauth.com/auth"&gt;
Link: &lt;https://indieauth.com/auth&gt;; rel="authorization_endpoint"</code></pre>
  <?php endif; ?>
</div>

<div class="ui divider"></div>

<div id="token_endpoint" class="ui container">
  <h3>Token Endpoint</h3>

  <p><i>The token endpoint is where this app will make a request to get an access token after obtaining authorization.</i></p>

  <?php if($tokenEndpoint): ?>
    <div class="ui positive message">Found your token endpoint: <code><?= $tokenEndpoint ?></code></div>
  <?php else: ?>
    <div class="ui negative message">Could not find your token endpoint!</div>
    <p>You need to set your token endpoint in a <code>&lt;link&gt;</code> tag on your home page or in an HTTP header.</p>
    <p>You will need to <a href="/creating-a-token-endpoint">create a token endpoint</a> for your website which can issue access tokens when given an authorization code.</p>
    <pre><code>&lt;link rel="token_endpoint" href="https://<?= $meParts['host'] ?>/token"&gt;
Link: &lt;https://<?= $meParts['host'] ?>/token&gt;; rel="token_endpoint"</code></pre>
  <?php endif; ?>

</div>

<div class="ui divider"></div>

<div id="micropub_endpoint" class="ui container">
  <h3>Micropub Endpoint</h3>

  <p><i>The Micropub endpoint is the URL this app will use to post new photos.</i></p>

  <?php if($micropubEndpoint): ?>
    <div class="ui positive message">Found your Micropub endpoint: <code><?= $micropubEndpoint ?></code></div>
  <?php else: ?>
    <div class="ui negative message">Could not find your Micropub endpoint!</div>
    <p>You need to set your Micropub endpoint in a <code>&lt;link&gt;</code> tag on your home page or in an HTTP header.</p>
    <p>You will need to <a href="/creating-a-micropub-endpoint">create a Micropub endpoint</a> for your website which can create posts on your site.</p>
    <pre><code>&lt;link rel="micropub" href="https://<?= $meParts['host'] ?>/micropub"&gt;
Link: &lt;https://<?= $meParts['host'] ?>/micropub&gt;; rel="micropub"</code></pre>
  <?php endif; ?>

</div>

<div class="ui divider"></div>

<?php if(isset($authorizationURL) && $authorizationURL): ?>

  <h3>Ready!</h3>

  <p>Clicking the button below will take you to <strong>your</strong> authorization server which is where you will allow this app to be able to post to your site.</p>

  <a href="<?= $authorizationURL ?>" class="ui primary button">Authorize</a>

<?php endif; ?>
