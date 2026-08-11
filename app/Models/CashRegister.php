<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    protected $fillable = [
        'user_id',
        'opening_amount',
        'closing_amount',
        'expected_amount',
        'difference',
        'notes',
        'opened_at',
        'closed_at',
        'status',
    ];

    protected $casts = [
        'opening_amount'  => 'decimal:2',
        'closing_amount'  => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'difference'      => 'decimal:2',
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashSalesTotal(): float
    {
        return $this->salesTotalByPaymentMethod('cash');
    }

    public function transferSalesTotal(): float
    {
        return $this->salesTotalByPaymentMethod('transfer');
    }

    public function cardSalesTotal(): float
    {
        return $this->salesTotalByPaymentMethod('card');
    }

    public function salesTotalByPaymentMethod(string $paymentMethod): float
    {
        return (float) $this->sales()
            ->where('status', 'completed')
            ->where('payment_method', $paymentMethod)
            ->sum('total');
    }

    public function totalSales(): float
    {
        return (float) $this->sales()
            ->where('status', 'completed')
            ->sum('total');
    }

    public function calculateExpectedAmount(): float
    {
        return (float) $this->opening_amount + $this->cashSalesTotal();
    }

    public function close(float $closingAmount, ?string $closingNotes = null): void
    {
        $expected = $this->calculateExpectedAmount();

        $notes = $this->notes;

        if (filled($closingNotes)) {
            $notes = filled($notes)
                ? $notes . "\n\n" . $closingNotes
                : $closingNotes;
        }

        $this->update([
            'closing_amount'  => $closingAmount,
            'expected_amount' => $expected,
            'difference'      => $closingAmount - $expected,
            'closed_at'       => now(),
            'status'          => 'closed',
            'notes'           => $notes,
        ]);
    }

    public static function formatMoney(float $amount): string
    {
        return '$' . number_format($amount, 2, ',', '.');
    }

    public static function lastClosed(): ?self
    {
        return static::query()
            ->where('status', 'closed')
            ->whereNotNull('closing_amount')
            ->latest('closed_at')
            ->first();
    }

    public static function suggestedOpeningAmount(): float
    {
        $lastClosed = static::lastClosed();

        return $lastClosed ? (float) $lastClosed->closing_amount : 0;
    }
}
