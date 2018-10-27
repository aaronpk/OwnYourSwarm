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
  <h3>Import Options</h3>

  <h4>Micropub</h4>

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

  <h4>Comments and Responses</h4>

  <div class="ui form">
    <div class="grouped fields" id="send_responses_option">

      <div class="field">
        <div class="ui toggle checkbox">
          <input type="checkbox" <?= $user->send_responses_other_users == '1' ? 'checked="checked"' : '' ?>class="hidden" name="send_responses_other_users">
          <label>Send Webmentions for comments and likes from other Swarm users</label>
        </div>
      </div>
      <div class="field">
        <div class="ui toggle checkbox">
          <input type="checkbox" <?= $user->send_responses_swarm == '1' ? 'checked="checked"' : '' ?>class="hidden" name="send_responses_swarm">
          <label>Send Webmentions for system comments from Swarm</label>
        </div>
      </div>

    </div>
  </div>

</div>

<br>

<div class="panel hidden" id="automatic-syndication">
  <h3>Automatic Syndication</h3>

  <p>You can add rules that will match words in your Swarm post and tell your Micropub endpoint to syndicate your post to various destinations. You can use this to make rules like "syndicate my checkins where I mention 'indieweb' to Twitter".</p>

  <table class="ui table">
    <tr>
      <td>Type</td>
      <td>Keyword</td>
      <td>Syndication Target</td>
      <td width="110"></td>
    </tr>
    <?php foreach($rules as $rule): ?>
      <tr class="rule">
        <td>
          <?= $rule->type == 'keyword' ? 'Keyword Match' : '' ?>
          <?= $rule->type == 'photo' ? 'Contains a Photo' : '' ?>
          <?= $rule->type == 'shout' ? 'Contains a "Shout"' : '' ?>
        </td>
        <td><?= htmlspecialchars($rule->match); ?></td>
        <td><?= htmlspecialchars($rule->syndicate_to_name); ?></td>
        <td align="left"><a href="#" class="hidden delete" data-id="<?= $rule->id ?>">&times;</a></td>
      </tr>
    <?php endforeach; ?>
    <tr>
      <td>
        <select name="new-syndicate-to-type" id="new-syndicate-to-type">
          <option value="keyword">Keyword Match</option>
          <option value="photo">Contains a Photo</option>
          <option value="shout">Contains a "Shout"</option>
        </select>
      </td>
      <td>
        <div class="ui input">
          <input type="text" id="new-syndicate-to-keyword" placeholder="keyword, or blank for all">
        </div>
      </td>
      <td>
        <div class="">
          <select name="new-syndicate-to-destination" id="new-syndicate-to-destination">
            <option value="">-- update your syndication endpoints below --</option>
          </select>
        </div>
      </td>
      <td><input type="button" id="new-syndicate-to-btn" class="ui primary button" value="Add Rule"></td>
    </tr>
  </table>

  <p style="font-size:0.9em; color: #999;">Note that OwnYourSwarm won't actually post anything to Twitter or Facebook for you. All this does is set the appropriate parameter in the Micropub request to indicate to your Micropub endpoint that the post should be syndicated. If you don't yet have this set up, you might want to try <a href="https://silo.pub">silo.pub</a> or <a href="https://www.brid.gy/publish">Bridgy Publish</a> for an easy way to post to Twitter, Facebook and others.</p>
</div>

<br>

<div class="panel">
  <h3>Syndication Endpoints</h3>

  <a href="#" id="reload-syndication-endpoints" class="ui tiny button">Reload</a>

  <div id="syndication-endpoints">
    <div class="ui error message hidden" style="margin-top: 1em;">
      <b>No Syndication Targets</b>
      <p>OwnYourSwarn didn't find any syndication targets at your Micropub endpoint. Learn more about <a href="https://www.w3.org/TR/micropub/#syndication-targets">Micropub syndication</a>.</p>
      <p class="error hidden">Error: <span class="details"></span></p>
    </div>
    <ul class="list" style="margin-top: 1em;"></ul>
  </div>
