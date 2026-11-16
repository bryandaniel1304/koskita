<?php
/**
 * Backtest SIMULASI 150 pengguna sintetis memakai EvaluationService skripsi.
 * Katalog = 239 kos asli (DB lokal koskita, identik dengan server).
 * Pengguna & rating = SINTETIS (bukan responden). Semua di SQLite sementara.
 *
 * php backtest.php <seed> <jumlah_user> <out.json>
 */
chdir('C:/Users/TRITON 14 AI/Documents/Projects/KosKita/research-dashboard');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Evaluation\EvaluationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$seed = (int) ($argv[1] ?? 42);
$nUsers = (int) ($argv[2] ?? 150);
$out = $argv[3] ?? null;
mt_srand($seed);

// ---------- 1. Ambil katalog asli dari MySQL lokal ----------
$src = DB::connection('source');
$koses = $src->table('koses')->select('id', 'name', 'price', 'gender_type', 'location', 'distance_to_campus')->get();
$facilities = $src->table('facilities')->select('id', 'name')->get();
$rules = $src->table('rules')->select('id', 'name')->get();
$kosFac = $src->table('kos_facility')->select('kos_id', 'facility_id')->get();
$kosRule = $src->table('kos_rule')->select('kos_id', 'rule_id')->get();

// ---------- 2. SQLite sementara sebagai koneksi "source" ----------
$file = sys_get_temp_dir() . "/backtest_{$seed}_" . getmypid() . '.sqlite';
@unlink($file);
touch($file);
config(['database.connections.source' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => false]]);
DB::purge('source');
$s = Schema::connection('source');
$s->create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role')->default('user'); $t->timestamps(); });
$s->create('user_profiles', function (Blueprint $t) {
    $t->id(); $t->unsignedBigInteger('user_id'); $t->string('gender')->nullable(); $t->string('occupation')->nullable();
    $t->integer('budget_min')->nullable(); $t->integer('budget_max')->nullable(); $t->json('preferred_facilities')->nullable();
    $t->json('preferred_rules')->nullable(); $t->string('preferred_location')->nullable(); $t->timestamps();
});
$s->create('koses', function (Blueprint $t) { $t->id(); $t->string('name'); $t->integer('price'); $t->string('gender_type'); $t->string('location'); $t->double('distance_to_campus'); $t->timestamps(); });
foreach (['facilities', 'rules'] as $m) { $s->create($m, function (Blueprint $t) { $t->id(); $t->string('name'); $t->timestamps(); }); }
$s->create('kos_facility', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('kos_id'); $t->unsignedBigInteger('facility_id'); });
$s->create('kos_rule', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('kos_id'); $t->unsignedBigInteger('rule_id'); });
$s->create('user_interactions', function (Blueprint $t) {
    $t->id(); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('kos_id'); $t->integer('rating')->nullable();
    $t->boolean('is_favorite')->default(false); $t->integer('click_count')->default(0); $t->timestamps();
});
$db = DB::connection('source');
$ins = fn ($table, $rows) => collect($rows)->chunk(200)->each(fn ($c) => $db->table($table)->insert($c->values()->all()));
$ins('koses', $koses->map(fn ($k) => (array) $k));
$ins('facilities', $facilities->map(fn ($f) => (array) $f));
$ins('rules', $rules->map(fn ($r) => (array) $r));
$ins('kos_facility', $kosFac->map(fn ($r) => (array) $r));
$ins('kos_rule', $kosRule->map(fn ($r) => (array) $r));

// ---------- 3. Generator pengguna sintetis ----------
$rand = fn () => mt_rand() / mt_getrandmax();
$gauss = function () use ($rand) { return sqrt(-2 * log(max($rand(), 1e-12))) * cos(2 * M_PI * $rand()); };
$pick = fn (array $a) => $a[array_rand($a)];
$sample = function (array $a, int $n) { shuffle($a); return array_slice($a, 0, $n); };

