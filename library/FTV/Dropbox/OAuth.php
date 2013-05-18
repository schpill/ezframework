<?php
    /**
     * Oauth class
     *
     * @package         FTV
     * @subpackage      Dropbox
     * @author          Gerald Plusquellec
     */

    abstract class FTV_Dropbox_Oauth 
    {
        /**
         * After a user has authorized access, dropbox can redirect the user back
         * to this url.
         * 
         * @var string
         */
        public $authorizeCallbackUrl = null; 
       
        /**
         * Uri used to fetch request tokens 
         * 
         * @var string
         */
        const URI_REQUEST_TOKEN = 'https://api.dropbox.com/1/oauth/request_token';

        /**
         * Uri used to redirect the user to for authorization.
         * 
         * @var string
         */
        const URI_AUTHORIZE = 'https://www.dropbox.com/1/oauth/authorize';

        /**
         * Uri used to 
         * 
         * @var string
         */
        const URI_ACCESS_TOKEN = 'https://api.dropbox.com/1/oauth/access_token';

        /**
         * An OAuth request token. 
         * 
         * @var string 
         */
        protected $oauthToken = null;

        /**
         * OAuth token secret 
         * 
         * @var string 
         */
        protected $oauthTokenSecret = null;


        /**
         * Constructor
         * 
         * @param string $consumerKey 
         * @param string $consumerSecret 
         */
        abstract public function __construct($consumerKey, $consumerSecret);

        /**
         * Sets the request token and secret.
         *
         * The tokens can also be passed as an array into the first argument.
         * The array must have the elements token and token_secret.
         * 
         * @param string|array $token 
         * @param string $token_secret 
         * @return void
         */
        public function setToken($token, $token_secret = null) 
        {
            if (is_array($token)) {
                $this->oauthToken = $token['token'];
                $this->oauthTokenSecret = $token['token_secret'];
            } else {
                $this->oauthToken = $token;
                $this->oauthTokenSecret = $token_secret;
            }

        }

        /**
         * Returns the oauth request tokens as an associative array.
         *
         * The array will contain the elements 'token' and 'token_secret'.
         * 
         * @return array 
         */
        public function getToken() 
        {
            return array(
                'token' => $this->oauthToken,
                'token_secret' => $this->oauthTokenSecret,
            );

        }

        /**
         * Returns the authorization url
         * 
         * @param string $callBack Specify a callback url to automatically redirect the user back 
         * @return string 
         */
        public function getAuthorizeUrl($callBack = null) {
            
            // Building the redirect uri
            $token = $this->getToken();
            $uri = self::URI_AUTHORIZE . '?oauthToken=' . $token['token'];
            if ($callBack) $uri.='&oauth_callback=' . $callBack;
            return $uri;
        }

        /**
         * Fetches a secured oauth url and returns the response body. 
         * 
         * @param string $uri 
         * @param mixed $arguments 
         * @param string $method 
         * @param array $httpHeaders 
         * @return string 
         */
        public abstract function fetch($uri, array $arguments = array(), $method = 'GET', array $httpHeaders = array()); 

        /**
         * Requests the OAuth request token.
         * 
         * @return array 
         */
        abstract public function getRequestToken(); 

        /**
         * Requests the OAuth access tokens.
         *
         * @return array
         */
        abstract public function getAccessToken(); 

    }
