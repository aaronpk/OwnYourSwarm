<!doctype html>
<html lang="en">
  <head>
    <title><?= $this->e($title) ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" type="text/css" href="/semantic-ui/semantic.min.css">
    <link rel="stylesheet" href="/assets/style.css">
  </head>

<body>

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
