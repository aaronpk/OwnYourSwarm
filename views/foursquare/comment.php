<?php $this->layout('layout-public', ['title' => $title]); ?>

<div class="checkin-comment h-entry">

  <div class="context">
    <i class="ui reply icon"></i>
    <a href="<?= $checkin->canonical_url ?>" class="<?= $comment->type == 'like' ? 'u-like-of' : 'u-in-reply-to' ?>">
      <?= $checkin->canonical_url ?>
    </a>
  </div>

  <div class="comment">
    <?php if($comment->type == 'comment'): ?>
      <span class="content">
        <div class="author-full u-author h-card">
          <span class="author-icon large">
            <img src="<?= $comment->author_photo ?>" class="u-photo hexatar" width="60">
          </span>
          <a href="<?= $comment->author_url ?: 'https://swarmapp.com/' ?>" class="u-url">
            <?= $comment->author_name ?>
          </a>
        </div>
        <div class="comment-content e-content p-name"><?= $comment->content ?></div>
      </span>
    <?php else: ?>
      <span class="author-icon u-author h-card">
        <img src="<?= $comment->author_photo ?>" class="u-photo <?= $comment->type == 'like' ? 'hexatar' : '' ?>" alt="<?= $this->e($comment->author_name ?: 'Swarm') ?>" width="30">
        <a href="<?= $comment->author_url ?: 'https://swarmapp.com/' ?>" class="u-url"></a>
      </span>
      <span class="content">
        <?php if($comment->type == 'like'): ?>
          <span><?= $this->e($comment->author_name) ?></span>
          <span class="e-content p-name">liked your checkin</span>
        <?php elseif($comment->type == 'coin'): ?>
          <span class="e-content p-name"><?= $comment->content ?></span>
        <?php endif ?>
      </span>
    <?php endif ?>
    <?php if($comment->type == 'coin'): ?>
      <span class="score">+<span class="p-swarm-coins"><?= $comment->coins ?></span></span>
    <?php endif ?>
  </div>

  <div class="meta">
    <a href="" class="u-url">
      <time class="dt-published" datetime="<?= $published->format('c') ?>">
        <?= $published->format('Y-m-d H:i P') ?>
      </time>
    </a>
  </div>
</div>
<style type="text/css">

.checkin-comment {
  border: 1px #aaa solid;
  border-radius: 6px;
  max-width: 600px;
  margin: 0 auto;
  overflow: hidden;
}

.checkin-comment .context,
.checkin-comment .comment,
.checkin-comment .meta {
  padding: 5px 10px;  
}

.checkin-comment .context {
  background: #ddd;
}

.checkin-comment .comment {
  padding-top: 10px;  
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.checkin-comment .author-icon {
  padding-right: 6px;
  height: 30px;
}
.checkin-comment .author-icon.large {
  height: 60px;
}
.checkin-comment .content {
  flex: 1 0;
}
.checkin-comment .score {
  font-size: 1.5em;
  font-weight: bold;
  color: #666;
}

.checkin-comment .meta {
  font-size: 0.8em;
  text-align: right;
}

img.hexatar {
  border-radius: 4px; /* fallback */
  mask: url(/assets/hexatar-mask.svg) top left;
  -o-mask: url(/assets/hexatar-mask.svg) top left;
  -ms-mask: url(/assets/hexatar-mask.svg) top left;
  -webkit-mask: url(/assets/hexatar-mask.svg) top left;
  -o-mask-size: cover;
  -ms-mask-size: cover;
  -webkit-mask-size: cover;
}

.author-full {
  display: flex;
  flex-direction: row;
  align-items: center;
  font-size: 1.2em;
}
.comment-content {
  margin-top: 4px;
  padding-left: 66px;
  font-size: 1.4em;
  line-height: 1.3em;
}

</style>
