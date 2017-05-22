<?php $this->layout('layout', ['title' => $title]); ?>

<div class="panel">
  <h3>Load Checkins</h3>
  <a class="ui small yellow button" id="load-checkins" href="">Load Recent Checkins</a>

  <ul id="recent-checkin-list" class="hidden">
  </ul>
</div>

<br>

<div class="panel">
  <h3>Import Past Checkin</h3>

  <p>Note: This feature is in super beta! There is currently no feedback once you click "Import".</p>
  <p>Enter one of your checkin IDs, and that checkin will be re-processed. If the checkin has already been sent to your site, and if there are any new photos, this will run a Micropub update to add the new photos. If you want to re-send a checkin that has already been sent, you can click the "Reset" button to delete any trace of a specific checkin from OwnYourSwarm.</p>

  <form>
    <div class="ui input">
      <input type="text" id="import_checkin_id" placeholder="checkin ID">
    </div>
    <a class="ui small yellow button" id="preview-checkin" href="">Preview</a>
    <a class="ui small green button" id="import-checkin" href="">Import</a>
    <a class="ui small red button" id="reset-checkin" href="">Reset</a>
  </form>
</div>

<br>

<div class="panel">
  <h3>Preview Checkin Payload</h3>

  <div id="last-checkin-preview" class="<?= $user->last_checkin_payload ? '' : 'hidden' ?>">
    <div id="send-checkin-again">
      <p>Click the button below to send this checkin to your Micropub endpoint again.</p>
      <a class="ui small green button" id="post-checkin" href="">Send Again</a>
    </div>

    <pre id="micropub-response" class="hidden"></pre>

    <div style="display: flex; flex-direction: row; margin-top: 1em;">
      <div style="flex: 1 0; border: 1px #e5e5e5 solid; margin: 2px; padding: 2px; overflow-x: scroll;">
        <h4>Swarm Checkin Object</h4>
        <pre id="preview-swarm-json" style="font-size: 11px;line-height: 13px;"><?= htmlspecialchars(json_encode(json_decode($user->last_checkin_payload), JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES)) ?></pre>
      </div>
      <div style="flex: 1 0; border: 1px #e5e5e5 solid; margin: 2px; padding: 2px; overflow-x: scroll;">
        <h4>h-entry Checkin Object</h4>
        <?php if($user->micropub_style == 'simple'): ?>
          <p>Note: This is a preview of the post request. For multipart requests, the photo is not shown below. Linebreaks in form-encoded requests are for display purposes only. Form-encoded requests are displayed in a simplified form to be more readable.</p>
        <?php endif; ?>
        <pre id="preview-micropub-payload" style="font-size: 11px;line-height: 13px;"><?= htmlspecialchars($hentry_checkin) ?></pre>
      </div>
    </div>
  </div>
  <div id="last-checkin-none" class="<?= $user->last_checkin_payload ? 'hidden' : '' ?>">
    <p>OwnYourSwarm hasn't received any checkins from your account yet. Once you check in to a venue on Swarm, you will be able to see your last checkin here.</p>
    <p>You can also get a checkin ID from <a href="<?= $user->foursquare_url ?>/history">your history</a> to test with.</p>
  </div>
</div>

<br>

<script>
$(function(){
  $('.ui.radio.checkbox').checkbox();

  $("#load-checkins").click(function(){
    $("#load-checkins").addClass("loading");
    $.get("/checkin/recent.json", function(response){
      $("#load-checkins").removeClass("loading");
      if(response.checkins.length) {
        $("#recent-checkin-list").text("");
        for(var i in response.checkins) {
          $("#recent-checkin-list").append(
            "<li>" +
              "<a href=\"\" data-checkin=\""+response.checkins[i].id+"\">"+response.checkins[i].venue+"</a>" +
              " " + response.checkins[i].date_short +
            "</li>"
          );
        }
        $("#recent-checkin-list a").unbind("click").bind("click", function(){
          $("#import_checkin_id").val($(this).data("checkin"));
          $("#preview-checkin").click();
          document.getElementById("preview-checkin").scrollIntoView();
          return false;
        });
        $("#recent-checkin-list").removeClass("hidden");
      } else {
        $("#recent-checkin-list").addClass("hidden");
      }
    });
    return false;
  });

  $("#post-checkin").click(function(){
    $("#post-checkin").addClass("loading");
    $.post("/checkin/test.json", function(response){
      $("#post-checkin").removeClass("loading");
      $("#micropub-response").removeClass("hidden").text(response.response);
    });
    return false;
  });

  $("#preview-checkin").click(function(){
    $("#preview-checkin").addClass("loading");
    $.post("/checkin/preview.json", {
      checkin: $("#import_checkin_id").val()
    }, function(response){
      $("#last-checkin-none").addClass("hidden");
      $("#last-checkin-preview").removeClass("hidden");

      $("#send-checkin-again").addClass("hidden");
      $("#preview-checkin").removeClass("loading");
      $("#preview-swarm-json").text(response.swarm);
      $("#preview-micropub-payload").text(response.micropub);

      document.getElementById("last-checkin-preview").scrollIntoView();
    });
    return false;
  });

  $("#import-checkin").click(function(){
    $("#import-checkin").addClass("loading");
    $.post("/checkin/import.json", {
      checkin: $("#import_checkin_id").val()
    }, function(response){
      $("#import-checkin").removeClass("loading");
    });
    return false;
  });

  $("#reset-checkin").click(function(){
    $("#reset-checkin").addClass("loading");
    $.post("/checkin/reset.json", {
      checkin: $("#import_checkin_id").val()
    }, function(response){
      $("#reset-checkin").removeClass("loading");
    });
    return false;
  });
});
</script>
