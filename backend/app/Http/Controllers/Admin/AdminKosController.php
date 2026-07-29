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


    public function index()
    {
        $koses = Kos::with('facilities', 'rules', 'images')->latest()->get();
        return view('admin.koses.index', compact('koses'));
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
            'distance_to_campus' => 'required|numeric|min:0',
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
            'distance_to_campus' => $request->distance_to_campus,
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
        $kos = Kos::with('facilities', 'rules', 'images')->findOrFail($id);
        $facilities = Facility::all();
        $rules = Rule::all();
        return view('admin.koses.edit', compact('kos', 'facilities', 'rules'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'gender_type' => 'required|string|in:putra,putri,campur',
            'location' => 'required|string|max:255',
            'distance_to_campus' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url|max:2048',
            'facilities' => 'nullable|array',
            'rules' => 'nullable|array',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:4096',
        ]);

        $kos = Kos::findOrFail($id);
        $kos->update([
            'name' => $request->name,
            'price' => $request->price,
            'gender_type' => $request->gender_type,
            'location' => $request->location,
            'distance_to_campus' => $request->distance_to_campus,
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
