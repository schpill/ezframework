<?php
    /**
     * Crud class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Crud
    {
        private $_em;
        public static $dataTypes = array(
            'numeric' => array(
                'tinyint', 'bool', 'smallint', 'int',
                'numeric', 'int4', 'integer', 'mediumint', 'bigint',
                'decimal', 'float', 'double'
            ),
            'text' => array(
                'char', 'bpchar', 'varchar',
                'smalltext', 'text', 'mediumtext', 'longtext'
            ),
            'time' => array('date', 'datetime', 'timestamp')
        );

        public function __construct($em)
        {
            $this->_em = $em;
        }

        public function create($data)
        {
            if (is_object($data)) {
                $data = (array) $data;
            }

            if (!is_array($data)) {
                throw new FTV_Exception("You must provide an array to create a row.");
            }

            return $this->_em->create($data);
        }

        public function read($id)
        {
            $row = $this->_em->find($id)->toArray();
            return $row;
        }

        public function update($id, $data)
        {
            $row = $this->_em->find($id);

            if (is_object($data)) {
                $data = (array) $data;
            }

            if (!is_array($data)) {
                throw new FTV_Exception("You must provide an array to update a row.");
            }

            return $row->create($data);

        }

        public function delete($id)
        {
            return $this->_em->find($id)->delete();
        }

        public static function displayBoolNum($boolNum)
        {
            if (1 == $boolNum) {
                return 'Oui';
            } elseif (0 == $boolNum) {
                return 'Non';
            }
            return '';
        }

        public static function closure($code)
        {
            $file = APPLICATION_PATH . DS . 'cache' . DS . sha1($code) . '.php';
            $code = '<?php ' . NL . 'echo ' . $code . ';' . NL;
            FTV_File::put($file, $code);
            ob_start();
            include $file;
            $content = ob_get_contents();
            ob_end_clean();
            FTV_File::delete($file);
            return $content;
        }

        public static function internalFunction($function)
        {
            return @eval('return ' . $function . ';');
        }

        public static function pagination(FTV_Paginator $paginator)
        {
            $view = new FTV_View;
            $view->paginator = $paginator;
            $tpl = APPLICATION_PATH . DS . 'modules' . DS . 'Crud' . DS . 'views' . DS . 'scripts' . DS . 'partials' . DS . 'pagination' . DS . 'paginator.phtml';
            $view->render($tpl, false);
            return render($tpl);
        }

        public static function checkEmpty($field)
        {
            $getter = 'get' . i::camelize($field);
            $request = request();
            $value = $request->$getter();
            return (empty($value)) ? '' : $value;
        }

        public static function defaultConfig($em)
        {
            $config = array();
            $config['fields'] = array();
            foreach ($em->fieldsSave() as $field) {
                $config['fields'][$field] = array(
                    'label'         => ucwords(repl('_', ' ', $field)),
                    'content'       => '',
                    'contentSearch' => '',
                    'sortable'      => true,
                    'searchable'    => true,
                    'required'      => false,
                    'onList'        => true,
                    'onExport'      => true,
                    'onView'        => true,
                    'options'       => null,
                    'fieldType'     => 'text',
                );
            }

            $baseConfig = array(
                'addable'                   => true,
                'editable'                  => true,
                'deletable'                 => true,
                'duplicable'                => true,
                'viewable'                  => true,
                'pagination'                => true,
                'titleList'                 => 'Liste de '. $em->_getTable(),
                'titleAdd'                  => 'Ajouter un enregistrement',
                'titleEdit'                 => 'Mettre à jour un enregistrement',
                'titleDelete'               => 'Supprimer un enregistrement',
                'titleView'                 => 'Afficher un enregistrement',
                'noResultMessage'           => 'Aucun enregistrement à afficher',
                'itemsByPage'               => 20,
                'search'                    => true,
                'order'                     => true,
                'defaultOrder'              => $em->pk(),
                'defaultOrderDirection'     => 'ASC',
                'export'                    => array('excel', 'csv', 'pdf', 'json'),
            );

            $config = $config + $baseConfig;

            return $config;
        }

        public static function getSelectFromVocabulary($key, array $vocabulary, $selectName = 'selectName')
        {
            $select = '<select id="' . $selectName . '">' . NL;
            $select .= '<option value="crudNothing">Choisir</option>' . NL;
            foreach ($vocabulary as $key => $value) {
                $select .= '<option value="' . $key . '">'. FTVHelper_Html::display($value) . '</option>' . NL;
            }
            $select .= '</select>' . NL;
            return $select;
        }

        public static function getDataFromKey($key, $model, $field, $order = null)
        {
            $array = array();
            $em = new $model;
            $emKey = $em->_getEmFromKey($key);

            $fields = $emKey->fields();

            $array[''] = 'Choisir';
            if (is_array($field)) {
                $data = $emKey->order($order)->fetch()->select();
            } else {
                $data = $emKey->order($field)->fetch()->select();
            }
            if (null !== $data) {
                foreach ($data as $row) {
                    if (!is_array($field)) {
                        $getter = 'get' . i::camelize($field);
                        $value = $row->$getter();
                    } else {
                        $value = array();
                        foreach ($field as $tmpField) {
                            if (!strstr($tmpField, '%%')) {
                                if (in_array($tmpField, $fields)) {
                                    $getter = 'get' . i::camelize($tmpField);
                                    array_push($value, $row->$getter());
                                } else {
                                    array_push($value, $tmpField);
                                }
                            } else {
                                list($tmpField, $fn) = explode('%%', $tmpField, 2);
                                array_push($value, $row->$tmpField()->$fn());
                            }
                        }
                        $value = implode(' ', $value);
                    }
                    $array[$row->getId()] = FTVHelper_Html::display($value);
                }
            }
            return $array;
        }

        public static function getSelectFromKey($key, $model, $field, $selectName = 'selectName', $order = null)
        {
            $select = '<select id="' . $selectName . '">' . NL;
            $select .= '<option value="crudNothing">Choisir</option>' . NL;
            $em = new $model;
            $emKey = $em->_getEmFromKey($key);

            $fields = $emKey->fields();

            if (is_array($field)) {
                $data = $emKey->order($order)->fetch()->select();
            } else {
                $data = $emKey->order($field)->fetch()->select();
            }
            if (null !== $data) {
                foreach ($data as $row) {
                    if (!is_array($field)) {
                        $getter = 'get' . i::camelize($field);
                        $value = $row->$getter();
                    } else {
                        $value = array();
                        foreach ($field as $tmpField) {
                            if (!strstr($tmpField, '%%')) {
                                if (in_array($tmpField, $fields)) {
                                    $getter = 'get' . i::camelize($tmpField);
                                    array_push($value, $row->$getter());
                                } else {
                                    array_push($value, $tmpField);
                                }
                            } else {
                                list($tmpField, $fn) = explode('%%', $tmpField, 2);
                                array_push($value, $row->$tmpField()->$fn());
                            }
                        }
                        $value = implode(' ', $value);
                    }
                    $select .= '<option value="' . $row->getId() . '">'. FTVHelper_Html::display($value) . '</option>' . NL;
                }
            }
            $select .= '</select>' . NL;
            return $select;
        }

        public static function makeQueryDisplay($queryJs, $em)
        {
            $config  = config::get('crud.' . get_class($em) . '.info');
            if (null === $config) {
                $config = FTV_Crud::defaultConfig($em);
            }

            $fields = $config['fields'];

            $queryJs = substr($queryJs, 9, -2);
            $query = repl('##', ' AND ', $queryJs);
            $query = repl('%%', ' ', $query);

            $query = repl('NOT LIKE', 'ne contient pas', $query);
            $query = repl('LIKESTART', 'commence par', $query);
            $query = repl('LIKEEND', 'finit par', $query);
            $query = repl('LIKE', 'contient', $query);
            $query = repl('%', '', $query);

            foreach ($fields as $field => $fieldInfos) {
                if (strstr($query, $field)) {
                    if (strlen($fieldInfos['content'])) {
                        $seg = u::cut($field, " '", $query);
                        $segs = explode(" '", $query);
                        for ($i = 0 ; $i < count($segs) ; $i++) {
                            $seg = trim($segs[$i]);
                            if (strstr($seg, $field)) {
                                $goodSeg = trim($segs[$i + 1]);
                                list($oldValue, $dummy) = explode("'", $goodSeg, 2);
                                $content = repl(array('##self##', '##em##', '##field##'), array($oldValue, $em, $field), $fieldInfos['content']);
                                $value = FTVHelper_Html::display(FTV_Crud::internalFunction($content));
                                $newSeg = repl("$oldValue'", "$value'", $goodSeg);
                                $query = repl($goodSeg, $newSeg, $query);
                            }
                        }
                    }
                    $query = repl($field, i::lower($fieldInfos['label']), $query);
                }
            }
            $query = repl('=', 'vaut', $query);
            $query = repl('<', 'plus petit que', $query);
            $query = repl('>', 'plus grand que', $query);
            $query = repl('>=', 'plus grand ou vaut', $query);
            $query = repl('<=', 'plus petit ou vaut', $query);
            $query = repl(' AND ', ' et ', $query);
            $query = repl(" '", ' <span style="color: #ffdd00;">', $query);
            $query = repl("'", '</span>', $query);

            return $query;
        }

        public static function makeQuery($queryJs, $em)
        {
            $queryJs = substr($queryJs, 9, -2);

            $prefix = $em->_getDbName() . '.' . $em->_getTable() . '.';

            $query = repl('##', ' AND ' . $prefix, $prefix . $queryJs);
            $query = repl('%%', ' ', $query);
            $query = repl('LIKESTART', 'LIKE', $query);
            $query = repl('LIKEEND', 'LIKE', $query);
            return $query;
        }

        public static function exportExcel($data, $em)
        {
            $config  = config::get('crud.' . get_class($em) . '.info');
            if (null === $config) {
                $config = FTV_Crud::defaultConfig($em);
            }

            $fields = $config['fields'];

            $excel = '<html xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns="http://www.w3.org/TR/REC-html40">

        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <meta name="ProgId" content="Excel.Sheet">
            <meta name="Generator" content="Microsoft Excel 11">
            <style id="Classeur1_17373_Styles">
            <!--table
                {mso-displayed-decimal-separator:"\,";
                mso-displayed-thousand-separator:" ";}
            .xl1517373
                {padding-top:1px;
                padding-right:1px;
                padding-left:1px;
                mso-ignore:padding;
                color:windowtext;
                font-size:10.0pt;
                font-weight:400;
                font-style:normal;
                text-decoration:none;
                font-family:Arial;
                mso-generic-font-family:auto;
                mso-font-charset:0;
                mso-number-format:General;
                text-align:general;
                vertical-align:bottom;
                mso-background-source:auto;
                mso-pattern:auto;
                white-space:nowrap;}
            .xl2217373
                {padding-top:1px;
                padding-right:1px;
                padding-left:1px;
                mso-ignore:padding;
                color:#FFFF99;
                font-size:10.0pt;
                font-weight:700;
                font-style:normal;
                text-decoration:none;
                font-family:Arial, sans-serif;
                mso-font-charset:0;
                mso-number-format:General;
                text-align:center;
                vertical-align:bottom;
                background:#003366;
                mso-pattern:auto none;
                white-space:nowrap;}
            -->
            </style>
        </head>

            <body>
            <!--[if !excel]>&nbsp;&nbsp;<![endif]-->

            <div id="Classeur1_17373" align="center" x:publishsource="Excel">

            <table x:str border="0" cellpadding="0" cellspacing="0" width=640 style="border-collapse:
             collapse; table-layout: fixed; width: 480pt">
             <col width="80" span=8 style="width: 60pt">
             <tr height="17" style="height:12.75pt">
              ##headers##
             </tr>
             ##content##
            </table>
            </div>
        </body>
    </html>';
            $tplHeader = '<td class="xl2217373">##value##</td>';
            $tplData = '<td>##value##</td>';

            $headers = array();

            foreach ($fields as $field => $fieldInfos) {
                if (true === $fieldInfos['onExport']) {
                    $label = $fieldInfos['label'];
                    $headers[] = FTVHelper_Html::display($label);
                }
            }
            $xlsHeader = '';
            foreach ($headers as $header) {
                $xlsHeader .= repl('##value##', $header, $tplHeader);
            }
            $excel = repl('##headers##', $xlsHeader, $excel);

            $xlsContent = '';
            foreach ($data as $item) {
                $xlsContent .= '<tr>';
                foreach ($fields as $field => $fieldInfos) {
                    if (true === $fieldInfos['onExport']) {
                        $content = $fieldInfos['content'];
                        $getter = 'get' . i::camelize($field);
                        $value = $item->$getter();
                        if (strstr($content, '##self##') || strstr($content, '##em##')) {
                            $content = repl(array('##self##', '##em##', '##field##'), array($value, $em, $field), $content);
                            $value = FTV_Crud::internalFunction($content);
                        }
                        if (empty($value)) {
                            $value = '&nbsp;';
                        }
                        $xlsContent .= repl('##value##', FTVHelper_Html::display($value), $tplData);
                    }
                }
                $xlsContent .= '</tr>';
            }

            $excel = repl('##content##', $xlsContent, $excel);
            header ("Content-type: application/excel");
            header ('Content-disposition: attachement; filename="extraction_' . $em->_getTable() . '_' . date('d_m_Y_H_i_s') . '.xls"');
            header("Content-Transfer-Encoding: binary");
            header("Expires: 0");
            header("Cache-Control: no-cache, must-revalidate");
            header("Pragma: no-cache");
            die($excel);
        }

        public static function exportJson($data, $em)
        {
            $config  = config::get('crud.' . get_class($em) . '.info');
            if (null === $config) {
                $config = FTV_Crud::defaultConfig($em);
            }

            $fields = $config['fields'];

            $array = array();

            $i = 0;

            foreach ($data as $item) {
                foreach ($fields as $field => $fieldInfos) {
                    if (true === $fieldInfos['onExport']) {
                        $content = $fieldInfos['content'];
                        $getter = 'get' . i::camelize($field);
                        $value = $item->$getter();
                        if (strstr($content, '##self##') || strstr($content, '##em##')) {
                            $content = repl(array('##self##', '##em##', '##field##'), array($value, $em, $field), $content);
                            $value = FTV_Crud::internalFunction($content);
                        }
                        if (empty($value)) {
                            $value = null;
                        }
                        $array[$i][$fieldInfos['label']] = FTVHelper_Html::display($value);
                    }
                }
                $i++;
            }

            $json = json_encode($array);
            header('Content-disposition: attachment; filename=extraction_' . $em->_getTable() . '_' . date('d_m_Y_H_i_s') . '.json');
            header('Content-type: application/json');
            FTVHelper_Render::json($json);
            exit;

        }

        public static function exportCsv($data, $em)
        {
            $config  = config::get('crud.' . get_class($em) . '.info');
            if (null === $config) {
                $config = FTV_Crud::defaultConfig($em);
            }

            $fields = $config['fields'];

            $csv = '';

            foreach ($fields as $field => $fieldInfos) {
                if (true === $fieldInfos['onExport']) {
                    $label = $fieldInfos['label'];
                    $csv .= FTVHelper_Html::display($label) . ';';
                }
            }

            $csv = substr($csv, 0, -1);

            foreach ($data as $item) {
                $csv .= "\n";
                foreach ($fields as $field => $fieldInfos) {
                    if (true === $fieldInfos['onExport']) {
                        $content = $fieldInfos['content'];
                        $getter = 'get' . i::camelize($field);
                        $value = $item->$getter();
                        if (strstr($content, '##self##') || strstr($content, '##em##')) {
                            $content = repl(array('##self##', '##em##', '##field##'), array($value, $em, $field), $content);
                            $value = FTV_Crud::internalFunction($content);
                        }
                        if (empty($value)) {
                            $value = '';
                        }
                        $csv .= FTVHelper_Html::display($value) . ';';
                    }
                }
                $csv = substr($csv, 0, -1);
            }

            if (true === u::isUtf8($csv)) {
                $csv = utf8_decode($csv);
            }

            header ("Content-type: application/excel");
            header ('Content-disposition: attachement; filename="extraction_' . $em->_getTable() . '_' . date('d_m_Y_H_i_s') . '.csv"');
            header("Content-Transfer-Encoding: binary");
            header("Expires: 0");
            header("Cache-Control: no-cache, must-revalidate");
            header("Pragma: no-cache");
            die($csv);
        }

        public static function exportPdf($data, $em)
        {
            $config  = config::get('crud.' . get_class($em) . '.info');
            if (null === $config) {
                $config = FTV_Crud::defaultConfig($em);
            }

            $fields = $config['fields'];

            $pdf = '<html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <link href="//fonts.googleapis.com/css?family=Abel" rel="stylesheet" type="text/css" />
            <title>Extraction ' . $em->_getTable() . '</title>
            <style>
                *
                {
                    font-family: Abel, ubuntu, verdana, tahoma, arial, sans serif;
                    font-size: 11px;
                }
                h1
                {
                    text-transform: uppercase;
                    font-size: 135%;
                }
                th
                {
                    font-size: 120%;
                    color: #fff;
                    background-color: #394755;
                    text-transform: uppercase;
                }
                td
                {
                    border: solid 1px #394755;
                }

                a, a:visited, a:hover
                {
                    color: #000;
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <center><h1>Extraction &laquo ' . $em->_getTable() . ' &raquo;</h1></center>
            <p></p>
            <table width="100%" cellpadding="5" cellspacing="0" border="0">
            <tr>
                ##headers##
            </tr>
            ##content##
            </table>
            <p>&copy; AJF Finance 2012 - ' . date('Y') . ' </p>
        </body>
        </html>';
            $tplHeader = '<th>##value##</th>';
            $tplData = '<td>##value##</td>';

            $headers = array();

            foreach ($fields as $field => $fieldInfos) {
                if (true === $fieldInfos['onExport']) {
                    $label = $fieldInfos['label'];
                    $headers[] = FTVHelper_Html::display($label);
                }
            }
            $pdfHeader = '';
            foreach ($headers as $header) {
                $pdfHeader .= repl('##value##', $header, $tplHeader);
            }
            $pdf = repl('##headers##', $pdfHeader, $pdf);

            $pdfContent = '';
            foreach ($data as $item) {
                $pdfContent .= '<tr>';
                foreach ($fields as $field => $fieldInfos) {
                    if (true === $fieldInfos['onExport']) {
                        $content = $fieldInfos['content'];
                        $getter = 'get' . i::camelize($field);
                        $value = $item->$getter();
                        if (strstr($content, '##self##') || strstr($content, '##em##')) {
                            $content = repl(array('##self##', '##em##', '##field##'), array($value, $em, $field), $content);
                            $value = FTV_Crud::internalFunction($content);
                        }
                        if (empty($value)) {
                            $value = '&nbsp;';
                        }
                        $pdfContent .= repl('##value##', FTVHelper_Html::display($value), $tplData);
                    }
                }
                $pdfContent .= '</tr>';
            }

            $pdf = repl('##content##', $pdfContent, $pdf);
            return FTV_Pdf::make($pdf, "extraction_" . $em->_getTable() . "_" . date('d_m_Y_H_i_s'), false);
        }

        public function makeFormElement($field, $value, $fieldInfos, $em, $hidden = false)
        {
            if (true === $hidden) {
                return FTV_Form::hidden($field, $value, array('id' => $field));
            }
            $label = FTVHelper_Html::display($fieldInfos['label']);
            $oldValue = $value;
            if (ake('contentForm', $fieldInfos)) {
                $content = $fieldInfos['contentForm'];
                $content = repl(array('##self##', '##field##', '##em##'), array($value, $field, $em), $content);

                $value = FTV_Crud::internalFunction($content);
            }
            if (true === is_string($value)) {
                $value = FTVHelper_Html::display($value);
            }

            $type = $fieldInfos['fieldType'];
            $required = $fieldInfos['required'];

            switch ($type) {
                case 'select':
                    return FTV_Form::select($field, $value, $oldValue, array('id' => $field, 'required' => $required), $label);
                case 'password':
                    return FTV_Form::$type($field, array('id' => $field, 'required' => $required), $label);
                default:
                    return FTV_Form::$type($field, $value, array('id' => $field, 'required' => $required), $label);
            }
        }
    }
