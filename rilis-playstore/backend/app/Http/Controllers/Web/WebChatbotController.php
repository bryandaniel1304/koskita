<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\OnlineNannyService;
use Illuminate\Http\Request;

/**
 * "Online Nanny" versi situs -- pakai ulang OnlineNannyService yang sama
 * persis dengan app Flutter (Api\ChatbotController), cuma bedanya di sini
 * penggunanya diambil dari sesi browser ($request->user() via guard "web"),
 * bukan token Sanctum. Jawaban & data yang ditarik (kos, rekomendasi) akan
 * selalu sama untuk akun yang sama, karena keduanya baca dari database yang
 * sama persis.
 */
class WebChatbotController extends Controller
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
