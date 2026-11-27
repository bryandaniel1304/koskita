{{-- Baris "belum ada data" seragam untuk semua tabel admin -- ikon + pesan,
     dipakai lewat @include('admin.partials.empty-row', ['colspan' => N, 'icon' => 'bi-...', 'text' => '...']) --}}
<tr>
    <td colspan="{{ $colspan }}" class="text-center py-5">
        <i class="bi {{ $icon ?? 'bi-inbox' }} d-block mb-2" style="font-size: 2rem; color: #CBD5E1;"></i>
        <span class="text-muted">{{ $text }}</span>
    </td>
</tr>