$facByKos = []; foreach ($kosFac as $r) { $facByKos[$r->kos_id][] = $r->facility_id; }
$ruleByKos = []; foreach ($kosRule as $r) { $ruleByKos[$r->kos_id][] = $r->rule_id; }
$facName = $facilities->pluck('name', 'id')->all();
$ruleName = $rules->pluck('name', 'id')->all();
$facPop = array_count_values($kosFac->pluck('facility_id')->all()); arsort($facPop);
$popularFac = array_slice(array_keys($facPop), 0, 12);
$prices = $koses->pluck('price')->all(); $pMin = min($prices); $pMax = max($prices);
$dMax = max(1, $koses->max('distance_to_campus'));
$areas = $koses->pluck('location')->all(); // proporsional jumlah kos per area
$norm = fn ($a) => ($a = strtolower(trim((string) $a))) === 'bsd' ? 'bsd city' : $a;
$genderOk = fn ($kos, $g) => !(($kos->gender_type === 'putri' && $g === 'pria') || ($kos->gender_type === 'putra' && $g === 'wanita'));

// Segmen selera TERSEMBUNYI (tidak ada di profil). Tiap segmen punya bobot
// atribut + "kualitas tak teramati" per kos yang disukai bersama anggota
// segmen -- inilah sinyal yang hanya bisa ditangkap CF, bukan CB.
$segments = [];
foreach (range(0, 4) as $sg) {
    $hidden = [];
    foreach ($koses as $k) { $hidden[$k->id] = $gauss(); }
    $segments[$sg] = ['cheap' => $rand() * 2 - 1, 'near' => $rand(), 'rich' => $rand(), 'hidden' => $hidden];
}

$now = now();
$userRows = []; $profileRows = []; $interRows = [];
$stats = ['ratings' => 0, 'relevant' => 0];
for ($u = 1; $u <= $nUsers; $u++) {
    $g = $rand() < 0.5 ? 'pria' : 'wanita';
    $area = $pick($areas);
    $inArea = $koses->filter(fn ($k) => $norm($k->location) === $norm($area) && $genderOk($k, $g))->values();
    $anchor = $inArea[mt_rand(0, $inArea->count() - 1)];
    $bMin = (int) (floor($anchor->price * (0.6 + 0.35 * $rand()) / 250000) * 250000);
    $bMax = (int) (ceil($anchor->price * (1.05 + 0.45 * $rand()) / 250000) * 250000);
    $statedFac = $sample($popularFac, mt_rand(2, 5));
    $statedRule = $sample(array_keys($ruleName), mt_rand(0, 2));
    $seg = $segments[mt_rand(0, 4)];

    $userRows[] = ['id' => $u, 'name' => "sintetis $u", 'role' => 'user', 'created_at' => $now, 'updated_at' => $now];
    $profileRows[] = [
        'user_id' => $u, 'gender' => $g, 'budget_min' => $bMin, 'budget_max' => $bMax, 'preferred_location' => $area,
        'preferred_facilities' => json_encode(array_map(fn ($id) => $facName[$id], $statedFac)),
        'preferred_rules' => json_encode(array_map(fn ($id) => $ruleName[$id], $statedRule)),
        'created_at' => $now, 'updated_at' => $now,
    ];

    // Utilitas sebenarnya (ground truth sintetis).
    $utility = function ($k) use ($g, $area, $bMin, $bMax, $statedFac, $statedRule, $seg, $facByKos, $ruleByKos, $pMin, $pMax, $dMax, $norm, $genderOk, $gauss) {
        $f = $facByKos[$k->id] ?? []; $r = $ruleByKos[$k->id] ?? [];
        $facMatch = count($statedFac) ? count(array_intersect($statedFac, $f)) / count($statedFac) : 0;
        $ruleMatch = count($statedRule) ? count(array_intersect($statedRule, $r)) / count($statedRule) : 0.5;
        $cheap = 1 - ($k->price - $pMin) / max(1, $pMax - $pMin);
        $near = 1 - min(1, $k->distance_to_campus / $dMax);
        $rich = min(1, count($f) / 15);
        $segScore = ($seg['cheap'] * ($cheap - 0.5) + $seg['near'] * $near + $seg['rich'] * $rich) / 2;
        $u = 0.35 * $facMatch + 0.10 * $ruleMatch + 0.20 * $segScore + 0.30 * (1 / (1 + exp(-$seg['hidden'][$k->id]))) + 0.08 * $gauss();
        if (!$genderOk($k, $g)) { $u -= 0.6; }
        if ($norm($k->location) !== $norm($area)) { $u -= 0.35; }
        if ($k->price < $bMin || $k->price > $bMax) { $u -= 0.25; }
        return $u;
    };

    // Paparan: 70% dari kos yang memenuhi preferensi, sisanya jelajah bebas.
    $feasible = $koses->filter(fn ($k) => $genderOk($k, $g) && $norm($k->location) === $norm($area) && $k->price >= $bMin && $k->price <= $bMax)->values()->all();
    $n = mt_rand(6, 15);
    $nFeas = min(count($feasible), (int) round($n * 0.7));
    $seen = array_merge($sample($feasible, $nFeas), $sample($koses->all(), $n - $nFeas));
    $seenIds = [];
    $t = 0;
    foreach ($seen as $k) {
        if (isset($seenIds[$k->id])) { continue; }
        $seenIds[$k->id] = true;
        $val = $utility($k);
        // Peta utilitas -> rating 1..5 (ambang dikalibrasi sekali, sama untuk semua user).
        $rating = $val >= 0.55 ? 5 : ($val >= 0.45 ? 4 : ($val >= 0.33 ? 3 : ($val >= 0.15 ? 2 : 1)));
        $interRows[] = ['user_id' => $u, 'kos_id' => $k->id, 'rating' => $rating, 'click_count' => 1, 'created_at' => $now->copy()->addMinutes($u * 100 + $t), 'updated_at' => $now];
        $t++;
        $stats['ratings']++; $stats['relevant'] += $rating >= 4 ? 1 : 0;
    }
}
$ins('users', $userRows);
$ins('user_profiles', $profileRows);
$ins('user_interactions', $interRows);

