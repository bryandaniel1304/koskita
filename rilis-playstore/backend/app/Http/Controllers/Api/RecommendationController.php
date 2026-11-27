<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecommendationService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    protected $recommendationService;

    public function __construct(RecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $limit = $request->input('limit', 10);
        
        $recommendations = $this->recommendationService->getRecommendations($user->id, $limit);

        return response()->json($recommendations);
    }
}
