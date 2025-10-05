<?php
namespace Espo\Modules\SRNCashFlow\Jobs;

use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Core\ServiceFactory;
use Espo\Modules\SRNCashFlow\Services\ContractReminderService;

class SRNContractExpirationReminder implements Job
{
    public function __construct(private ServiceFactory $serviceFactory)
    {}

    public function run(Data $data): void
    {
        /** @var ContractReminderService $service */
        $service = $this->serviceFactory->create('SRNCashFlow:ContractReminder');

        if ($data->has('batchSize')) {
            $service->setBatchSize((int) ($data->get('batchSize') ?? 0));
        }

        $service->run();
    }
}