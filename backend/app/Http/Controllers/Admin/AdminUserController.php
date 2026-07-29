<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $users = User::where('role', 'user')
            ->with('profile')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function show($id, RecommendationService $recommendationService)
    {
        $user = User::with(['profile', 'interactions.kos'])->findOrFail($id);

        $recommendationResult = $recommendationService->getRecommendations((int) $id, 10);

        return view('admin.users.show', [
            'targetUser' => $user,
            'recommendationResult' => $recommendationResult,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:user,admin',
        ]);

        $user = User::findOrFail($id);

        if ((int) $id === Auth::id() && $request->role !== 'admin') {
            return back()->withErrors(['role' => 'Tidak bisa mengubah role akun sendiri.']);
        }

        $user->update(['role' => $request->role]);

        return back()->with('success', 'Role pengguna berhasil diperbarui.');
    }

    public function destroy($id)
    {
        if ((int) $id === Auth::id()) {
            return back()->withErrors(['delete' => 'Tidak bisa menghapus akun sendiri.']);
        }

        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('admin.users')->with('success', 'Pengguna berhasil dihapus.');
    }
}