$dist = array_count_values(array_column($interRows, 'rating')); ksort($dist);
$byUser = collect($interRows)->groupBy('user_id');
$stats += [
    'users' => $nUsers,
    'rating_dist' => $dist,
    'warm_eligible' => $byUser->filter(fn ($rows) => $rows->where('rating', '>=', 4)->count() >= 2)->count(),
    'cold_eligible' => $byUser->filter(fn ($rows) => $rows->where('rating', '>=', 4)->count() >= 1)->count(),
];

// ---------- 4. Evaluasi dengan kode skripsi ----------
$svc = new EvaluationService();
$K = [5, 10];
$result = [
    'seed' => $seed, 'stats' => $stats,
    'warm' => $svc->compareBaselines(0.6, $K, EvaluationService::SCENARIO_WARM),
    'cold' => $svc->compareBaselines(0.6, $K, EvaluationService::SCENARIO_COLD),
    'alphas' => $svc->compareAlphas([0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8], $K),
];
DB::disconnect('source');
@unlink($file);

$json = json_encode($result, JSON_PRETTY_PRINT);
if ($out) { file_put_contents($out, $json); }
echo json_encode($stats), PHP_EOL;
foreach (['warm', 'cold'] as $sc) {
    foreach ($result[$sc] as $r) {
        $m = $r['metrics_by_k'];
        printf("%-5s %-10s n=%3d  P@5=%.3f R@5=%.3f NDCG@5=%.3f MAP@5=%.3f | P@10=%.3f R@10=%.3f NDCG@10=%.3f MAP@10=%.3f\n",
            $sc, $r['strategy'], $r['users_evaluated'], $m[5]['precision'], $m[5]['recall'], $m[5]['ndcg'], $m[5]['map'],
            $m[10]['precision'], $m[10]['recall'], $m[10]['ndcg'], $m[10]['map']);
    }
}
foreach ($result['alphas'] as $r) { printf("alpha %.1f  NDCG@10=%.3f P@10=%.3f\n", $r['alpha'], $r['metrics_by_k'][10]['ndcg'], $r['metrics_by_k'][10]['precision']); }
