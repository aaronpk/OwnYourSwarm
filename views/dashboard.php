<?php $this->layout('layout', ['title' => $title]); ?>

<?php if(import_disabled($user)): ?>
  <div class="ui error message" id="disabled-message">
    <p><b>Your account is disabled</b></p>
    <p>
      Your Micropub endpoint returned an error too many times in a row.
      <!-- Review the <a href="/docs">documentation</a> to see an example of what OwnYourSwarm will send. -->
      If you would like to re-enable sending checkins to your Micropub endpoint, use the <a href="/import">import tool</a> to test your endpoint. If you successfully post a checkin to your enpdoint, importing will be re-enabled.
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
  <h3>Micropub Options</h3>

  <p>Choose how you would like OwnYourSwarm to send checkins to your website. If your software doesn't support checkins natively, you can use the "Simple" option to have it send a plaintext version of the checkins, which will appear on your site like regular notes.</p>

  <div class="ui form">
    <div class="grouped fields" id="micropub_style_option">

      <div class="field">
        <div class="ui radio checkbox">
          <input type="radio" name="micropub_style" <?= $user->micropub_style == 'json' ? 'checked="checked"' : '' ?>class="hidden" value="json">
          <label>JSON - This will send a JSON request with the checkin data in an h-card in the "checkin" property.</label>
        </div>
      </div>
      <div class="field">
        <div class="ui radio checkbox">
          <input type="radio" name="micropub_style" <?= $user->micropub_style == 'simple' ? 'checked="checked"' : '' ?> class="hidden" value="simple">
          <label>Simple - This will send a form-encoded request with the checkin information in the post contents, e.g. "Checked in to ______". Photos will be sent as a file upload.</label>
        </div>
      </div>

    </div>
  </div>

</div>

<br>

<div class="panel">
  <h3>Foursquare</h3>

  <p>Your account is connected to the Foursquare account <b><?= $user->foursquare_url ?: $user->foursquare_user_id ?></b>.</p>

  <a class="ui tiny yellow button" class="disconnect-foursquare" href="/foursquare/disconnect">Disconnect Foursquare</a>
</div>

<br>

<script>
$(function(){
  $('.ui.radio.checkbox').checkbox();

  $("#micropub_style_option .radio").click(function(){
    $.post('/user/prefs.json', {
      micropub_style: $("input[name=micropub_style]:checked").val()
    }, function(){
    });
  });

});
</script>