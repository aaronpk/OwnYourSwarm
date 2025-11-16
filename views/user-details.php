<?php $this->layout('layout', ['title' => $title]); ?>

<div class="panel">
  <h3>User Details</h3>

  <div class="ui form">

    <div class="field">
      <label for="foursquare-access-token">Foursquare Access Token</label>
      <input id="foursquare-access-token" type="text" readonly value="<?= htmlspecialchars($user->foursquare_access_token) ?>">
    </div>

    <div class="field">
      <label for="micropub-endpoint">Micropub Endpoint</label>
      <input id="micropub-endpoint" type="text" readonly value="<?= htmlspecialchars($user->micropub_endpoint) ?>">
    </div>
    
    <div class="field">
      <label for="micropub-access-token">Micropub Access Token</label>
      <input id="micropub-access-token" type="text" readonly value="<?= htmlspecialchars($user->micropub_access_token) ?>">
    </div>

    <div class="field">
      <label for="config">Import Script Config</label>
      <textarea id="config" rows="6" style="font-family: monospace"><?= json_encode($credentials, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES) ?></textarea>
    </div>

  </div>
</div>
