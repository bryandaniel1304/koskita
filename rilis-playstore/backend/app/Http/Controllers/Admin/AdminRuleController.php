<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use Illuminate\Http\Request;

class AdminRuleController extends Controller
{
    public function index()
    {
        $rules = Rule::withCount('koses')->orderBy('id')->get();
        return view('admin.rules.index', compact('rules'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:rules,name',
        ]);

        Rule::create(['name' => $request->name]);

        return back()->with('success', 'Aturan berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $rule = Rule::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:rules,name,' . $rule->id,
        ]);

        $rule->update(['name' => $request->name]);

        return back()->with('success', 'Aturan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $rule = Rule::findOrFail($id);
        $rule->delete();

        return back()->with('success', 'Aturan berhasil dihapus.');
    }
}
