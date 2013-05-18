<?php 
    /**
     * Section class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Section
    {
        /**
         * All of the captured sections.
         *
         * @var array
         */
        public static $sections = array();

        /**
         * The last section on which injection was started.
         *
         * @var array
         */
        public static $last = array();

        /**
         * Start injecting content into a section.
         *
         * <code>
         *      // Start injecting into the "header" section
         *      FTV_Section::start('header');
         *
         *      // Inject a raw string into the "header" section without buffering
         *      FTV_Section::start('header', '<title>Laravel</title>');
         * </code>
         *
         * @param  string          $section
         * @param  string|Closure  $content
         * @return void
         */
        public static function start($section, $content = '')
        {
            if ($content === '') {
                ob_start() && self::$last[] = $section;
            } else {
                self::extend($section, $content);
            }
        }

        /**
         * Inject inline content into a section.
         *
         * This is helpful for injecting simple strings such as page titles.
         *
         * <code>
         *      // Inject inline content into the "header" section
         *      FTV_Section::inject('header', '<title>Laravel</title>');
         * </code>
         *
         * @param  string  $section
         * @param  string  $content
         * @return void
         */
        public static function inject($section, $content)
        {
            self::start($section, $content);
        }

        /**
         * Stop injecting content into a section and return its contents.
         *
         * @return string
         */
        public static function yield_section()
        {
            return self::yield(self::stop());
        }

        /**
         * Stop injecting content into a section.
         *
         * @return string
         */
        public static function stop()
        {
            static::extend($last = array_pop(self::$last), ob_get_clean());

            return $last;
        }

        /**
         * Extend the content in a given section.
         *
         * @param  string  $section
         * @param  string  $content
         * @return void
         */
        protected static function extend($section, $content)
        {
            if (array_key_exists($section, self::$sections)) {
                self::$sections[$section] = str_replace('@parent', $content, self::$sections[$section]);
            } else {
                self::$sections[$section] = $content;
            }
        }

        /**
         * Append content to a given section.
         *
         * @param  string  $section
         * @param  string  $content
         * @return void
         */
        public static function append($section, $content)
        {
            if (array_key_exists($section, self::$sections)) {
                self::$sections[$section] .= $content;
            } else {
                self::$sections[$section] = $content;
            }
        }

        /**
         * Get the string contents of a section.
         *
         * @param  string  $section
         * @return string
         */
        public static function yield($section)
        {
            return (array_key_exists($section, self::$sections)) ? self::$sections[$section] : '';
        }

    }
