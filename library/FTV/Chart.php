<?php
    /**
     * Chart class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */

    class FTV_Chart
    {
        private static $_first = true;
        private static $_count = 0;
       
        private $_chartType;
       
        private $_data;
        private $_dataType;
        private $_skipFirstRow;
       
        /**
         * sets the chart type and updates the chart counter
         */
        public function __construct($chartType, $skipFirstRow = false)
        {
            $this->_chartType = $chartType;
            $this->_skipFirstRow = $skipFirstRow;
            self::$_count++;
        }
       
        /**
         * loads the dataset and converts it to the correct format
         */
        public function load($data, $dataType = 'json')
        {
            $this->_data = ($dataType != 'json') ? $this->dataToJson($data) : $data;
        }
       
        /**
         * load jsapi
         */
        private function initChart()
        {
            self::$_first = false;
           
            $output = '';
            // start a code block
            $output .= '<script type="text/javascript" src="https://www.google.com/jsapi"></script>' . "\n";
            $output .= '<script type="text/javascript">google.load(\'visualization\', \'1.0\', {\'packages\':[\'corechart\']});</script>' . "\n";
           
            return $output;
        }
       
        /**
         * draws the chart
         */
       
        public function draw($div, Array $options = array())
        {
            $output = '';
           
            if(self::$_first) {
                $output .= $this->initChart();
            }
           
            // start a code block
            $output .= '<script type="text/javascript">' . "\n";

            // set callback function
            $output .= 'google.setOnLoadCallback(drawChart' . self::$_count . ');' . "\n";
           
            // create callback function
            $output .= 'function drawChart' . self::$_count . '() {' . "\n";
           
            $output .= 'var data = new google.visualization.DataTable(' . $this->_data . ');' . "\n";
           
            // set the options
            $output .= 'var options = ' . json_encode($options) . ';' . "\n";
           
            // create and draw the chart
            $output .= 'var chart = new google.visualization.' . $this->_chartType . '(document.getElementById(\'' . $div . '\'));' . "\n";
            $output .= 'chart.draw(data, options);' . "\n";
           
            $output .= '} </script>' . "\n";
            return $output;
        }
               
        /**
         * substracts the column names from the first and second row in the dataset
         */
        private function getColumns($data)
        {
            $cols = array();
            foreach($data[0] as $key => $value) {
                if(is_numeric($key)){
                    if(is_string($data[1][$key])) {
                        $cols[] = array('id' => '', 'label' => $value, 'type' => 'string');
                    } else {
                        $cols[] = array('id' => '', 'label' => $value, 'type' => 'number');
                    }
                    $this->_skipFirstRow = true;
                } else {
                    if(is_string($value)) {
                        $cols[] = array('id' => '', 'label' => $key, 'type' => 'string');
                    } else {
                        $cols[] = array('id' => '', 'label' => $key, 'type' => 'number');
                    }
                }
            }
            return $cols;
        }
       
        /**
         * convert array data to json
         */
        private function dataToJson($data)
        {
            $cols = $this->getColumns($data);
           
            $rows = array();
            foreach($data as $key => $row) {
                if($key != 0 || !$this->_skipFirstRow) {
                    $c = array();
                    foreach($row as $v) {
                        $c[] = array('v' => $v);
                    }
                    $rows[] = array('c' => $c);
                }
            }
            return json_encode(array('cols' => $cols, 'rows' => $rows));
        }
    }

    /* Exemple
     *
        $chart = new FTV_Chart('LineChart');

        $data = array(
            'cols' => array(
                array('id' => '', 'label' => 'Annee', 'type' => 'string'),
                array('id' => '', 'label' => 'Recettes', 'type' => 'number'),
                array('id' => '', 'label' => 'Revenus', 'type' => 'number')
            ),
            'rows' => array(
                array('c' => array(array('v' => '1990'), array('v' => 150), array('v' => 100))),
                array('c' => array(array('v' => '1995'), array('v' => 300), array('v' => 50))),
                array('c' => array(array('v' => '2000'), array('v' => 180), array('v' => 200))),
                array('c' => array(array('v' => '2005'), array('v' => 400), array('v' => 100))),
                array('c' => array(array('v' => '2010'), array('v' => 300), array('v' => 600))),
                array('c' => array(array('v' => '2015'), array('v' => 350), array('v' => 400)))
            )
        );
        $chart->load(json_encode($data));

        $options = array('title' => 'Revenus', 'theme' => 'maximized', 'width' => 500, 'height' => 200);
        echo $chart->draw('Revenus', $options);


        // demonstration of pie chart and simple array
        $chart = new FTV_Chart('PieChart');

        $data = array(
            array('champignons', 'slices'),
            array('oignons', 2),
            array('olives', 1),
            array('fromage', 4)
        );
        $chart->load($data, 'array');

        $options = array('title' => 'pizza', 'is3D' => true, 'width' => 500, 'height' => 400);
        echo $chart->draw('Pizza', $options);
        echo '<div id="Revenus"></div><div id="Pizza"></div>';
        exit;
     *
     * */
