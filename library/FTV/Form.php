<?php
    /**
     * Form class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Form
    {
        public static $labels = array();
        public static $macros = array();
        const spoofer = '_method';

        /**
        * Default twitter form class
        *
        * Options are form-vertical, form-horizontal, form-inline, form-search
        */
        public $formClass = 'form-horizontal';

        /**
        * Automatically create an id for each field based on the field name
        */
        public $nameAsId = true;

        /**
        * Text string to identify the required label
        */
        public $requiredLabel = '.req';

        /**
        * Extra text added before the label for required fields
        */
        public $requiredPrefix = '';

        /**
        * Extra text added after the label for required fields
        */
        public $requiredSuffix = ' *';

        /**
        * Extra class added to the label for required fields
        */
        public $requiredClass = 'label-required';

        /**
        * Display a class for the control group if an input field fails validation
        */
        public $controlGroupError = 'error';

        /**
        * Display inline validation error text
        */
        public $displayInlineErrors = false;

        public static function open($action = null, $method = 'POST', $attributes = array(), $https = null, $upload = false)
        {
            $method = i::upper($method);

            if (!array_key_exists('id', $attributes)) {
                $attributes['id'] = md5(self::action($action, $https));
            }

            $attributes['method'] =  self::method($method);
            $attributes['action'] = self::action($action, $https);

            if (true === $upload) {
                $attributes['enctype'] = 'multipart/form-data';
            }

            if (!array_key_exists('accept-charset', $attributes)) {
                $attributes['accept-charset'] = 'utf-8';
            }

            $append = '';
            if ($method == 'PUT' || $method == 'DELETE') {
                $append = self::hidden(self::spoofer, $method);
            }

            return '<form' . FTV_Html::attributes($attributes) . '>' . $append;
        }

        protected static function method($method)
        {
            return ($method !== 'GET') ? 'POST' : $method;
        }

        protected static function action($action, $https = null)
        {
            $uri = (null === $action) ? URLSITE : $action;
            return (null === $https) ? $uri : repl('http://', 'https://', $uri);
        }

        public static function close()
        {
            return '</form>';
        }

        public static function token()
        {
            return self::input('hidden', '_token', u::token());
        }

        public static function label($name, $value, $attributes = array())
        {
            self::$labels[] = $name;
            $attributes = FTV_Html::attributes($attributes);
            //*GP* $value = FTV_Html::entities($value);
            return '<label for="' . $name . '"' . $attributes . '>' . $value . '</label>';
        }

        /**
        * Builds the label html
        *
        * @param string $name The name of the html field
        * @param string $label The label name
        * @param boolean $required
        * @return string
        */
        public static function buildLabel($name, $label = '', $required = false)
        {
            $out = '';
            if (!empty($label)) {
                $class = 'control-label';
                $requiredLabel = '.req';
                $requiredSuffix = '<span class="required"><i class="icon-asterisk"></i></span>';
                $requiredPrefix = '';
                $requiredClass = 'labelrequired';
                if (false !== $required) {
                    $label = $requiredPrefix . $label . $requiredSuffix;
                    $class .= ' ' . $requiredClass;
                }
                $out .= self::label($name, $label, array('class' => $class));
            }
            return $out;
        }

        /**
        * Builds the Twitter Bootstrap control wrapper
        *
        * @param string $field The html for the field
        * @param string $name The name of the field
        * @param string $label The label name
        * @param boolean $checkbox
        * @return string
        */
        private function buildWrapper($field, $name, $label = '', $checkbox = false, $required)
        {
            $getter = 'get' . i::camelize($name);
            $error = null;
            $actual = FTV_Session::instance('FTVForm')->getActual();
            if (null !== $actual) {
                $error = $actual->getErrors()->$getter();
            }
            $class = 'control-group';
            if (!empty(self::$controlGroupError) && !empty($error)) {
                $class .= ' ' . self::$controlGroupError;
            }

            $id = ' id="control-group-' . $name . '"';
            $out = '<div class="' . $class . '"' . $id . '>';
            $out .= self::buildLabel($name, $label, $required);
            $out .= '<div class="controls">' . PHP_EOL;
            $out .= ($checkbox === true) ? '<label class="checkbox">' : '';
            $out .= $field;

            if (!empty($error)) {
                $out .= '<span class="help-inline">' . $error . '</span>';
            }

            $out .= ($checkbox === true) ? '</label>' : '';
            $out .= '</div>';
            $out .= '</div>' . PHP_EOL;
            return $out;
        }

        public static function input($type, $name, $value = null, $attributes = array(), $label = '', $checkbox = false)
        {
            $name = (isset($attributes['name'])) ? $attributes['name'] : $name;
            if (!ake('required', $attributes)) {
                $required = false;
            } else {
                $required = $attributes['required'];
            }
            if (false === $required) {
                unset($attributes['required']);
            }
            if (!ake('id', $attributes)) {
                $attributes['id'] = $name;
            }
            $id = self::id($name, $attributes);
            $class = '';
            $attributes = array_merge($attributes, compact('type', 'name', 'value', 'id'));
            if ($type == 'date') {
                $class .= ' datepicker';
            }
            $field = '<input class="span6'.$class.'"' . FTV_Html::attributes($attributes) . ' />';
            return self::buildWrapper($field, $name, $label, $checkbox, $required);
        }

        public static function text($name, $value = null, $attributes = array(), $label = '')
        {
            return self::input('text', $name, $value, $attributes, $label);
        }

        public static function password($name, $attributes = array(), $label = '')
        {
            return self::input('password', $name, null, $attributes, $label);
        }

        public static function hidden($name, $value = null, $attributes = array(), $label = '')
        {
            return self::input('hidden', $name, $value, $attributes, $label);
        }

        public static function search($name, $value = null, $attributes = array(), $label = '')
        {
            return self::input('search', $name, $value, $attributes, $label);
        }

        public static function email($name, $value = null, $attributes = array(), $label = '')
        {
            return self::input('email', $name, $value, $attributes, $label);
        }

        public static function telephone($name, $value = null, $attributes = array(), $label = '')
        {
            return self::input('tel', $name, $value, $attributes, $label);
        }

        public static function url($name, $value = null, $attributes = array(), $label = '')
        {
            return self::input('url', $name, $value, $attributes, $label);
        }

        public static function number($name, $value = null, $attributes = array(), $label = '')
        {
            return self::input('number', $name, $value, $attributes, $label);
        }

        public static function date($name, $value = null, $attributes = array(), $label = '')
        {
            return self::input('date', $name, $value, $attributes, $label);
        }

        public static function file($name, $attributes = array(), $label = '')
        {
            return self::input('file', $name, null, $attributes, $label);
        }

        public static function textarea($name, $value = '', $attributes = array(), $label = '')
        {
            $attributes['name'] = $name;
            $attributes['id'] = self::id($name, $attributes);

            if ( ! ake('rows', $attributes)) $attributes['rows'] = 10;
            if ( ! ake('cols', $attributes)) $attributes['cols'] = 50;
            if ( ! ake('required', $attributes)) $attributes['required'] = false;

            $required = $attributes['required'];
            if (false === $required) {
                unset($attributes['required']);
            }

            $field = '<textarea class="span6"' . FTV_Html::attributes($attributes) . '>' . FTV_Html::entities($value) . '</textarea>';
            return self::buildWrapper($field, $name, $label, false, $required);
        }

        public static function select($name, $options = array(), $selected = null, $attributes = array(), $label = '')
        {
            $attributes['id'] = self::id($name, $attributes);
            $attributes['name'] = $name;
            $html = array();
            foreach ($options as $value => $display) {
                if (is_array($display)) {
                    $html[] = self::optgroup($display, $value, $selected);
                } else {
                    $html[] = self::option($value, $display, $selected);
                }
            }
            if ( ! ake('required', $attributes)) $attributes['required'] = false;

            $required = $attributes['required'];

            if (false === $required) {
                unset($attributes['required']);
            }

            $field = '<select class="span6"' . FTV_Html::attributes($attributes) . '>' . implode('', $html) . '</select>';
            return self::buildWrapper($field, $name, $label, false, $required);
        }

        protected static function optgroup($options, $label, $selected)
        {
            $html = array();
            foreach ($options as $value => $display) {
                $html[] = self::option($value, $display, $selected);
            }
            return '<optgroup label="' . FTV_Html::entities($label) . '">' . implode('', $html) . '</optgroup>';
        }

        protected static function option($value, $display, $selected)
        {
            if (is_array($selected)) {
                $selected = (in_array($value, $selected)) ? 'selected' : null;
            } else {
                $selected = ((string) $value == (string) $selected) ? 'selected' : null;
            }

            $attributes = array('value' => FTVHelper_Html::display($value), 'selected' => $selected);
            return '<option' . FTV_Html::attributes($attributes) . '>' . FTVHelper_Html::display($display) . '</option>';
        }

        public static function checkbox($name, $value = 1, $checked = false, $attributes = array(), $label = '')
        {
            return self::checkable('checkbox', $name, $value, $checked, $attributes, $label);
        }

        public static function radio($name, $value = null, $checked = false, $attributes = array(), $label = '')
        {
            if (null === $value) $value = $name;

            return self::checkable('radio', $name, $value, $checked, $attributes, $label);
        }

        protected static function checkable($type, $name, $value, $checked, $attributes, $label = '')
        {
            if ($checked) $attributes['checked'] = 'checked';
            $attributes['id'] = self::id($name, $attributes);
            return self::input($type, $name, $value, $attributes, $label, true);
        }

        public static function submit($value = null, $attributes = array(), $btnClass = 'btn')
        {
            $attributes['type'] = 'submit';
            if ($btnClass != 'btn') {
                $btnClass = 'btn btn-' . $btnClass;
            }
            if ( ! isset($attributes['class']))  {
                $attributes['class'] = $btnClass;
            } elseif (strpos($attributes['class'], $btnClass) === false) {
                $attributes['class'] .= ' ' . $btnClass;
            }

            return self::button($value, $attributes);
        }

        public static function reset($value = null, $attributes = array(), $btnClass = 'btn')
        {
            $attributes['type'] = 'reset';

            if ($btnClass != 'btn') {
                $btnClass = 'btn btn-' . $btnClass;
            }
            if ( ! isset($attributes['class']))  {
                $attributes['class'] = $btnClass;
            } elseif (strpos($attributes['class'], $btnClass) === false) {
                $attributes['class'] .= ' ' . $btnClass;
            }
            return self::button($value, $attributes);
        }

        /**
        * Shortcut method for creating a primary submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitPrimary($value, $attributes = array())
        {
            return self::submit($value, $attributes, 'primary');
        }

        /**
        * Shortcut method for creating an info submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitInfo($value, $attributes = array())
        {
            return self::submit($value, $attributes, 'info');
        }

        /**
        * Shortcut method for creating a success submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitSuccess($value, $attributes = array())
        {
            return self::submit($value, $attributes, 'success');
        }

        /**
        * Shortcut method for creating a warning submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitWarning($value, $attributes = array())
        {
            return self::submit($value, $attributes, 'warning');
        }

        /**
        * Shortcut method for creating a danger submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitDanger($value, $attributes = array())
        {
            return self::submit($value, $attributes, 'danger');
        }

        /**
        * Shortcut method for creating an inverse submit button
        *
        * @param string $value
        * @param array $attributes
        * @return [type]
        */
        public static function submitInverse($value, $attributes = array())
        {
            return self::submit($value, $attributes, 'inverse');
        }


        public static function image($url, $name = null, $attributes = array())
        {
            $attributes['src'] = $url;
            return self::input('image', $name, null, $attributes);
        }

        public static function button($value = null, $attributes = array())
        {
            return '<button' . FTV_Html::attributes($attributes) . '>' . FTV_Html::entities($value) . '</button>';
        }

        protected static function id($name, $attributes)
        {
            // If an ID has been explicitly specified in the attributes, we will
            // use that ID. Otherwise, we will look for an ID in the array of
            // label names so labels and their elements have the same ID.
            if (array_key_exists('id', $attributes)) {
                return $attributes['id'];
            }
            if (in_array($name, self::$labels)) {
                return $name;
            }
        }

        public static function instance()
        {
            return new FTV_Form_View();
        }
    }

