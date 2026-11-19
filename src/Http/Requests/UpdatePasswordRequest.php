<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use STS\FilamentImpersonate\ImpersonateManager;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    /**
     * A first password is refused while impersonating, as adding a passkey is: it would
     * outlive the impersonation as a way back into the account.
     */
    public function authorize(): bool
    {
        return $this->user()?->getAuthPassword() || ! app(ImpersonateManager::class)->isImpersonating();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // A user who signed up through a provider has none to give, so none is asked.
            'current_password' => $this->user()?->getAuthPassword() ? ['required', 'current_password'] : ['exclude'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];
    }
}
