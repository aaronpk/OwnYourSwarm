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

<p>When your account is in JSON mode, the request your Micropub endpoint receives will always be a JSON payload containing a Microformats 2 object describing the checkin. If your account is set to "simple" then a simplified form-encoded request will be made instead.</p>

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

<p>The basic structure of a form-encoded POST payload looks like the below. Most web servers will parse this automatically. (Newlines are for display purposes only.</p>

<pre>h=entry&amp;
published=2017-03-24T17:30:32-07:00&amp;
content=Checked in to PDX Airport
</pre>

<p>The properties in the h-entry will depend on the checkin. The set of properties possible is listed below. Note that in the Micropub JSON syntax, all properties will be arrays, even if there is only one value, e.g. the "published" date above.</p>

<p>You can see an example of a full object after you connect OwnYourSwarm and check in somewhere, then look at your dashboard.</p>

<h4>published</h4>

<p>The <code>published</code> property will contain the ISO8601 timestamp of the date of the checkin. This timestamp will include the timezone offset of the checkin.</p>

<h4>syndication</h4>

<p>The <code>syndication</code> property will contain the Swarm permalink to the checkin. Note that the Swarm permalinks sent in this parameter are not publicly visible, they are only visible to your Swarm friends.</p>

<h4>photo</h4>

<p>If your checkin includes one or more photos, then the URL to your photos will be in the <code>photo</code> property. In "simple" mode, the photos will be uploaded to your endpoint directly instead of referenced by URL. You can access the photo by looking for an uploaded file named "photo".</p>

<p>Note that photos are sent from the Swarm app asynchronously, so it's actually possible for your checkin to be created before the photo has been uploaded to the server, such as when you're on a slow network connection. If this happens, the checkin posted to your website will not include a photo initially. See the <a href="#updates">Updates</a> section below for a description of how to handle receiving the photo after the initial checkin has been created.</p>

<p>In "simple" mode, OwnYourSwarm will still send update requests in JSON format.</p>

<h4>content</h4>

<p>If your checkin contains a user-entered note, then the <code>content</code> property will be included. The <code>content</code>  property will be either a plain string, or an object containing both a plaintext and HTML version of the text, depending on whether there is any HTML formatting in the text.</p>

<p>If you tag people in the checkin, then "with X, Y, Z" will be added to the note, just like it appears in Swarm. The users' names will be hyperlinked to their Foursquare profile URLs. If they also use OwnYourSwarm, then their personal URL will also be included.</p>

<p>Note that if you don't include any user-entered text, then OwnYourSwarm will not include the "with X, Y, Z" text in the content in JSON mode, but will include it in simple mode.</p>

<b>Content with HTML</b>

<pre>  "content": [{
    "value": "#indiewebcamp day 1 - with Aaron",
    "html": "#indiewebcamp day 1 - with &lt;a href="https://aaronparecki.com/"&gt;Aaron&lt;/a&gt;"
  }]
</pre>

<b>Plaintext Content</b>

<pre>  "content": ["Checkin shout"]</pre>

<b>Simple Mode</b>

<p>In "simple" mode, OwnYourSwarm will include "Checked in at {venue name}" to the content. This means if you are able to post notes from Micropub clients, you can very likely support posting checkins from OwnYourSwarm without any additional work!</p>

<h4>category</h4>

<p>The <code>category</code> property will contain any hashtags you've used in your checkin text, as well as an <code>h-card</code> for any people you've tagged in the checkin.</p>

<p>Hashtags in the text will be included as a string without the leading "#", e.g.</p>

<pre>  "content":[
    "#indiewebcamp day 1"
  ],
  "category": [
    "indiewebcamp"
  ]</pre>

<p id="person-tag">If you tag one or more people in the checkin, then their information will be included as a <a href="https://indieweb.org/person-tag">person tag</a> like the below.</p>

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
        "url": ["https://aaronparecki.com/","https://foursquare.com/user/59164"],
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

<b>Simple Mode</b>

<p>In "simple" mode, the checkin property will be only the Foursquare venue URL. You can use the presence of this property to detect that this is a checkin, and you can add a link to the venue.</p>

<h4 id="checked-in-by">checked-in-by</h4>

<p>If a friend checked you in, then an additional property will be included to indicate who created the checkin on your behalf. This is an <code>h-card</code> in the same format as the <a href="#person-tag">person tags</a>. This is only sent for JSON Micropub requests.</p>

<pre>  "checked-in-by": [
    {
      "type": ["h-card"],
      "properties": {
        "name": ["Aaron"],
        "url": ["https://aaronparecki.com/","https://foursquare.com/user/59164"],
        "photo": ["https://igx.4sqi.net/img/user/300x300/QREPPTELDVOJ5CE5.jpg"]
      }
    }
  ]
</pre>

<p>Read more about the <a href="https://www.w3.org/TR/micropub/#json-syntax">Micropub JSON syntax</a>.</p>

<h4 id="visibility">visibility</h4>

<p>On the dashboard, you can disable sending "off the grid" checkins entirely. If you enable sending off the grid checkins, then the checkin will include a new property <code>visibility=private</code>.</p>

<p>You can also tell OwnYourSwarm to always mark checkins as private posts, in which case the <code>visibility</code> property will always be set to <code>private</code> for every checkin. You can use this feature if you'd like your checkins on your website but don't want them to be public posts.</p>

<p>Note that these features require that your website supports private posts. It is up to your Micropub endpoint to recognize this property and handle hiding it from not-logged-in users.</p>

</section>

<section id="updates">
<h3><a href="#updates"><span>🔗</span></a> Updates</h3>

<p>When you add a photo to a checkin on Swarm, occasionally it will be uploaded asynchronously after the checkin itself is created. This means the initial checkin sent to your site will sometimes not include a photo.</p>

<p>When this happens, OwnYourSwarm will periodically poll your recent checkins until a photo appears, then send a <a href="https://www.w3.org/TR/micropub/#update">Micropub update request</a> to add it to your post. The polling is based on an exponential back-off schedule. Below is an example request to add a photo.</p>

<pre>{
  "action": "update",
  "url": "https://example.com/your/checkin/url",
  "add": {
    "photo": [
      "https://igx.4sqi.net/img/general/original/59164_nCV1s2M0arGbdr_Tdx6sAhWD_BKPbyuMx6o-SXvIKWM.jpg"
    ]
  }
}
</pre>

<p>There may be one or more URLs in the <code>photo</code> property. OwnYourSwarm will continue polling your checkin for some time, as photos may appear in the checkin individually, and you can actually continue to add photos to the checkin in Swarm well after the initial checkin was created.</p>

</section>

<section id="coins">
<h3><a href="#coins"><span>🔗</span></a> Coins</h3>

<p>The coins that Swarm awards to checkins are sent to your checkins via <a href="https://www.w3.org/TR/webmention/">Webmention</a>. Each description of the coins awarded is given a URL on OwnYourSwarm, and a Webmention is sent from that URL to your checkin URL. OwnYourSwarm waits about 5 seconds after posting your checkin via Micropub before it sends these Webmentions.</p>

<a href="https://ownyourswarm.p3k.io/checkin/58d57db03bd4ab039c76dfed/144ba522fe8454c1e97aef5735329f55"><img src="/images/still-the-mayor.png" width="628"></a>

<p>If your website is already set up to receive comments via Webmention, then you should start seeing these as normal comments automatically. These URLs are marked up with <a href="http://microformats.org/wiki/h-entry">h-entry</a>, including a <a href="https://indieweb.org/in-reply-to">in-reply-to</a> property linking to your original checkin.</p>

<p>The coin pages also have a vendor-specific property in the h-entry, <code>p-swarm-coins</code>, indicating the number of coins Swarm awarded for this item. The only thing you need to worry about beyond handling this as a normal Webmention comment is parsing the <code>p-swarm-coins</code> property if you want to show that.</p>

<p>A complete example of this comment is given below in parsed Microformats 2 JSON format.</p>

<pre>{
  "type": [
    "h-entry"
  ],
  "properties": {
    "author": [
      {
        "type": [
          "h-card"
        ],
        "properties": {
          "photo": [
            "https://ss1.4sqi.net/img/points/coin_icon_crown.png"
          ],
          "url": [
            "https://swarmapp.com/"
          ],
          "name": [
            "Swarm"
          ]
        },
        "value": "https://swarmapp.com/"
      }
    ],
    "name": [
      "You're still the Mayor. Time to make some rules!"
    ],
    "swarm-coins": [
      "3"
    ],
    "in-reply-to": [
      "https://aaronparecki.com/2017/03/24/7/"
    ],
    "url": [
      "https://ownyourswarm.p3k.io/checkin/58d57db03bd4ab039c76dfed/144ba522fe8454c1e97aef5735329f55"
    ],
    "published": [
      "2017-03-24T13:12:42-07:00"
    ],
    "content": [
      {
        "html": "You're still the Mayor. Time to make some rules!",
        "value": "You're still the Mayor. Time to make some rules!"
      }
    ]
  }
}</pre>

<p>The author of the h-entry will always be Swarm, but the profile photo will change depending on the icon Swarm uses for each line.</p>

<p><a href="https://aaronparecki.com/2017/03/24/9/day-94-ownyourswarm-coins">Read more</a> about coins sent as Webmentions.</p>
</section>

<section id="likes-comments">
<h3><a href="#likes-comments"><span>🔗</span></a> Likes and Comments</h3>

<p>When people like and comment on your checkin, OwnYourSwarm will <a href="https://indieweb.org/backfeed">backfeed</a> these responses to your post via Webmention. OwnYourSwarm creates a URL for each like and comment, and sends a Webmention from that URL to your checkin it created.</p>

<a href="https://ownyourswarm.p3k.io/checkin/58d5ba2865e7c71563456f5f/58d6bfdfb12d9f109ae1ab79"><img src="/images/comment.png"></a>

<a href="https://ownyourswarm.p3k.io/checkin/58d5ba2865e7c71563456f5f/fc1fa21b8404b0233e26b3e4f563bd30"><img src="/images/like.png"></a>

<p>These pages are marked up as a traditional <a href="https://indieweb.org/like">like</a> and <a href="https://indieweb.org/comment">comment</a> using <code>h-entry</code> with the appropriate <code>like-of</code> or <code>in-reply-to</code> property. If your website is set up to handle likes and comments already, then these will work without any additional work. If you haven't yet set up the ability to receive likes and comments, then once you can handle these, you will also be able to receive likes and comments from thousands of other IndieWeb sites!</p>

<p>See <a href="https://indieweb.org/Webmention-developer">Webmention developers</a> for details on how to accept and verify webmentions on your website.</p>

<p>OwnYourSwarm will poll your last 100 checkins looking for new likes and comments on your checkins. Whenever you check in, your polling interval is reset to the highest level. Your account will be checked frequently at first, slowly tapering off if there is no new activity. This should keep a balance between catching recent activity on your checkins while not overloading the system.</p>

</section>

</main>

<style type="text/css">
.docs img {
  max-width: 100%;
}
.ui.fixed.menu {
  position: absolute;
}
</style>
