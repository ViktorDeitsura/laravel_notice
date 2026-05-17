<?php

namespace App\Services\Reports;

use App\Jobs\GenerateNotificationReportJob;
use App\Models\ReportRequest;

class ReportService
{
    public function createAndDispatch(array $payload): ReportRequest
    {
        $reportRequest = ReportRequest::query()->create([
            'user_id' => $payload['user_id'],
            'date_from' => $payload['date_from'],
            'date_to' => $payload['date_to'],
            'status' => ReportRequest::STATUS_PENDING,
        ]);

        GenerateNotificationReportJob::dispatch($reportRequest->id);

        return $reportRequest;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(ReportRequest $reportRequest): array
    {
        return [
            'id' => $reportRequest->id,
            'status' => $reportRequest->status,
            'file_path' => $reportRequest->status === ReportRequest::STATUS_READY ? $reportRequest->file_path : null,
            'error_message' => $reportRequest->error_message,
        ];
    }
}
