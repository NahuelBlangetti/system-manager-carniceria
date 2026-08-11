<?php

namespace App\Models;

use App\Filament\Pages\ValidarImport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImport extends Model
{
    protected $fillable = [
        'user_id',
        'supplier_id',
        'filename',
        'file_path',
        'file_hash',
        'status',
        'products',
        'error_message',
        'product_count',
        'processed_at',
    ];

    protected $casts = [
        'products'     => 'array',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Elimina del panel las notificaciones de "Revisar" de esta importación. */
    public function dismissReviewNotifications(): void
    {
        $user = $this->user;

        if (! $user) {
            return;
        }

        $url = ValidarImport::getUrl(['id' => $this->id]);

        $user->notifications()
            ->whereJsonContains('data->actions', ['url' => $url])
            ->delete();
    }

    /**
     * Descarta una importación lista para validar: marca cancelled,
     * borra el archivo temporal si quedó y limpia notificaciones.
     */
    public function cancel(): void
    {
        if ($this->status !== 'done') {
            return;
        }

        if ($this->file_path && Storage::disk('local')->exists($this->file_path)) {
            Storage::disk('local')->delete($this->file_path);
        }

        $this->update(['status' => 'cancelled']);
        $this->dismissReviewNotifications();
    }
}
