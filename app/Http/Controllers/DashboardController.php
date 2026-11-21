<?php

namespace App\Http\Controllers;

use App\Models\AnalysisJob;
use App\Models\Website;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $stats = [
            'projects' => $user->projects()->count(),
            'websites' => Website::where('user_id', $user->id)->count(),
            'completed' => AnalysisJob::where('user_id', $user->id)->where('status', AnalysisJob::STATUS_COMPLETED)->count(),
            'pending' => AnalysisJob::where('user_id', $user->id)->whereIn('status', AnalysisJob::ACTIVE_STATUSES)->count(),
            'failed' => AnalysisJob::where('user_id', $user->id)->where('status', AnalysisJob::STATUS_FAILED)->count(),
        ];

        $latestJob = AnalysisJob::with('website')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $projects = $user->projects()
            ->withCount('websites')
            ->latest('id')
            ->take(5)
            ->get();

        return view('dashboard', [
            'stats' => $stats,
            'latestJob' => $latestJob,
            'projects' => $projects,
        ]);
    }
}
