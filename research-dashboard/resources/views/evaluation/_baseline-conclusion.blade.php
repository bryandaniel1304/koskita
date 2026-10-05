{{-- Kesimpulan hybrid vs baseline pada K=10, dipakai untuk skenario warm-start maupun cold-start. --}}
<div class="card-custom p-4 mb-4" style="border-left: 4px solid var(--primary);">
    <h6 class="fw-bold mb-2">📝 {{ $title }}</h6>
    <p class="mb-2">
        Model <strong>{{ $hybridLabel }}</strong> unggul pada
        <strong class="{{ $analysis['winning_metrics_count'] === $analysis['total_metrics'] ? 'text-success' : 'text-warning' }}">
            {{ $analysis['winning_metrics_count'] }} dari {{ $analysis['total_metrics'] }} metrik
        </strong> dibandingkan rata-rata baseline pada K=10.
    </p>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Dibandingkan dengan</th><th>Precision</th><th>Recall</th><th>NDCG</th><th>MAP</th></tr></thead>
            <tbody>
            @foreach($analysis['comparisons'] as $c)
                <tr>
                    <td>{{ $c['label'] }}</td>
                    @foreach(['precision', 'recall', 'ndcg', 'map'] as $metric)
                        <td class="fw-bold {{ $c['diffs'][$metric] > 0 ? 'text-success' : ($c['diffs'][$metric] < 0 ? 'text-danger' : 'text-muted') }}">
                            {{ $c['diffs'][$metric] > 0 ? '+' : '' }}{{ number_format($c['diffs'][$metric], 1) }}%
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="small text-muted mt-2 mb-0">Persentase = seberapa besar metrik Hybrid lebih tinggi/rendah dibanding strategi tersebut. Positif (hijau) berarti Hybrid lebih unggul.</p>
    @isset($note)
        <p class="small text-muted mt-2 mb-0">{{ $note }}</p>
    @endisset
</div>
