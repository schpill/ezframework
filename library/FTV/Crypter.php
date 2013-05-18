<?php
    /**
     * Crypter class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Crypter 
    {
        public static $cipher = MCRYPT_RIJNDAEL_256;
        public static $mode = MCRYPT_MODE_CBC;
        public static $block = 32;
        
        public static function encrypt($value, $key = null)
        {
            $iv = mcrypt_create_iv(self::iv_size(), self::randomizer());

            $value = self::pad($value);

            $value = mcrypt_encrypt(self::$cipher, self::key($key), $value, self::$mode, $iv);

            return base64_encode($iv . $value);
        }

        public static function decrypt($value, $key = null)
        {
            $value = base64_decode($value);
            $iv = substr($value, 0, self::iv_size());
            $value = substr($value, self::iv_size());
            $key = self::key($key);
            $value = mcrypt_decrypt(self::$cipher, $key, $value, self::$mode, $iv);
            return self::unpad($value);
        }
        
        public static function randomizer()
        {
            if (defined('MCRYPT_DEV_URANDOM')) {
                return MCRYPT_DEV_URANDOM;
            } elseif (defined('MCRYPT_DEV_RANDOM')) {
                return MCRYPT_DEV_RANDOM;
            } else {
                mt_srand();
                return MCRYPT_RAND;
            }
        }

        protected static function iv_size()
        {
            return mcrypt_get_iv_size(self::$cipher, self::$mode);
        }

        protected static function pad($value)
        {
            $pad = self::$block - (FTV_Inflector::length($value) % self::$block);

            return $value .= str_repeat(chr($pad), $pad);
        }

        protected static function unpad($value)
        {
            $pad = ord($value[($length = FTV_Inflector::length($value)) - 1]);

            if ($pad && $pad < self::$block) {
                if (preg_match('/' . chr($pad) . '{' . $pad . '}$/', $value)) {
                    return substr($value, 0, $length - $pad);
                } else {
                    throw new FTV_Exception("Decryption error. Padding is invalid.");
                }
            }
            return $value;
        }

        protected static function key($key = null)
        {
            if (null === $key) {
                $key = FTV_Registry::get('FTVIdentityToken');
            }
            if (null === $key) {
                $key = FTV_Config::get('app.secretKey');
            }
            if (null === $key) {
                $key = FTV_Utils::token();
            }
            return $key
        }

    }
