<?php
/**
 * BeforeInstall seguro: no elimina datos ni tablas.
 */
class BeforeInstall
{
    public function run($GLOBALS['container'])
    {
        return true;
    }
}