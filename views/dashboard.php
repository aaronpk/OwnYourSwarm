<?php $this->layout('layout', ['title' => $title]); ?>

<?php if(import_disabled($user)): ?>
  <div class="ui error message" id="disabled-message">
    <p><b>Your account is disabled</b></p>
    <p>
      Your Micropub endpoint returned an error too many times in a row.
      <!-- Review the <a href="/docs">documentation</a> to see an example of what OwnYourSwarm will send. -->
      If you would like to re-enable sending checkins to your Micropub endpoint, use the "Last Checkin" tool to test your endpoint. If you successfully post a checkin to your enpdoint, importing will be re-enabled.
    </p>
  </div>
<?php else: ?>
  <div class="ui success message">
    <p><b>Your account is active!</b></p>
    <p>
      Your future checkins will be sent to your site as a Micropub request! 
      <!-- Review the <a href="/docs">documentation</a> to see an example of what OwnYourSwarm will send. -->
      Once you check in, you'll see a preview of the Micropub request OwnYourSwarm sends below.
    </p>
  </div>
<?php endif ?>

<br>

<div class="panel">
  <h3>Foursquare</h3>

  <p>Your account is connected to the Foursquare account <b><?= $user->foursquare_url ?: $user->foursquare_user_id ?></b>.</p>

  <a class="ui tiny yellow button" class="disconnect-foursquare" href="/foursquare/disconnect">Disconnect Foursquare</a>
</div>

<br>

<div class="panel">
  <h3>Last Checkin</h3>

  <? if($user->last_checkin_payload): ?>
    <p>Click the button below to send this checkin to your Micropub endpoint again.</p>

    <a class="ui small green button" id="post-checkin" href="">Send Again</a>

    <pre id="micropub-response" class="hidden"></pre>

    <div style="display: flex; flex-direction: row; margin-top: 1em;">
      <div style="flex: 1 0; border: 1px #e5e5e5 solid; margin: 2px; overflow-x: scroll;">
        <h4>Swarm Checkin Object</h4>
        <pre style="font-size: 11px;line-height: 13px;"><?= htmlspecialchars(json_encode(json_decode($user->last_checkin_payload), JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES)) ?></pre>
      </div>
      <div style="flex: 1 0; border: 1px #e5e5e5 solid; margin: 2px; overflow-x: scroll;">
        <h4>h-entry Checkin Object</h4>
        <pre style="font-size: 11px;line-height: 13px;"><?= htmlspecialchars(json_encode($hentry_checkin, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES)) ?></pre>
      </div>
    </div>

  <? else: ?>
    <p>OwnYourSwarm hasn't received any checkins from your account yet. Once you check in to a venue on Swarm, you will be able to see your last checkin here.</p>
  <? endif ?>
</div>

<br>

<script>
$(function(){
  $("#post-checkin").click(function(){
    $("#post-checkin").addClass("loading");
    $.post("/checkin/test.json", function(response){
      $("#post-checkin").removeClass("loading");
      $("#micropub-response").removeClass("hidden").text(response.response);
    });
    return false;
  });
});
</script>
