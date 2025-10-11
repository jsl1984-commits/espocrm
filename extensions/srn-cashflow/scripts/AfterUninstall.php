<?php
/**
 * AfterUninstall seguro: sólo rebuild para limpiar metadata en cache.
 */
class AfterUninstall
{
    public function run($GLOBALS['container'])
    {
        try {
            $dataManager = $GLOBALS['container']->get('dataManager');
            $dataManager->rebuild();
        } catch (\Throwable $e) {
            error_log('[SRNCashFlow][AfterUninstall] Rebuild falló: ' . $e->getMessage());
        }
        return true;
    }
}