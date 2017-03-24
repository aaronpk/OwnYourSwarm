<?php $this->layout('layout-public', ['title' => $title]); ?>

<div class="checkin-coin h-entry">

  <div class="context">
    <i class="ui reply icon"></i>
    <a href="<?= $checkin->canonical_url ?>" class="u-in-reply-to">
      <?= $checkin->canonical_url ?>
    </a>
  </div>

  <div class="coin">
    <span class="icon u-author h-card">
      <img src="<?= $coin->icon ?>" class="u-photo" alt="Swarm" width="30">
      <a href="https://swarmapp.com/" class="u-url"></a>
    </span>
    <span class="content p-content p-name"><?= $this->e($coin->content) ?></span>
    <span class="score">+<span class="p-swarm-coins"><?= $coin->coins ?></span></span>
  </div>

  <div class="meta">
    <a href="" class="u-url">
      <time class="dt-published" datetime="<?= date('c', strtotime($coin->date_created)) ?>">
        <?= date('Y-m-d H:i:s', strtotime($coin->date_created)) ?>
      </time>
    </a>
  </div>
</div>
<style type="text/css">

.checkin-coin {
  border: 1px #aaa solid;
  border-radius: 6px;
  max-width: 600px;
  margin: 0 auto;
  overflow: hidden;
}

.checkin-coin .context,
.checkin-coin .coin,
.checkin-coin .meta {
  padding: 5px 10px;  
}

.checkin-coin .context {
  background: #ddd;
}

.checkin-coin .coin {
  padding-top: 10px;  
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.checkin-coin .icon {
  padding-right: 6px;
}
.checkin-coin .content {
  flex: 1 0;
}
.checkin-coin .score {
  font-size: 1.5em;
  font-weight: bold;
  color: #666;
}

.checkin-coin .meta {
  font-size: 0.8em;
  text-align: right;
}

</style>
