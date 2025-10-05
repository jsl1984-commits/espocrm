<?php
namespace Espo\Modules\SRNCashFlow\Jobs;

use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\ORM\EntityManager;

class SRNVatSettlement implements Job
{
    public function __construct(private EntityManager $em) {}

    public function run(Data $data): void
    {
        // TODO: consolidar IVA del mes N y crear movimiento el día 20 de N+1.
    }
}
