<?php
    /**
     * Image class
     *
     * @package     FTV
     * @author      Gerald Plusquellec
     */
    class FTV_Image
    {
        protected static $_adapterOptions = array(
            'preserveAlpha' => true,
            'alphaMaskColor' => array(
                255, 
                255, 
                255
            ),
            'preserveTransparency' => true,
            'transparencyMaskColor' => array(
                0, 
                0, 
                0
            ),
            'resizeUp' => true
        );
        
        /**
        * @param string $adapterClass
        * @param array $adapterOptions
        * @return FTV_Image_Adapter_Abstract
        */
        public static function factory($adapterClass = null, $adapterOptions = null)
        {
            if (null === $adapterClass) {
                if (extension_loaded('gd')) {
                    $adapterClass = 'FTV_Image_Adapter_Gd';
                } else {
                    /* TODO */
                    $adapterClass = 'FTV_Image_Adapter_ImageMagick';
                }
            }
            
            if (null === $adapterOptions) {
                $adapterOptions = self::$_adapterOptions;
            }
            
            return new $adapterClass($adapterOptions);
        }
    }
