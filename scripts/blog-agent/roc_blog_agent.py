"""
Run On Console — blog agent helper (runs on the owner's PC, standard Python only).

The writing is done by the "ROC blog writer" scheduled task in the Claude app; this
script only talks to the website.

    python roc_blog_agent.py context            -> prints what the writer needs (JSON)
    python roc_blog_agent.py post article.json  -> sends one article, prints the result

The token (CMS > Blog agent > Make a token) is read from
    %USERPROFILE%\.roc-blog-agent-token
(one line). It never goes into this folder or into git.

article.json:
    {"title", "focus_keyword", "meta_title", "meta_description", "excerpt",
     "category", "content_html", "image_alt", "publish_at"}
"""
import json, os, sys, urllib.request, urllib.error

API = 'https://runonconsole.com/api/v1/blog-agent.php'
TOKEN_FILE = os.path.join(os.path.expanduser('~'), '.roc-blog-agent-token')


def token():
    try:
        t = open(TOKEN_FILE, encoding='utf-8').read().strip()
    except OSError:
        t = ''
    if not t:
        print(json.dumps({'success': False, 'error': 'No token. Make one in CMS > Blog agent and save it in ' + TOKEN_FILE}))
        sys.exit(2)
    return t


def call(method, body=None):
    data = json.dumps(body).encode('utf-8') if body is not None else None
    req = urllib.request.Request(API + ('?action=context' if method == 'GET' else ''), data=data, method=method, headers={
        'X-ROC-Agent-Token': token(), 'Content-Type': 'application/json', 'Accept': 'application/json',
        'User-Agent': 'ROC-Blog-Agent/1.0 (+https://runonconsole.com)'})
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return json.loads(r.read().decode('utf-8'))
    except urllib.error.HTTPError as e:
        raw = e.read().decode('utf-8', 'replace')
        try:
            return json.loads(raw)
        except ValueError:
            return {'success': False, 'error': 'HTTP %d' % e.code, 'body': raw[:300]}
    except Exception as e:  # network down, firewall, …
        return {'success': False, 'error': str(e)}


def main():
    if len(sys.argv) >= 2 and sys.argv[1] == 'context':
        out = call('GET')
    elif len(sys.argv) >= 3 and sys.argv[1] == 'post':
        with open(sys.argv[2], encoding='utf-8') as f:
            out = call('POST', json.load(f))
    else:
        print(__doc__)
        sys.exit(1)
    sys.stdout.reconfigure(encoding='utf-8')
    print(json.dumps(out, ensure_ascii=False, indent=1))
    sys.exit(0 if out.get('success') else 1)


if __name__ == '__main__':
    main()
