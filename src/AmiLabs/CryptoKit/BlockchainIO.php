<?php

namespace AmiLabs\CryptoKit;

use \AmiLabs\DevKit\Registry;
use \AmiLabs\CryptoKit\Blockchain;

/**
 * Blockchain I/O Facade.
 */
class BlockchainIO{
    /**
     * Singleton implementation.
     *
     * @ \AmiLabs\CryptoKit\BlockchainIO
     * @return \AmiLabs\CryptoKit\Blockchain\ILayer
     */
    public static function getInstance($layer = FALSE)
    {
        return self::initLayer($layer);
    }

    /**
     * Returns appropriate block chain layer.
     *
     * @param  string $layer
     * @return \AmiLabs\CryptoKit\Blockchain\ILayer
     */
    public static function getLayer($layer = FALSE)
    {
        return self::initLayer($layer);
    }
    /**
     * Contructor.
     *
     * @todo Ability to use classname in config
     */
    // protected function __construct()
    public static function initLayer($layer = FALSE)
    {
        $cfgLayer = Registry::useStorage('CFG')->get('CryptoKit/layer', FALSE);
        if(FALSE !== $layer){
            $layerName = $layer;
        }elseif(FALSE !== $cfgLayer){
            $layerName = $cfgLayer;
        }else{
            $layer = 'Counterparty';
        }
        $class = "\\AmiLabs\\CryptoKit\\Blockchain\\Layer\\" . $layerName;
        return new $class;
    }
}
