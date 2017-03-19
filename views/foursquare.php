<?php $this->layout('layout', ['title' => $title]); ?>

<h2><?= $this->e($title) ?></h2>

<p>Click the button below to connect your Foursquare account.</p>

<a href="/foursquare/auth" class="ui primary button">Connect Foursquare</a>
