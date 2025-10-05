<?php
/**
 * BeforeInstall seguro: no elimina datos ni tablas.
 */
class BeforeInstall
{
    public function run($container, array $params = [])
    {
        return true;
    }
}