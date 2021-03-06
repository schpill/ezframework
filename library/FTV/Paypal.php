<?php
    /**
     * Paypal class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */

    class FTV_Paypal
    {

        /**
         * Magic method for handling API methods.
         *
         * @since   PHP 5.3
         * @param   string  $method
         * @param   array   $args
         * @return  array
         */
        public static function __callStatic($method, $args)
        {
            // if production mode...
            if (Config::get('paypal.production_mode') === true) {
                // use production credentials
                $credentials = Config::get('paypal.production');
                // use production endpoint
                $endpoint = 'https://api-3t.paypal.com/nvp';
            } else {
                // use sandbox credentials
                $credentials = Config::get('paypal.sandbox');

                // use sandbox endpoint
                $endpoint = 'https://api-3t.sandbox.paypal.com/nvp';
            }

            // build credentials
            $params = array(
                'VERSION' => '74.0',
                'USER' => $credentials['username'],
                'PWD' => $credentials['password'],
                'SIGNATURE' => $credentials['signature'],
                'METHOD' => i::camelize($method),
            );

            // build post data
            $fields = http_build_query($params + $args[0]);

            // curl request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            $response = curl_exec($ch);

            // if errors...
            if (curl_errno($ch)) {
                #$errors = curl_error($ch);
                curl_close($ch);

                // return false
                return false;
            } else {
                curl_close($ch);

                // return array
                parse_str($response, $result);
                return $result;
            }
        }

        /**
         * Automatically verify Paypal IPN communications.
         *
         * @return  boolean
         */
        public static function ipn()
        {
            // only accept post data
            if (false === u::isPost()) {
                return false;
            }

            // if production mode...
            if (Config::get('paypal.production_mode')) {
                // use production endpoint
                $endpoint = 'https://www.paypal.com/cgi-bin/webscr';
            } else {
                // use sandbox endpoint
                $endpoint = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
            }

            // build response
            $fields = http_build_query(array('cmd' => '_notify-validate') + FTV_Input::all());

            // curl request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // if errors...
            if (curl_errno($ch)) {
                #$errors = curl_error($ch);
                curl_close($ch);

                // return false
                return false;
            } else
            {
                // close connection
                curl_close($ch);

                // if success...
                if ($code === 200 and $response === 'VERIFIED') {
                    return true;
                } else {
                    return false;
                }
            }
        }
    }
