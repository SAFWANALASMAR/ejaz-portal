<?php

namespace App\Actions\Registers;

use App\Enums\BodyRole;
use App\Enums\MeetingBody;
use App\Models\BodyMembership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * حفظ عضوية في مجلس الإدارة أو لجنة بصفتها ومدتها (السكرتير — BodyMembershipPolicy). واللجنة إلزامية
 * لعضوية اللجنة وممنوعة لغيرها (وقيد في القاعدة). ويُسجَّل الحفظ.
 */
class SaveBodyMembership
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, BodyMembership $membership, array $data): BodyMembership
    {
        if (! ($membership->exists ? $actor->can('update', $membership) : $actor->can('create', BodyMembership::class))) {
            throw new AuthorizationException;
        }

        $body = $data['body'] ?? null;
        $committee = ($body instanceof MeetingBody ? $body->value : $body) === MeetingBody::Committee->value;

        $validated = Validator::make($data, [
            'body' => ['required', Rule::in(array_map(fn (MeetingBody $body): string => $body->value, MeetingBody::membershipBodies()))],
            'committee_id' => [Rule::requiredIf($committee), Rule::prohibitedIf(! $committee), 'nullable', 'integer', Rule::exists('committees', 'id')->whereNull('deleted_at')],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role' => ['required', Rule::enum(BodyRole::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $member = User::query()->find($validated['user_id']);

        if ($member === null || ! $member->is_active || $member->anonymized_at !== null) {
            throw ValidationException::withMessages(['user_id' => __('registers.validation.member')]);
        }

        $membership->fill([
            'body' => $validated['body'],
            'committee_id' => $committee ? $validated['committee_id'] : null,
            'user_id' => $validated['user_id'],
            'role' => $validated['role'],
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return $membership;
    }
}
