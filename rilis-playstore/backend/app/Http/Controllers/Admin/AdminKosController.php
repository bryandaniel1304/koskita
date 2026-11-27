<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kos;
use App\Models\KosImage;
use App\Models\Facility;
use App\Models\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AdminKosController extends Controller
{


    public function index(Request $request)
    {
        $query = Kos::with('facilities', 'rules', 'images', 'owner')->latest();

        if ($request->filled('owner_id')) {
            if ($request->owner_id === 'none') {
                $query->whereNull('owner_id');
            } else {
                $query->where('owner_id', $request->owner_id);
            }
        }

        $koses = $query->get();
        $owners = \App\Models\User::where('role', 'owner')->orderBy('name')->get(['id', 'name']);

        return view('admin.koses.index', compact('koses', 'owners'));
    }

    /**
     * Export data kos ke CSV -- buat laporan/lampiran skripsi tanpa perlu
     * screenshot tabel. Streaming (bukan simpan ke file dulu) supaya aman
     * dipakai walau data sudah ratusan baris.
     */
    public function exportCsv()
    {
        $koses = Kos::with('facilities', 'rules', 'owner')->latest()->get();

        $filename = 'kos-koskita-' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($koses) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Nama', 'Lokasi', 'Harga', 'Tipe', 'Total Kamar', 'Kamar Terisi', 'Latitude', 'Longitude', 'Pemilik', 'Fasilitas', 'Aturan', 'Dibuat']);
            foreach ($koses as $kos) {
                fputcsv($out, [
                    $kos->id,
                    $kos->name,
                    $kos->location,
                    $kos->price,
                    $kos->gender_type,
                    $kos->total_rooms,
                    $kos->occupied_rooms,
                    $kos->latitude,
                    $kos->longitude,
                    $kos->owner?->name ?? '-',
                    $kos->facilities->pluck('name')->implode('; '),
                    $kos->rules->pluck('name')->implode('; '),
                    $kos->created_at?->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create()
    {
        $facilities = Facility::all();
        $rules = Rule::all();
        return view('admin.koses.create', compact('facilities', 'rules'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'gender_type' => 'required|string|in:putra,putri,campur',
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'distance_to_campus' => 'required|numeric|min:0',
            'total_rooms' => 'required|integer|min:1|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url|max:2048',
            'facilities' => 'nullable|array',
            'rules' => 'nullable|array',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:4096',
        ]);

        $kos = Kos::create([
            'name' => $request->name,
            'price' => $request->price,
            'gender_type' => $request->gender_type,
            'location' => $request->location,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'distance_to_campus' => $request->distance_to_campus,
            'total_rooms' => $request->total_rooms,
            'description' => $request->description,
            'image_url' => $request->image_url ?? 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?auto=format&fit=crop&w=500&q=80',
        ]);

        if ($request->has('facilities')) {
            $kos->facilities()->sync($request->facilities);
        }

        if ($request->has('rules')) {
            $kos->rules()->sync($request->rules);
        }

        $this->storeUploadedPhotos($kos, $request);

        return redirect()->route('admin.koses.index')->with('success', 'Data kos berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $kos = Kos::with('facilities', 'rules', 'images', 'owner')->findOrFail($id);
        $facilities = Facility::all();
        $rules = Rule::all();
        return view('admin.koses.edit', compact('kos', 'facilities', 'rules'));
    }

    public function update(Request $request, $id)
    {
        $kos = Kos::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'gender_type' => 'required|string|in:putra,putri,campur',
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'distance_to_campus' => 'required|numeric|min:0',
            // Tidak boleh diset lebih kecil dari jumlah kamar yang sedang
            // terisi (booking confirmed) -- akan bikin data tidak masuk akal
            // (mis. 3 kamar terisi tapi total kamar cuma 2).
            'total_rooms' => 'required|integer|min:' . max(1, $kos->occupied_rooms) . '|max:255',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url|max:2048',
            'facilities' => 'nullable|array',
            'rules' => 'nullable|array',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:4096',
        ], [
            'total_rooms.min' => 'Jumlah kamar tidak boleh lebih kecil dari jumlah kamar yang sedang terisi (' . $kos->occupied_rooms . ').',
        ]);

        $kos->update([
            'name' => $request->name,
            'price' => $request->price,
            'gender_type' => $request->gender_type,
            'location' => $request->location,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'distance_to_campus' => $request->distance_to_campus,
            'total_rooms' => $request->total_rooms,
            'description' => $request->description,
            'image_url' => $request->image_url ?? $kos->image_url,
        ]);

        // Sync facilities
        $kos->facilities()->sync($request->facilities ?? []);

        // Sync rules
        $kos->rules()->sync($request->rules ?? []);

        $this->storeUploadedPhotos($kos, $request);

        return redirect()->route('admin.koses.index')->with('success', 'Data kos berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $kos = Kos::findOrFail($id);
        $kos->delete();
        return redirect()->route('admin.koses.index')->with('success', 'Data kos berhasil dihapus.');
    }

    public function destroyImage($kosId, $imageId)
    {
        $image = KosImage::where('kos_id', $kosId)->findOrFail($imageId);
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Foto berhasil dihapus.');
    }

    /**
     * Badge "Kos Terverifikasi" -- admin mengonfirmasi data/foto kos sesuai
     * kondisi asli. Toggle sederhana (bukan alur tinjau berjenjang seperti
     * verifikasi pemilik) karena keputusannya ada di tangan admin sendiri,
     * bukan menunggu dokumen dari pihak lain.
     */
    public function toggleVerified($id)
    {
        $kos = Kos::findOrFail($id);
        $kos->update(['verified_at' => $kos->verified_at ? null : now()]);

        return back()->with('success', $kos->verified_at ? 'Kos ditandai terverifikasi.' : 'Status verifikasi kos dicabut.');
    }

    /**
     * Simpan file foto yang diunggah admin ke storage/app/public/kos-images
     * dan buat baris kos_images. Foto pertama yang di-upload untuk kos yang
     * belum punya cover otomatis dijadikan cover.
     */
    protected function storeUploadedPhotos(Kos $kos, Request $request): void
    {
        if (!$request->hasFile('photos')) {
            return;
        }

        $hasCover = $kos->images()->where('is_cover', true)->exists();
        $nextOrder = $kos->images()->max('sort_order') + 1;

        foreach ($request->file('photos') as $file) {
            $path = $file->store('kos-images', 'public');

            KosImage::create([
                'kos_id' => $kos->id,
                'path' => $path,
                'is_cover' => !$hasCover,
                'sort_order' => $nextOrder++,
            ]);

            $hasCover = true;
        }
    }
}
