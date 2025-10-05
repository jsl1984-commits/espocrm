<?php
namespace Espo\Modules\SRNCashFlow\Entities;
use Espo\Core\ORM\Entity;

class SRNContract extends Entity
{
    public const STATUS_DRAFT      = 'Borrador';
    public const STATUS_ACTIVE     = 'Activo';
    public const STATUS_SUSPENDED  = 'Suspendido';
    public const STATUS_FINISHED   = 'Finalizado';
    public const STATUS_TERMINATED = 'Rescindido';
    public const STATUS_EXPIRED    = 'Expirado';

    public const FIELD_REMINDER_INTERVALS = 'reminderIntervals';
    public const FIELD_SENT_STAGES        = 'reminderStagesSent';

    public function getReminderIntervals(): array
    {
        $intervals = $this->get(self::FIELD_REMINDER_INTERVALS);
        return is_array($intervals) ? $intervals : [];
    }

    public function getSentReminderStages(): array
    {
        $sent = $this->get(self::FIELD_SENT_STAGES);
        return is_array($sent) ? $sent : [];
    }

    public function addSentReminderStage(int $stage): void
    {
        $sent = $this->getSentReminderStages();
        if (!in_array($stage, $sent, true)) {
            $sent[] = $stage;
            sort($sent, SORT_NUMERIC);
            $this->set(self::FIELD_SENT_STAGES, $sent);
        }
    }
}