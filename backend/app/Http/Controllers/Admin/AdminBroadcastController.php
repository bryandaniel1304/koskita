<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminBroadcastController extends Controller
{
    public function index()
    {
        $broadcasts = AdminBroadcast::with('creator:id,name')->latest()->paginate(20);

        return view('admin.broadcasts.index', compact('broadcasts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'message' => 'required|string|max:1000',
            'target_role' => 'nullable|in:user,owner',
        ]);

        AdminBroadcast::create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.broadcasts.index')->with('success', 'Pengumuman berhasil dikirim.');
    }

    public function destroy($id)
    {
        AdminBroadcast::findOrFail($id)->delete();

        return redirect()->route('admin.broadcasts.index')->with('success', 'Pengumuman dihapus.');
    }
}
