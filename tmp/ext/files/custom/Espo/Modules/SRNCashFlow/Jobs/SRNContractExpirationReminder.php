<?php
namespace Espo\Modules\SRNCashFlow\Jobs;

use Espo\Core\Job\Base as BaseJob;
use Espo\Modules\SRNCashFlow\Services\ContractReminderService;

class SRNContractExpirationReminder extends BaseJob
{
    public function run($data = null)
    {
        /** @var ContractReminderService $service */
        $service = $this
            ->getContainer()
            ->get('serviceFactory')
            ->create('SRNCashFlow:ContractReminder');

        if (is_array($data) && isset($data['batchSize'])) {
            $service->setBatchSize((int)$data['batchSize']);
        }

        $service->run();
        return true;
    }
}