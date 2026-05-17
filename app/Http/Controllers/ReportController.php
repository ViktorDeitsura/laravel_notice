<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateReportRequest;
use App\Models\ReportRequest;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * @var ReportService
     */
    private $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function store(CreateReportRequest $request): JsonResponse
    {
        $reportRequest = $this->reportService->createAndDispatch($request->validated());

        return response()->json([
            'id' => $reportRequest->id,
            'status' => $reportRequest->status,
        ], 201);
    }

    public function status(ReportRequest $reportRequest): JsonResponse
    {
        return response()->json($this->reportService->getStatus($reportRequest));
    }

    public function download(ReportRequest $reportRequest): Response
    {
        if ($reportRequest->status !== ReportRequest::STATUS_READY) {
            return response()->json([
                'message' => 'Report is not ready yet.',
            ], 409);
        }

        if (! $reportRequest->file_path || ! Storage::disk('local')->exists($reportRequest->file_path)) {
            return response()->json([
                'message' => 'Report file not found.',
            ], 404);
        }

        return Storage::disk('local')->download($reportRequest->file_path);
    }
}
