<?php
/*\
|*| This file is part of the LivecodingAuth wrapper library for the livecoding.tv API
|*| Copyright 2015-2016 William Bartolini <https://github.com/Wapaca/livecoding-auth/issues>
|*| Copyright 2015-2016 bill-auger <https://github.com/Wapaca/livecoding-auth/issues>
|*|
|*| LivecodingAuth is free software: you can redistribute it and/or modify
|*| it under the terms of the GNU Affero General Public License as published by
|*| the Free Software Foundation, either version 3 of the License, or
|*| (at your option) any later version.
|*|
|*| LivecodingAuth is distributed in the hope that it will be useful,
|*| but WITHOUT ANY WARRANTY; without even the implied warranty of
|*| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
|*| GNU Affero General Public License for more details.
|*|
|*| You should have received a copy of the GNU Affero General Public License
|*| along with LivecodingAuth.  If not, see <http://www.gnu.org/licenses/>.
\*/


/**
* Livecoding.tv API usage example
*
* This is an example file to help understanding the LiveCodingAuth API wrapper library.
* This script assumes that you have already created an app on the LCTV API website
*   and that you have selected the "confidential/authorization-grant" app type.
* The constants $CLIENT_ID, $CLIENT_SECRET, and $REDIRECT_URL below
*   must match your app configuration on the LCTV API website.
* Access this script like http://your-site.net/example.php?channel=your-lctv-channel.
*
* @package LivecodingAuth\ChannelStatusExample
* @author Wapaca     - <https://github.com/Wapaca/livecoding-auth/issues>
* @author bill-auger - <https://github.com/Wapaca/livecoding-auth/issues>
* @license AGPLv3
* @version 0.0.1
**/


require('livecodingAuth.php');


session_start();

// Prepare the environment
define("CLIENT_ID", getenv('LCTV_CLIENT_ID'));
define("CLIENT_SECRET", getenv('LCTV_CLIENT_SECRET'));
define("REDIRECT_URL", getenv('LCTV_REDIRECT_URL'));
if(isset($_SESSION['channel']))
  define("CHANNEL_NAME", $_SESSION['channel']);
else if (isset($_GET['channel']))
  define("CHANNEL_NAME", htmlspecialchars($_GET['channel']));
else
  define("CHANNEL_NAME", null);
define('CHANNEL_STATUS_DATA_PATH', 'livestreams/' . CHANNEL_NAME . '/');
define('INVALID_CHANNEL_MSG', 'You must specify a channel name like: example.php?channel=my-channel .');


// Validate channel name param
$CHANNEL_NAME = CHANNEL_NAME;
if (empty( $CHANNEL_NAME ))
  die(INVALID_CHANNEL_MSG);
else
  $_SESSION['channel'] = CHANNEL_NAME;
unset($CHANNEL_NAME);

// Instantiate auth helper
try {
  $LivecodingAuth = new LivecodingAuth(CLIENT_ID, CLIENT_SECRET, REDIRECT_URL);
}
catch(Exception $ex) {
  die($ex->getMessage());
}

// Check for previous authorization
if (!$LivecodingAuth->getIsAuthorized()) {

  // Here we have not yet been authorized

  // Display a link for the user to authorize the app with this script as the redirect URL
  $auth_link = $LivecodingAuth->getAuthLink();
  echo "This app is not yet authorized. Use the link or URL below to authorize it.<br/>";
  echo "<a href=\"$auth_link\">Connect my account</a><br/>" ;

  // Here we wait for the user to click the authorization link
  //   which will result in another request for this page
  //   with $LivecodingAuth->getIsAuthorized() then returning true here.

} else {

  // Here we are authorized from a previous request

  // Fetch data from some API endpoint
  $data = $LivecodingAuth->fetchData(CHANNEL_STATUS_DATA_PATH);

  // Present a result
  $is_online = $data->is_live;
  echo CHANNEL_NAME . " is " . (($is_online) ? 'online' : 'offline') ;

}

?>
