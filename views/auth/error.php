<?php $this->layout('layout', ['title' => $title]); ?>

<div class="ui middle aligned center aligned grid">
  <div class="column">

    <h2 class="ui red header">Error</h2>

    <h3><?= $this->e($error) ?></h3>
    <p><?= $this->e($description) ?></p>

  </div>
</div>
