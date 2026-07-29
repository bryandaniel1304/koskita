<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\Request;

class AdminFacilityController extends Controller
{
    public function index()
    {
        $facilities = Facility::withCount('koses')->orderBy('id')->get();
        return view('admin.facilities.index', compact('facilities'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:facilities,name',
        ]);

        Facility::create(['name' => $request->name]);

        return back()->with('success', 'Fasilitas berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $facility = Facility::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:facilities,name,' . $facility->id,
        ]);

        $facility->update(['name' => $request->name]);

        return back()->with('success', 'Fasilitas berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $facility = Facility::findOrFail($id);
        $facility->delete();

        return back()->with('success', 'Fasilitas berhasil dihapus.');
    }
}
