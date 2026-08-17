<?php

namespace App\Http\Controllers;

use App\Models\EvaluationRun;
use App\Models\Source\SourceUser;
use App\Models\Source\SourceUserInteraction;
use App\Models\SusResponse;

class DashboardController extends Controller
{
    public function index()
    {
        $respondents = SourceUser::where('role', 'user')->with('profile')->get();
        $totalRespondents = $respondents->count();

        $ratedUserIds = SourceUserInteraction::whereNotNull('rating')->distinct()->pluck('user_id');
        $warmStartCount = $respondents->whereIn('id', $ratedUserIds)->count();
        $coldStartCount = $totalRespondents - $warmStartCount;

        $genderBreakdown = $respondents->pluck('profile.gender')->filter()->countBy();
        $occupationBreakdown = $respondents->pluck('profile.occupation')->filter()->countBy();
        $locationBreakdown = $respondents->pluck('profile.preferred_location')->filter()->countBy();

        $susCount = SusResponse::count();
        $avgSusScore = SusResponse::avg('sus_score');

        $latestBatchLabel = EvaluationRun::max('batch_label');
        $latestRuns = $latestBatchLabel
            ? EvaluationRun::where('batch_label', $latestBatchLabel)->orderBy('k')->orderBy('strategy')->get()
            : collect();

        return view('dashboard', compact(
            'totalRespondents',
            'warmStartCount',
            'coldStartCount',
            'genderBreakdown',
            'occupationBreakdown',
            'locationBreakdown',
            'susCount',
            'avgSusScore',
            'latestRuns'
        ));
    }
}
