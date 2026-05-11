# mu-plugins/

This directory mirrors WordPress's `wp-content/mu-plugins/` — files dropped here are auto-loaded on every request and can't be deactivated from the admin.

## Deployment

These files live in the theme repo for source control, but WordPress reads them from `wp-content/mu-plugins/`, **not** from inside the theme. Deploy by copying to the live host:

```
# Cloudways shell on syntharchy-wp
cp wp-content/themes/signal-and-noise/mu-plugins/rss-plausible-tracker.php \
   wp-content/mu-plugins/
```

Or via SFTP: put `rss-plausible-tracker.php` directly under `wp-content/mu-plugins/` on the server. No activation step — MU plugins run as soon as the file exists.

## Removal

MU plugins have no deactivation hook, so deleting the file leaves two orphans behind:

1. **WP-Cron entry** for `sn_rss_tracker_daily_prune` — fires daily against a now-missing hook. WordPress silently no-ops missing hook targets, so this is harmless but ugly in `wp-cron-status` tools. To clean up, run on the host:
   ```php
   wp_clear_scheduled_hook( 'sn_rss_tracker_daily_prune' );
   ```
   Or via WP-CLI: `wp cron event delete sn_rss_tracker_daily_prune`.

2. **The `wp_rss_feed_log` table** and `sn_rss_tracker_settings` / `sn_rss_tracker_db_version` options persist by design (the data is potentially valuable even after the plugin is removed). Drop manually if undesired.

## Files

- **`rss-plausible-tracker.php`** — fires a Plausible event on every non-bot RSS feed request, logs to `wp_rss_feed_log` as a fallback trend source, renders a 30-day count widget on the WP admin dashboard, and a full settings/stats tab under Appearance → Signal & Noise → RSS. Self-contained: no theme dependencies, survives theme switches.
- **`tests/bot-detection.php`** — standalone fixture test for the bot regex. Runnable with bare `php mu-plugins/tests/bot-detection.php` — no PHPUnit, no WordPress, no composer. Regression-locked against the Feedly-filter bug fixed pre-merge.
