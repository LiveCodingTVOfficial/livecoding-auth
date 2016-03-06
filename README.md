### LiveCodingAuth

A PHP Class to simplify access to the livecoding.tv API. It abstracts away the tedious auth dance, allowing instant access to the API. Simply set the following environment vars:
```
  * CLIENT_ID     // <-- your app client id defined on the LCTV API website
  * CLIENT_SECRET // <-- your app client secret defined on the LCTV API website
  * REDIRECT_URL  // <-- your app redirect URL that you defined on the LCTV API website
                         // this is your proxy page like example.php above
                         // (e.g. https://mysite.net/example.php)
```
Refer to example.php for example usage.

Note that the example script uses session storage and so each visitor to your site will need to authorize the app once for each session.

If you plan only to offer some simple read-only public data then you could pass TEXT_STORE to the constructor and then authorize the app once yourself.

If you need more elaborate behavior you will want to subclass LivecodingAuthTokens to use the data backend of your choice. e.g.

livecodingAuth.php
```php
....
// define your custom flag
define("SESSION_STORE", 'session');
define("TEXT_STORE", 'flat-file');
define("MY_CUSTOM_STORE", 'my-livecodingauthtokens-subclass');
....
// switch on your custom flag
if ($storage == SESSION_STORE) {
  $this->tokens = new LivecodingAuthTokensSession();
} else if ($storage == TEXT_STORE) {
  $this->tokens = new LivecodingAuthTokensText();
} else if ($storage == MY_CUSTOM_STORE) {
  $this->tokens = new LivecodingAuthTokensMyCustomSubclass();
}
....
```
your-example.php
```php
....
// pass your custom flag into constructor
$LivecodingAuth = new LivecodingAuth(CLIENT_ID, CLIENT_SECRET, REDIRECT_URL, READ_SCOPE, MY_CUSTOM_STORE);
....
```
