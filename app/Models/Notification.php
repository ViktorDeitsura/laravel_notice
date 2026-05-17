<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_ERROR = 'error';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_TELEGRAM = 'telegram';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'channel',
        'message',
        'status',
        'error_message',
        'attempts',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function scopeForHistory(Builder $query, int $userId, array $filters = []): Builder
    {
        return $query->where('user_id', $userId)
            ->when(isset($filters['status']), fn (Builder $q): Builder => $q->where('status', (string) $filters['status']))
            ->when(isset($filters['channel']), fn (Builder $q): Builder => $q->where('channel', (string) $filters['channel']))
            ->when(isset($filters['date_from']), fn (Builder $q): Builder => $q->whereDate('created_at', '>=', (string) $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $q): Builder => $q->whereDate('created_at', '<=', (string) $filters['date_to']));
    }
}
