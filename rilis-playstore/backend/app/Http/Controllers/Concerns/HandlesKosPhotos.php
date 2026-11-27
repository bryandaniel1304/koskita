<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Kos;
use App\Models\KosImage;
use Illuminate\Http\Request;

/**
 * Dipakai bareng oleh AdminKosController dan OwnerKosController -- keduanya
 * butuh logika upload foto kos yang identik.
 */
trait HandlesKosPhotos
{
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
