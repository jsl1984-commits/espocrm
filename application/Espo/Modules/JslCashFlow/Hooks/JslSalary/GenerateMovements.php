<?php
namespace Espo\Modules\JslCashFlow\Hooks\JslSalary;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\EntityManager;

class GenerateMovements implements AfterSave
{
    public static int $order = 10;

    public function __construct(private EntityManager $em) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'JslSalary' || !$entity->isNew()) {
            return;
        }

        $periods = (int) ($entity->get('periodsForward') ?? 12);
        $net = (float) $entity->get('netSalary');
        $companyId = $entity->get('companyId');
        $contactId = $entity->get('contactId');
        $hireDate = new \DateTime((string) $entity->get('hireDate'));

        $payDayOpt = (string) $entity->get('payDay');

        $current = clone $hireDate;

        for ($i = 0; $i < $periods; $i++) {
            $payDate = $this->calculatePayDate(clone $current, $payDayOpt);

            // Movement 1: net salary
            $this->createMovement($entity->getId(), $companyId, $net, $payDate, false);
            // Movement 2: payroll contribution (35%) next month day 12, next business day forward
            $contribDate = clone $payDate;
            $contribDate->modify('first day of next month');
            $contribDate->setDate((int)$contribDate->format('Y'), (int)$contribDate->format('m'), 12);
            $contribDate = $this->adjustToNextBusinessDay($contribDate);
            $this->createMovement($entity->getId(), $companyId, $net * 0.35, $contribDate, true);

            $current->modify('+1 month');
        }
    }

    private function calculatePayDate(\DateTime $date, string $opt): \DateTime
    {
        if ($opt === 'LastBusinessDay') {
            $date->modify('last day of this month');
            return $this->adjustToPrevBusinessDay($date);
        }

        $day = (int) $opt;
        $y = (int) $date->format('Y');
        $m = (int) $date->format('m');
        $date->setDate($y, $m, $day);
        return $this->adjustToPrevBusinessDay($date);
    }

    private function adjustToPrevBusinessDay(\DateTime $date): \DateTime
    {
        while (in_array((int) $date->format('N'), [6,7])) {
            $date->modify('-1 day');
        }
        return $date;
    }

    private function adjustToNextBusinessDay(\DateTime $date): \DateTime
    {
        while (in_array((int) $date->format('N'), [6,7])) {
            $date->modify('+1 day');
        }
        return $date;
    }

    private function createMovement(
        string $salaryId,
        ?string $companyId,
        float $amount,
        \DateTime $paymentDate,
        bool $isContribution
    ): void {
        $movement = $this->em->getRDBRepository('JslFinancialMovement')->getNew();

        $movement->set('salaryId', $salaryId);
        $movement->set('sourceCompanyId', $companyId);
        $movement->set('targetCompanyId', $companyId);
        $movement->set('amount', $amount);
        $movement->set('invoiceDate', $paymentDate->format('Y-m-d'));
        $movement->set('paymentDate', $paymentDate->format('Y-m-d'));
        $movement->set('environment', 'Production');
        $movement->set('scenario', 'Real');
        $movement->set('useFactoring', false);
        $movement->set('type', $isContribution ? 'Bonus' : 'Salary');

        $this->em->saveEntity($movement);
    }
}
