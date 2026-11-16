"""Generate compat.css: fallbacks for Safari/iOS 12-14 and old Edge, derived from site.css.
Every fallback is either (a) a plain-value declaration that a modern browser immediately overrides
because the modern declaration follows it in site.css? No: compat.css loads AFTER site.css, so we
scope fallbacks inside @supports not (...) blocks that modern browsers skip entirely."""
import re, sys
css = open('theme/site.css').read()
# strip comments
css = re.sub(r'/\*.*?\*/', '', css, flags=re.S)
out = []
def rules(css):
    # yields (prelude, body) for simple rules; media blocks are flattened with their prelude kept
    depth = 0; i = 0; buf = ''; stack = []
    for m in re.finditer(r'[{}]', css):
        pass
    # simple tokenizer
    res = []; pos = 0
    def parse(s, media):
        i = 0
        while True:
            o = s.find('{', i)
            if o < 0: break
            sel = s[max(s.rfind('}', 0, o), s.rfind('{', 0, o)) + 1:o].strip()
            # find matching close
            d = 1; j = o + 1
            while d and j < len(s):
                if s[j] == '{': d += 1
                elif s[j] == '}': d -= 1
                j += 1
            body = s[o+1:j-1]
            if sel.startswith('@media') or sel.startswith('@supports'):
                parse(body, media + [sel])
            elif sel.startswith('@'):
                pass
            else:
                res.append((media, sel, body))
            i = j
    parse(css, [])
    return res
R = rules(css)
def wrap(media, inner):
    s = inner
    for m in reversed(media): s = f"{m} {{ {s} }}"
    return s
# 1) clamp()/min()/max(): fallback = first argument (clamp min) / for min(): first arg; max(): last arg
fb1 = []
for media, sel, body in R:
    decls = []
    for prop, val in re.findall(r'([a-z-]+)\s*:\s*([^;]+)', body):
        if 'clamp(' in val:
            v = re.sub(r'clamp\(\s*([^,]+),[^)]*\)', r'\1', val)
            decls.append(f"{prop}: {v.strip()}")
        elif re.search(r'(?<![a-z-])(min|max)\(', val):
            def pick(m):
                args = [a.strip() for a in m.group(2).split(',')]
                return args[0] if m.group(1) == 'min' else args[-1]
            v = re.sub(r'(?<![a-z-])(min|max)\(([^()]*)\)', pick, val)
            if '(' not in v or v.strip().startswith('calc('): decls.append(f"{prop}: {v.strip()}")
    if decls: fb1.append(wrap(media, f"{sel} {{ {'; '.join(decls)}; }}"))
out.append("/* iOS 12-13.0: no clamp()/min()/max(). Use the smallest size. */\n@supports not (width: clamp(1px, 2px, 3px)) {\n" + "\n".join(fb1) + "\n}")
# 2) inset -> longhands; dvh -> vh. Proxy: Safari < 14.1 lacks `inset`.
fb2 = []
for media, sel, body in R:
    decls = []
    m = re.search(r'(?<![a-z-])inset\s*:\s*([^;]+)', body)
    if m:
        parts = m.group(1).split()
        t, r, b, l = (parts + parts[:1]*3)[:4] if len(parts) == 1 else (parts[0], parts[1], parts[0], parts[1]) if len(parts) == 2 else (parts[0], parts[1], parts[2], parts[1]) if len(parts) == 3 else parts[:4]
        decls += [f"top: {t}", f"right: {r}", f"bottom: {b}", f"left: {l}"]
    for prop, val in re.findall(r'([a-z-]+)\s*:\s*([^;]+dvh[^;]*)', body):
        decls.append(f"{prop}: {val.replace('dvh', 'vh').strip()}")
    if decls: fb2.append(wrap(media, f"{sel} {{ {'; '.join(decls)}; }}"))
# 2b) flex gap -> margins, for flex containers with a single-value gap (row direction assumed unless flex-direction: column)
fb3 = []
for media, sel, body in R:
    if not re.search(r'display\s*:\s*(inline-)?flex', body): continue
    g = re.search(r'(?<![a-z-])gap\s*:\s*([^;]+)', body)
    if not g: continue
    parts = g.group(1).split()
    rowgap, colgap = (parts[0], parts[0]) if len(parts) == 1 else (parts[0], parts[1])
    column = bool(re.search(r'flex-direction\s*:\s*column', body))
    wraps = bool(re.search(r'flex-wrap\s*:\s*wrap', body))
    if column:
        fb3.append(wrap(media, f"{sel} > * + * {{ margin-top: {rowgap}; }}"))
    elif wraps:
        fb3.append(wrap(media, f"{sel} {{ margin: calc(-1 * {rowgap}) 0 0 calc(-1 * {colgap}); }} {sel} > * {{ margin: {rowgap} 0 0 {colgap}; }}"))
    else:
        fb3.append(wrap(media, f"{sel} > * + * {{ margin-left: {colgap}; }}"))
out.append("/* Safari < 14.1, iOS < 14.5: no inset, no dvh, no gap in flexbox. */\n@supports not (inset: 0) {\n" + "\n".join(fb2 + fb3) + "\n}")
# 3) aspect-ratio boxes that are not <img>: give them a padding-top ratio hack when the box has no text content
fb4 = []
for media, sel, body in R:
    m = re.search(r'aspect-ratio\s*:\s*(\d+)\s*/\s*(\d+)', body)
    if not m or ' img' in sel or sel.endswith('img'): continue
    w, h = int(m.group(1)), int(m.group(2))
    fb4.append(wrap(media, f"{sel} {{ height: 0; padding-top: {h*100/w:.2f}%; position: relative; }} {sel} > * {{ position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }}"))
out.append("/* Safari < 15: no aspect-ratio. Reserve the box with padding. */\n@supports not (aspect-ratio: 1 / 1) {\n" + "\n".join(fb4) + "\n}")
# 4) :focus-visible in selector lists: re-emit the rule with :focus-visible replaced by :focus, so old Safari keeps the hover styles
fb5 = []
for media, sel, body in R:
    if ':focus-visible' in sel and ',' in sel:
        keep = [s.strip() for s in sel.split(',') if ':focus-visible' not in s]
        if keep: fb5.append(wrap(media, f"{', '.join(keep)} {{ {body.strip()} }}"))
out.append("/* Safari < 15.4 drops any rule list that contains :focus-visible. Re-state the hover part on its own. */\n" + "\n".join(fb5))
open('theme/compat.css', 'w').write("/* Compatibility layer for older browsers. Generated from site.css on 2026-11-16. Loads after site.css; modern browsers skip every block. */\n\n" + "\n\n".join(out) + "\n")
print('rules parsed:', len(R), '| clamp/min/max fallbacks:', len(fb1), '| inset/dvh:', len(fb2), '| flex gap:', len(fb3), '| aspect-ratio boxes:', len(fb4), '| focus-visible lists:', len(fb5))
print('compat.css bytes:', len(open('theme/compat.css').read()))
