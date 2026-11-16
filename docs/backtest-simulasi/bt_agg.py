import json, glob, statistics as st, sys
D = sys.argv[1]
runs = [json.load(open(f)) for f in sorted(glob.glob(D + '/bt_*.json'))]
print('seed:', [r['seed'] for r in runs])
for k in ['ratings', 'relevant', 'warm_eligible', 'cold_eligible']:
    v = [r['stats'][k] for r in runs]; print(f'{k}: rata2 {st.mean(v):.0f} (min {min(v)}, max {max(v)})')
def ms(v): return f'{st.mean(v):.3f}±{st.stdev(v):.3f}'
for sc in ['warm', 'cold']:
    print(f'\n== {sc.upper()}-START ==')
    print(f"{'strategi':<11}{'n':>5}  " + '  '.join(f'{m}@{K:<3}'.ljust(13) for K in (5, 10) for m in ('P', 'R', 'NDCG', 'MAP')))
    for i, name in enumerate(['hybrid', 'cb_only', 'cf_only', 'popularity']):
        rows = [r[sc][i] for r in runs]
        n = st.mean(x['users_evaluated'] for x in rows)
        cells = [ms([x['metrics_by_k'][str(K)][m] for x in rows]) for K in ('5', '10') for m in ('precision', 'recall', 'ndcg', 'map')]
        print(f'{name:<11}{n:>5.0f}  ' + '  '.join(c.ljust(13) for c in cells))
print('\n== ALPHA (warm, NDCG@10 / P@10 / R@10) ==')
for i, a in enumerate([0.2, .3, .4, .5, .6, .7, .8]):
    rows = [r['alphas'][i]['metrics_by_k']['10'] for r in runs]
    print(f'alpha {a}: NDCG {ms([x["ndcg"] for x in rows])}  P {ms([x["precision"] for x in rows])}  R {ms([x["recall"] for x in rows])}')
