<?php

namespace App\Http\Controllers\Tech;

use App\Http\Controllers\Controller;
use App\Models\CronJobLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CronJobLogController extends Controller
{
    public function index(Request $request): View
    {
        $jobFilter = $request->string('job')->toString();
        $statusFilter = $request->string('status')->toString();

        $logs = CronJobLog::query()
            ->when($jobFilter, function ($query, $jobFilter) {
                return $query->where('job_name', 'like', '%' . $jobFilter . '%');
            })
            ->when($statusFilter, function ($query, $statusFilter) {
                return $query->where('status', $statusFilter);
            })
            ->orderByDesc('ran_at')
            ->orderByDesc('id')
            ->simplePaginate(25);

        $logs->appends([
            'job' => $jobFilter,
            'status' => $statusFilter,
        ]);

        $knownJobs = CronJobLog::query()
            ->select('job_name')
            ->distinct()
            ->orderBy('job_name')
            ->pluck('job_name')
            ->all();

        $knownStatuses = CronJobLog::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->all();

        return view('tech.cronjobs.index', [
            'logs' => $logs,
            'jobFilter' => $jobFilter,
            'statusFilter' => $statusFilter,
            'knownJobs' => $knownJobs,
            'knownStatuses' => $knownStatuses,
        ]);
    }
}
