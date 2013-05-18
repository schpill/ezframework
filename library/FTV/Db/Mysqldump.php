<?php
    /**
     * Dump MySQL DB class
     *
     * @package         FTV
     * @subpackage      Db
     * @author          Gerald Plusquellec
     */

    class FTV_Db_Mysqldump
    {

        // This can be set both on counstruct or manually 
        public $host                = 'localhost', $user = '', $pass = '', $db = '';
        public $filename            = 'dump.sql';

        // Usable switch 
        public $dropTableIfExists   = false;

        // Internal stuff
        private $tables             = array();
        private $dbHandler;
        private $fileHandler;

        /**
         * Constructor of MySQLDump
         * 
         * @param string $db        Database name
         * @param string $user      MySQL account username
         * @param string $pass      MySQL account password
         * @param string $host      MySQL server to connect to
         * @return null
         */
        public function __construct($db = '', $user = '', $pass = '', $host = 'localhost')
        {
            $this->db = $db; 
            $this->user = $user; 
            $this->pass = $pass; 
            $this->host = $host;
        }

        /**
         * Main call
         * 
         * @param string $filename  Name of file to write sql dump to     
         * @return bool
         */
        public function start($filename = '')
        {
            // Output file can be redefined here
            if(!empty($filename)) {
                $this->filename = $filename;
            }
            // We must set a name to continue
            if(empty($this->filename)) {
                throw new FTV_Exception("Output file name is not set.");
            }
            // Trying to bind a file with block
            $this->fileHandler = fopen($this->filename, "wb");
            if($this->fileHandler === false) {
                throw new FTV_Exception("Output file is not writable.");
            }
            // Connecting with MySQL
            try {
                $this->dbHandler = new PDO("mysql:dbname={$this->db};host={$this->host}", $this->user, $this->pass);
            } catch (PDOException $e) {
                throw new FTV_Exception("Connection to MySQL failed with message: " . $e->getMessage());
            }
            // Fix for always-unicode output 
            $this->dbHandler->exec("SET NAMES utf8");        
            // Formating dump file
            $this->writeHeader();
            // Listing all tables from database
            $this->tables = array();
            foreach ($this->dbHandler->query("SHOW TABLES") as $row) {
                array_push($this->tables, current($row));
            }
            // Exporting tables one by one 
            foreach($this->tables as $table) {
                $this->write("----------------------------------------------------------\n\n");
                $this->getTableStructure($table);
                $this->listValues($table);
            }
            // Releasing file
            return fclose($this->fileHandler);
        }
        
        /**
         * Output routine
         * 
         * @param string $string  SQL to write to dump file
         * @return bool
         */    
        private function write($string) 
        {
            if(fwrite($this->fileHandler, $string) === false) {
                throw new FTV_Exception("Writting to file failed ! Probably, there is no more free space left ?");
            }
        }

        /**
         * Writting header for dump file
         * 
         * @return null
         */   
        private function writeHeader()
        {
            // Some info about software, source and time
            $this->write("-- FTV SQL Dump\n");
            $this->write("--\n");
            $this->write("-- Host: {$this->host}\n");
            $this->write("-- Generation Time: " . date('r') . "\n\n");   
            $this->write("--\n");
            $this->write("-- Database: `{$this->db}`\n");
            $this->write("--\n\n");        
        }

        /**
         * Table structure extractor
         * 
         * @param string $tablename  Name of table to export
         * @return null
         */    
        private function getTableStructure($tablename)
        {
            $this->write( "--\n-- Table structure for table `$tablename`\n--\n\n" );
            if (true === $this->dropTableIfExists) {
               $this->write( "DROP TABLE IF EXISTS `$tablename`;\n\n" );
            }
            foreach ($this->dbHandler->query("SHOW CREATE TABLE `$tablename`") as $row) {
               $this->write( $row['Create Table'].";\n\n" );
            }
        }

        /**
         * Table rows extractor
         * 
         * @param string $tablename  Name of table to export
         * @return null
         */   
        private function listValues($tablename)
        {
            $this->write("--\n-- Dumping data for table `$tablename`\n--\n\n");
            foreach ($this->dbHandler->query("SELECT * FROM `$tablename`", PDO::FETCH_NUM) as $row) {
                $vals = array();
                foreach($row as $val) {
                    $vals[] = $this->dbHandler->quote($val);
                }
                $this->write("INSERT INTO `$tablename` VALUES(" . implode(", ", $vals) . ");\n");
            }
            $this->write("\n");
        }
    }
