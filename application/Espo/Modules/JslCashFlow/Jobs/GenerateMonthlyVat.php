<?php
namespace Espo\Modules\JslCashFlow\Jobs;

use Espo\Core\Job\Job as JobInterface;
use Espo\Core\Job\Queue\JobParams;
use Espo\ORM\EntityManager;

class GenerateMonthlyVat implements JobInterface
{
    public function __construct(private EntityManager $em) {}

    public function run(JobParams $params): void
    {
        // TODO: aggregate Financial Movements by month (invoiceDate) into JslConsolidatedTax
        // Create or update one record per calendar month; set paymentDate to 20 of N+1 adjusted to business day
    }
}
