<?php


/**
 * @authors: Nicolaas [at] Sunny Side Up .co.nz
 **/

class SearchEngineAdvancedSettings extends DataObject
{

    /**
     * @var string
     */
    private static $singular_name = "Advanced";
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }
}
