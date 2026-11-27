<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Rule;
use App\Models\User;
use App\Models\UserInteraction;
use App\Models\UserProfile;
use App\Services\RecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule as ValidationRule;

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

        $editMode = $role === 'user' && $request->query('mode') === 'edit';
        $locations = self::LOCATIONS;

        return view('admin.users.index', compact('users', 'search', 'role', 'editMode', 'locations'));
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

        $filename = ($role === 'owner' ? 'pemilik' : 'penyewa') . '-koskita-' . now()->format('Y-m-d_H-i-s') . '.csv';

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

        // Profil awal dari registrasi berisi nilai bawaan (completed_at null),
        // bukan pilihan pengguna -- vektor preferensinya belum terbentuk,
        // jadi rekomendasi tidak dihitung (alur alternatif UC-45).
        $recommendationResult = null;
        if ($user->role === 'user' && $user->profile?->completed_at !== null) {
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
            'is_master_admin' => 'nullable|boolean',
        ]);

        $user = User::findOrFail($id);

        if ((int) $id === Auth::id() && $request->role !== 'admin') {
            return back()->withErrors(['role' => 'Tidak bisa mengubah role akun sendiri.']);
        }

        // Tidak boleh melepas status master admin dari diri sendiri --
        // cegah admin terkunci dari menu Kelola Pengguna/Pengumuman/Laporan
        // tanpa ada master admin lain yang bisa mengembalikannya.
        if ((int) $id === Auth::id() && Auth::user()->isMasterAdmin() && !$request->boolean('is_master_admin')) {
            return back()->withErrors(['is_master_admin' => 'Tidak bisa melepas status Master Admin dari akun sendiri.']);
        }

        $user->update([
            'role' => $request->role,
            // Flag ini cuma relevan buat role=admin -- otomatis dimatikan
            // kalau role diturunkan dari admin, supaya tidak ada user/owner
            // biasa yang "diam-diam" tercatat sebagai master admin.
            'is_master_admin' => $request->role === 'admin' ? $request->boolean('is_master_admin') : false,
        ]);

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

    /** Area yang sama dengan pilihan di layar profil aplikasi. */
    public const LOCATIONS = ['Karawaci', 'BSD', 'Serpong', 'Alam Sutera'];

    /** @return array<int, string> area baku + area lama pengguna bila di luar daftar */
    private static function locationOptions(User $user): array
    {
        $current = $user->profile?->preferred_location;

        return $current && !in_array($current, self::LOCATIONS, true)
            ? [...self::LOCATIONS, $current]
            : self::LOCATIONS;
    }

    public function edit($id)
    {
        $user = User::with('profile')->findOrFail($id);

        // Pilihan = master data + nilai lama di profil yang mungkin sudah
        // tidak ada di master, supaya menyimpan form tidak diam-diam membuangnya.
        $facilityOptions = Facility::orderBy('name')->pluck('name')
            ->merge($user->profile?->preferred_facilities ?? [])->unique()->values();
        $ruleOptions = Rule::orderBy('name')->pluck('name')
            ->merge($user->profile?->preferred_rules ?? [])->unique()->values();

        return view('admin.users.edit', [
            'targetUser' => $user,
            'facilityOptions' => $facilityOptions,
            'ruleOptions' => $ruleOptions,
            'locations' => self::locationOptions($user),
        ]);
    }

    /**
     * Ubah data akun (nama, email, HP) dan -- untuk penyewa -- profil
     * preferensinya. Aturan validasi profil sama dengan
     * Api\AuthController::updateProfile.
     */
    public function updateData(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', ValidationRule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
        ];
        if ($user->role === 'user') {
            $rules += [
                'gender' => 'required|in:pria,wanita',
                'occupation' => 'required|in:mahasiswa,pekerja',
                'preferred_location' => ['required', ValidationRule::in(self::locationOptions($user))],
                'budget_min' => 'required|integer|min:0',
                'budget_max' => 'required|integer|gte:budget_min',
                'preferred_facilities' => 'nullable|array',
                'preferred_facilities.*' => 'string|max:255',
                'preferred_rules' => 'nullable|array',
                'preferred_rules.*' => 'string|max:255',
            ];
        }
        $validated = $request->validate($rules);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        if ($user->role === 'user') {
            $profile = $user->profile ?? new UserProfile(['user_id' => $user->id]);
            $profile->fill([
                'gender' => $validated['gender'],
                'occupation' => $validated['occupation'],
                'preferred_location' => $validated['preferred_location'],
                'budget_min' => $validated['budget_min'],
                'budget_max' => $validated['budget_max'],
                'preferred_facilities' => array_values($validated['preferred_facilities'] ?? []),
                'preferred_rules' => array_values($validated['preferred_rules'] ?? []),
            ]);
            // Preferensi yang diisikan admin dianggap profil lengkap, supaya
            // rekomendasinya ikut terhitung (lihat show()).
            $profile->completed_at ??= now();
            $profile->save();
        }

        return redirect()->route('admin.users.show', $user->id)->with('success', 'Data ' . $user->name . ' berhasil diperbarui.');
    }

    /**
     * Simpan satu sel dari mode "Edit Tabel" di daftar responden (dipanggil
     * lewat fetch tiap kali sel diubah). Aturan validasinya sama dengan
     * updateData(), hanya per kolom.
     */
    public function updateCell(Request $request, $id)
    {
        $user = User::with('profile')->where('role', 'user')->findOrFail($id);
        $profile = $user->profile ?? new UserProfile(['user_id' => $user->id]);

        $field = $request->input('field');
        $rules = match ($field) {
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', ValidationRule::unique('users', 'email')->ignore($user->id)],
            'gender' => 'required|in:pria,wanita',
            'occupation' => 'required|in:mahasiswa,pekerja',
            'preferred_location' => ['required', ValidationRule::in(self::locationOptions($user))],
            'budget_min', 'budget_max' => 'required|integer|min:0|max:100000000',
            default => null,
        };
        if ($rules === null) {
            return response()->json(['message' => 'Kolom tidak bisa diubah.'], 422);
        }

        $validator = validator($request->only('value'), ['value' => $rules], [
            'value.required' => 'Tidak boleh kosong.',
            'value.in' => 'Pilihan tidak valid.',
            'value.string' => 'Isian tidak valid.',
            'value.max' => str_starts_with((string) $field, 'budget') ? 'Budget terlalu besar.' : 'Isian terlalu panjang.',
            'value.email' => 'Format email tidak valid.',
            'value.unique' => 'Email sudah dipakai akun lain.',
            'value.integer' => 'Harus angka bulat.',
            'value.min' => 'Tidak boleh negatif.',
        ]);
        if ($validator->fails()) {
            $error = $validator->errors()->first('value');

            return response()->json(['message' => $error, 'errors' => ['value' => [$error]]], 422);
        }
        $value = $validator->validated()['value'];

        $error = match (true) {
            $field === 'budget_min' && $profile->budget_max !== null && (int) $value > $profile->budget_max => 'Budget min tidak boleh melebihi budget maks.',
            $field === 'budget_max' && $profile->budget_min !== null && (int) $value < $profile->budget_min => 'Budget maks tidak boleh kurang dari budget min.',
            default => null,
        };
        if ($error) {
            return response()->json(['message' => $error, 'errors' => ['value' => [$error]]], 422);
        }

        if (in_array($field, ['name', 'email'], true)) {
            $user->update([$field => $value]);
        } else {
            $profile->{$field} = $value;
            $profile->save();
        }

        return response()->json(['message' => 'Tersimpan.', 'value' => $value]);
    }

    /** Hapus satu interaksi (rating/favorit/klik) milik pengguna. */
    public function destroyInteraction($userId, $interactionId)
    {
        $interaction = UserInteraction::where('user_id', $userId)->findOrFail($interactionId);
        $interaction->delete();

        return back()->with('success', 'Interaksi berhasil dihapus.');
    }
}
