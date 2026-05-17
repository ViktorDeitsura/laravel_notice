<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\ReportRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateNotificationReportJob implements ShouldQueue
{
    use Queueable;

    public $tries = 5;

    /**
     * @var list<int>
     */
    public $backoff = [5, 15, 30, 60];

    /**
     * @var int
     */
    private $reportRequestId;

    public function __construct(int $reportRequestId)
    {
        $this->reportRequestId = $reportRequestId;
    }

    public function handle(): void
    {
        $reportRequest = ReportRequest::query()->find($this->reportRequestId);

        if (! $reportRequest) {
            return;
        }

        $reportRequest->forceFill([
            'status' => ReportRequest::STATUS_PROCESSING,
            'error_message' => null,
        ])->save();

        $channelStatusStats = Notification::query()
            ->where('user_id', $reportRequest->user_id)
            ->whereDate('created_at', '>=', $reportRequest->date_from)
            ->whereDate('created_at', '<=', $reportRequest->date_to)
            ->selectRaw('channel, status, COUNT(*) as total')
            ->groupBy('channel', 'status')
            ->get();

        $channels = [
            Notification::CHANNEL_EMAIL,
            Notification::CHANNEL_TELEGRAM,
        ];

        $statsByChannel = [];
        foreach ($channels as $channel) {
            $statsByChannel[$channel] = [
                Notification::STATUS_PROCESSING => 0,
                Notification::STATUS_SENT => 0,
                Notification::STATUS_ERROR => 0,
            ];
        }

        foreach ($channelStatusStats as $stat) {
            $channel = (string) $stat->channel;
            $status = (string) $stat->status;

            if (! isset($statsByChannel[$channel])) {
                $statsByChannel[$channel] = [
                    Notification::STATUS_PROCESSING => 0,
                    Notification::STATUS_SENT => 0,
                    Notification::STATUS_ERROR => 0,
                ];
            }

            if (isset($statsByChannel[$channel][$status])) {
                $statsByChannel[$channel][$status] = (int) $stat->total;
            }
        }

        $lines = ['channel,total,processing,sent,error,date_from,date_to,generated_at'];
        $generatedAt = now()->toDateTimeString();

        foreach ($statsByChannel as $channel => $counts) {
            $total = $counts[Notification::STATUS_PROCESSING]
                + $counts[Notification::STATUS_SENT]
                + $counts[Notification::STATUS_ERROR];

            $lines[] = sprintf(
                '%s,%d,%d,%d,%d,%s,%s,%s',
                $channel,
                $total,
                $counts[Notification::STATUS_PROCESSING],
                $counts[Notification::STATUS_SENT],
                $counts[Notification::STATUS_ERROR],
                $reportRequest->date_from->toDateString(),
                $reportRequest->date_to->toDateString(),
                $generatedAt
            );
        }

        $filePath = sprintf(
            'reports/report_%d_%s.csv',
            $reportRequest->id,
            now()->format('Ymd_His')
        );

        Storage::disk('local')->put($filePath, implode(PHP_EOL, $lines).PHP_EOL);

        $reportRequest->forceFill([
            'status' => ReportRequest::STATUS_READY,
            'file_path' => $filePath,
            'error_message' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $reportRequest = ReportRequest::query()->find($this->reportRequestId);

        if (! $reportRequest) {
            return;
        }

        $reportRequest->forceFill([
            'status' => ReportRequest::STATUS_ERROR,
            'error_message' => $exception ? $exception->getMessage() : null,
        ])->save();
    }
}
