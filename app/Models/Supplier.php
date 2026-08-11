<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'cuit',
        'phone',
        'email',
        'contact_person',
        'payment_terms',
        'notes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function carcassPurchases(): HasMany
    {
        return $this->hasMany(CarcassPurchase::class);
    }

    public function getPaymentTermsLabelAttribute(): string
    {
        return match ($this->payment_terms) {
            'contado'      => 'Contado',
            '15_dias'      => '15 días',
            '30_dias'      => '30 días',
            '60_dias'      => '60 días',
            'consignacion' => 'Consignación',
            default        => $this->payment_terms,
        };
    }
}
