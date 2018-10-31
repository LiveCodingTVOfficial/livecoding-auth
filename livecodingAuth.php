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
* Livecoding.tv API
*
* This file contains the LivecodingAuth library classes.
* Normally, this file need not be modified except to implement a custom model sublass.
* This script assumes that you have already created an app on the LCTV API website
*   and that you have selected the "confidential/authorization-grant" app type.
* Setup instructions exists in the README.md file in this repo
*   and a usage example exists in the example.php file in this repo.
*
* @package LivecodingAuth\LivecodingAuth
* @author Wapaca     - <https://github.com/Wapaca/livecoding-auth/issues>
* @author bill-auger - <https://github.com/Wapaca/livecoding-auth/issues>
* @license AGPLv3
* @version 0.0.1
**/


define('CURL_NOT_FOUND_MSG', 'This library requires that curl be available on this server.');
define('INVALID_CLIENT_ID_MSG', 'You must specify a client ID.');
define('INVALID_CLIENT_SECRET_MSG', 'You must specify a client secret.');
define('INVALID_REDIRECT_URL_MSG', 'You must specify a redirect URL.');
define('INVALID_STORAGE_FLAG_MSG', 'Unknown storage flag: ');
define('LCTV_TOKEN_URL', 'https://www.livecoding.tv/o/token/');
define('LCTV_API_URL', 'https://www.livecoding.tv:443/api/');
define("READ_SCOPE", 'read');                // Read basic public profile information
define("READVIEWER_SCOPE", 'read:viewer');   // Play live streams and videos for you
define("READUSER_SCOPE", 'read:user');       // Read your personal information
define("READCHANNEL_SCOPE", 'read:channel'); // Read private channel information
define("CHAT_SCOPE", 'chat');                // Access chat on your behalf
define("SESSION_STORE", 'session');
define("TEXT_STORE", 'flat-file');


