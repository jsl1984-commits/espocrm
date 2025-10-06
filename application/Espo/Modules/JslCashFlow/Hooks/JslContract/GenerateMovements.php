<?php
namespace Espo\Modules\JslCashFlow\Hooks\JslContract;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

class GenerateMovements implements AfterSave
{
    public static int $order = 10;

    public function __construct(
        private EntityManager $em,
        private Metadata $metadata,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'JslContract') {
            return;
        }

        if (!$entity->isNew()) {
            return;
        }

        $milestoneCount = (int) ($entity->get('milestoneCount') ?? 0);
        if ($milestoneCount <= 0) {
            return;
        }

        $frequency = (string) $entity->get('frequency');
        $customDays = (int) ($entity->get('customFrequencyDays') ?? 0);

        $net = (float) ($entity->get('netAmountPerMilestone') ?? 0);
        $taxType = (string) $entity->get('taxType');
        $taxRate = (float) ($entity->get('taxRate') ?? 0);

        $startDate = (string) $entity->get('startDate');
        if (!$startDate) {
            return;
        }

        $invoiceDate = $entity->get('invoiceDate') ?: $startDate;

        $useFactoring = (bool) $entity->get('useFactoring');
        $paymentTerm = (string) $entity->get('paymentTerm');
        $paymentTermDays = (int) ($entity->get('paymentTermDays') ?? 0);

        $env = (string) $entity->get('environment');
        $scenario = (string) $entity->get('scenario');

        $sourceCompanyId = $entity->get('providerCompanyId');
        $targetCompanyId = $entity->get('clientCompanyId');

        $currentDate = new \DateTime($invoiceDate);

        for ($i = 0; $i < $milestoneCount; $i++) {
            $currentInvoiceDate = $this->adjustToNextBusinessDay($currentDate);

            $currentPaymentDate = $this->calculatePaymentDate(
                clone $currentInvoiceDate,
                $paymentTerm,
                $paymentTermDays,
                $useFactoring
            );

            $this->createMovement($entity->getId(), $sourceCompanyId, $targetCompanyId, $net, $taxRate, $taxType,
                $currentInvoiceDate, $currentPaymentDate, $env, $scenario, $useFactoring);

            if ($taxRate > 0) {
                $this->createMovement($entity->getId(), $sourceCompanyId, $targetCompanyId, $net * $taxRate, $taxRate,
                    $taxType, $currentInvoiceDate, $currentPaymentDate, $env, $scenario, $useFactoring, true);
            }

            $currentDate = $this->addFrequency($currentDate, $frequency, $customDays);
        }

        $endDate = clone $currentDate;
        $endDate->modify('-1 day');
        $entity->set('endDate', $endDate->format('Y-m-d'));

        $this->em->saveEntity($entity);
    }

    private function addFrequency(\DateTime $date, string $frequency, int $customDays): \DateTime
    {
        $intervalSpec = match ($frequency) {
            'Monthly' => 'P1M',
            'Bimonthly' => 'P2M',
            'Quarterly' => 'P3M',
            'Semiannual' => 'P6M',
            'Annual' => 'P1Y',
            'Custom' => 'P' . max($customDays, 1) . 'D',
            default => 'P1M',
        };
        $new = clone $date;
        $new->add(new \DateInterval($intervalSpec));
        return $new;
    }

    private function adjustToNextBusinessDay(\DateTime $date): \DateTime
    {
        while (in_array((int) $date->format('N'), [6,7])) {
            $date->modify('+1 day');
        }
        return $date;
    }

    private function calculatePaymentDate(\DateTime $invoiceDate, string $paymentTerm, int $paymentTermDays, bool $useFactoring): \DateTime
    {
        if ($useFactoring) {
            $invoiceDate->modify('+2 days');
            return $this->adjustToNextBusinessDay($invoiceDate);
        }

        $days = match ($paymentTerm) {
            'Cash' => 0,
            '30' => 30,
            '45' => 45,
            '60' => 60,
            '90' => 90,
            'Custom' => max($paymentTermDays, 0),
            default => 30,
        };

        $invoiceDate->modify("+{$days} days");
        return $this->adjustToNextBusinessDay($invoiceDate);
    }

    private function createMovement(
        string $contractId,
        ?string $sourceCompanyId,
        ?string $targetCompanyId,
        float $amount,
        float $taxRate,
        string $taxType,
        \DateTime $invoiceDate,
        \DateTime $paymentDate,
        string $environment,
        string $scenario,
        bool $useFactoring,
        bool $isTax = false
    ): void {
        $movement = $this->em->getRDBRepository('JslFinancialMovement')->getNew();

        $movement->set('contractId', $contractId);
        $movement->set('sourceCompanyId', $sourceCompanyId);
        $movement->set('targetCompanyId', $targetCompanyId);
        $movement->set('amount', $amount);
        $movement->set('taxRate', $taxRate);
        $movement->set('invoiceDate', $invoiceDate->format('Y-m-d'));
        $movement->set('paymentDate', $paymentDate->format('Y-m-d'));
        $movement->set('useFactoring', $useFactoring);
        $movement->set('environment', $environment);
        $movement->set('scenario', $scenario);

        $type = $this->resolveMovementType($taxType, $isTax);
        $movement->set('type', $type);

        $this->em->saveEntity($movement);
    }

    private function resolveMovementType(string $taxType, bool $isTax): string
    {
        if ($isTax) {
            return match ($taxType) {
                'VAT' => 'InvoiceTaxed',
                'Fee14_5', 'Fee16' => 'Honorar',
                default => 'Invoice',
            };
        }

        return match ($taxType) {
            'VAT' => 'InvoiceTaxed',
            'Fee14_5', 'Fee16' => 'Honorar',
            default => 'Invoice',
        };
    }
}
