<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnlineNannyService;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function respond(Request $request, OnlineNannyService $nanny)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $result = $nanny->respond($request->user(), $request->message);

        return response()->json($result);
    }
}
