<?php
namespace Espo\Modules\SRNCashFlow\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Container;
use Espo\Core\Utils\Log;
use Espo\ORM\EntityManager;
use Espo\Modules\SRNCashFlow\Entities\SRNContract;
use Espo\Core\Mail\Sender as MailSender;

class ContractReminderService
{
    private EntityManager $em;
    private MailSender $mailSender;
    private Log $logger;
    private $config;
    private int $batchSize = 200;

    public function __construct(Container $GLOBALS['container'])
    {
        $this->em         = $GLOBALS['container']->get('entityManager');
        $this->mailSender = $GLOBALS['container']->get('mailSender');
        $this->logger     = $GLOBALS['container']->get('logger');
        $this->config     = $GLOBALS['container']->get('config');
    }

    public function setBatchSize(int $batchSize): self
    {
        if ($batchSize > 0) {
            $this->batchSize = $batchSize;
        }
        return $this;
    }

    public function run(): array
    {
        $tzName = $this->config->get('defaultTimeZone') ?: 'UTC';
        $tz     = new DateTimeZone($tzName);
        $now    = new DateTimeImmutable('now', $tz);

        $maxWindow = $this->config->get('srnMaxReminderWindow');
        if ($maxWindow === null) {
            $maxWindow = 6;
        }

        $upperDate = $now->modify('+' . (int)$maxWindow . ' months')->format('Y-m-d');

        $stats = [
            'evaluated' => 0,
            'notified'  => 0,
            'skippedNoEmails' => 0,
            'skippedNoExpiration' => 0,
            'skippedAlreadySent' => 0,
            'errors'    => 0
        ];

        $offset = 0;

        do {
            $batch = $this->fetchBatch($offset, $this->batchSize, $upperDate);
            $count = count($batch);
            if ($count === 0) {
                break;
            }

            foreach ($batch as $contract) {
                $stats['evaluated']++;
                try {
                    $this->processContract($contract, $now, $stats);
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->logger->error('[SRNCashFlow][Reminder] Error contrato ' . $contract->getId() . ': ' . $e->getMessage());
                }
            }

            $offset += $count;
            $this->em->clear();
        } while ($count === $this->batchSize);

        $this->logger->info('[SRNCashFlow][Reminder] Fin. Stats: ' . json_encode($stats));
        return $stats;
    }

    protected function fetchBatch(int $offset, int $limit, string $upperDate): array
    {
        $repo = $this->em->getRepository('SRNContract');

        return $repo->where([
            'status'        => SRNContract::STATUS_ACTIVE,
            'sendReminders' => true
        ])->where([
            'expirationDate<=' => $upperDate,
            'expirationDate!=' => null
        ])->limit($limit)->offset($offset)->find();
    }

    protected function processContract(SRNContract $contract, DateTimeImmutable $now, array &$stats): void
    {
        $expiration = $contract->get('expirationDate');
        if (!$expiration) {
            $stats['skippedNoExpiration']++;
            return;
        }

        try {
            $expDate = new DateTimeImmutable($expiration, $now->getTimezone());
        } catch (\Throwable $e) {
            $this->logger->warning('[SRNCashFlow][Reminder] Fecha inválida contrato ' . $contract->getId());
            $stats['skippedNoExpiration']++;
            return;
        }

        $monthsDiff = ($expDate->format('Y') - $now->format('Y')) * 12 + ($expDate->format('n') - $now->format('n'));
        if ($monthsDiff < 0) {
            return;
        }

        $intervals  = $contract->getReminderIntervals();
        if (empty($intervals)) {
            return;
        }

        $sentStages = $contract->getSentReminderStages();

        if (in_array($monthsDiff, $intervals, true)) {
            if (in_array($monthsDiff, $sentStages, true)) {
                $stats['skippedAlreadySent']++;
                return;
            }
            $emails = $contract->get('reminderEmails') ?: [];
            if (empty($emails)) {
                $stats['skippedNoEmails']++;
                return;
            }
            $this->notify($contract, $expDate, $emails, $monthsDiff);
            $contract->addSentReminderStage($monthsDiff);
            $this->em->saveEntity($contract);
            $stats['notified']++;
        }
    }

    protected function notify(SRNContract $contract, DateTimeImmutable $expDate, array $emails, int $stage): void
    {
        $subject = sprintf('[Contrato] Vence en %d mes(es): %s', $stage, $contract->get('name'));
        $body = sprintf(
            "Contrato: %s\nID: %s\nVencimiento: %s\nTipo: %s\nEstado: %s\nMeses restantes: %d\n",
            $contract->get('name'),
            $contract->getId(),
            $expDate->format('Y-m-d'),
            $contract->get('contractType'),
            $contract->get('status'),
            $stage
        );

        $from = $this->config->get('srnReminderFrom')
            ?: ($this->config->get('outboundEmailFromAddress') ?: 'no-reply@example.com');

        foreach ($emails as $to) {
            try {
                $this->mailSender->send($from, $to, $subject, $body);
                $this->logger->debug('[SRNCashFlow][Reminder] Enviado a ' . $to . ' contrato ' . $contract->getId() . ' stage=' . $stage);
            } catch (\Throwable $e) {
                $this->logger->warning('[SRNCashFlow][Reminder] Falló envío a ' . $to . ' contrato ' . $contract->getId() . ': ' . $e->getMessage());
            }
        }
    }
}