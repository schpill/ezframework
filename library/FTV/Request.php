<?php
    /**
     * Request class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Request
    {
        const METHOD_HEAD       = 'HEAD';
        const METHOD_GET        = 'GET';
        const METHOD_POST       = 'POST';
        const METHOD_PUT        = 'PUT';
        const METHOD_DELETE     = 'DELETE';
        const METHOD_OPTIONS    = 'OPTIONS';
        const METHOD_OVERRIDE   = '_METHOD';

        /**
         * @var array
         */
        protected static $formDataMediaTypes = array('application/x-www-form-urlencoded');

        /**
         * @var array
         */
        protected $env;
        protected $body = null;
        static $defaultInputStream = null;
        
        /**
         * Constructor
         * @param array $env
         */
        public function __construct(array $env = array())
        {
            if (!count($env)) {
                $env = array_merge($_SERVER, $_ENV, $_GET, $_POST, $_COOKIE, $_SESSION);
            }
            $this->env = $env;
        }

        /**
         * Get HTTP method
         * @return string
         */
        public function getMethod()
        {
            return $this->env['REQUEST_METHOD'];
        }

        /**
         * Is this a GET request?
         * @return bool
         */
        public function isGet()
        {
            return $this->getMethod() === self::METHOD_GET;
        }

        /**
         * Is this a POST request?
         * @return bool
         */
        public function isPost()
        {
            return $this->getMethod() === self::METHOD_POST;
        }

        /**
         * Is this a PUT request?
         * @return bool
         */
        public function isPut()
        {
            return $this->getMethod() === self::METHOD_PUT;
        }

        /**
         * Is this a DELETE request?
         * @return bool
         */
        public function isDelete()
        {
            return $this->getMethod() === self::METHOD_DELETE;
        }

        /**
         * Is this a HEAD request?
         * @return bool
         */
        public function isHead()
        {
            return $this->getMethod() === self::METHOD_HEAD;
        }

        /**
         * Is this a OPTIONS request?
         * @return bool
         */
        public function isOptions()
        {
            return $this->getMethod() === self::METHOD_OPTIONS;
        }

        /**
         * Is this an AJAX request?
         * @return bool
         */
        public function isAjax()
        {
            if ($this->params('isajax')) {
                return true;
            } elseif (isset($this->env['X_REQUESTED_WITH']) && $this->env['X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                return true;
            } else {
                return false;
            }
        }

        /**
         * @return bool
         */
        public function isXhr()
        {
            return $this->isAjax();
        }

        /**
         * Fetch GET and POST data
         *
         * This method returns a union of GET and POST data as a key-value array, or the value
         * of the array key if requested; if the array key does not exist, NULL is returned.
         *
         * @param  string           $key
         * @return array|mixed|null
         */
        public function params($key = null)
        {
            $union = array_merge($this->get(), $this->post());
            if ($key) {
                if (isset($union[$key])) {
                    return $union[$key];
                } else {
                    return null;
                }
            } else {
                return $union;
            }
        }

        /**
         * Fetch GET data
         *
         * This method returns a key-value array of data sent in the HTTP request query string, or
         * the value of the array key if requested; if the array key does not exist, null is returned.
         *
         * @param  string           $key
         * @return array|mixed|null
         */
        public function get($key = null)
        {
            if (!isset($this->env['FTV.request.query_hash'])) {
                $output = array();
                if (function_exists('mb_parse_str') && !isset($this->env['FTV.tests.ignore_multibyte'])) {
                    mb_parse_str($this->env['QUERY_STRING'], $output);
                } else {
                    parse_str($this->env['QUERY_STRING'], $output);
                }
                $this->env['FTV.request.query_hash'] = u::stripSlashesIfMagicQuotes($output);
            }
            if ($key) {
                if (isset($this->env['FTV.request.query_hash'][$key])) {
                    return $this->env['FTV.request.query_hash'][$key];
                } else {
                    return null;
                }
            } else {
                return $this->env['FTV.request.query_hash'];
            }
        }

        /**
         * Fetch POST data
         *
         * This method returns a key-value array of data sent in the HTTP request body, or
         * the value of a hash key if requested; if the array key does not exist, NULL is returned.
         *
         * @param  string           $key
         * @return array|mixed|null
         * @throws \RuntimeException If environment input is not available
         */
        public function post($key = null)
        {
            if (!isset($this->env['FTV.input'])) {
                throw new FTV_Exception('Missing FTV.input in environment variables');
            }
            if (!isset($this->env['FTV.request.form_hash'])) {
                $this->env['FTV.request.form_hash'] = array();
                if ($this->isFormData() && is_string($this->env['FTV.input'])) {
                    $output = array();
                    if (function_exists('mb_parse_str') && !isset($this->env['FTV.tests.ignore_multibyte'])) {
                        mb_parse_str($this->env['FTV.input'], $output);
                    } else {
                        parse_str($this->env['FTV.input'], $output);
                    }
                    $this->env['FTV.request.form_hash'] = u::stripSlashesIfMagicQuotes($output);
                } else {
                    $this->env['FTV.request.form_hash'] = u::stripSlashesIfMagicQuotes($_POST);
                }
            }
            if ($key) {
                if (isset($this->env['FTV.request.form_hash'][$key])) {
                    return $this->env['FTV.request.form_hash'][$key];
                } else {
                    return null;
                }
            } else {
                return $this->env['FTV.request.form_hash'];
            }
        }

        /**
         * @param  string           $key
         * @return array|mixed|null
         */
        public function put($key = null)
        {
            return $this->post($key);
        }

        /**
         * @param  string           $key
         * @return array|mixed|null
         */
        public function delete($key = null)
        {
            return $this->post($key);
        }

        /**
         * Fetch COOKIE data
         *
         * This method returns a key-value array of Cookie data sent in the HTTP request, or
         * the value of a array key if requested; if the array key does not exist, NULL is returned.
         *
         * @param  string            $key
         * @return array|string|null
         */
        public function cookies($key = null)
        {
            if (!isset($this->env['FTV.request.cookie_hash'])) {
                $cookieHeader = isset($this->env['COOKIE']) ? $this->env['COOKIE'] : '';
                $this->env['FTV.request.cookie_hash'] = FTV_Cookie::parseHeader($cookieHeader);
            }
            if ($key) {
                if (isset($this->env['FTV.request.cookie_hash'][$key])) {
                    return $this->env['FTV.request.cookie_hash'][$key];
                } else {
                    return null;
                }
            } else {
                return $this->env['FTV.request.cookie_hash'];
            }
        }

        /**
         * Does the Request body contain parseable form data?
         * @return bool
         */
        public function isFormData()
        {
            $method = isset($this->env['FTV.method_override.original_method']) ? $this->env['FTV.method_override.original_method'] : $this->getMethod();

            return ($method === self::METHOD_POST && is_null($this->getContentType())) || in_array($this->getMediaType(), self::$formDataMediaTypes);
        }

        /**
         * Get Headers
         *
         * This method returns a key-value array of headers sent in the HTTP request, or
         * the value of a hash key if requested; if the array key does not exist, NULL is returned.
         *
         * @param  string $key
         * @param  mixed  $default The default value returned if the requested header is not available
         * @return mixed
         */
        public function headers($key = null, $default = null)
        {
            if (null !== $key) {
                $key = i::upper($key);
                $key = str_replace('-', '_', $key);
                $key = preg_replace('@^HTTP_@', '', $key);
                if (isset($this->env[$key])) {
                    return $this->env[$key];
                } else {
                    return $default;
                }
            } else {
                $headers = array();
                foreach ($this->env as $key => $value) {
                    if (strpos($key, 'FTV.') !== 0) {
                        $headers[$key] = $value;
                    }
                }

                return $headers;
            }
        }

        /**
         * Get Body
         * @return string
         */
        public function getBody($asString = false)
        {
            if (is_null($this->body)) {
                if (!is_null(self::$defaultInputStream)) {
                    $this->body = self::$defaultInputStream;
                } else {
                    $this->body = fopen('php://input','r');
                    self::$defaultInputStream = $this->body;
                }
            }
            if (false !== $asString) {
                $body = stream_get_contents($this->body);
                return $body;
            } else {
                return $this->body;
            }
        }

        /**
         * Get Content Type
         * @return string
         */
        public function getContentType()
        {
            if (isset($this->env['CONTENT_TYPE'])) {
                return $this->env['CONTENT_TYPE'];
            } else {
                return null;
            }
        }

        /**
         * Get Media Type (type/subtype within Content Type header)
         * @return string|null
         */
        public function getMediaType()
        {
            $contentType = $this->getContentType();
            if ($contentType) {
                $contentTypeParts = preg_split('/\s*[;,]\s*/', $contentType);

                return i::lower($contentTypeParts[0]);
            } else {
                return null;
            }
        }

        /**
         * Get Media Type Params
         * @return array
         */
        public function getMediaTypeParams()
        {
            $contentType = $this->getContentType();
            $contentTypeParams = array();
            if ($contentType) {
                $contentTypeParts = preg_split('/\s*[;,]\s*/', $contentType);
                $contentTypePartsLength = count($contentTypeParts);
                for ($i = 1 ; $i < $contentTypePartsLength ; $i++) {
                    $paramParts = explode('=', $contentTypeParts[$i]);
                    $contentTypeParams[i::lower($paramParts[0])] = $paramParts[1];
                }
            }

            return $contentTypeParams;
        }

        /**
         * Get Content Charset
         * @return string|null
         */
        public function getContentCharset()
        {
            $mediaTypeParams = $this->getMediaTypeParams();
            if (isset($mediaTypeParams['charset'])) {
                return $mediaTypeParams['charset'];
            } else {
                return null;
            }
        }

        /**
         * Get Content-Length
         * @return int
         */
        public function getContentLength()
        {
            if (isset($this->env['CONTENT_LENGTH'])) {
                return (int) $this->env['CONTENT_LENGTH'];
            } else {
                return 0;
            }
        }

        /**
         * Get Host
         * @return string
         */
        public function getHost()
        {
            if (isset($this->env['HOST'])) {
                if (strpos($this->env['HOST'], ':') !== false) {
                    $hostParts = explode(':', $this->env['HOST']);

                    return $hostParts[0];
                }

                return $this->env['HOST'];
            } else {
                return $this->env['SERVER_NAME'];
            }
        }

        /**
         * Get Host with Port
         * @return string
         */
        public function getHostWithPort()
        {
            return sprintf('%s:%s', $this->getHost(), $this->getPort());
        }

        /**
         * Get Port
         * @return int
         */
        public function getPort()
        {
            return (int) $this->env['SERVER_PORT'];
        }

        /**
         * Get Scheme (https or http)
         * @return string
         */
        public function getScheme()
        {
            return $this->env['FTV.url_scheme'];
        }

        /**
         * Get Script Name (physical path)
         * @return string
         */
        public function getScriptName()
        {
            return $this->env['SCRIPT_NAME'];
        }

        /**
         * @return string
         */
        public function getRootUri()
        {
            return $this->getScriptName();
        }

        /**
         * Get Path (physical path + virtual path)
         * @return string
         */
        public function getPath()
        {
            return $this->getScriptName() . $this->getPathInfo();
        }

        /**
         * Get Path Info (virtual path)
         * @return string
         */
        public function getPathInfo()
        {
            return $this->env['PATH_INFO'];
        }

        /**
         * @return string
         */
        public function getResourceUri()
        {
            return $this->getPathInfo();
        }

        /**
         * Get URL (scheme + host [ + port if non-standard ])
         * @return string
         */
        public function getUrl()
        {
            $url = $this->getScheme() . '://' . $this->getHost();
            if (($this->getScheme() === 'https' && $this->getPort() !== 443) || ($this->getScheme() === 'http' && $this->getPort() !== 80)) {
                $url .= sprintf(':%s', $this->getPort());
            }

            return $url;
        }

        /**
         * Get IP
         * @return string
         */
        public function getIp()
        {
            if (isset($this->env['X_FORWARDED_FOR'])) {
                return $this->env['X_FORWARDED_FOR'];
            } elseif (isset($this->env['CLIENT_IP'])) {
                return $this->env['CLIENT_IP'];
            }

            return $this->env['REMOTE_ADDR'];
        }

        /**
         * Get Referrer
         * @return string|null
         */
        public function getReferrer()
        {
            if (isset($this->env['REFERER'])) {
                return $this->env['REFERER'];
            } else {
                return null;
            }
        }

        /**
         * Get Referer (for those who can't spell)
         * @return string|null
         */
        public function getReferer()
        {
            return $this->getReferrer();
        }

        /**
         * Get User Agent
         * @return string|null
         */
        public function getUserAgent()
        {
            if (isset($this->env['USER_AGENT'])) {
                return $this->env['USER_AGENT'];
            } else {
                return null;
            }
        }

        public function __call($func, $argv)
        {
            $continue = false;
            if (substr($func, 0, 4) == 'find') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($func, 4)));
                $continue = true;
            } elseif (substr($func, 0, 3) == 'get') {
                $uncamelizeMethod = i::uncamelize(lcfirst(substr($func, 3)));
                $continue = true;
            }
            if (true === $continue) {
                $var = i::lower($uncamelizeMethod);
                $value = searchInArray($var, $this->env);
                return $value;
            }
        }
    }