if (!class_exists('LivecodingAuth')) {

  /**
   * @since 0.0.1
   * @class LivecodingAuth - Negotiates and manages livecoding.tv API tokens and data requests
   **/
  class LivecodingAuth {

    /**
    * Client id as defined on the LCTV API website
    * @since 0.0.1
    * @access private
    * @var string
    **/
    private $client_id;
    /**
    * Client secret as defined on the LCTV API website
    * @since 0.0.1
    * @access private
    * @var string
    **/
    private $client_secret;
    /**
    * Redirect URL as defined on the LCTV API website
    * @since 0.0.1
    * @access private
    * @var string
    **/
    private $redirect_url;
    /**
    * API access scope
    * @since 0.0.1
    * @access private
    * @var string
    **/
    private $scope;
    /**
    * API token request state
    * @since 0.0.1
    * @access private
    * @var string
    **/
    private $state;
    /**
    * API authorization state
    * @since 0.0.1
    * @access private
    * @var string
    **/
    private $is_authorized;
    /**
    * API user authorization link
    * @since 0.0.1
    * @access private
    * @var string
    **/
    private $auth_link;
    /**
    * Standard token request headers
    * @since 0.0.1
    * @access private
    * @var string
    **/
    private $token_req_headers;
    /**
    * Standard token request params
    * @since 0.0.1
    * @access private
    * @var string
    **/
    private $token_req_params;
    /**
    * Standard API request params
    * @since 0.0.1
    * @access private
    * @var string
    **/
    private $api_req_params;

    /**
    * Negotiates and manages livecoding.tv API tokens and data requests
    * @since 0.0.1
    * @api
    * @access public
    * @param string $client_id     - As defined in your LCTV API app configuration
    * @param string $client_secret - As defined in your LCTV API app configuration
    * @param string $redirect_url  - As defined in your LCTV API app configuration
    * @param string $scope         - One of the *_SCOPE constants (default: 'read')
    * @param string $storage       - One of the *_STORE constants (default: 'session')
    * @throws Exception            - If curl not accessible
    * @throws Exception            - If missing credentials
    * @throws Exception            - If unknown storage flag given
    **/
    function __construct($client_id, $client_secret, $redirect_url,
                         $scope = READ_SCOPE, $storage = SESSION_STORE) {
      // Assert curl accessibilty and validate params
      if (!function_exists('curl_version')) throw new Exception(CURL_NOT_FOUND_MSG, 1);
      else if (empty($client_id)) throw new Exception(INVALID_CLIENT_ID_MSG, 1);
      else if (empty($client_secret)) throw new Exception(INVALID_CLIENT_SECRET_MSG, 1);
      else if (empty($redirect_url)) throw new Exception(INVALID_REDIRECT_URL_MSG, 1);

      // Initialize data members
      $this->client_id = $client_id;
      $this->client_secret = $client_secret;
      $this->redirect_url = $redirect_url;
      $this->scope = $scope;
      $this->state = uniqid();

      if ($storage == SESSION_STORE) {
        $this->tokens = new LivecodingAuthTokensSession();
      } else if ($storage == TEXT_STORE) {
        $this->tokens = new LivecodingAuthTokensText();
      } else {
        throw new Exception(INVALID_STORAGE_FLAG_MSG . $storage , 1);
      }

      $this->auth_link = 'https://www.livecoding.tv/o/authorize/?' . http_build_query(array(
        'scope' => $this->scope,
        'state' => $this->state,
        'redirect_uri' => $this->redirect_url,
        'response_type' => 'code',
        'client_id' => $this->client_id,
      ));

      $this->token_req_headers = [
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        'Authorization: Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
      ];
      $this->token_req_params = [
        'grant_type' => '',
        'code' => $this->tokens->getCode(),
        'redirect_uri' => $this->redirect_url
      ];
      $this->api_req_params = [
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        'Authorization: TOKEN_TYPE_DEFERRED ACCESS_TOKEN_DEFERRED'
      ];

      // Check the storage for existing tokens
      if ($this->tokens->isAuthorized()) {
        // Here we are authorized from a previous request

        // Nothing to do - yay
      }
      else if (isset($_GET['state']) && $_GET['state'] == $this->tokens->getState() ) {
        // Here we are returning from user auth approval link
        $this->fetchTokens($_GET['code']) ;
      }
      else {
        // Here we have not yet been authorized

        // Save the state before displaying auth link
        $this->tokens->setState($this->state);
      }

    } // __construct

    /**
    * Request some data from the API
    * @since 0.0.1
    * @api
    * @access public
    * @param string $data_path - The data to get e.g. 'livestreams/channelname/'
    * @return string           - The requested data as JSON string or error message
    **/
    public function fetchData($data_path) {
      // Refresh tokens from API server if necessary
      if ($this->tokens->is_stale()) {
        $this->refreshToken();
      }

      // Retrieve some data:
      $data = $this->sendGetRequest($data_path);

      // Here we return some parsed JSON data - Caller can now do something interesting
      return $data;
    } // fetchData

    /**
    * Check if auth tokens exist and we are prepared to make API requests
    * @since 0.0.1
    * @api
    * @access public
    * @return boolean - Returns TRUE if the app is ready to make requests,
    *                       or FALSE if user authorization is required
    **/
    public function getIsAuthorized() {
      return $this->tokens->isAuthorized();
    }

    /**
    * Get link URL for manual user authorization
    * @since 0.0.1
    * @api
    * @access public
    * @return string - The URL for manual user authorization
    **/
    public function getAuthLink() {
      return $this->auth_link;
    } // getAuthLink

    /**
    * Wrapper to make a get request
    * @since 0.0.1
    * @access private
    * @param string $url           - API endpoint request URL
    * @param string $custom_header - (optional) HTTP header
    * @return string               - JSON encoded API response
    **/
    private function get_url_contents($url, $custom_header = []) {
        $crl = curl_init();
        $timeout = 5;
        curl_setopt($crl, CURLOPT_HTTPHEADER, $custom_header);
        curl_setopt($crl, CURLOPT_URL ,$url);
        curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);
        curl_close($crl);

        return $ret;
    } // get_url_contents

    /**
    * Wrapper to make a post request
    * @since 0.0.1
    * @access private
    * @param string $url           - tokens request URL
    * @param string $fields        - POST fields
    * @param string $custom_header - (optional) HTTP header
    * @return string               - JSON encoded API tokens response
    **/
    private function post_url_contents($url, $fields, $custom_header = []) {

        $fields_string = http_build_query($fields);

        $crl = curl_init();
        $timeout = 5;

        curl_setopt($crl, CURLOPT_HTTPHEADER, $custom_header);

        curl_setopt($crl, CURLOPT_URL, $url);
        curl_setopt($crl, CURLOPT_POST, count($fields));
        curl_setopt($crl, CURLOPT_POSTFIELDS, $fields_string);

        curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);
        curl_close($crl);

        return $ret;
    } // post_url_contents

    /**
    * Fetch initial tokens after manual user auth
    * @since 0.0.1
    * @access private
    * @param string $code - Auth code returned by the API in redirect URL params
    **/
    private function fetchTokens($code) {
      $this->tokens->setCode($code);
      $this->token_req_params['code'] = $code;
      $this->token_req_params['grant_type'] = 'authorization_code';
      $res = $this->post_url_contents(LCTV_TOKEN_URL,
        $this->token_req_params, $this->token_req_headers);

      // Store access tokens
      $this->tokens->storeTokens($res);
    } // fetchTokens

    /**
    * Refresh stale tokens
    * @since 0.0.1
    * @access private
    **/
    private function refreshToken() {
      $this->token_req_params['grant_type'] = 'refresh_token';
      $this->token_req_params['refresh_token'] = $this->tokens->getRefreshToken();
      $res = $this->post_url_contents(LCTV_TOKEN_URL,
        $this->token_req_params, $this->token_req_headers);

      // Store access tokens
      $this->tokens->storeTokens($res);
    } // refreshToken

    /**
    * Request API data
    * @since 0.0.1
    * @access private
    * @param string $data_path - The data to get e.g. 'livestreams/channelname/'
    * @return string           - The requested data as JSON string or error message
    **/
    private function sendGetRequest($data_path) {
      $this->api_req_params[2] = $this->tokens->makeAuthParam();

      $res = $this->get_url_contents(LCTV_API_URL . $data_path, $this->api_req_params);

      $res = json_decode($res);

      if(isset($res->error))
        return "{ error: '$res->error' }";
      else
        return $res;
    } // sendGetRequest

  } // class LivecodingAuth

} // if(!class_exists)


