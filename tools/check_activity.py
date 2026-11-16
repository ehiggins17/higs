#!/usr/bin/env python3
"""Print site logins and changes since the last run, from the Activity Log and Simple History APIs.
State: /home/user/higs/.aauw-last-check (gitignored). Auth: the proxy injects the X-AAUW-Token header."""
import json, re, os, sys, urllib.request, ssl, datetime
S = 'https://silver-caribou-284529.hostingersite.com'
STATE = '/home/user/higs/.aauw-last-check'
ctx = ssl.create_default_context(cafile='/root/.ccr/ca-bundle.crt')
def get(u):
    return json.loads(urllib.request.urlopen(urllib.request.Request(u, headers={'User-Agent': 'activity-check'}), timeout=60, context=ctx).read())
last = {'event_id': 0, 'sh_id': 0, 'sessions': {}}
if os.path.exists(STATE):
    last = json.load(open(STATE))
today = datetime.date.today().isoformat()
rep = get(f'{S}/wp-json/aauw-activity/v1/report?from={(datetime.date.today()-datetime.timedelta(days=1)).isoformat()}&to={today}&limit=1000')
status = get(f'{S}/wp-json/aauw-activity/v1/status')
sh = get(f'{S}/wp-json/simple-history/v1/events?per_page=50')
new_events = []
# events come newest first; we track by (when,user,event,detail) hash since ids are not exposed
seen = set(last.get('seen', []))
for e in reversed(rep['events']):
    key = f"{e['when']}|{e['user']}|{e['event']}|{e['detail'][:80]}"
    if key not in seen:
        new_events.append(e); seen.add(key)
new_sh = []
seen_sh = set(last.get('seen_sh', []))
for e in reversed(sh):
    k = str(e.get('id'))
    if k not in seen_sh:
        who = (e.get('initiator_data') or {}).get('user_login', '') if isinstance(e.get('initiator_data'), dict) else ''
        msg = re.sub('<[^>]+>', '', e.get('message_html') or e.get('message', '')).strip()
        new_sh.append((e.get('date_local'), who, msg)); seen_sh.add(k)
first_run = not os.path.exists(STATE)
json.dump({'seen': sorted(seen)[-2000:], 'seen_sh': sorted(seen_sh)[-500:], 'ran': datetime.datetime.now(datetime.timezone.utc).isoformat()}, open(STATE, 'w'))
print('Site time:', status['site_time'])
if first_run:
    print('Baseline recorded. Current state:')
    for ed in status['editors']:
        ls = ed['last_session'] or {}
        print(f"  {ed['user']}: logins {ed['total_logins']}, last login {ls.get('login_at')}, last activity {ls.get('last_activity')}, {ls.get('ended_by')}")
    sys.exit(0)
if not new_events and not new_sh:
    print('NOTHING NEW'); sys.exit(0)
print(f'NEW ACTIVITY: {len(new_events)} log events, {len(new_sh)} history entries')
for e in new_events:
    if e['event'] in ('login', 'logout', 'create', 'edit', 'publish', 'unpublish', 'upload', 'trash', 'delete'):
        print(f"  {e['when']} | {e['user']} | {e['event']} | {e['detail'][:110]}")
views = [e for e in new_events if e['event'] in ('admin_view', 'page_view')]
if views:
    by = {}
    for e in views: by.setdefault(e['user'], []).append(e['detail'][:50])
    for u, v in by.items(): print(f"  {u} opened {len(v)} screens/pages: " + '; '.join(dict.fromkeys(v))[:300])
for d, who, msg in new_sh:
    print(f"  history {d} | {who} | {msg[:110]}")
