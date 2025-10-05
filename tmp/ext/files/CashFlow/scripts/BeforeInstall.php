<?php
/**
 * BeforeInstall (versión segura): no toca la base. Solo permite continuar.
 */
class BeforeInstall
{
    public function run($GLOBALS['container'])
    {
        // Aquí podrías validar versión de EspoCRM, dependencias, etc.
        return true;
    }
}