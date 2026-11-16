# Redirects for aauw-aami.org

Rules that send old annarbor-mi.aauw.net addresses to their new homes on the WordPress site.
They only take effect for visitors who reach aauw-aami.org with an old path, which is
what happens once AAUW National forwards the old domain.

- `htaccess-snippet.txt`: paste into `public_html/.htaccess` on Hostinger, above `# BEGIN WordPress`.
- `redirection-plugin-import.csv`: or import into the Redirection plugin (Tools > Redirection > Import).

Covers 30 documents (position descriptions, policies, community membership form), one old
page path, and the three old post-ID addresses (?p=44, ?p=4949, ?p=4705).

Not covered, because the files were never copied to the new site: the 2024-25 membership
application, the 2025 book sale flyer, the five 2020 board member profiles, the Mah-Jongg
instructions and tournament flyers, and the 2019 open house flyer. Upload any of those you
want to keep and add a line in the same pattern.

## Keeping the old files without showing them

The 15 PDFs in `old-files/` were downloaded from annarbor-mi.aauw.net on 2026-11-16.
Upload them to the WordPress Media Library (Media > Add New) and do not link them from
any page. They stay reachable at their new address for anyone who has an old link, and
the redirect lines added at the end of both rule files send the old addresses there.
WordPress files are not listed anywhere on the site and carry no page of their own, so
"uploaded but unlinked" is the hidden-but-kept state you want.

Fifteen files, 4.7 MB: the 2024-25 membership application, the 2025 book sale flyer,
five 2020 board member profiles, the Mah-Jongg instructions, six tournament flyers and
updates (2018 to 2026), and the 2019 open house flyer.
