<?php $this->layout('layout', ['title' => $title]); ?>

<div class="ui middle aligned center aligned grid">
  <div class="column">

    <h2 class="ui teal header">
      <div class="content">
        Sign in with your domain
      </div>
    </h2>

    <form class="ui large form" action="/auth/start" method="post">
      <div class="field">
        <div class="ui left icon input">
          <i class="globe icon"></i>
          <input type="url" name="url" placeholder="https://you.example.com">
        </div>
      </div>

      <button class="ui fluid large teal submit button">Login</button>
    </form>
  </div>
</div>
<script>
// add https:// to URL fields
// https://aaronparecki.com/2018/06/03/3/
document.addEventListener('DOMContentLoaded', function() {
  function addDefaultScheme(target) {
    if(target.value.match(/^(?!https?:).+\..+/)) {
      target.value = "https://"+target.value;
    }
  }
  var elements = document.querySelectorAll("input[type=url]");
  Array.prototype.forEach.call(elements, function(el, i){
    el.addEventListener("blur", function(e){
      addDefaultScheme(e.target);
    });
    el.addEventListener("keydown", function(e){
      if(e.keyCode == 13) {
        addDefaultScheme(e.target);
      }
    });
  });
});
</script>