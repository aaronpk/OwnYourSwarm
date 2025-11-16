<!doctype html>
<html lang="en">
  <head>
    <title><?= $this->e($title) ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="pingback" href="https://webmention.io/pingback/aaronpk" />
    <link rel="webmention" href="https://webmention.io/webmention/aaronpk" />

    <link rel="stylesheet" type="text/css" href="/semantic-ui/semantic.min.css">
    <link rel="stylesheet" href="/assets/style.css">

    <script src="/assets/jquery-3.2.0.min.js"></script>
    <script src="/semantic-ui/semantic.min.js"></script>
  </head>

<body>

<div class="ui fixed inverted menu">
  <div class="ui container">
    <a href="/" class="header item">
      <!-- <img class="logo" src="/images/ownyourswarm.png"> -->
      OwnYourSwarm
    </a>
    <?php if(session('me')) { ?>
      <a href="/dashboard" class="item">Dashboard</a>
      <a href="/import" class="item">Import</a>
    <?php } ?>
    <a href="/docs" class="item">Docs</a>
    <?php if(session('me')) { ?>
      <div class="right menu">
        <div class="item">
          <a><?= session('me') ?></a>
        </div>
        <a href="/auth/signout" class="item">Sign Out</a>
      </div>
    <?php } else if(isset($authorizing)) { ?>
      <div class="right menu">
        <div class="item"><?= $authorizing ?></div>
      </div>
    <?php } else { ?>
      <div class="right menu">
        <div class="item">
          <a href="/auth/signin" class="ui primary button">Sign In</a>
        </div>
      </div>
    <?php } ?>
  </div>
</div>

  <div class="ui main container">
    <?= $this->section('content') ?>
  </div>

  <div class="ui inverted vertical footer segment">
    <div class="ui container">
      <p class="credits">&copy; <?=date('Y')?> by <a href="https://aaronparecki.com">Aaron Parecki</a>.
        This code is <a href="https://github.com/aaronpk/OwnYourSwarm">open source</a>.
        Feel free to send a pull request, or <a href="https://github.com/aaronpk/OwnYourSwarm/issues">file an issue</a>.</p>
    </div>
  </div>

</body>
</html>