if (!class_exists('LivecodingAuthTokens')) {

  /**
  *
  * LivecodingAuthTokens
  *
  * LivecodingAuthTokens is intended to be semi-abstract
  * Only its subclasses should be instantiated
  * @since 0.0.1
  * @internal
  * @access public
  * @class LivecodingAuthTokens
  * @see LivecodingAuthTokensSession
  * @see LivecodingAuthTokensText
  **/
  abstract class LivecodingAuthTokens {

    /**
    * Store token data to subclass defined backend
    * @since 0.0.1
    * @internal
    * @access public
    * @param string $tokens - JSON encoded API response containing granted tokens
    **/
    public function storeTokens($tokens) {
      $tokens = json_decode($tokens);
      if(!isset($tokens->error))
      {
        $this->setAccessToken($tokens->access_token);
        $this->setTokenType($tokens->token_type);
        $this->setRefreshToken($tokens->refresh_token);
        $this->setExpiresIn(date('Y-m-d H:i:s', (time() + $tokens->expires_in)));
        $this->setScope($tokens->scope);
      }
    } // storeTokens

    /**
    * Determine if our access token needs to be refreshed
    * @since 0.0.1
    * @internal
    * @access public
    * @return boolean - Returns TRUE if the access token has expired,
    *                       or FALSE if the access token is still valid
    **/
    public function is_stale() {
      return (strtotime($this->getExpiresIn()) - time()) < 7200;
    } // is_stale

    /**
    * Concatenate current auth token to param string for data request
    * @since 0.0.1
    * @internal
    * @access public
    * @return string - The properly formatted authorization parameter
    **/
    public function makeAuthParam() {
      return 'Authorization: '.$this->getTokenType() . ' ' . $this->getAccessToken();
    }


    // Subclasses should override these getters and setters

    /**
    * Check if auth tokens exist and we are prepared to make API requests
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @return boolean - Returns TRUE if the app is ready to make requests,
    *                       or FALSE if user authorization is required
    **/
    abstract public function isAuthorized();

    /**
    * Set API token authorization code
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @param string $code - API token authorization code
    **/
    abstract public function setCode($code);
    /**
    * Get API token authorization code
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @return string - API token authorization code
    **/
    abstract public function getCode();

    /**
    * Set API token request state
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @param string $code - API token request state
    **/
    abstract public function setState($state);
    /**
    * Get API token request state
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @return string - API token request state
    **/
    abstract public function getState();

    /**
    * Set API access token
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @param string $code - API access token
    **/
    abstract public function setAccessToken($access_token);
    /**
    * Get API access token
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @return string - API access token
    **/
    abstract public function getAccessToken();

    /**
    * Set API token type
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @param string $code - API token type
    **/
    abstract public function setTokenType($token_type);
    /**
    * Get API token type
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @return string - API token type
    **/
    abstract public function getTokenType();

    /**
    * Set API refresh token
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @param string $code - API refresh token
    **/
    abstract public function setRefreshToken($refresh_token);
    /**
    * Get API refresh token
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @return string - API refresh token
    **/
    abstract public function getRefreshToken();

    /**
    * Set API access token expitartion date
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @param string $code - API access token expitartion date (formatted by date())
    **/
    abstract public function setExpiresIn($expires_in);
    /**
    * Get API access token expitartion date
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @return string - access token expitartion date (formatted for strtotime())
    **/
    abstract public function getExpiresIn();

    /**
    * Set API access scope
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @param string $code - API access scope
    **/
    abstract public function setScope($scope);
    /**
    * Get API access scope
    *     * Subclass Responsibility *
    * @since 0.0.1
    * @internal
    * @access public
    * @return string - API access scope
    **/
    abstract public function getScope();

  }

} // if(!class_exists('LivecodingAuthTokens'))


