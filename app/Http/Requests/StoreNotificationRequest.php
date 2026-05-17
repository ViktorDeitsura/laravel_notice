<?php

namespace App\Http\Requests;

use App\Models\Notification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'channel' => ['required', 'string', Rule::in([Notification::CHANNEL_EMAIL, Notification::CHANNEL_TELEGRAM])],
            'message' => ['required', 'string', 'max:500'],
        ];
    }
}
