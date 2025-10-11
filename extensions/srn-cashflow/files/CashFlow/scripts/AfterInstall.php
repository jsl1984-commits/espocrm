<?php
/**
 * AfterInstall (versión segura): rebuild + config defaults + job programado.
 */
class AfterInstall
{
    public function run($GLOBALS['container'])
    {
        $dataManager = $GLOBALS['container']->get('dataManager');
        $dataManager->rebuild();

        $config = $GLOBALS['container']->get('config');
        $changed = false;

        if (!$config->has('srnReminderFrom')) {
            $config->set('srnReminderFrom', '');
            $changed = true;
        }
        if (!$config->has('srnDefaultScenario')) {
            $config->set('srnDefaultScenario', 'Real');
            $changed = true;
        }
        if ($changed) {
            $config->save();
        }

        $em = $GLOBALS['container']->get('entityManager');
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
    }
}