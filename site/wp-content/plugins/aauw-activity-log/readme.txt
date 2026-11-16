=== AAUW Editor Activity Log ===
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPL-2.0-or-later

Records what each site editor does: logins, time on the site, screens and pages opened, and every page created, edited, published, trashed or uploaded to.

== What it records ==

For every user who can edit content (Editors and Administrators):

* Login time, last activity, logout time, session length, and whether the session ended by logging out or by going idle for 30 minutes.
* Each admin screen opened (Dashboard, Pages list, the editor for a specific page, Media Library, and so on).
* Each page of the public site viewed while logged in.
* Pages and posts created, with word count.
* Edits, with what changed: title, content (word count before and after), excerpt, address, parent page, menu order, status.
* Publishing and unpublishing.
* Trashing and permanent deletion.
* File uploads.

It records nothing about ordinary visitors.

== Installing ==

1. Plugins > Add New > Upload Plugin > choose aauw-activity-log.zip > Install Now > Activate.
2. The report is under Users > Activity Log.
3. Settings at the bottom of that page: exclude specific users, and how long to keep records (default one year).

== Reading the report ==

* Summary: one row per editor for the chosen dates: logins, total and average time logged in, last login, counts of screens and pages viewed, pages created, edits, published, uploads, trashed.
* Sessions: one row per login with the exact times and what happened during it.
* Events: everything in order, newest first. Export sessions or events to CSV for a spreadsheet.

== Notes ==

* Time logged in is measured from login to the last recorded activity. WordPress sends a heartbeat every minute or so while the editor is open, so time spent writing counts even without saving.
* Sessions ended by closing the browser rather than logging out show as "Timed out (idle)" 30 minutes after the last activity.
* Tell editors this log exists and what it records.
* Deleting the plugin removes its tables. Deactivating keeps them.

== Who can see it ==

Only one account: the site's first user (ID 1, username "admin"). Everyone else, including other
Administrators, sees no Users > Activity Log menu, cannot open the page or the exports, and does not
see the plugin on the Plugins screen. To hand it to a different account, add this line to wp-config.php:

define( 'AAUW_ACTIVITY_LOG_OWNER', 'username-or-email' );

Anyone with database or file access to the hosting account can still read the raw tables.

== Private API (for the owner only) ==

With an Application Password for the owner account (Users > Profile > Application Passwords), these
read-only addresses return the log as JSON. Any other account gets a 403.

/wp-json/aauw-activity/v1/status
/wp-json/aauw-activity/v1/report?from=2026-11-01&to=2026-11-30&user=0&limit=500
