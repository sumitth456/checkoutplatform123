<?php

namespace App\Models;

use App\Policies\TransactionPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

#[UsePolicy(TransactionPolicy::class)]
class Transaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'order_id',
        'subscription_id',
        'customer_id',
        'payment_gateway_id',
        'parent_transaction_id',
        'type',
        'status',
        'external_id',
        'external_status',
        'authorization_id',
        'refund_id',
        'refunded_at',
        'currency',
        'amount',
        'fee_amount',
        'net_amount',
        'payment_method_type',
        'payment_method_last4',
        'payment_method_brand',
        'failure_code',
        'failure_message',
        'gateway_response',
        'processed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'gateway_response' => 'array',
            'metadata' => 'array',
            'processed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction): void {
            if (blank($transaction->uuid)) {
                $transaction->uuid = (string) Str::uuid();
            }
        });
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayConfig::class, 'payment_gateway_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }

    public function childTransactions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_transaction_id');
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function maskedPaymentMethod(): string
    {
        $brand = $this->payment_method_brand ?? 'Card';
        $last4 = $this->payment_method_last4 ?? '????';

        return trim("{$brand} •••• {$last4}");
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['captured', 'succeeded', 'completed', 'paid'], true);
    }
}
