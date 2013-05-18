<?php
    /**
     * Translation class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Translation extends FTV_Object
    {
        public function __construct($fileTranslation)
        {
            $config = u::get('FTVConfig');
            $from   = $config['app']['lng'];
            $to     = (null === u::get('FTVlng')) ? $from : u::get('FTVlng');

            $this->setFrom($from);
            $this->setTo($to);

            if ($from != $to) {
                if (FTV_File::exists($fileTranslation)) {
                    $translations = include($fileTranslation);
                    $this->setSentences($translations);
                    u::set('FTVTranslate', $this);
                } else {
                    throw new FTV_Exception('The translation file does not exist.');
                }
            }
        }

        public function translate($sentence, $api = false)
        {
            $from   = $this->getFrom();
            $to     = $this->getTo();

            if ($from == $to) {
                return $sentence;
            }

            $sentences = $this->getSentences();
            if (ake($sentence, $sentences)) {
                return $sentences[$sentence];
            } else {
                if (false === $api) {
                    return $sentence;
                } else {
                    return $this->_apiTranslate($sentence);
                }
            }
        }

        private function _apiTranslate($sentence)
        {
            $urlApi = 'http://translate.google.de/translate_a/t?client=t&text=' . urlencode($sentence) . '&hl=de&sl=##from##&tl=##to##&ie=UTF-8&oe=UTF-8';
            $from   = $this->getFrom();
            $to     = $this->getTo();

            $res    = fgc(repl('##from##', $from, repl('##to##', $to, $urlApi)));

            return u::cut('[[["', '","', $res);
        }
    }
