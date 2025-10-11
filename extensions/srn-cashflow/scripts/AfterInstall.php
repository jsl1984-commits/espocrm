<?php
/**
 * AfterInstall seguro:
 * - Rebuild
 * - Aplica defaults de configuración si faltan
 * - Crea Scheduled Job sólo si no existe
 */
class AfterInstall
{
    public function run($GLOBALS['container'])
    {
        $dataManager = $GLOBALS['container']->get('dataManager');
        $dataManager->rebuild();

        $config   = $GLOBALS['container']->get('config');
        $defaults = [
            'srnReminderFrom'      => '',
            'srnDefaultScenario'   => 'Real',
            'srnMaxReminderWindow' => 6
        ];
        $changed = false;
        foreach ($defaults as $k => $v) {
            if (!$config->has($k)) {
                $config->set($k, $v);
                $changed = true;
            }
        }
        if ($changed) {
            $config->save();
        }

        try {
            $em   = $GLOBALS['container']->get('entityManager');
            $repo = $em->getRepository('ScheduledJob');
            $existing = $repo->where([
                'job' => 'Espo\\Modules\\SRNCashFlow\\Jobs\\SRNContractExpirationReminder'
            ])->findOne();
            if (!$existing) {
                $job = $em->getEntity('ScheduledJob');
                $job->set([
                    'name'       => 'SRN Contract Expiration Reminder',
                    'job'        => 'Espo\\Modules\\SRNCashFlow\\Jobs\\SRNContractExpirationReminder',
                    'status'     => 'Active',
                    'scheduling' => '0 7 * * *',
                    'isInternal' => false
                ]);
                $em->saveEntity($job);
            }
        } catch (\Throwable $e) {
            error_log('[SRNCashFlow][AfterInstall] No se pudo crear Scheduled Job: ' . $e->getMessage());
        }

        return true;
    }
}