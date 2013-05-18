<?php
    /**
     * Upload class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Upload implements ArrayAccess, Iterator, Countable
    {
        /**
         * @var  array  Container for uploaded file objects
         */
        protected $container = array();

        /**
         * @var  int  index pointer for Iterator
         */
        protected $index = 0;

        /**
         * @var  array  Default configuration values
         */
        protected $defaults = array(
            // global settings
            'auto_process'    => false,
            'langCallback'    => null,
            'moveCallback'    => null,
            // validation settings
            'max_size'        => 0,
            'max_length'      => 0,
            'ext_whitelist'   => array(),
            'ext_blacklist'   => array(),
            'type_whitelist'  => array(),
            'type_blacklist'  => array(),
            'mime_whitelist'  => array(),
            'mime_blacklist'  => array(),
            // file settings
            'prefix'          => '',
            'suffix'          => '',
            'extension'       => '',
            'randomize'       => false,
            'normalize'       => false,
            'normalize_separator' => '_',
            'change_case'     => false,
            // save-to-disk settings
            'path'            => '',
            'create_path'     => true,
            'path_chmod'      => '0777',
            'file_chmod'      => '0666',
            'auto_rename'     => true,
            'overwrite'       => false,
        );

        /**
         * @var  array  Container for callbacks
         */
        protected $callbacks = array(
            'before_validation' => array(),
            'after_validation' => array(),
            'before_save' => array(),
            'after_save' => array(),
        );

        /**
         * Constructor
         *
         * @param  array|null  $config  Optional array of configuration items
         */
        public function __construct(array $config = null)
        {
            // override defaults if needed
            if (is_array($config))  {
                foreach ($config as $key => $value) {
                    array_key_exists($key, $this->defaults) and $this->defaults[$key] = $value;
                }
            }

            // we can't do anything without any files uploaded
            if (empty($_FILES)) {
                throw new FTV_Exception('No uploaded files were found. Did you specify "enctype" in your &lt;form&gt; tag?');
            }

            // process the data in the $_FILES array
            $this->processFiles();

            // if auto-process was active, run validation on all file objects
            if ($this->defaults['auto_process']) {
                // and validate it
                $this->validate();
            }
        }

        /**
         * Run save on all loaded file objects
         *
         * @param  int|string|array  $selection  Optional array index, element name or array with filter values
         *
         * @return void
         */
        public function save($selection = null)
        {
            // prepare the selection
            if (func_num_args()) {
                if (is_array($selection)) {
                    $filter = array();

                    foreach ($this->container as $file) {
                        $match = true;
                        foreach($selection as $item => $value) {
                            if ($value != $file->{$item}) {
                                $match = false;
                                break;
                            }
                        }

                        $match && $filter[] = $file;
                    }

                    $selection = $filter;
                }
                else {
                    $selection =  (array) $this[$selection];
                }
            } else {
                $selection = $this->container;
            }

            // loop through all selected files
            foreach ($selection as $file) {
                $file->save();
            }
        }

        /**
         * Run validation on all selected file objects
         *
         * @param  int|string|array  $selection  Optional array index, element name or array with filter values
         *
         * @return void
         */
        public function validate($selection = null)
        {
            // prepare the selection
            if (func_num_args()) {
                if (is_array($selection)) {
                    $filter = array();

                    foreach ($this->container as $file) {
                        $match = true;
                        foreach($selection as $item => $value) {
                            if ($value != $file->{$item}) {
                                $match = false;
                                break;
                            }
                        }

                        $match and $filter[] = $file;
                    }

                    $selection = $filter;
                } else {
                    $selection =  (array) $this[$index];
                }
            } else {
                $selection = $this->container;
            }

            // loop through all selected files
            foreach ($selection as $file)  {
                $file->validate();
            }
        }

        /**
         * Return a consolidated status of all uploaded files
         *
         * @return bool
         */
        public function isValid()
        {
            // loop through all files
            foreach ($this->container as $file) {
                // return false at the first non-valid file
                if ( ! $file->isValid())  {
                    return false;
                }
            }

            // only return true if there are uploaded files, and they are all valid
            return empty($this->container) ? false : true;
        }

        /**
         * Return the list of uploaded files
         *
         * @param  int|string  $index  Optional array index or element name
         *
         * @return array
         */
        public function getAllFiles($index = null)
        {
            // return the selection
            return (func_num_args() && ! is_null($index)) ? (array) $this[$index] : $this->container;
        }

        /**
         * Return the list of uploaded files that valid
         *
         * @param  int|string  $index  Optional array index or element name
         *
         * @return array
         */
        public function getValidFiles($index = null)
        {
            // prepare the selection
            $selection =  (func_num_args() && ! is_null($index)) ? (array) $this[$index] : $this->container;

            // storage for the results
            $results = array();

            // loop through all files
            foreach ($selection as $file) {
                // store only files that are valid
                $file->isValid() and $results[] = $file;
            }

            // return the results
            return $results;
        }

        /**
         * Return the list of uploaded files that invalid
         *
         * @param  int|string  $index  Optional array index or element name
         *
         * @return array
         */
        public function getInvalidFiles($index = null)
        {
            // prepare the selection
            $selection =  (func_num_args() && ! is_null($index)) ? (array) $this[$index] : $this->container;

            // storage for the results
            $results = array();

            // loop through all files
            foreach ($selection as $file) {
                // store only files that are invalid
                $file->isValid() or $results[] = $file;
            }

            // return the results
            return $results;
        }

        /**
         * Registers a Callback for a given event
         *
         * @param  string  $event  The type of the event
         * @param  mixed  $callback  Any valid callback, must accept a File object
         *
         * @return  void
         */
        public static function register($event, $callback)
        {
            // check if this is a valid event type
            if (array_key_exists($event, $this->callbacks)) {
                // check if the callback is acually callable
                if (is_callable($callback)) {
                    // store it
                    $this->callbacks[$event][] = $callback;
                } else {
                    throw new InvalidArgumentException('Callback passed is not callable');
                }
            } else {
                throw new InvalidArgumentException($event.' is not a valid event');
            }
        }

        /**
         * Set the configuration for this file
         *
         * @param  $name  string|array  name of the configuration item to set, or an array of configuration items
         * @param  $value mixed  if $name is an item name, this holds the configuration values for that item
         *
         * @return  void
         */
        public function setConfig($item, $value = null)
        {
            // unify the parameters
            is_array($item) or $item = array($item => $value);

            // update the configuration
            foreach ($item as $name => $value) {
                // is this a valid config item? then update the defaults
                array_key_exists($name, $this->defaults) && $this->defaults[$name] = $value;
            }

            // and push it to all file objects in the containers
            foreach ($this->container as $file) {
                $file->setConfig($item);
            }
        }

        /**
         * Process the data in the $_FILES array, unify it, and create File objects for them
         */
        protected function processFiles()
        {
            // normalize the multidimensional fields in the $_FILES array
            foreach($_FILES as $name => $file) {
                // was it defined as an array?
                if (is_array($file['name'])) {
                    $data = $this->unifyFile($name, $file);
                    foreach ($data as $entry) {
                        $this->addFile($entry);
                    }
                } else {
                    // normal form element, just create a File object for this uploaded file
                    $this->addFile(array_merge(array('element' => $name), $file));
                }
            }
        }

        /**
         * Convert the silly different $_FILE structures to a flattened array
         *
         * @param  string  $name  key name of the file
         * @param  array   $file  $_FILE array structure
         *
         * @return  array  unified array file uploaded files
         */
        protected function unifyFile($name, $file)
        {
            // storage for results
            $data = array();

            // loop over the file array
            foreach ($file['name'] as $key => $value) {
                // we're not an the end of the element name nesting yet
                if (is_array($value)) {
                    // recurse with the array data we have at this point
                    $data = array_merge(
                        $data,
                        $this->unifyFile($name.'.'.$key,
                            array(
                                'name'     => $file['name'][$key],
                                'type'     => $file['type'][$key],
                                'tmp_name' => $file['tmp_name'][$key],
                                'error'    => $file['error'][$key],
                                'size'     => $file['size'][$key],
                            )
                        )
                    );
                } else {
                    $data[] = array(
                        'element'  => $name.'.'.$key,
                        'name'     => $file['name'][$key],
                        'type'     => $file['type'][$key],
                        'tmp_name' => $file['tmp_name'][$key],
                        'error'    => $file['error'][$key],
                        'size'     => $file['size'][$key],
                    );
                }
            }

            return $data;
        }

        /**
         * Add a new uploaded file structure to the container
         *
         * @param  array  $entry  uploaded file structure
         */
        protected function addFile(array $entry)
        {
            // add the new file object to the container
            $file = new uploadFile($entry, $this->callbacks);
            $file->isValid = true;
            $this->container[] = $file;

            // and load it with a default config
            end($this->container)->setConfig($this->defaults);
        }

        //------------------------------------------------------------------------------------------------------------------

        /**
         * Countable methods
         */
        public function count()
        {
            return count($this->container);
        }

        /**
         * ArrayAccess methods
         */
        public function offsetExists($offset)
        {
            return isset($this->container[$offset]);
        }

        public function offsetGet($offset)
        {
            // if the requested key is alphanumeric, do a search on element name
            if (is_string($offset)) {
                // if it's in form notation, convert it to dot notation
                $offset = str_replace(array('][', '[', ']'), array('.', '.', ''), $offset);

                // see if we can find this element or elements
                $found = array();
                foreach($this->container as $key => $file) {
                    if (strpos($file->element, $offset) === 0)
                    {
                        $found[] = $this->container[$key];
                    }
                }

                if ( ! empty($found)) {
                    return $found;
                }
            }

            // else check on numeric offset
            elseif (isset($this->container[$offset]))  {
                return $this->container[$offset];
            }

            // not found
            return null;
        }

        public function offsetSet($offset, $value)
        {
            throw new FTV_Exception('An Upload Files instance is read-only, its contents can not be altered');
        }

        public function offsetUnset($offset)
        {
            throw new FTV_Exception('An Upload Files instance is read-only, its contents can not be altered');
        }

        /**
         * Iterator methods
         */
        function rewind()
        {
            $this->index = 0;
        }

        function current()
        {
            return $this->container[$this->index];
        }

        function key()
        {
            return $this->index;
        }

        function next()
        {
            $this->index++;
        }

        function valid()
        {
            return isset($this->container[$this->index]);
        }
    }

    class uploadFile implements ArrayAccess, Iterator, Countable
    {
        const UPLOAD_ERR_MAX_SIZE             = 101;
        const UPLOAD_ERR_EXT_BLACKLISTED      = 102;
        const UPLOAD_ERR_EXT_NOT_WHITELISTED  = 103;
        const UPLOAD_ERR_TYPE_BLACKLISTED     = 104;
        const UPLOAD_ERR_TYPE_NOT_WHITELISTED = 105;
        const UPLOAD_ERR_MIME_BLACKLISTED     = 106;
        const UPLOAD_ERR_MIME_NOT_WHITELISTED = 107;
        const UPLOAD_ERR_MAX_FILENAME_LENGTH  = 108;
        const UPLOAD_ERR_MOVE_FAILED          = 109;
        const UPLOAD_ERR_DUPLICATE_FILE       = 110;
        const UPLOAD_ERR_MKDIR_FAILED         = 111;
        const UPLOAD_ERR_EXTERNAL_MOVE_FAILED = 112;

        /**
         * @var  array  Container for uploaded file objects
         */
        protected $container = array();

        /**
         * @var  int  index pointer for Iterator
         */
        protected $index = 0;

        /**
         * @var  array  Container for validation errors
         */
        protected $errors = array();

        /**
         * @var  array  Configuration values
         */
        protected $config = array(
            'langCallback'    => null,
            'moveCallback'    => null,
            // validation settings
            'max_size'        => 0,
            'max_length'      => 0,
            'ext_whitelist'   => array(),
            'ext_blacklist'   => array(),
            'type_whitelist'  => array(),
            'type_blacklist'  => array(),
            'mime_whitelist'  => array(),
            'mime_blacklist'  => array(),
            // file settings
            'prefix'          => '',
            'suffix'          => '',
            'extension'       => '',
            'randomize'       => false,
            'normalize'       => false,
            'normalize_separator' => '_',
            'change_case'     => false,
            // save-to-disk settings
            'path'            => '',
            'create_path'     => true,
            'path_chmod'      => '0777',
            'file_chmod'      => '0666',
            'auto_rename'     => true,
            'overwrite'       => false,
        );

        /**
         * @var  bool  Flag to indicate if validation has run on this object
         */
        protected $isValidated = false;

        /**
         * @var  bool  Flag to indicate the result of the validation run
         */
        protected $isValid = true;

        /**
         * @var  array  Container for callbacks
         */
        protected $callbacks = array();

        /**
         * Constructor
         *
         * @param  array  $file  Array with unified information about the file uploaded
         */
        public function __construct(array $file, &$callbacks = array())
        {
            // store the file data for this file
            $this->container = $file;

            // the file callbacks reference
            $this->callbacks =& $callbacks;
        }

        /**
         * Magic getter, gives read access to all elements in the file container
         *
         * @param  string  $name  name of the container item to get
         *
         * @return  mixed  value of the item, or null if the item does not exist
         */
        public function __get($name)
        {
            $name = i::lower($name);
            return isset($this->container[$name]) ? $this->container[$name] : null;
        }

        /**
         * Magic setter, gives write access to all elements in the file container
         *
         * @param  string  $name  name of the container item to set
         * @param  mixed  $value  value to set it to
         */
        public function __set($name, $value)
        {
            $name = i::lower($name);
            isset($this->container[$name]) and $this->container[$name] = $value;
        }

        /**
         * Return the validation state of this object
         *
         * @return  bool
         */
        public function isValidated()
        {
            return $this->isValidated;
        }

        /**
         * Return the state of this object
         *
         * @return  bool
         */
        public function isValid()
        {
            return $this->isValid;
        }

        /**
         * Return the error objects collected for this file upload
         *
         * @return  array
         */
        public function getErrors()
        {
            return $this->isValidated ? $this->errors : array();
        }

        /**
         * Set the configuration for this file
         *
         * @param  $name  string|array  name of the configuration item to set, or an array of configuration items
         * @param  $value mixed  if $name is an item name, this holds the configuration values for that item
         *
         * @return  void
         */
        public function setConfig($item, $value = null)
        {
            // unify the parameters
            is_array($item) or $item = array($item => $value);

            // update the configuration
            foreach ($item as $name => $value) {
                array_key_exists($name, $this->config) && $this->config[$name] = $value;
            }
        }

        /**
         * Run validation on the uploaded file, based on the config being loaded
         *
         * @return  bool
         */
        public function validate()
        {
            // reset the error container
            $this->errors = array();

            // validation starts, call the pre-validation callback
            $this->runCallbacks('before_validation');

            // was the upload of the file a success?
            if ($this->container['error'] == 0) {
                // add some filename details (pathinfo can't be trusted with utf-8 filenames!)
                $this->container['extension'] = ltrim(strrchr(ltrim($this->container['name'], '.'), '.'),'.');
                if (empty($this->container['extension']))  {
                    $this->container['filename'] = $this->container['name'];
                } else {
                    $this->container['filename'] = substr($this->container['name'], 0, strlen($this->container['name'])-(strlen($this->container['extension'])+1));
                }

                // does this upload exceed the maximum size?
                if ( ! empty($this->config['max_size']) and is_numeric($this->config['max_size']) and $this->container['size'] > $this->config['max_size']) {
                    $this->addError(self::UPLOAD_ERR_MAX_SIZE);
                }

                // add mimetype information
                try {
                    $handle = finfo_open(FILEINFO_MIME_TYPE);
                    $this->container['mimetype'] = finfo_file($handle, $this->container['tmp_name']);
                    finfo_close($handle);
                } catch (\ErrorException $e) {
                    $this->container['mimetype'] = false;
                    $this->addError(UPLOAD_ERR_NO_FILE);
                }

                // always use the more specific of the mime types available
                if ($this->container['mimetype'] == 'application/octet-stream' and $this->container['type'] != $this->container['mimetype']) {
                    $this->container['mimetype'] = $this->container['type'];
                }

                // make sure it contains something valid
                if (empty($this->container['mimetype']) or strpos($this->container['mimetype'], '/') === false) {
                    $this->container['mimetype'] = 'application/octet-stream';
                }

                // split the mimetype info so we can run some tests
                preg_match('|^(.*)/(.*)|', $this->container['mimetype'], $mimeinfo);

                // check the file extension black- and whitelists
                if (in_array(strtolower($this->container['extension']), (array) $this->config['ext_blacklist']))  {
                    $this->addError(self::UPLOAD_ERR_EXT_BLACKLISTED);
                } elseif ( ! empty($this->config['ext_whitelist']) and ! in_array(strtolower($this->container['extension']), (array) $this->config['ext_whitelist'])) {
                    $this->addError(self::UPLOAD_ERR_EXT_NOT_WHITELISTED);
                }

                // check the file type black- and whitelists
                if (in_array($mimeinfo[1], (array) $this->config['type_blacklist'])) {
                    $this->addError(self::UPLOAD_ERR_TYPE_BLACKLISTED);
                }
                if ( ! empty($this->config['type_whitelist']) and ! in_array($mimeinfo[1], (array) $this->config['type_whitelist'])) {
                    $this->addError(self::UPLOAD_ERR_TYPE_NOT_WHITELISTED);
                }

                // check the file mimetype black- and whitelists
                if (in_array($this->container['mimetype'], (array) $this->config['mime_blacklist'])) {
                    $this->addError(self::UPLOAD_ERR_MIME_BLACKLISTED);
                } elseif ( ! empty($this->config['mime_whitelist']) and ! in_array($this->container['mimetype'], (array) $this->config['mime_whitelist'])) {
                    $this->addError(self::UPLOAD_ERR_MIME_NOT_WHITELISTED);
                }

                // update the status of this validation
                $this->isValid = empty($this->errors);

                // validation finished, call the post-validation callback
                $this->runCallbacks('after_validation');
            } else {
                // upload was already a failure, store the corresponding error
                $this->addError($this->container['error']);

                // and mark this validation a failure
                $this->isValid = false;
            }

            // set the flag to indicate we ran the validation
            $this->isValidated = true;

            // return the validation state
            return $this->isValid;
        }

        /**
         * Save the uploaded file
         *
         * @return  bool
         */
        public function save()
        {
            if ($this->isValid) {
                // make sure we have a valid path
                if (empty($this->container['path'])) {
                    $this->container['path'] = rtrim($this->config['path'], DS) . DS;
                }
                if (false === is_dir($this->container['path']) && (bool) $this->config['create_path']) {
                    mkdir($this->container['path'], $this->config['path_chmod'], true);

                    if ( ! is_dir($this->container['path'])) {
                        throw new FTV_Exception('Can\'t save the uploaded file. Destination path specified does not exist.');
                    }
                }

                // was a new name for the file given?
                if (false === array_key_exists('filename', $this->container)) {
                    // do we need to generate a random filename?
                    if ( (bool) $this->config['randomize'])  {
                        $this->container['filename'] = md5(serialize($this->container));
                    } else {
                        $this->container['filename']  = $this->container['name'];
                        (bool) $this->config['normalize'] && $this->normalize();
                    }
                }
                // array with all filename components
                $filename = array(
                    $this->config['prefix'],
                    u::get('backadmUser') . '_',
                    time() . '_',
                    $this->container['filename'],
                    '',
                    '.',
                    empty($this->config['extension']) ? $this->container['extension'] : $this->config['extension']
                );

                // remove the dot if no extension is present
                empty($filename[6]) && $filename[5] = '';

                //~ dieDump($this->container['filename']);
                // if we're saving the file locally
                if (false === $this->config['moveCallback']) {
                    // check if the file already exists
                    if (file_exists($this->container['path'] . implode('', $filename))) {
                        if ((bool) $this->config['auto_rename']) {
                            $counter = 0;
                            do {
                                $filename[1] = '_' . $counter++;
                            } while (file_exists($this->container['path'] . implode('', $filename)));
                        } else {
                            if ( ! (bool) $this->config['overwrite']) {
                                throw new FTV_Exception(self::UPLOAD_ERR_DUPLICATE_FILE);
                            }
                        }
                    }
                }

                // no need to store it as an array anymore
                $this->container['filename'] = implode('', $filename);

                // does the filename exceed the maximum length?
                if ( ! empty($this->config['max_length']) && strlen($this->container['filename']) > $this->config['max_length']) {
                    throw new FTV_Exception(self::UPLOAD_ERR_MAX_FILENAME_LENGTH);
                }

                // update the status of this validation
                $this->isValid = empty($this->errors);

                // if the file is still valid, run the before save callbacks
                if ($this->isValid) {

                    // recheck the path, it might have been altered by a callback
                    if ($this->isValid && ! is_dir($this->container['path']) && (bool) $this->config['create_path']) {
                        @mkdir($this->container['path'], $this->config['path_chmod'], true);

                        if ( ! is_dir($this->container['path'])) {
                            throw new FTV_Exception(self::UPLOAD_ERR_MKDIR_FAILED);
                        }
                    }
                }

                // if the file is still valid, move it
                if ($this->isValid) {
                    // check if file should be moved to an ftp server
                    if ($this->config['moveCallback']) {
                        if (!$moved) {
                            throw new FTV_Exception(self::UPLOAD_ERR_EXTERNAL_MOVE_FAILED);
                        }
                    } else {
                        if(!move_uploaded_file($this->container['tmp_name'], $this->container['path'] . $this->container['filename'])) {
                            throw new FTV_Exception(self::UPLOAD_ERR_MOVE_FAILED);
                        } else {
                            $setterPath = $this->container['element'] . 'UploadPath';
                            $setterStatus = $this->container['element'] . 'UploadStatus';
                            u::set($setterPath, $this->container['path'] . $this->container['filename']);
                            chmod($this->container['path'] . $this->container['filename'], (int) $this->config['file_chmod']);
                        }
                    }
                }

                // validation starts, call the post-save callback
                if ($this->isValid) {
                    $this->runCallbacks('after_save');
                }
            }
            // return the status of this operation
            return empty($this->errors);
        }

        /**
         * Run callbacks of he defined type
         */
        protected function runCallbacks($type)
        {
            // make sure we have callbacks of this type
            if (array_key_exists($type, $this->callbacks)) {
                // run the defined callbacks
                foreach ($this->callbacks[$type] as $callback) {
                    // check if the callback is valid
                    if (is_callable($callback)) {
                        // call the defined callback
                        $result = call_user_func($callback, $this);

                        // and process the results. we need FileError instances only
                        foreach ((array) $result as $entry) {
                            if (is_object($entry) && $entry instanceof uploadFileError) {
                                $this->errors[] = $entry;
                            }
                        }

                        // update the status of this validation
                        $this->isValid = empty($this->errors);
                    }
                }
            }
        }

        /**
         * Convert a filename into a normalized name. only outputs 7 bit ASCII characters.
         */
        protected function normalize()
        {
            // Decode all entities to their simpler forms
            $this->container['filename'] = html_entity_decode($this->container['filename'], ENT_QUOTES, 'UTF-8');

            // Remove all quotes
            $this->container['filename'] = preg_replace("#[\"\']#", '', $this->container['filename']);

            // Strip unwanted characters
            $this->container['filename'] = preg_replace("#[^a-z0-9]#i", $this->config['normalize_separator'], $this->container['filename']);
            $this->container['filename'] = preg_replace("#[/_|+ -]+#u", $this->config['normalize_separator'], $this->container['filename']);
            $this->container['filename'] = trim($this->container['filename'], $this->config['normalize_separator']);
        }

        /**
         * Add a new error object to the list
         *
         * @param  array  $entry  uploaded file structure
         */
        protected function addError($error)
        {
            $this->errors[] = new uploadFileError($error, $this->config['langCallback']);
        }

        //------------------------------------------------------------------------------------------------------------------

        /**
         * Countable methods
         */
        public function count()
        {
            return count($this->container);
        }

        /**
         * ArrayAccess methods
         */
        public function offsetExists($offset)
        {
            return isset($this->container[$offset]);
        }

        public function offsetGet($offset)
        {
            return $this->container[$offset];
        }

        public function offsetSet($offset, $value)
        {
            $this->container[$offset] = $value;
        }

        public function offsetUnset($offset)
        {
            throw new FTV_Exception('You can not unset a data element of an Upload File instance');
        }

        /**
         * Iterator methods
         */
        function rewind()
        {
            return reset($this->container);
        }

        function current()
        {
            return current($this->container);
        }

        function key()
        {
            return key($this->container);
        }

        function next()
        {
            return next($this->container);
        }

        function valid()
        {
            return key($this->container) !== null;
        }
    }

    class uploadFileError
    {
        /**
        * @var array Default error messages
        */
        protected $messages = array(
            0 => 'The file uploaded with success',
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'No file was uploaded',
            6 => 'Configured temporary upload folder is missing',
            7 => 'Failed to write uploaded file to disk',
            8 => 'Upload blocked by an installed PHP extension',
            101 => 'The uploaded file exceeds the defined maximum size',
            102 => 'Upload of files with this extension is not allowed',
            103 => 'Upload of files with this extension is not allowed',
            104 => 'Upload of files of this file type is not allowed',
            105 => 'Upload of files of this file type is not allowed',
            106 => 'Upload of files of this mime type is not allowed',
            107 => 'Upload of files of this mime type is not allowed',
            108 => 'The uploaded file name exceeds the defined maximum length',
            109 => 'Unable to move the uploaded file to it\'s final destination',
            110 => 'A file with the name of the uploaded file already exists',
            111 => 'Unable to create the file\'s destination directory',
            112 => 'Unable to upload the file to the destination using FTP',
        );

        /**
        * @var int Current error number
        */
        protected $error = 0;

        /**
        * @var string Current error message
        */
        protected $message = '';

        /**
        * Constructor
        *
        * @param int $error Number of the error message
        */
        public function __construct($error, $langCallback = null)
        {
            $this->error = $error;

            if (is_callable($langCallback)) {
                $this->message = call_user_func($langCallback, $error);
            }

            if (empty($this->message)) {
                $this->message = isset($this->messages[$error]) ? $this->messages[$error] : 'Unknown error message number: '.$error;
            }
        }

        /**
        * Return the error code
        *
        * @return int The error code set
        */
        public function getError()
        {
            return $this->error;
        }

        /**
        * Return the error message
        *
        * @return int The error message set
        */
        public function getMessage()
        {
            return $this->message;
        }

        /**
        * __toString magic method, will output the stored error message
        */
        public function __toString()
        {
            return $this->getMessage();
        }
    }

