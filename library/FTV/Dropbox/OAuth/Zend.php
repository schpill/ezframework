<?php
    /**
     * Oauth Zend class
     *
     * @package         FTV
     * @subpackage      Dropbox Oauth
     * @author          Gerald Plusquellec
     */
    class FTV_Dropbox_Oauth_Zend extends FTV_Dropbox_Oauth 
    {
        /**
         * OAuth object
         *
         * @var Zend_Oauth_Consumer
         */
        protected $oAuth;
        /**
         * OAuth consumer key
         *
         * We need to keep this around for later.
         *
         * @var string
         */
        protected $consumerKey;
        /**
         *
         * @var zendOauthToken
         */
        protected $zendOauthToken;

        /**
         * Constructor
         *
         * @param string $consumerKey
         * @param string $consumerSecret
         */
        public function __construct($consumerKey, $consumerSecret) 
        {
            if (!class_exists('Zend_Oauth_Consumer')) {
                // We're going to try to load in manually
                include 'Zend/Oauth/Consumer.php';
            }
            if (!class_exists('Zend_Oauth_Consumer')) {
                throw new FTV_Dropbox_Exception('The Zend_Oauth_Consumer class could not be found!');
            }
            $this->OAuth = new Zend_Oauth_Consumer(array(
                        "consumerKey"       => $consumerKey,
                        "consumerSecret"    => $consumerSecret,
                        "requestTokenUrl"   => self::URI_REQUEST_TOKEN,
                        "accessTokenUrl"    => self::URI_ACCESS_TOKEN,
                        "authorizeUrl"      => self::URI_AUTHORIZE,
                        "signatureMethod"   => "HMAC-SHA1",
                    ));
            $this->consumerKey = $consumerKey;
        }

        /**
         * Sets the request token and secret.
         *
         * The tokens can also be passed as an array into the first argument.
         * The array must have the elements token and tokenSecret.
         *
         * @param string|array $token
         * @param string $tokenSecret
         * @return void
         */
        public function setToken($token, $tokenSecret = null) 
        {
            if (is_a($token, "zendOauthToken")) {
                if (is_a($token, "Zend_Oauth_Token_Access")) {
                    $this->OAuth->setToken($token);
                }
                $this->zendOauthToken = $token;
                return parent::setToken($token->getToken(), $token->getTokenSecret());
            } elseif (is_string($token) && is_null($tokenSecret)) {
                return $this->setToken(unserialize($token));
            } elseif (isset($token['zendOauthToken'])) {
                return $this->setToken(unserialize($token['zendOauthToken']));
            } else {
                parent::setToken($token, $tokenSecret);
                return;
            }
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
        public function fetch($uri, array $arguments = array(), $method = 'GET', array $httpHeaders = array()) 
        {
            $token = $this->OAuth->getToken();
            if (!is_a($token, "zendOauthToken")) {
                if (is_a($this->zendOauthToken, "Zend_Oauth_Token_Access")) {
                    $token = $this->zendOauthToken;
                } else {
                    $token = new Zend_Oauth_Token_Access();
                    $token->setToken($this->oauthToken);
                    $token->setTokenSecret($this->oauthTokenSecret);
                }
            }
            /* @var $token Zend_Oauth_Token_Access */
            $oauthOptions = array(
                'consumerKey' => $this->consumerKey,
                'signatureMethod' => "HMAC-SHA1",
                'consumerSecret' => $this->OAuth->getConsumerSecret(),
            );
            $config = array("timeout" => 15);

            /* @var $consumerRequest Zend_Oauth_Client */
            $consumerRequest = $token->getHttpClient($oauthOptions);
            $consumerRequest->setMethod($method);
            if (is_array($arguments)) {
                $consumerRequest->setUri($uri);
                if ($method == "GET") {
                    foreach ($arguments as $param => $value) {
                        $consumerRequest->setParameterGet($param, $value);
                    }
                } else {
                    foreach ($arguments as $param => $value) {
                        $consumerRequest->setParameterPost($param, $value);
                    }
                }
            } elseif (is_string($arguments)) {
                preg_match("/\?file=(.*)$/i", $uri, $matches);
                if (isset($matches[1])) {
                    $uri = repl($matches[0], "", $uri);
                    $filename = $matches[1];
                    $uri = Zend_Uri::factory($uri);
                    $uri->addReplaceQueryParameters(array("file" => $filename));
                    $consumerRequest->setParameterGet("file", $filename);
                }
                $consumerRequest->setUri($uri);
                $consumerRequest->setRawData($arguments);
            } elseif (is_resource($arguments)) {
                $consumerRequest->setUri($uri);
                /** Placeholder for Oauth streaming support. */
            }
            if (count($httpHeaders)) {
                foreach ($httpHeaders as $k => $v) {
                    $consumerRequest->setHeaders($k, $v);
                }
            }
            $response = $consumerRequest->request();
            $body = Zend_Json::decode($response->getBody());
            switch ($response->getStatus()) {
                // Not modified
                case 304:
                    return array(
                        'httpStatus' => 304,
                        'body'       => null,
                    );
                    break;
                case 403:
                    throw new FTV_Dropbox_Exception_Forbidden('Forbidden.
                        This could mean a bad OAuth request, or a file or folder already existing at the target location.
                        ' . $body["error"] . "\n");
                case 404:
                    throw new FTV_Dropbox_Exception_NotFound('Resource at uri: ' . $uri . ' could not be found. ' .
                            $body["error"] . "\n");
                case 507:
                    throw new FTV_Dropbox_Exception_OverQuota('This dropbox is full. ' .
                            $body["error"] . "\n");
            }

            return array(
                'httpStatus'    => $response->getStatus(),
                'body'          => $response->getBody(),
            );
        }

        /**
         * Requests the OAuth request token.
         *
         * @return void
         */
        public function getRequestToken() 
        {
            $token = $this->OAuth->getRequestToken();
            $this->setToken($token);
            return $this->getToken();
        }

        /**
         * Requests the OAuth access tokens.
         *
         * This method requires the 'unauthorized' request tokens
         * and, if successful will set the authorized request tokens.
         *
         * @return void
         */
        public function getAccessToken() 
        {
            if (is_a($this->zendOauthToken, "zendOauthToken_Request")) {
                $requestToken = $this->zendOauthToken;
            } else {
                $requestToken = new zendOauthToken_Request();
                $requestToken->setToken($this->oauthToken);
                $requestToken->setTokenSecret($this->oauthTokenSecret);
            }
            $token = $this->OAuth->getAccessToken($_GET, $requestToken);
            $this->setToken($token);
            return $this->getToken();
        }

        /**
         * Returns the oauth request tokens as an associative array.
         *
         * The array will contain the elements 'token' and 'tokenSecret' and the serialized
         * zendOauthToken object.
         *
         * @return array
         */
        public function getToken() 
        {
            return array(
                'token' => $this->oauthToken,
                'tokenSecret' => $this->oauthTokenSecret,
                'zendOauthToken' => serialize($this->zendOauthToken),
            );
        }

        /**
         * Returns the authorization url
         *
         * Overloading Dropbox_OAuth to use the built in functions in Zend_Oauth
         *
         * @param string $callBack Specify a callback url to automatically redirect the user back
         * @return string
         */
        public function getAuthorizeUrl($callBack = null)
        {
            if ($callBack) {
                $this->OAuth->setCallbackUrl($callBack);
            }
            return $this->OAuth->getRedirectUrl();
        }

    }
