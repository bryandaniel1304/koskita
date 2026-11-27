<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\RecommendationService;
use Illuminate\Http\Request;

class WebRecommendationController extends Controller
{
    public function index(Request $request, RecommendationService $service)
    {
        $result = $service->getRecommendations($request->user()->id, 12);

        return view('web.recommendations.index', $result);
    }
}
