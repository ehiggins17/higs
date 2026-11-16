# AAUW Ann Arbor site: every way in

Owner: Eric (WordPress account `admin`, user ID 1). Until the branch takes over, Eric and Claude make the changes.
Never put a password or token in this file. Values live in the places named below.

| Route | What it reaches | Status | Where the secret lives |
|---|---|---|---|
| GitHub `ehiggins17/higs` | Source of truth: plugins, theme files, redirects, docs. | Working. Push access granted 2026-11-16. | GitHub App installation |
| GitHub Actions deploy | Pushes `site/wp-content/` to Hostinger over FTPS on every push. | Needs three repository secrets (see workflow file). | GitHub > Settings > Secrets |
| Site Control plugin API | Install plugins, edit theme/plugin files, options, export, search-replace, cache flush. | Plugin written; needs one-time upload and a token. | Token: Claude's credential store, header `X-AAUW-Token`. Generated at Settings > Site Control. |
| Activity Log plugin API | Who logged in, when, for how long, what they changed. | Plugin written; needs one-time upload. Uses the same token. | Same |
| WordPress REST API (core) | Pages, media, users, menus. | Works once the token or Application Password authenticates. | Application Password: Claude's credential store for the staging host |
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

## One-time steps still needed
1. Upload and activate both plugins once from Plugins > Add New > Upload (zips in `dist/`). After that, plugin installs go through the API.
2. Settings > Site Control > Create token. Paste it into Claude's credential form for header X-AAUW-Token.
3. Create an FTP account in hPanel and add the three GitHub secrets.
4. If the Application Password keeps failing, add this line at the top of .htaccess so Hostinger passes the Authorization header:
   `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`
