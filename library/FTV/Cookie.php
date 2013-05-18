<?php 
    /**
     * Cookie class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Cookie
    {

        /**
         * @var  string  Magic salt to add to the cookie
         */
        public static $salt = null;

        /**
         * @var  integer  Number of seconds before the cookie expires
         */
        public static $expiration = 0;

        /**
         * @var  string  Restrict the path that the cookie is available to
         */
        public static $path = '/';

        /**
         * @var  string  Restrict the domain that the cookie is available to
         */
        public static $domain = null;

        /**
         * @var  boolean  Only transmit cookies over secure connections
         */
        public static $secure = false;

        /**
         * @var  boolean  Only transmit cookies over HTTP, disabling Javascript access
         */
        public static $httponly = false;

        /**
         * Gets the value of a signed cookie. Cookies without signatures will not
         * be returned. If the cookie signature is present, but invalid, the cookie
         * will be deleted.
         *
         *     // Get the "theme" cookie, or use "blue" if the cookie does not exist
         *     $theme = FTV_Cookie::get('theme', 'blue');
         *
         * @param   string  $key        cookie name
         * @param   mixed   $default    default value to return
         * @return  string
         */
        public static function get($key, $default = null)
        {
            if ( ! ake($key, $_COOKIE)) {
                // The cookie does not exist
                return $default;
            }

            // Get the cookie value
            $cookie = $_COOKIE[$key];

            // Find the position of the split between salt and contents
            $split = strlen(self::salt($key, null));

            if (isset($cookie[$split]) && $cookie[$split] === '~') {
                // Separate the salt and the value
                list ($hash, $value) = explode('~', $cookie, 2);

                if (FTV_Cookie::salt($key, $value) === $hash) {
                    // Cookie signature is valid
                    return $value;
                }

                // The cookie signature is invalid, delete it
                FTV_Cookie::delete($key);
            }

            return $default;
        }

        /**
         * Sets a signed cookie. Note that all cookie values must be strings and no
         * automatic serialization will be performed!
         *
         *     // Set the "theme" cookie
         *     FTV_Cookie::set('theme', 'red');
         *
         * @param   string  $name       name of cookie
         * @param   string  $value      value of cookie
         * @param   integer $expiration lifetime in seconds
         * @return  boolean
         * @uses    FTV_Cookie::salt
         */
        public static function set($name, $value, $expiration = null)
        {
            if (null === $expiration) {
                // Use the default expiration
                $expiration = FTV_Cookie::$expiration;
            }

            if ($expiration !== 0) {
                // The expiration is expected to be a UNIX timestamp
                $expiration += time();
            }

            // Add the salt to the cookie value
            $value = self::salt($name, $value) . '~' . $value;

            return setcookie($name, $value, $expiration, self::$path, self::$domain, self::$secure, self::$httponly);
        }

        /**
         * Deletes a cookie by making the value null and expiring it.
         *
         *     FTV_Cookie::delete('theme');
         *
         * @param   string  $name   cookie name
         * @return  boolean
         * @uses    FTV_Cookie::set
         */
        public static function delete($name)
        {
            // Remove the cookie
            unset($_COOKIE[$name]);

            // nullify the cookie and make it expire
            return setcookie($name, null, -86400, self::$path, self::$domain, self::$secure, self::$httponly);
        }

        /**
         * Generates a salt string for a cookie based on the name and value.
         *
         *     $salt = FTV_Cookie::salt('theme', 'red');
         *
         * @param   string  $name   name of cookie
         * @param   string  $value  value of cookie
         * @return  string
         */
        public static function salt($name, $value)
        {
            // Require a valid salt
            if ( ! FTV_Cookie::$salt)
            {
                throw new FTV_Exception('A valid cookie salt is required. Please set FTV_Cookie::$salt.');
            }

            // Determine the user agent
            $agent = isset($_SERVER['HTTP_USER_AGENT']) ? i::lower($_SERVER['HTTP_USER_AGENT']) : 'unknown';

            return sha1($agent . $name . $value . self::$salt);
        }

        /**
        * Parse cookie header
        *
        * This method will parse the HTTP requst's `Cookie` header
        * and extract cookies into an associative array.
        *
        * @param string
        * @return array
        */
        public static function parseHeader($header)
        {
            $cookies = array();
            $header = rtrim($header, "\r\n");
            $headerPieces = preg_split('@\s*[;,]\s*@', $header);
            foreach ($headerPieces as $c) {
                $cParts = explode('=', $c);
                if (count($cParts) === 2) {
                    $key = urldecode($cParts[0]);
                    $value = urldecode($cParts[1]);
                    if (!isset($cookies[$key])) {
                        $cookies[$key] = $value;
                    }
                }
            }

            return $cookies;
        }

    }
