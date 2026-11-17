# AAUW Ann Arbor site: every way in

Owner: Eric (WordPress account `admin`, user ID 1). Until the branch takes over, Eric and Claude make the changes.
Never put a password or token in this file. Values live in the places named below.

| Route | What it reaches | Status | Where the secret lives |
|---|---|---|---|
| GitHub `ehiggins17/higs` | Source of truth: plugins, theme files, redirects, docs. | Working. Push access granted 2026-11-16. | GitHub App installation |
| GitHub Actions deploy | Unpacks `site/wp-content/` onto the site through the Site Control API on every push. | Working since 2026-11-17 (Site Control 1.1.1 fixed a bug that unpacked deploys into a stray `wp-content/wp-content/` folder; the workflow now fails if zero files land). | Secret `AAUW_SITE_TOKEN`, variable `AAUW_SITE_URL` |
| Site Control plugin API | Install plugins, edit theme/plugin files, options, export, search-replace, cache flush. | Working since 2026-11-16 (v1.0.3). | Token: Claude's credential store, header `X-AAUW-Token`. Generated at Settings > Site Control. |
| Activity Log plugin API | Who logged in, when, for how long, what they changed. | Working (v1.3.0). Uses the same token. | Same |
| WordPress REST API (core) | Pages, media, users, menus. | Working with the token (used to upload the 15 PDFs). | Application Password: Claude's credential store for the staging host |
| wp-admin | Everything, by hand. | Eric's `admin` login. Kathy and Kim have their own. | Eric's password manager |
| Hostinger hPanel | File manager, FTP accounts, SSH, database, PHP settings, domains, backups. | Eric's Hostinger account (free first year). | Eric's password manager |
| Hostinger FTP/FTPS | Direct file access; what the deploy workflow uses. | Create under hPanel > Files > FTP Accounts. | GitHub secrets |
| Hostinger SSH / WP-CLI | Command line on the server. | Available under hPanel > Advanced > SSH Access. Not reachable from Claude's cloud session (HTTPS only). | Eric's machine |
| Netlify (current live site) | aauw-aami.org until the domain is switched. | Branch's Netlify team under Kathy's account; Eric deploys. 300 credits/month, 50% used by Nov 14. | Netlify login |
| Squarespace domain | DNS for aauw-aami.org. | Eric was added as domain manager in October. | Squarespace login |
| Google Workspace | Branch email on aauw-aami.org. | Kathy is the Google for Nonprofits admin. | Kathy |
| AAUW National (aauw.net) | The old site and its redirect. | Only National can change it. Mona offered a redirect. | National |

## Hosts Claude's session can reach
silver-caribou-284529.hostingersite.com, aauw-aami.org, aauw-aami.netlify.app, annarbor-mi.aauw.net (files only; pages sit behind a bot check).
After the domain switch, save the token credential again for aauw-aami.org; credentials are per host.

## Done on 2026-11-16
- Site Control, Activity Log and Browser Compatibility plugins installed and active; token in Claude's credential store.
- Theme and Site Features plugin imported into `site/wp-content/`.
- Redirect rules live in .htaccess (backup kept beside it); 15 old-site PDFs registered in the Media Library.

## Still optional
1. Create an FTP account in hPanel and add the three GitHub secrets so pushes deploy automatically (until then Claude deploys through the API).
2. Hostinger CDN image optimization converts photos to WebP files that are larger than the originals; consider turning it off in hPanel > Performance > CDN.
3. After the domain switch, save the token credential again for aauw-aami.org.
