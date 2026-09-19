<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Hamısı')->badge(User::count())];
        foreach (User::ROLES as $role => $label) {
            $tabs[$role] = Tab::make($label)
                ->badge(User::where('role', $role)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('role', $role));
        }

        return $tabs;
    }
}
