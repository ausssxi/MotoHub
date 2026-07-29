#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""4県(兵庫・福岡・静岡・北海道)の schools:import 用CSVを生成し、
ImportDrivingSchools::validateRow のロジックを再現して dry-run counts を予測する。
DBに到達できない環境のための予測であり、実 dry-run は Docker のDB上で確認すること。"""
import json, csv, re, os, sys, unicodedata

HERE = os.path.dirname(os.path.abspath(__file__))
def J(p): return json.load(open(os.path.join(HERE, p), encoding='utf-8'))

VERIFIED = '2026-07-29'
STATUSES = {'open', 'nirin_suspended', 'closed'}
VERIFY = {'human', 'machine'}

SEIREI = ['神戸市', '静岡市', '浜松市', '福岡市', '北九州市', '札幌市']
PREF_PREFIX = ['北海道', '兵庫県', '福岡県', '静岡県']

# source addr が劣化している既知の1件のみ、実在自治体へ補正（静内=日高郡新ひだか町。
# source は「日高郡ひだか町」で 新 が欠落＝実在しない自治体名になるため）。
CITY_FIX = {'日高郡ひだか町': '日高郡新ひだか町'}

def city_of(addr):
    """住所から市区町村を抽出。市優先(郡regex誤射回避)・政令市は市+区・県名混入除去。"""
    a = (addr or '').strip()
    a = unicodedata.normalize('NFKC', a)
    for p in PREF_PREFIX:
        if a.startswith(p):
            a = a[len(p):]
    for s in SEIREI:
        if a.startswith(s):
            m = re.match(re.escape(s) + r'(.+?区)', a)
            return s + (m.group(1) if m else '')
    if '市' in a:
        return a[:a.index('市') + 1]
    m = re.match(r'^(.+?郡.+?[町村])', a)
    if m:
        return CITY_FIX.get(m.group(1), m.group(1))
    m = re.match(r'^(.+?[町村])', a)
    city = m.group(1) if m else ''
    return CITY_FIX.get(city, city)

def norm(s):
    """名前照合用の正規化: 空白除去・ケ/ヶ・全半角。"""
    s = unicodedata.normalize('NFKC', s or '')
    s = s.replace('ヶ', 'ケ').replace('ケ', 'ケ')
    return re.sub(r'\s+', '', s)

# 6校の人手確定 override: name -> (status, source_url)
OVERRIDE = {
    ('hyogo', '尼崎ドライブスクール'): ('nirin_suspended', 'https://www.amagasaki-ds.co.jp/normal_motorcycle'),
    ('hyogo', '兵庫県山陽自動車教習所'): ('nirin_suspended', 'https://sanyo-ds.co.jp/vehicle_type/'),
    ('hyogo', '姫路南自動車学院'): ('closed', 'https://www.himego.co.jp/'),
    ('shizuoka', '静岡菊川自動車学校'): ('nirin_suspended', 'https://shizukiku.com/'),
    ('hokkaido', '小樽中央自動車学校'): ('closed', 'https://www.hokkaidochuo.co.jp/topics/topics-4588'),
    ('hokkaido', '室蘭総合自動車学校'): ('nirin_suspended', 'https://muroran-sogo.com/'),
}

COLUMNS = ['prefecture', 'prefecture_slug', 'city', 'name', 'official_url',
           'futsuu_nirin', 'oogata_nirin', 'source_url', 'verified_at', 'status', 'verify_method']

def validate_row(d):
    """ImportDrivingSchools::validateRow の忠実な再現。エラー理由 or None。"""
    for req in ['prefecture', 'prefecture_slug', 'city', 'name', 'source_url']:
        if d[req] == '':
            return f"{req} が空です"
    if not re.match(r'^[a-z]+$', d['prefecture_slug']):
        return f"prefecture_slug 不正: {d['prefecture_slug']}"
    for flag in ['futsuu_nirin', 'oogata_nirin']:
        if d[flag] not in ('0', '1'):
            return f"{flag} は0/1のみ: {d[flag]}"
    if d['futsuu_nirin'] == '0' and d['oogata_nirin'] == '0':
        return '普通二輪・大型二輪ともに0（二輪非対応校）'
    if d['status'] != '' and d['status'] not in STATUSES:
        return f"status不正: {d['status']}"
    if d['verify_method'] != '' and d['verify_method'] not in VERIFY:
        return f"verify_method不正: {d['verify_method']}"
    return None

def row(pref_name, slug, name, addr, url, f, o, source_url):
    st, vm = 'open', 'machine'
    ov = OVERRIDE.get((slug, name))
    if ov:
        st, vm = ov[0], 'human'
        source_url = ov[1]
    return {
        'prefecture': pref_name, 'prefecture_slug': slug, 'city': city_of(addr),
        'name': name, 'official_url': url or '',
        'futsuu_nirin': '1' if f else '0', 'oogata_nirin': '1' if o else '0',
        'source_url': source_url, 'verified_at': VERIFIED,
        'status': st, 'verify_method': vm,
    }

def build_hyogo():
    data = J('_hyogo_joined.json')
    src = 'https://www.hyogo-dsa.or.jp/'
    rows = []
    for x in data:
        if not (x.get('futsuu') or x.get('oogata')):
            continue
        rows.append(row('兵庫県', 'hyogo', x['name'], x.get('addr', ''), x.get('url'),
                        x.get('futsuu'), x.get('oogata'), src))
    return rows

def build_fukuoka():
    roster = J('fukuoka_roster.json')
    urlmap = {n: u for n, u in [(x['name'], x['url']) for x in J('fukuoka_urlmap.json')]}
    NIRIN_NASHI = {'小倉自動車学校', '城野自動車学校', '田川自動車学校',
                   'ドライビングスクール折尾', '直方自動車学校'}
    F1O0 = {'うきは市立自動車学校', '西鉄自動車学校', '柳川自動車学校', '古賀自動車学校'}
    rows = []
    for x in roster:
        name = x['name']
        if name in NIRIN_NASHI:
            continue
        f = True
        o = name not in F1O0  # f1o0 の4校は大型なし
        url = urlmap.get(name, '')
        src = url  # 大阪型: 各校公式が源
        if name == '古賀自動車学校':
            url = 'https://koga-ds.com/'
            src = 'https://koga-ds.com/price/'
        rows.append(row('福岡県', 'fukuoka', name, x.get('addr', ''), url, f, o, src))
    return rows

def build_shizuoka():
    parsed = J('_shizuoka_kyokai_parsed.json')
    # addr は roster から名前照合で補完
    roster = J('shizuoka_roster.json')
    raddr = {norm(x['name']): x.get('addr', '') for x in roster}
    AREA_URL = 'http://www.shizuoka-shiteikyo.or.jp/school/{}.html'
    rows = []
    unmatched = []
    for x in parsed:
        lic = x.get('license', '') or ''
        f = '普通二輪' in lic
        o = '大型二輪' in lic
        if not (f or o):
            continue
        addr = raddr.get(norm(x['name']), '')
        if addr == '':
            unmatched.append(x['name'])
        area = x.get('area', 'index')
        src = AREA_URL.format(area if area in ('toubu', 'chubu', 'seibu') else 'index')
        rows.append(row('静岡県', 'shizuoka', x['name'], addr, x.get('url'), f, o, src))
    if unmatched:
        print('  [静岡] addr未照合:', unmatched, file=sys.stderr)
    return rows

def build_hokkaido():
    matrix = J('hadsa_matrix.json')
    schools = J('hokkaido_schools.json')
    raddr = {norm(x['name']): x.get('addr', '') for x in schools}
    src = 'https://www.hadsa.or.jp/'
    rows = []
    unmatched = []
    for x in matrix:
        f = bool(x.get('futsu'))
        o = bool(x.get('oogata'))
        if not (f or o):
            continue
        addr = raddr.get(norm(x['name']), '')
        if addr == '':
            unmatched.append(x['name'])
        rows.append(row('北海道', 'hokkaido', x['name'], addr, x.get('official'), f, o, src))
    if unmatched:
        print('  [北海道] addr未照合:', unmatched, file=sys.stderr)
    return rows

BUILDERS = {'hyogo': build_hyogo, 'fukuoka': build_fukuoka,
            'shizuoka': build_shizuoka, 'hokkaido': build_hokkaido}

def main():
    print('=== 4県CSV生成 + 予測counts (validateRow再現) ===\n')
    for slug, fn in BUILDERS.items():
        rows = fn()
        # 書き出し
        out = os.path.join(HERE, f'driving_schools_{slug}.csv')
        with open(out, 'w', newline='', encoding='utf-8') as fh:
            w = csv.DictWriter(fh, fieldnames=COLUMNS)
            w.writeheader()
            for r in rows:
                w.writerow(r)
        # 予測counts
        errors = [(r['name'], validate_row(r)) for r in rows]
        skipped = [(n, e) for n, e in errors if e]
        valid = [r for r in rows if validate_row(r) is None]
        pub = [r for r in valid if r['status'] == 'open']
        susp = [r for r in valid if r['status'] == 'nirin_suspended']
        clos = [r for r in valid if r['status'] == 'closed']
        f1o1 = sum(1 for r in valid if r['futsuu_nirin'] == '1' and r['oogata_nirin'] == '1')
        f1o0 = sum(1 for r in valid if r['futsuu_nirin'] == '1' and r['oogata_nirin'] == '0')
        f0o1 = sum(1 for r in valid if r['futsuu_nirin'] == '0' and r['oogata_nirin'] == '1')
        no_city = [r['name'] for r in rows if r['city'] == '']
        print(f'[{slug}] 行数={len(rows)}  → 新規(valid)={len(valid)} / エラー(skip)={len(skipped)}')
        print(f'    f1o1={f1o1} f1o0={f1o0} f0o1={f0o1}')
        print(f'    公開(open)={len(pub)} / 非公開: nirin_suspended={len(susp)} closed={len(clos)}')
        if susp:
            print('      suspended:', [r['name'] for r in susp])
        if clos:
            print('      closed:', [r['name'] for r in clos])
        if skipped:
            print('    !! skip:', skipped)
        if no_city:
            print('    !! city空:', no_city)
        print(f'    -> {out}')
        print()

if __name__ == '__main__':
    main()
