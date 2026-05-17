<?php

namespace App\Http\Requests;

use App\Models\Notification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationHistoryRequest extends FormRequest
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
            'status' => ['sometimes', 'string', Rule::in([
                Notification::STATUS_PROCESSING,
                Notification::STATUS_SENT,
                Notification::STATUS_ERROR,
            ])],
            'channel' => ['sometimes', 'string', Rule::in([
                Notification::CHANNEL_EMAIL,
                Notification::CHANNEL_TELEGRAM,
            ])],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
