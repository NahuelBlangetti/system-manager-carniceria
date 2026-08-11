<?php

namespace App\Filament\Resources\CarcassPurchases\Pages;

use App\Filament\Resources\CarcassPurchases\CarcassPurchaseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCarcassPurchase extends CreateRecord
{
    protected static string $resource = CarcassPurchaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['status']  = 'draft';

        return $data;
    }
}
