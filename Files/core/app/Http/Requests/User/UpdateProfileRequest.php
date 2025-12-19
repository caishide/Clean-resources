<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateProfileRequest - Validates profile update requests
 *
 * Encapsulates validation logic for user profile updates, following Laravel best practices.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'firstname' => 'required|string|max:50',
            'lastname' => 'required|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:50',
            'state' => 'nullable|string|max:50',
            'zip' => 'nullable|string|max:20',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'firstname.required' => 'The first name field is required',
            'firstname.max' => 'First name must not be greater than 50 characters',
            'lastname.required' => 'The last name field is required',
            'lastname.max' => 'Last name must not be greater than 50 characters',
            'address.max' => 'Address must not be greater than 255 characters',
            'city.max' => 'City must not be greater than 50 characters',
            'state.max' => 'State must not be greater than 50 characters',
            'zip.max' => 'ZIP code must not be greater than 20 characters',
            'image.image' => 'The uploaded file must be an image',
            'image.mimes' => 'The image must be of type jpg, jpeg, or png',
            'image.max' => 'The image size must not exceed 2MB',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from all input
        $this->merge([
            'firstname' => trim($this->firstname),
            'lastname' => trim($this->lastname),
            'address' => trim($this->address),
            'city' => trim($this->city),
            'state' => trim($this->state),
            'zip' => trim($this->zip),
        ]);
    }
}
