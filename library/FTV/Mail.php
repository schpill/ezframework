<?php
    /**
     * Mail class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Mail
    {
        static $_instance;
        static $publicKey;
        static $privateKey;

        // singleton
        public static function getInstance()
        {
            if (!self::$_instance instanceof self) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        public function __construct()
        {
            self::$publicKey    = config('app.mailjet.publicKey');
            self::$privateKey   = config('app.mailjet.privateKey');
        }

        public function send($mailOptions)
        {
            if (!is_array($mailOptions)) {
                throw new FTV_Exception('Wrong parameters');
            }

            self::getInstance();

            // $mailOptions = array(
                // 'mailToAddress' => 'gplusquellec@free.fr',
                // 'mailToName' => 'Gerald',
                // 'mailFromAddress' => 'info@webz0ne.com',
                // 'mailFromName' => 'info@webz0ne.com',
                // 'mailFormat' => 'text/html',
                // 'mailSubject' => 'test',
                // 'mailTxt' => 'test',
                // 'mailAttachments' => array('file1', 'file2'),
                // 'mailHtml' => 'test',
                // 'mailIso' => 'ISO-8859-1',
                // 'mailPriority' => 3,
                // 'mailXMailer' => 'Apple Mail (2.936)'
            // );

            if (!array_key_exists('mailToAddress', $mailOptions) || !ake('mailFromAddress', $mailOptions) || !ake('mailSubject', $mailOptions)) {
                throw new FTV_Exception('Wrong parameters');
            }

            if (false === u::isEmail($mailOptions['mailToAddress']) || false === u::isEmail($mailOptions['mailFromAddress'])) {
                throw new FTV_Exception('Wrong parameters');
            }

            extract($mailOptions);

            if (!isset($mailTxt) && !isset($mailHtml)) {
               throw new FTV_Exception('Wrong parameters');
            }

            if (!isset($mailXMailer)) {
                $mailXMailer = 'Apple Mail (2.936)';
            }

            if (!isset($mailPriority)) {
                $mailPriority = 3;
            }

            if (!isset($mailIso)) {
                $mailIso = 'ISO-8859-1';
            }

            if (!isset($mailToName)) {
                $mailToName = $mailToAddress;
            }

            if (!isset($mailFromName)) {
                $mailFromName = $mailFromAddress;
            }

            $config = array(
                'auth' => 'login',
                'username' => self::$publicKey,
                'password' => self::$privateKey,
                'ssl' => 'tls',
                'auth' => 'login',
                'port' => '587'
            );

            $transport = new Zend_Mail_Transport_Smtp('in.mailjet.com', $config);
            $mail = new Zend_Mail($mailIso);

            if (isset($mailAttachments) && count($mailAttachments)) {
                foreach ($mailAttachments as $mailAttachment) {
                    self::attachFileMail($mailAttachment, $mail);
                }
            }

            $mail->addHeader('X-Mailer', $mailXMailer);
            $mail->addHeader('Priority', $mailPriority);
            if (isset($mailTxt)) {
                $mail->setBodyText($mailTxt);
            }
            if (isset($mailHtml)) {
                $mail->setBodyHtml($mailHtml);
            }
            $mail->setFrom($mailFromAddress, $mailFromName);
            $mail->addTo($mailToAddress, $mailToName);
            $mail->setSubject($mailSubject);
            return $mail->send($transport);
        }

        public static function attachFileMail($file, $mail)
        {
            $myFile = file_get_contents($file);
            $nameTab = explode('/', $file);
            $fileName = end($nameTab);
            $at = new Zend_Mime_Part($myFile);
            $at->type = 'image/gif';
            $at->disposition = Zend_Mime::DISPOSITION_INLINE;
            $at->encoding = Zend_Mime::ENCODING_BASE64;
            $at->filename = $fileName;
            $mail->addAttachment($at);
        }

    }
