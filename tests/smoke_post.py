#!/usr/bin/env python3
"""ローカルサーバ(127.0.0.1:8088)に対し、描画済みフォームをそのまま再送信して保存系ルートを検査する。"""
import html
import os
import re
import sys
import urllib.parse
import urllib.request
import http.cookiejar

BASE = os.environ.get('CANAME_BASE', 'http://127.0.0.1:8088')
jar = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


opn = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect())


def get(path):
    return op.open(BASE + path).read().decode('utf-8')


def post(path, data):
    body = urllib.parse.urlencode(data).encode()
    try:
        r = opn.open(urllib.request.Request(BASE + path, data=body))
        return r.status, r.headers.get('Location', '')
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location', '')


def form_fields(page, action):
    """action を持つ form の input/select/textarea を (name,value) で返す（form属性連結も対応）。"""
    m = re.search(r'<form[^>]*action="[^"]*' + re.escape(action) + r'"[^>]*>(.*?)</form>', page, re.S)
    if not m:
        raise SystemExit(f'form {action} not found')
    tag = m.group(0)[:m.group(0).index('>') + 1]
    fid = re.search(r'id="([^"]+)"', tag)
    frag = m.group(1)
    if fid:
        frag += ''.join(re.findall(r'<(?:input|select|textarea)[^>]*form="' + fid.group(1) + r'"[^>]*>(?:.*?</(?:select|textarea)>)?', page, re.S))
    data = []
    for t in re.finditer(r'<input([^>]*)>', frag):
        a = t.group(1)
        n = re.search(r'name="([^"]*)"', a)
        if not n:
            continue
        if 'type="checkbox"' in a and 'checked' not in a:
            continue
        if 'type="submit"' in a:
            continue
        v = re.search(r'value="([^"]*)"', a)
        data.append((n.group(1), html.unescape(v.group(1)) if v else ''))
    for t in re.finditer(r'<select([^>]*)>(.*?)</select>', frag, re.S):
        n = re.search(r'name="([^"]*)"', t.group(1))
        sel = re.search(r'<option[^>]*value="([^"]*)"[^>]*selected', t.group(2))
        first = re.search(r'<option[^>]*value="([^"]*)"', t.group(2))
        if n:
            data.append((n.group(1), html.unescape((sel or first).group(1)) if (sel or first) else ''))
    for t in re.finditer(r'<textarea([^>]*)>(.*?)</textarea>', frag, re.S):
        n = re.search(r'name="([^"]*)"', t.group(1))
        if n:
            data.append((n.group(1), html.unescape(t.group(2))))
    return data


def token(page):
    return re.search(r'name="_token" value="([^"]*)"', page).group(1)


def check(label, res, expect_prefix):
    ok = res[0] in (302, 303) and expect_prefix in res[1]
    print(f'{"OK " if ok else "NG "} {label:<28} {res[0]} {res[1]}')
    return ok


results = []
lp = get('/login')
results.append(check('login', post('/login', [('_token', token(lp)), ('login_id', 'admin'), ('password', os.environ.get('CANAME_ADMIN_PW', 'caname2026'))]), '/'))
pid = int(sys.argv[1]) if len(sys.argv) > 1 else 2

# 案件ヘッダ保存（往復）
results.append(check('projects/save', post('/projects/save', form_fields(get(f'/projects/edit?project_id={pid}'), '/projects/save')), '/projects'))
# 案件コピー → 削除
t = token(get('/projects'))
st, loc = post('/projects/copy', [('_token', t), ('project_id', str(pid))])
new_id = re.search(r'project_id=(\d+)', loc)
results.append(check('projects/copy', (st, loc), 'project_id='))
if new_id:
    nid = new_id.group(1)
    results.append(check('estimate(copy)', (302, get(f'/estimate?project_id={nid}') and '/x'), '/x'))
    results.append(check('projects/delete', post('/projects/delete', [('_token', t), ('project_id', nid)]), '/projects'))
# 明細
ep = get(f'/estimate?project_id={pid}')
results.append(check('estimate/settings', post('/estimate/settings', form_fields(ep, '/estimate/settings')), '/estimate'))
results.append(check('estimate/lines/save', post('/estimate/lines/save', form_fields(ep, '/estimate/lines/save')), '/estimate'))
results.append(check('estimate/lines/refresh', post('/estimate/lines/refresh', form_fields(ep, '/estimate/lines/save')), '/estimate'))
# 営業実行予算・その他工事・特記・経理予算
results.append(check('summary/save', post('/summary/save', form_fields(get(f'/summary?project_id={pid}'), '/summary/save')), '/summary'))
results.append(check('extra/save', post('/extra/save', form_fields(get(f'/extra?project_id={pid}&no=1'), '/extra/save')), '/extra'))
results.append(check('notes/save', post('/notes/save', form_fields(get(f'/notes?project_id={pid}'), '/notes/save')), '/notes'))
results.append(check('budget/save', post('/budget/save', form_fields(get(f'/budget?project_id={pid}'), '/budget/save')), '/budget'))
# マスタ
results.append(check('items/save', post('/items/save', form_fields(get('/items/edit?id=1'), '/items/save')), '/items'))
# CSRF 不正
st, loc = post('/summary/save', [('_token', 'bad'), ('project_id', str(pid))])
print(('OK ' if st in (302, 303, 400, 403) else 'NG '), 'csrf reject', st, loc)
print('ALL OK' if all(results) else 'FAILED')
