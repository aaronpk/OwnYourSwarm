<?php $this->layout('layout', ['title' => $title]); ?>

<main class="docs">

<h2>OwnYourSwarm Documentation</h2>

<p>To begin using OwnYourSwarm, you will need a server that supports <a href="https://micropub.net/">Micropub</a>. Micropub is the API standard that OwnYourSwarm uses to send checkins to your website.</p>

<p>After you sign in with your domain and authorize OwnYourSwarm to create posts on your website, you will then connect your Swarm account. At that point, your account is active, and any future checkins will be sent to your website as soon as you check in on Swarm.</p>

<section id="authentication">
<h3><a href="#authentication"><span>🔗</span></a> Authentication</h3>

<p>The first step to using OwnYourSwarm is to authorize it to post to your website. To do this, you will need to support the IndieAuth spec. When you first attempt to sign in, you will be walked through the process of setting up the required pieces.</p>

<p>To quickly get started, you can use the indieauth.com service to handle your authorization endpoint and token endpoint. Your Micropub server will need to be able to verify the tokens itself. Copy the lines below to your home page to use the indieauth.com services to bootstrap your implementation.</p>

<pre><code>&lt;link rel="authorization_endpoint" href="https://indieauth.com/auth"&gt;
&lt;link rel="token_endpoint" href="https://tokens.indieauth.com/token"&gt;</code></pre>

<p>You can read more about implementing these two components yourself <a href="https://indieweb.org/obtaining-an-access-token">on the IndieWeb wiki</a>.</p>

<p>Once OwnYourSwarm has obtained an access token, it will send it along with each request in an HTTP Authorization header like the below.</p>

<pre><code>HTTP/1.1 POST /micropub
Authorization: Bearer xxxxxxxxxxxxxxxxxxxxxxx</code></pre>
</section>

<section id="checkins">
<h3><a href="#checkins"><span>🔗</span></a> Checkins</h3>

<p>The request your Micropub endpoint receives will always be a JSON payload containing a Microformats 2 object describing the checkin.</p>

<p>The basic structure of a JSON payload looks like the below.</p>

<pre>{
  "type": [
    "h-entry"
  ],
  "properties": {
    "published": [
      "2017-03-24T17:30:32-07:00"
    ]
    ...
  }
}</pre>

<p>The properties in the h-entry will depend on the checkin. The set of properties possible is listed below. Note that in the Micropub JSON syntax, all properties will be arrays, even if there is only one value, e.g. the "published" date above.</p>

<p>You can see an example of a full object after you connect OwnYourSwarm and check in somewhere, then look at your dashboard.</p>

<h4>published</h4>

<p>The <code>published</code> property will contain the ISO8601 timestamp of the date of the checkin. This timestamp will include the timezone offset of the checkin.</p>

<h4>syndication</h4>

<p>The <code>syndication</code> property will contain the Swarm permalink to the checkin. Note that Swarm permalinks are only visible to the user who created the checkin.</p>

<h4>photo</h4>

<p>If your checkin includes one or more photos, then the URL to your photos will be in the <code>photo</code> property.</p>

<p>Note that photos are sent from the Swarm app asynchronously, so it's actually possible for your checkin to be created before the photo has been uploaded to the server, such as when you're on a slow network connection. If this happens, the checkin posted to your website will not include a photo initially. See the <a href="#updates">Updates</a> section below for a description of how to handle receiving the photo after the initial checkin has been created.</p>

<h4>content</h4>

<p>If your checkin contains a note, or if you've tagged people in the checkin, then the <code>content</code> property will be included. The <code>content</code>  property will be either a plain string, or an object containing both a plaintext and HTML version of the text, depending on whether there is any HTML formatting in the text.</p>

<p>If you tag people in the checkin, their names will be hyperlinked to their Foursquare profile URLs. (If they also use OwnYourSwarm, then their personal URL will be used instead.)</p>

<p>Note that when you tag people in a checkin, Swarm adds the text "with X, Y, Z" to the end of the text automatically.</p>

<b>Content with HTML</b>

<pre>  "content": [{
    "value": "#indiewebcamp day 1 - with Aaron",
    "html": "#indiewebcamp day 1 - with &lt;a href="https://aaronparecki.com/"&gt;Aaron&lt;/a&gt;"
  }]
</pre>

<b>Plaintext Content</b>

<pre>  "content": ["Checkin shout"]</pre>

<h4>category</h4>

<p>The <code>category</code> property will contain any hashtags you've used in your checkin text, as well as an <code>h-card</code> for any people you've tagged in the checkin.</p>

<p>Hashtags in the text will be included as a string without the leading "#", e.g.</p>

<pre>  "content":[
    "#indiewebcamp day 1"
  ],
  "category": [
    "indiewebcamp"
  ]</pre>

<p>If you tag one or more people in the checkin, then their information will be included as a <a href="https://indieweb.org/person-tag">person tag</a> like the below.</p>

<pre>  "content": [{
    "value": "#indiewebcamp day 1 - with Aaron",
    "html": "#indiewebcamp day 1 - with &lt;a href="https://aaronparecki.com/"&gt;Aaron&lt;/a&gt;"
  }],
  "category": [
    "indiewebcamp",
    {
      "type": ["h-card"],
      "properties": {
        "name": ["Aaron"],
        "url": ["https://foursquare.com/user/59164", "https://aaronparecki.com/"],
        "photo": ["https://igx.4sqi.net/img/user/300x300/QREPPTELDVOJ5CE5.jpg"]
      }
    }
  ]
</pre>

<h4>checkin</h4>

<p>The information about the venue you've checked in to will be included in the <code>checkin</code> property. The value of this property will be an <code>h-card</code> with the following properties.</p>

Always present:
<ul>
  <li><code>name</code> - The name of the venue. This will always be an array with a single value.</li>
  <li><code>url</code> - The URL(s) for the venue. The first value will always be the Foursquare URL of the venue. If the venue has a website, then the website will be included as another value. If the venue has a Twitter account, the Twitter profile URL will also be included.</li>
</ul>

Present when available from Swarm:
<ul>
  <li><code>latitude</code> - The latitude of the venue. This may be intentionally less accurate for <a href="https://developer.foursquare.com/docs/responses/venue">private venues</a>.</li>
  <li><code>longitude</code> -  The longitude of the venue. This may be intentionally less accurate for private venues.</li>
  <li><code>street-address</code> - The street address of the venue</li>
  <li><code>locality</code> - The city or other large geographical region, e.g. "Portland"</li>
  <li><code>region</code> - The state or region, e.g. "Oregon"</li>
  <li><code>country-name</code> - The country name, e.g. "United States"</li>
  <li><code>postal-code</code> - The postal code, e.g. "97214"</li>
  <li><code>tel</code> - The venue's phone number</li>
</ul>

<p>Read more about the <a href="https://www.w3.org/TR/micropub/#json-syntax">Micropub JSON syntax</a>.</p>

</section>

<section id="updates">
<h3><a href="#updates"><span>🔗</span></a> Updates</h3>

<p>Documentation coming soon...</p>
</section>

<section id="likes-comments">
<h3><a href="#likes-comments"><span>🔗</span></a> Likes and Comments</h3>

<p>Documentation coming soon...</p>
</section>

<section id="coins">
<h3><a href="#coins"><span>🔗</span></a> Coins</h3>

<p>Documentation coming soon...</p>
</section>

</main>

<style type="text/css">
.ui.fixed.menu {
  position: absolute;
}
</style>