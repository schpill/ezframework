<?php
    /**
     * String class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */

    class FTV_String
    {
        const UNDERSCORE = '[US]';

        /**
         * returns a randomly generated string
         * commonly used for password generation
         *
         * @param int $length
         * @return string
         */
        public static function random($length = 8)
        {
            // start with a blank string
            $string = '';

            // define possible characters
            $possible = '0123456789abcdfghjkmnpqrstvwxyz';

            // set up a counter
            $i = 0;

            // add random characters to $string until $length is reached
            while ($i < $length) {

                // pick a random character from the possible ones
                $char = substr($possible, mt_rand(0, strlen($possible) - 1), 1);

                // we don't want this character if it's already in the string
                if (!strstr($string, $char)) {
                    $string .= $char;
                    $i++;
                }

            }

            // done!
            return $string;
        }

        /**
         * replaces spaces with hyphens (used for urls)
         *
         * @param string $string
         * @return string
         */
        public static function addHyphens($string)
        {
            return repl(' ', '-', trim($string));
        }

        /**
         * replaces hypens with spaces
         *
         * @param string $string
         * @return string
         */
        public static function stripHyphens($string)
        {
            return repl('-', ' ', trim($string));
        }

        /**
         * replace empty spaces with underscores
         *
         * @param string $string
         * @return string
         */
        public static function replaceEmptySpace($string)
        {
            return repl(' ', '_', trim($string));
        }

        /**
         * replace underscores with empty spaces
         *
         * @param string $string
         * @return string
         */
        public static function replaceUnderscore($string)
        {
            return repl('_', ' ', trim($string));
        }

        /**
         * replace slashes with underscores
         *
         * @param string $string
         * @return string
         */
        public static function addUnderscores($string)
        {
            $string = repl('_', self::UNDERSCORE, $string);
            return repl('/', '_', trim($string));
        }

        /**
         * replaces underscores with slashes
         * if relative is true then return the path as relative
         *
         * @param string $string
         * @param bool $relative
         * @return string
         */
        public static function stripUnderscores($string, $relative = false)
        {
            $string = repl('_', '/', trim($string));
            if ($relative) {
                $string = self::stripLeading('/', $string);
            }
            $string = repl(self::UNDERSCORE, '_', $string);
            return $string;
        }

        /**
         * strips the leading $replace from the $string
         *
         * @param string $replace
         * @param string $string
         * @return string
         */
        public static function stripLeading($replace, $string)
        {
            if (substr($string, 0, strlen($replace)) == $replace) {
                return substr($string, strlen($replace));
            }
            return $string;
        }

        /**
         * returns the parent from the passed path
         *
         * @param string $path
         * @return string
         */
        public static function getParentFromPath($path)
        {
            $path = self::stripTrailingSlash($path);
            $parts = explode('/', $path);
            array_pop($parts);
            return implode('/', $parts);
        }

        /**
         * returns the current file from the path
         * this is a custom version of basename
         *
         * @param string $path
         * @return string
         */
        public static function getSelfFromPath($path)
        {
            $path = self::stripTrailingSlash($path);
            $parts = explode('/', $path);
            return array_pop($parts);
        }

        public static function truncateText($text, $count = 25, $stripTags = true)
        {
            if ($stripTags) {
                $filter = new Zend_Filter_StripTags();
                $text   = $filter->filter($text);
            }
            $words = split(' ', $text);
            $text  = (string)join(' ', array_slice($words, 0, $count));
            return $text;
        }

        public static function booleanise($string)
        {
            $string = strtolower($string);
            switch ($string) {
                case 'true':
                    return true;
                    break;
                case 'false':
                    return false;
                    break;
                default:
                    $int = (int)$string;
                    switch ($int) {
                        case 1:
                            return true;
                            break;
                        case 0:
                            return false;
                            break;
                        default:
                            return false;
                    }
            }
        }
    }
