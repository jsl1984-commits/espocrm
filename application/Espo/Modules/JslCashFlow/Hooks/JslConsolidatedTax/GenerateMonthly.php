<?php
namespace Espo\Modules\JslCashFlow\Hooks\JslConsolidatedTax;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class GenerateMonthly implements AfterSave
{
    public static int $order = 10;

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        // Placeholder for future logic if needed.
    }
}