if (!class_exists('LivecodingAuthTokensSession')) {

  /**
  *
  * LivecodingAuthTokensSession
  *
  * A LivecodingAuthTokens reference subclass using session storage
  * @since 0.0.1
  * @internal
  * @access public
  * @class LivecodingAuthTokensSession
  * @see LivecodingAuthTokens
  **/
  class LivecodingAuthTokensSession extends LivecodingAuthTokens {

    function __construct() {
      if (!isset($_SESSION)) {
        session_start();
      }
    } // __construct

    public function isAuthorized() {
      return isset($_SESSION['code']);
    } // isAuthorized

    public function getCode() {
      return ($this->isAuthorized()) ? $_SESSION['code'] : '' ;
    } // getCode

    public function setCode($code) {
      $_SESSION['code'] = $code;
    } // setState

    public function getState() {
      return $_SESSION['state'] ;
    } // getState

    public function setState($state) {
      $_SESSION['state'] = $state;
    } // setState

    public function getScope() {
      return $_SESSION['scope'];
    } // getScope

    public function setScope($scope) {
      $_SESSION['scope'] = $scope;
    } // setScope

    public function getTokenType() {
      return $_SESSION['token_type'];
    } // getTokenType

    public function setTokenType($token_type) {
      $_SESSION['token_type'] = $token_type;
    } // setTokenType

    public function getAccessToken() {
      return $_SESSION['access_token'];
    } // getAccessToken

    public function setAccessToken($access_token) {
      $_SESSION['access_token'] = $access_token;
    } // setAccessToken

    public function getRefreshToken() {
      return $_SESSION['refresh_token'];
    } // getRefreshToken

    public function setRefreshToken($refresh_token) {
      $_SESSION['refresh_token'] = $refresh_token;
    } // setRefreshToken

    public function getExpiresIn() {
      return $_SESSION['expires_in'];
    } // getExpiresIn

    public function setExpiresIn($expires_in) {
      $_SESSION['expires_in'] = $expires_in;
    } // setExpiresIn

  } // class LivecodingAuthTokens

} // if(!class_exists('LivecodingAuthTokensSession'))


if (!class_exists('LivecodingAuthTokensText')) {

  /**
  *
  * LivecodingAuthTokensText
  *
  * A LivecodingAuthTokens reference subclass using session storage
  * @since 0.0.1
  * @internal
  * @access public
  * @class LivecodingAuthTokensText
  * @see LivecodingAuthTokens
  **/
  class LivecodingAuthTokensText extends LivecodingAuthTokens {

    public function isAuthorized() {
      return file_exists('code') ;
    } // isAuthorized

    public function getCode() {
      return file_get_contents('code') ;
    } // getCode

    public function setCode($code) {
      file_put_contents('code', $code);
    } // setState

    public function getState() {
      return file_get_contents('state') ;
    } // getState

    public function setState($state) {
      file_put_contents('state', $state);
    } // setState

    public function getScope() {
      return file_get_contents('scope');
    } // getScope

    public function setScope($scope) {
      file_put_contents('scope', $scope);
    } // setScope

    public function getTokenType() {
      return file_get_contents('token_type');
    } // getTokenType

    public function setTokenType($token_type) {
      file_put_contents('token_type', $token_type);
    } // setTokenType

    public function getAccessToken() {
      return file_get_contents('access_token');
    } // getAccessToken

    public function setAccessToken($access_token) {
      file_put_contents('access_token', $access_token);
    } // setAccessToken

    public function getRefreshToken() {
      return file_get_contents('refresh_token');
    } // getRefreshToken

    public function setRefreshToken($refresh_token) {
      file_put_contents('refresh_token', $refresh_token);
    } // setRefreshToken

    public function getExpiresIn() {
      return file_get_contents('expires_in');
    } // getExpiresIn

    public function setExpiresIn($expires_in) {
      file_put_contents('expires_in', $expires_in);
    } // setExpiresIn

  } // class LivecodingAuthTokensText

} // if(!class_exists('LivecodingAuthTokensText'))
