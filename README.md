### LiveCodingAuth

A PHP Class to simplify access to the livecoding.tv API. It abstracts away the tedious auth dance, allowing visitors to your site instant access to the API. Simply set the following environment variables:
```
  * CLIENT_ID     <-- your app client id as defined on the LCTV API website
  * CLIENT_SECRET <-- your app client secret as defined on the LCTV API website
  * REDIRECT_URL  <-- your app redirect URL as defined on the LCTV API website
                          this is the full URL to your proxy page like example.php
                          (e.g. https://mysite.net/example.php)
```
Refer to example.php for an example client usage.

Note that the example script uses session storage and so each visitor to your site will need to authorize the app once for each session.

If you plan only to offer some simple read-only public data then you could pass TEXT_STORE to the constructor and then authorize the app once yourself.

If you need more elaborate behavior you will want to subclass LivecodingAuthTokens to access the data backend of your choice. For example:

livecodingAuth.php
```php
....

// define your custom flag constant
define("SESSION_STORE", 'session');
define("TEXT_STORE", 'flat-file');
define("MY_CUSTOM_STORE", 'my-storage-subclass');

....

// switch on your custom flag in superclass constructor
if ($storage == SESSION_STORE) {
  $this->tokens = new LivecodingAuthTokensSession();
} else if ($storage == TEXT_STORE) {
  $this->tokens = new LivecodingAuthTokensText();
} else if ($storage == MY_CUSTOM_STORE) {
  $this->tokens = new LivecodingAuthTokensMyCustomSubclass();
}

....

class LivecodingAuthTokensMyCustomSubclass extends LivecodingAuthTokens
{
  .... over-rides ....
}

....
```

your-proxy.php
```php
....

// pass your custom flag into constructor
$LivecodingAuth = new LivecodingAuth(CLIENT_ID, CLIENT_SECRET, REDIRECT_URL, READ_SCOPE, MY_CUSTOM_STORE);

....
```
