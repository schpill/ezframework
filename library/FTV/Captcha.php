<?php
    /**
     * Captcha class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Captcha
    {
        private $session;

        public function __construct()
        {
            $this->session = FTV_Session::instance('FTVSession');
        }

        public function get()
        {
            $first  = $this->code('FTVCaptcha1');
            $second = $this->code('FTVCaptcha2');
            $html   = '<img src="'. URLSITE .'captcha.php?key=' . $first . '" /> + <img src="'. URLSITE .'captcha.php?key=' . $second . '" />';
            return $html;
        }

        private function code($name)
        {
            $number                 = rand(1, 15);
            $this->session->$name   = $number;
            $height                 = 25;
            $width                  = 60;
            $fontSize               = 14;
            $im                     = imagecreate($width, $height);
            $bg                     = imagecolorallocate($im, 245, 245, 245);
            $textcolor              = imagecolorallocate($im, 0, 0, 0);
            imagestring($im, $fontSize, 5, 5, $number, $textcolor);
            ob_start();
            imagejpeg($im, null, 80);
            $image                  = ob_get_clean();
            $key                    = sha1(time() . session_id() . $number);
            $pathImage              = APPLICATION_PATH . DS . 'cache' . DS . $key . '.jpg';
            FTV_File::put($pathImage, $image);
            imagedestroy($im);
            return $key;
        }
    }
