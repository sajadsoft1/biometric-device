<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\DTOs\Commands;

use InvalidArgumentException;

/**
 * دستور setquestionnaire.
 *
 * radio=true انتخاب تکی است و حداکثر ۱۶ گزینه دارد.
 * radio=false انتخاب چندتایی است و حداکثر ۸ گزینه دارد.
 * schedules حداکثر ۸ بازه به شکل HH:MM-HH:MM*event است.
 * voice اگر null باشد در پیام نمی‌آید تا دستگاه آن را نخواند.
 */
class SetQuestionnaireDTO
{
    /**
     * @param list<string> $items
     * @param list<string> $schedules
     */
    public function __construct(
        public readonly string $title,
        public readonly bool $radio,
        public readonly int $optionFlag,
        public readonly bool $useQuestion,
        public readonly bool $useSchedule,
        public readonly int $card,
        public readonly array $items,
        public readonly array $schedules,
        public readonly ?string $voice = null,
        public readonly ?string $errorMessage = null,
    ) {
        $itemLimit = $radio ? 16 : 8;

        if (count($items) > $itemLimit) {
            throw new InvalidArgumentException("Questionnaire items cannot exceed {$itemLimit}.");
        }

        if (count($schedules) > 8) {
            throw new InvalidArgumentException('Questionnaire schedules cannot exceed 8.');
        }

        foreach ($items as $item) {
            if ( ! is_string($item) || $item === '') {
                throw new InvalidArgumentException('Each questionnaire item must be a non-empty string.');
            }
        }

        foreach ($schedules as $schedule) {
            if ( ! is_string($schedule) || preg_match('/^\d{2}:\d{2}-\d{2}:\d{2}\*\d+$/', $schedule) !== 1) {
                throw new InvalidArgumentException('Each schedule must match HH:MM-HH:MM*event.');
            }
        }
    }

    public function toDevicePayload(): array
    {
        $payload = [
            'cmd' => 'setquestionnaire',
            'title' => $this->title,
            'radio' => $this->radio,
            'optionflag' => $this->optionFlag,
            'usequestion' => $this->useQuestion,
            'useschedule' => $this->useSchedule,
            'card' => $this->card,
            'items' => array_values($this->items),
            'schedules' => array_values($this->schedules),
        ];

        if ($this->voice !== null) {
            $payload['voice'] = $this->voice;
        }

        if ($this->errorMessage !== null) {
            $payload['errmsg'] = $this->errorMessage;
        }

        return $payload;
    }
}
