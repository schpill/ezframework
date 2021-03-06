<?php
    /**
     * Log class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */

    class FTV_Log
    {
        static $_logFile;

        public function __construct($logFile = null)
        {
            if (null === $logFile) {
                self::$_logFile = APPLICATION_PATH . DS . 'logs' . DS . date('Y-m-d') . '.log';
            } else {
                self::$_logFile = $logFile;
            }
            if (false === FTV_File::exists(self::$_logFile)) {
                FTV_File::append(self::$_logFile, '');
            }
        }

        /**
         * Log an exception to the log file.
         *
         * @param  Exception  $e
         * @return void
         */
        public static function exception($e)
        {
            self::write('error', self::exceptionLine($e));
        }

        /**
         * Format a log friendly message from the given exception.
         *
         * @param  Exception  $e
         * @return string
         */
        protected static function exceptionLine($e)
        {
            return $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        }

        /**
         * Write a message to the log file.
         *
         * <code>
         *      // Write an "error" message to the log file
         *      FTV_Log::write('error', 'Something went horribly wrong!');
         *
         *      // Write an "error" message using the class' magic method
         *      FTV_Log::error('Something went horribly wrong!');
         *
         *      // Log an arrays data
         *      FTV_Log::write('info', array('name' => 'Sawny', 'passwd' => '1234', array(1337, 21, 0)), true);
         *      //Result: Array ( [name] => Sawny [passwd] => 1234 [0] => Array ( [0] => 1337 [1] => 21 [2] => 0 ) )
         *      //If we had omit the third parameter the result had been: Array
         * </code>
         *
         * @param  string  $type
         * @param  string  $message
         * @return void
         */
        public static function write($type, $message, $prettyPrint = false)
        {
            $message = (false !== $prettyPrint) ? print_r($message, true) : $message;

            u::run('FTV.log', array($type, $message));

            $message = self::format($type, $message);

            FTV_File::append(self::$_logFile, $message);
        }

        protected static function format($type, $message)
        {
            return date('Y-m-d H:i:s') . ' ' . i::upper($type) . " - {$message}". PHP_EOL;
        }

        /**
         * Dynamically write a log message.
         *
         * <code>
         *      // Write an "error" message to the log file
         *      FTV_Log::error('This is an error!');
         *
         *      // Write a "warning" message to the log file
         *      FTV_Log::warning('This is a warning!');
         *
         *      // Log an arrays data
         *      FTV_Log::info(array('name' => 'Sawny', 'passwd' => '1234', array(1337, 21, 0)), true);
         *      //Result: Array ( [name] => Sawny [passwd] => 1234 [0] => Array ( [0] => 1337 [1] => 21 [2] => 0 ) )
         *      //If we had omit the second parameter the result had been: Array
         * </code>
         */
        public static function __callStatic($method, $parameters)
        {
            $parameters[1] = (empty($parameters[1])) ? false : $parameters[1];

            self::write($method, $parameters[0], $parameters[1]);
        }

    }
