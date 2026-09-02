<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RecordStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $id = is_object($user) ? $user->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'email', 'max:150',
                // Soft-deleted users keep their row, so the uniqueness check
                // must see them or a retired address becomes reusable and the
                // two histories merge.
                Rule::unique('users', 'email')->ignore($id),
            ],
            // Required on create, optional on edit: blank means "leave it".
            'password' => [$id === null ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'plant_id' => ['nullable', 'integer', 'exists:plants,id'],
            'employee_code' => ['nullable', 'string', 'max:30', Rule::unique('users', 'employee_code')->ignore($id)],
            'position' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
            // At least one role: an account with none can sign in and reach
            // nothing, which reads as a broken system rather than a locked one.
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('requests.user_email_taken'),
            'roles.required' => __('requests.user_needs_role'),
            'roles.min' => __('requests.user_needs_role'),
            'password.confirmed' => __('requests.password_mismatch'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function roles(): array
    {
        return array_values(array_unique($this->validated()['roles']));
    }

    /**
     * Not named attributes(): FormRequest already declares it for custom
     * validation attribute names, and overriding it here would quietly break
     * every message that referenced one.
     *
     * @return array<string, mixed>
     */
    public function userAttributes(): array
    {
        return collect($this->validated())->except(['roles', 'password_confirmation'])->all();
    }
}
