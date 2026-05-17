<?php

namespace Tests\Feature;

use App\Jobs\GenerateNotificationReportJob;
use App\Models\ReportRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_report_request_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/reports', [
            'user_id' => 23,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-08',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', ReportRequest::STATUS_PENDING);

        $this->assertDatabaseHas('report_requests', [
            'id' => $response->json('id'),
            'user_id' => 23,
            'status' => ReportRequest::STATUS_PENDING,
        ]);

        Queue::assertPushed(GenerateNotificationReportJob::class);
    }

    public function test_report_status_endpoint_returns_current_status(): void
    {
        $report = ReportRequest::query()->create([
            'user_id' => 11,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-08',
            'status' => ReportRequest::STATUS_PROCESSING,
        ]);

        $this->getJson("/api/reports/{$report->id}/status")
            ->assertOk()
            ->assertJsonPath('id', $report->id)
            ->assertJsonPath('status', ReportRequest::STATUS_PROCESSING)
            ->assertJsonPath('file_path', null);
    }

    public function test_report_download_returns_conflict_when_not_ready(): void
    {
        $report = ReportRequest::query()->create([
            'user_id' => 11,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-08',
            'status' => ReportRequest::STATUS_PENDING,
        ]);

        $this->getJson("/api/reports/{$report->id}/download")
            ->assertStatus(409);
    }

    public function test_report_download_returns_file_when_ready(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('reports/report_1.csv', "channel,total\nemail,1\n");

        $report = ReportRequest::query()->create([
            'user_id' => 11,
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-08',
            'status' => ReportRequest::STATUS_READY,
            'file_path' => 'reports/report_1.csv',
        ]);

        $response = $this->get("/api/reports/{$report->id}/download");

        $response->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('content-type'));
    }
}
