<?php
    class FTV_Service_Bitly
    {
        const URI_BASE = 'http://api.bit.ly';

        const STATUS_OK = 'OK';
        const STATUS_RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
        const STATUS_INVALID_URI = 'INVALID_URI';
        const STATUS_MISSING_ARG_LOGIN = 'MISSING_ARG_LOGIN';
        const STATUS_UNKNOWN_ERROR = 'UNKNOWN_ERROR';

        const URL_SHORTEN = '/v3/shorten';
        const URL_EXPAND = '/v3/expand';
        const URL_CLICKS = '/v3/clicks';
        const URL_PRO_DOMAIN = '/v3/bitly_pro_domain';
        const URL_LOOKUP = '/v3/lookup';
        const URL_AUTHENTICATE = '/v3/authenticate';
        const URL_INFO = '/v3/info';
        const URL_VALIDATE = '/v3/validate';

        /**
        *
        * @var Zend_Http_Response
        */
        protected $_response = null;

        /**
        *
        * @var array
        */
        protected $_data = null;

        /**
        * @return Zend_Rest_Client
        */
        protected function _getClient()
        {
            if (null === $this->_client) {
                $this->_client = new Zend_Rest_Client(self::URI_BASE);
            }
            return $this->_client;
        }

        protected function _checkErrors()
        {
            switch ($this->_data['status_txt']) {
                case self::STATUS_OK:
                    break;
                case self::STATUS_RATE_LIMIT_EXCEEDED:
                case self::STATUS_INVALID_URI:
                case self::STATUS_MISSING_ARG_LOGIN:
                case self::STATUS_UNKNOWN_ERROR:
                default:
                    throw new FTV_Exception('Error in Bit.ly service : ' . $this->_data['status_txt'], $this->_data['status_code']);
                    break;
            }
        }
        
        /* needed ?? */
        public function __construct(array $config = array())
        {

        }

        /**
        *
        * @param string $path
        * @param array $options
        */
        protected function _callApi($path, $options)
        {
            $restClient = $this->_getClient();
            $restClient->getHttpClient()->resetParameters();

            if (!isset($options['login'])) {
                $options['login'] = config::get('ftv.service.bitlty.login');
            }
            if (!isset($options['apiKey'])) {
                $options['apiKey'] = config::get('ftv.service.bitlty.apiKey');
            }

            $param['format'] = 'json';

            $this->_response = $restClient->restGet($path, $options);

            switch ($param['format']) {
                case 'json':
                    $this->_data = Zend_Json::decode($this->_response->getBody());
                    break;
                case 'xml':
                    throw new FTV_Exception('Not yet implemented. Please use json format.');
                    break;
            }

            $this->_checkErrors();

            return $this->_data['data'];
        }

        public function shorten($param)
        {
            if (is_string($param)) {
                $param = array('longUrl' => $param);
            }

            if (!isset($param['longUrl'])) {
                throw new FTV_Exception('longUrl is need to shorten it.');
            }

            $url = self::URL_SHORTEN;

            $result = $this->_callApi($url, $param);

            return $result['url'];
        }

        public function expand($shortUrl)
        {
            throw new FTV_Exception('Not yet implemented');
        }

        public function clicks()
        {
            throw new FTV_Exception('Not yet implemented');
        }

        public function proDomain()
        {
            throw new FTV_Exception('Not yet implemented');
        }

        public function loookup()
        {
            throw new FTV_Exception('Not yet implemented');
        }

        public function authenticate()
        {
            throw new FTV_Exception('Not yet implemented');
        }

        public function info()
        {
            throw new FTV_Exception('Not yet implemented');
        }

        public function validate()
        {
            throw new FTV_Exception('Not yet implemented');
        }
    }
