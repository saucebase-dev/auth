<?php

namespace Modules\Auth\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Modules\Auth\Settings\AuthSettings;
use Saucebase\Core\RegistrationInvite;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class, function (string $attribute, string $email, \Closure $fail): void {
                // Sign-up is closed: only the address the invitation was sent to.
                if (! app(AuthSettings::class)->registration_enabled && ! RegistrationInvite::allows($email)) {
                    $fail(__('Use the address the invitation was sent to.'));
                }
            }],
            'password' => ['required', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms.accepted' => __('You must accept the Terms of Service and Privacy Policy to register.'),
        ];
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'password' => Hash::make($this->input('password')),
        ]);
    }
}
