<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\CreateStaffUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\SaudiPhone;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * حساب موظف: يُنشأ بكلمة مرور عشوائية، ويصل صاحبه بريد لتعيين كلمة مروره.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(CreateStaffUser::class)->handle($actor, [
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'phone' => filled($data['phone'] ?? null) ? SaudiPhone::normalize((string) $data['phone']) : null,
        ], $actor->can('assignRoles', User::class) ? array_values((array) ($data['roles'] ?? [])) : []);
    }
}
