<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $actor = $this->user();
        $actorLevel = User::ROLE_HIERARCHY[$actor->role] ?? 0;

        // An actor can only assign roles up to (and including) their own level.
        $allowedRoles = collect(User::ROLE_HIERARCHY)
            ->filter(fn (int $level) => $level <= $actorLevel)
            ->keys()
            ->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in($allowedRoles)],
        ];
    }
}
