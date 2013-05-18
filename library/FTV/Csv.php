<?php
    class FTV_Csv
    {
        public $filename;
        public $collection;
        public $delimiter;

        /**
         * Loads objects, filename and optionnaly a delimiter.
         * @param Collection $collection collection of objects / array (of non-objects)
         * @param string $filename : used later to save the file
         * @param string $delimiter Optional : delimiter used
         */
        public function __construct($collection, $filename, $delimiter = ';')
        {
            $this->filename = $filename;
            $this->delimiter = $delimiter;
            $this->collection = $collection;
        }

        /**
         * Main function
         * Adds headers
         * Outputs
         */
        public function export()
        {
            $this->headers();

            $header_line = false;

            foreach ($this->collection as $object) {
                $vars = get_object_vars($object);
                if (!$headerLine) {
                    $this->output(array_keys($vars));
                    $headerLine = true;
                }

                // outputs values
                $this->output($vars);
                unset($vars);
            }
        }

        /**
         * Wraps data and echoes
         * Uses defined delimiter
         */
        public function output($data)
        {
            $wrapedData = array_map(array('FTV_Csv', 'wrap'), $data);
            echo sprintf("%s\n", implode($this->delimiter, $wrapedData));
        }

        /**
         * Escapes data
         * @param string $data
         * @return string $data
         */
        public static function wrap($data)
        {
            $data = self::_safeOutput($data, '";');
            return sprintf('"%s"', $data);
        }

        /**
         * Adds headers
         */
        public function headers()
        {
            header('Content-type: text/csv');
            header('Content-Type: application/force-download; charset=UTF-8');
            header('Cache-Control: no-store, no-cache');
            header('Content-disposition: attachment; filename="'.$this->filename.'.csv"');
        }

        private static function _safeOutput($string, $html = false)
        {
            if (false === $html) {
                $string = strip_tags($string);
            }
            return @self::_htmlentitiesUTF8($string, ENT_QUOTES);
        }

        private static function _htmlentitiesUTF8($string, $type = ENT_QUOTES)
        {
            if (is_array($string)) {
                return array_map(array('FTV_Csv', '_htmlentitiesUTF8'), $string);
            }
            return htmlentities((string)$string, $type, 'utf-8');
        }
    }

