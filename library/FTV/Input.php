<?php
    /**
     * Input class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Input
    {
        public static function all()
        {
            $input = array_merge($_POST, $_GET, $_FILES);
            return $input;
        }

        public static function get($key, $default = null)
        {
            return arrayGet(self::all(), $key, $default);
        }

        public static function hasFile($key)
        {
            return strlen(self::get("{$key}.tmp_name", "")) > 0;
        }

        public static function saveFile($file, $destination)
        {
            $input = self::get($file);
            $name = $input['tmp_name'];

            if (! is_dir($destination)) {
                @mkdir($destination, 0777, true);

                if (false === is_dir($destination)) {
                    throw new FTV_Exception('Impossible to create the destination directory.');
                }
            }

            $filename = array(
                u::get('backadmUser') . '_',
                time() . '_',
                $input['name']
            );

            $filename = implode('', $filename);

            $destination = $destination . DS;

            if(!move_uploaded_file($name, $destination . $filename)) {
                throw new FTV_Exception('Uploading error, the file could not be copied.');
            } else {
                return $destination . $filename;
            }
        }
    }
