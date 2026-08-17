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
        $role = $request->query('role', 'user');

        if (!in_array($role, ['user', 'owner'])) {
            $role = 'user';
        }

        $users = User::where('role', $role)
            ->with(['profile', 'koses'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search', 'role'));
    }

    /**
     * Export data responden atau pemilik ke CSV -- laporan buat skripsi
     * (mis. lampiran daftar responden/pemilik), TIDAK menyertakan password/token.
     */
    public function exportCsv(Request $request)
    {
        $role = $request->query('role', 'user');
        if (!in_array($role, ['user', 'owner'])) {
            $role = 'user';
        }

        $users = User::where('role', $role)->with(['profile', 'koses'])->latest()->get();

        $filename = ($role === 'owner' ? 'pemilik' : 'responden') . '-koskita-' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($users, $role) {
            $out = fopen('php://output', 'w');
            if ($role === 'owner') {
                fputcsv($out, ['ID', 'Nama', 'Email', 'Nomor HP', 'Status Verifikasi', 'Jumlah Kos', 'Terdaftar']);
                foreach ($users as $user) {
                    fputcsv($out, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->phone ?? '-',
                        $user->owner_verification_status,
                        $user->koses->count(),
                        $user->created_at?->format('Y-m-d H:i'),
                    ]);
                }
            } else {
                fputcsv($out, ['ID', 'Nama', 'Email', 'Gender', 'Pekerjaan', 'Lokasi Preferensi', 'Budget Min', 'Budget Maks', 'Terdaftar']);
                foreach ($users as $user) {
                    $profile = $user->profile;
                    fputcsv($out, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $profile?->gender ?? '-',
                        $profile?->occupation ?? '-',
                        $profile?->preferred_location ?? '-',
                        $profile?->budget_min ?? '-',
                        $profile?->budget_max ?? '-',
                        $user->created_at?->format('Y-m-d H:i'),
                    ]);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function show($id, RecommendationService $recommendationService)
    {
        $user = User::with(['profile', 'interactions.kos', 'koses'])->findOrFail($id);

        $recommendationResult = null;
        if ($user->role === 'user') {
            $recommendationResult = $recommendationService->getRecommendations((int) $id, 10);
        }

        return view('admin.users.show', [
            'targetUser' => $user,
            'recommendationResult' => $recommendationResult,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:user,owner,admin',
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

    /**
     * Setujui/tolak dokumen verifikasi pemilik yang dikirim lewat aplikasi
     * (lihat Api\OwnerVerificationController::submit) -- badge "Pemilik
     * Terverifikasi" baru muncul begitu status di sini jadi 'approved'.
     */
    public function verifyOwner(Request $request, $id)
    {
        $request->validate([
            'decision' => 'required|in:approved,rejected',
        ]);

        $user = User::findOrFail($id);
        if ($user->role !== 'owner') {
            return back()->withErrors(['verification' => 'Hanya akun Penyedia Kos yang bisa diverifikasi.']);
        }

        $user->update([
            'owner_verification_status' => $request->decision,
            'owner_verified_at' => $request->decision === 'approved' ? now() : null,
        ]);

        return back()->with('success', $request->decision === 'approved'
            ? 'Pemilik berhasil diverifikasi.'
            : 'Verifikasi pemilik ditolak.');
    }
}
