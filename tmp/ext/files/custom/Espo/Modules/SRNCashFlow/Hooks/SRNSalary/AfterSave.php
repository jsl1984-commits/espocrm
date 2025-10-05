<?php
namespace Espo\Modules\SRNCashFlow\Hooks\SRNSalary;

use DateTimeImmutable;
use Espo\Core\Hook\Hook\AfterSave as AfterSaveHook;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Modules\SRNCashFlow\Entities\SRNSalary;
use Espo\ORM\Repository\Option\SaveOptions;

class AfterSave implements AfterSaveHook
{
    public function __construct(private EntityManager $entityManager) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity instanceof SRNSalary) {
            return;
        }

        $employeeId = $entity->get('employeeId');
        $netAmount = (float) ($entity->get('netAmount') ?? 0);
        if (!$employeeId || $netAmount <= 0) {
            return;
        }

        $employee = $this->entityManager->getEntity('SRNEmployee', $employeeId);
        if (!$employee) {
            return;
        }

        $payDay = (int) ($employee->get('salaryPaymentDay') ?? 30);

        $now = new DateTimeImmutable('first day of this month 00:00:00');
        $months = max(1, (int) ($entity->get('monthsForward') ?? 12));
        for ($i = 0; $i < $months; $i++) {
            $base = $now->modify("+{$i} month");
            $due = $base->setDate((int)$base->format('Y'), (int)$base->format('m'), $payDay);

            $movement = $this->entityManager->getNewEntity('SRNFinancialMovement');
            $movement->set([
                'salaryId' => $entity->getId(),
                'movementType' => 'Expense:Salary:Previsional',
                'amountNet' => $netAmount,
                'amountGross' => $netAmount,
                'dueDate' => $due->format('Y-m-d'),
                'environment' => $entity->get('environment') ?? 'Production',
                'scenario' => $entity->get('scenario') ?? 'Real',
                'status' => 'Draft',
                'reason' => 'SRN Salary monthly payment'
            ]);
            $this->entityManager->saveEntity($movement);
        }

        $retentionPercent = (float) ($entity->get('retentionPercent') ?? 35);
        if ($retentionPercent > 0) {
            $nextMonth = $now->modify('+1 month');
            $retentionDue = $nextMonth->setDate((int)$nextMonth->format('Y'), (int)$nextMonth->format('m'), 12);
            $retentionAmount = round($netAmount * ($retentionPercent / 100), 2);

            $ret = $this->entityManager->getNewEntity('SRNFinancialMovement');
            $ret->set([
                'salaryId' => $entity->getId(),
                'movementType' => 'Expense:Salary:Previsional',
                'amountNet' => $retentionAmount,
                'amountGross' => $retentionAmount,
                'dueDate' => $retentionDue->format('Y-m-d'),
                'environment' => $entity->get('environment') ?? 'Production',
                'scenario' => $entity->get('scenario') ?? 'Real',
                'status' => 'Draft',
                'reason' => 'SRN Salary retention ' . $retentionPercent . '%'
            ]);
            $this->entityManager->saveEntity($ret);
        }
    }
}