</div>

<br>

<div class="panel">
  <h3>Foursquare</h3>

  <p>Your OwnYourSwarm account is connected to the Foursquare account <b><?= $user->foursquare_url ?: $user->foursquare_user_id ?></b>.</p>

  <p><a class="ui tiny yellow button" class="disconnect-foursquare" href="/foursquare/disconnect">Disconnect Foursquare</a></p>

  <?php if($other_accounts > 0): ?>
    <p>This Foursquare account is connected to <?= $other_accounts ?> other OwnYourSwarm accounts.</p>
    <a class="ui tiny orange button" class="disconnect-foursquare" href="/foursquare/disconnect-other">Disconnect Other Accounts</a>
  <?php endif; ?>
</div>

<br>

<script>
$(function(){
  $('.ui.checkbox').checkbox();

  $.get("/user/syndication-targets.json", function(data){
    handle_discovered_syndication_targets(data);
  });

  $("#micropub_style_option .radio").click(function(){
    $.post('/user/prefs.json', {
      micropub_style: $("input[name=micropub_style]:checked").val()
    }, function(){
    });
  });

  $("#send_responses_option .checkbox").click(function(e){
    var name = $($(this).children('input')[0]).attr('name');
    var val = $("#send_responses_option").form('get value', name);
    var params = {};
    params[name] = val == "on" ? 1 : 0;

    $.post('/user/prefs.json', params, function(){
    });
  });

  $("#reload-syndication-endpoints").click(reload_syndication_endpoints);

  $("#new-syndicate-to-type").change(function(){
    if($(this).val() == "keyword") {
      $("#new-syndicate-to-keyword").removeAttr("disabled");
    } else {
      $("#new-syndicate-to-keyword").attr("disabled","disabled");
    }
  });

  $("#new-syndicate-to-btn").click(function(){
    $.post("/user/syndication-rules.json", {
      action: 'create',
      type: $("#new-syndicate-to-type").val(),
      keyword: $("#new-syndicate-to-keyword").val(),
      target: $("#new-syndicate-to-destination").val(),
      target_name: $("#new-syndicate-to-destination :selected").text()
    }, function(response){
      window.location.reload();
    });
    return false;
  });

  $("#automatic-syndication .rule").on("mouseover", function(){
    $(this).find(".delete").removeClass("hidden");
  });
  $("#automatic-syndication .rule").on("mouseout", function(){
    $(this).find(".delete").addClass("hidden");
  });
  $("#automatic-syndication .delete").click(function(){
    $.post("/user/syndication-rules.json", {
      action: 'delete',
      id: $(this).data('id')
    }, function(data){
      window.location.reload();
    });
    return false;
  });

});

function handle_discovered_syndication_targets(data) {
  if(data.targets) {

    $("#syndication-endpoints .list").html('');
    $("#new-syndicate-to-destination").html('');
    $("#new-syndicate-to-destination").append('<option value="">-- select an endpoint --</option>');
    for(var i in data.targets) {
      $("#syndication-endpoints .list").append('<li>'+data.targets[i].name+'</li>');
      $("#new-syndicate-to-destination").append('<option value="'+data.targets[i].uid+'">'+data.targets[i].name+'</option>');
    }

    $("#syndication-endpoints .alert-warning").addClass("hidden");
    $("#automatic-syndication").removeClass("hidden");
  } else {
    if(data.error) {
      $("#syndication-endpoints .details").text(data.error);
      $("#syndication-endpoints .error").removeClass("hidden");
    }
    $("#syndication-endpoints .alert-warning").removeClass("hidden");
    $("#automatic-syndication").addClass("hidden");
  }
}

function reload_syndication_endpoints() {
  $("#reload-syndication-endpoints").addClass("loading");
  $("#syndication-endpoints .list").html('');
  $.post("/user/syndication-targets.json", function(data){
    $("#reload-syndication-endpoints").removeClass("loading");
    handle_discovered_syndication_targets(data);
  });
  return false;
}

</script>
