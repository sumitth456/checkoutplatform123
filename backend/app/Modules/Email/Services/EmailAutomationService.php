<?php

namespace App\Modules\Email\Services;

use App\Models\EmailAutomation;
use App\Models\Order;
use App\Models\Subscription;
use App\Modules\Email\Enums\EmailTrigger;
use Illuminate\Database\Eloquent\Model;

final class EmailAutomationService
{
    public function __construct(
        private readonly EmailDispatchService $dispatch,
    ) {}

    public function trigger(EmailTrigger $trigger, Model $context): void
    {
        $automations = EmailAutomation::query()
            ->with('template')
            ->where('trigger_event', $trigger->value)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($automations as $automation) {
            if (! $this->passesConditions($automation, $context)) {
                continue;
            }

            $template = $automation->template;

            if ($template === null || ! $template->is_active) {
                continue;
            }

            [$email, $name] = $this->resolveRecipient($context);

            if (blank($email)) {
                continue;
            }

            $this->dispatch->queueTemplate(
                template: $template,
                recipientEmail: $email,
                recipientName: $name,
                context: $context,
                automation: $automation,
            );
        }
    }

    private function passesConditions(EmailAutomation $automation, Model $context): bool
    {
        $conditions = $automation->conditions ?? [];

        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $condition) {
            $field = (string) ($condition['field'] ?? '');
            $operator = (string) ($condition['operator'] ?? 'equals');
            $expected = $condition['value'] ?? null;
            $actual = data_get($context, $field);

            $match = match ($operator) {
                'not_equals' => $actual != $expected,
                'contains' => is_string($actual) && str_contains(strtolower($actual), strtolower((string) $expected)),
                default => $actual == $expected,
            };

            if (! $match) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveRecipient(Model $context): array
    {
        if ($context instanceof Order) {
            $context->loadMissing('customer');

            return [
                $context->customer_email ?? $context->customer?->email,
                trim(($context->customer?->first_name ?? '').' '.($context->customer?->last_name ?? '')) ?: null,
            ];
        }

        if ($context instanceof Subscription) {
            $context->loadMissing('customer');

            return [
                $context->customer?->email,
                trim(($context->customer?->first_name ?? '').' '.($context->customer?->last_name ?? '')) ?: null,
            ];
        }

        if (isset($context->email)) {
            return [(string) $context->email, $context->name ?? null];
        }

        return [null, null];
    }
}
