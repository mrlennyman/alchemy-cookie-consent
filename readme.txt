=== Alchemy Consent ===
Contributors: websitealchemy
Tags: cookie consent, gdpr, ccpa, cookie banner, consent mode
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, self-hosted cookie consent banner with Google Consent Mode v2 and optional geo-targeting.

== Description ==

Alchemy Consent shows an opt-in cookie banner, blocks non-essential tracking
until a visitor accepts, logs each consent choice, and registers with the
WP Consent API so Site Kit's Consent Mode picks it up automatically.

Deliberately scoped: no auto cookie scanner (the cookie list is
hand-maintained per site), no multilingual auto-translation, no IAB TCF.
Optional geo-targeting (off by default) offers Strict/Light/Exempt
regional behaviour for sites that want it.

== Installation ==

1. Upload and activate the plugin.
2. Alchemy Consent > Categories — turn on Marketing only if this site runs
   Google Ads or remarketing.
3. Alchemy Consent > Cookie List — review/edit the seeded list for anything
   this specific site runs beyond the defaults.
4. Add the `[alchemy_cookie_policy]` shortcode to your Cookie/Privacy Policy
   page — it renders the current cookie list automatically.
5. In Site Kit > Settings > Admin Settings, turn on Consent Mode. It
   should detect Alchemy Consent via the WP Consent API without further
   configuration.
6. Test with GA4 DebugView or Tag Assistant before/after clicking
   Accept / Reject.

== Frequently Asked Questions ==

= Does this work without Google Site Kit? =

Yes — the banner, blocking, consent logging, and WP Consent API
registration all work independently of Site Kit. Site Kit specifically
is what turns a granted "statistics" consent into a Google Consent
Mode v2 signal for GA4; without it, other WP Consent API-aware plugins
or your own theme code can still read the consent state via
`wp_has_consent()`.

= Does this scan my site for cookies automatically? =

No, by design. The cookie list is maintained manually per site
(Cookie List tab), with a quick-add helper for common services
(Google Analytics, Bing UET, Facebook Pixel, Hotjar, Microsoft
Clarity). This keeps the plugin lightweight and avoids the false
positives/negatives of automated scanning.

= What does geo-targeting actually change? =

It's off by default. When enabled, first-time visitors are sorted
into Strict (blocking banner, opt-in required), Light (auto-granted,
but with a visible way to opt out), or Exempt (auto-granted, nothing
shown at all) based on country — see the External Services section
for how detection works.

== External Services ==

This plugin connects to one external service, and only when geo-targeting
is turned on (Geo Targeting tab — off by default).

**Service used:** Cloudflare's public trace endpoint
(https://www.cloudflare.com/cdn-cgi/trace)

**Purpose:** To determine a first-time visitor's country, so the plugin
can decide whether to show a blocking consent banner (Strict), an
auto-granted banner with a visible opt-out (Light), or nothing at all
(Exempt). This works independently of whether the site itself uses
Cloudflare.

**Data sent:** No data is explicitly submitted by the plugin beyond the
visitor's browser making a standard HTTP request to Cloudflare's
endpoint — which inherently exposes the visitor's IP address to
Cloudflare, as it would for any web request. The plugin reads only the
two-letter country code from Cloudflare's response; nothing else in the
response is stored or transmitted further.

**When:** Only on a visitor's first pageview with no existing consent
cookie, and only if geo-targeting is enabled for that site. Returning
visitors with an existing choice never trigger this request.

**Provider policies:** Cloudflare Privacy Policy —
https://www.cloudflare.com/privacypolicy/

With geo-targeting left at its default (off), this plugin makes no
external requests of any kind.

== Wiring a non-Google tag (e.g. Bing UET) through GTM ==

Site Kit's Consent Mode only signals Google's own tags. For anything
else routed through GTM, Alchemy Consent pushes the visitor's choice to
window.dataLayer as:

  { event: 'alchemy_consent_default' | 'alchemy_consent_update',
    alchemy_consent_necessary: true,
    alchemy_consent_analytics: true|false,
    alchemy_consent_marketing: true|false }

'alchemy_consent_default' fires immediately on every pageload (existing
choice, or all-false if none yet). 'alchemy_consent_update' fires when a
visitor makes a fresh choice. In GTM: add a Data Layer Variable for
the relevant alchemy_consent_* key, then a Custom Event trigger listening
for both event names, gated on that variable being true. Attach it to
the Bing UET tag (or Facebook Pixel, Hotjar, Clarity, etc.) instead of
the tag's default trigger.

== Geo Targeting ==

Off by default — enabling it per site (Geo Targeting tab) is a real
change in compliance posture, not a cosmetic option. When on:

1. First-time visitors are checked against the Strict country list via
   a client-side fetch to https://www.cloudflare.com/cdn-cgi/trace
   (works regardless of whether this specific site is on Cloudflare).
2. Strict-list country, or detection fails/times out (2.5s) — normal
   blocking banner, unchanged from non-geo behaviour.
3. Any other country — banner doesn't block; enabled categories are
   granted automatically and logged with source "geo-default" (vs.
   "explicit" for an actual click), with the cookie-settings button
   available immediately to opt out.

A visitor's own past choice always overrides geo logic on repeat
visits — this only runs when no consent cookie exists yet.

Default Strict list: EU/EEA + UK + Canada (all provinces, not just
Quebec — country-level detection can't reliably isolate Quebec, and
applying Law 25's standard nationwide is the safer default). Edit the
list per site if a client's situation differs.

== Changelog ==

= 1.6.6 =
* Fixed: the GitHub repo had plugin files nested inside an extra
  alchemy-consent/ subfolder instead of sitting at the repo root.
  Combined with GitHub always wrapping a tag's downloaded zip in its
  own folder, this put alchemy-consent.php two levels deep, which the
  1.6.5 update attempt failed to install ("The package could not be
  installed") since WordPress's installer only unwraps one wrapping
  folder. Repo restructured so the plugin's files are now directly at
  the repo root — no code changes, packaging only.

= 1.6.5 =
No functional changes — version bump to verify the GitHub-based update
checker end to end on a live install (confirms client sites show a
real "Update available" notice and Update Now works, instead of
needing a manual re-upload).

= 1.6.4 =
Fixes from an /ultrareview pass on 1.6.3:
* Fixed: the wp_head dataLayer-bridge script's cookie parse had no
  try/catch, unlike banner.js's equivalent — a malformed alchemy_consent
  cookie (DevTools edit, a colliding third-party script, a truncating
  proxy) would throw and silently skip the dataLayer.push, blocking every
  GTM tag gated on alchemy_consent_default (Bing UET, FB Pixel, Hotjar,
  Clarity). Now wrapped the same way banner.js already was.
* Fixed: the 1.6.3 CSV export batching used OFFSET pagination on a table
  that keeps taking live inserts from the public consent-save endpoint
  for the whole duration of the export — a row inserted mid-export shifts
  every later page by one position under OFFSET, re-emitting some rows
  and silently dropping others. Switched to keyset pagination (WHERE id <
  last_id ORDER BY id DESC), which is unaffected by concurrent inserts
  and also fixes same-second consent_time rows sorting inconsistently
  across batches.

= 1.6.3 =
Cleanup pass — no live "WA Consent" installs exist yet (still in test
phase), so the remaining renamed-from-WA scaffolding was removed rather
than kept as permanent backward compatibility:
* Removed: the wa_consent -> alchemy_consent one-time migration
  (table rename, option copy) from Alchemy_Consent_Activator — dead
  code once every install starts life as Alchemy Consent.
* Removed: [wa_cookie_policy], [wa_consent_settings_link], and
  [wa_regulatory_links] legacy shortcode aliases. Use the alchemy_
  prefixed names.
* Fixed: the front-end localized script data was still named
  `waConsentData` — the one leftover "wa"-prefixed identifier in the
  codebase, despite 1.6.0 renaming everything else specifically
  because WordPress.org flagged "wa" as a restricted term. Renamed to
  `alchemyConsentData`.
* Fixed: accent_color and revisit_bg_color were saved with
  sanitize_text_field() rather than validated as actual hex colors —
  a malformed value now falls back to the field's default instead of
  being stored as-is.
* Changed: the four admin_post handlers' identical
  redirect-back-to-tab code consolidated into one helper.
* Changed: settings are now fetched once per pageview and reused
  (wp_enqueue_scripts and wp_footer both needed them) instead of
  calling get_option() twice.

= 1.6.2 =
Fixes from an internal code review:
* Fixed: saving the Geo Targeting tab with the Strict-countries field
  cleared (rather than left untouched) stored an empty list instead of
  falling back to the default — this silently exempted every visitor,
  including GDPR-covered countries, from the banner. Now an emptied
  field falls back to the default the same way an omitted one does.
* Fixed: the consent-log CSV export wrote page_url — sourced from the
  public, unauthenticated consent-save endpoint — into cells with no
  formula-injection guard, letting a crafted value execute if the
  export was opened in Excel/Sheets. Cell values are now prefixed with
  a leading apostrophe when they start with a formula-trigger character.
* Fixed: CSV export loaded the entire consent log into memory with no
  LIMIT; now streamed in batches.
* Fixed: clicking "Accept All" always granted analytics and marketing
  regardless of whether those categories are enabled for the site —
  now matches the geo auto-accept path and only grants enabled
  categories. The consent-save AJAX handler also now re-checks
  categories_enabled server-side rather than trusting the client.
* Fixed: the Analytics checkbox in the banner's Customize panel always
  rendered regardless of the Categories tab's "Show this category"
  toggle — Marketing already respected it, Analytics didn't.
* Fixed: Exempt-tier visitors (geo-targeting) saw no revisit button on
  their first pageview as intended, but it reappeared on every later
  pageview once the consent cookie existed. The cookie now records
  which tier granted consent so the button stays hidden for Exempt on
  return visits too. Cookies set before this version are still read
  correctly.
* Changed: five admin_post handlers each repeated the same nonce +
  capability check inline; consolidated into one helper.

= 1.6.1 =
Second Plugin Check pass, 13 warnings found and fixed:
* Fixed: the table-rename migration query now uses $wpdb->prepare()
  with the %i identifier placeholder instead of a phpcs:ignore comment
  — RENAME TABLE supports %i the same as SELECT/INSERT do.
* Fixed: two phpcs:ignore comments from the 1.5.1 pass didn't actually
  suppress anything — a phpcs:ignore only covers the single line
  directly beneath it, and both were followed by a second comment
  line before the real code, pushing the directive off-target.
  Reworded as single-line ignores with the explanation moved above.
* Fixed: cookie-list save restructured again — unslashes
  $_POST['cookies'] as the very first touch, before any isset/is_array
  check, which is what the sniff was actually asking for.
* Fixed: six local variables in the banner template (show_marketing,
  policy_page_id, policy_url, revisit_color, revisit_bg,
  revisit_bg_hover) prefixed with alchemy_ — WordPress coding
  standards want this in template files since an include() can expose
  these to a wider scope than a normal function would.

= 1.6.0 =
Renamed from "WA Consent" to "Alchemy Consent" (plugin, slug, folder,
main file, constants, classes, options, DB table, nonces, AJAX hooks,
and the consent cookie itself) — WordPress.org's Plugin Check flagged
"wa" as a restricted term that can't begin a plugin name or slug.
* Migration: an already-live install running the old name migrates
  automatically on first load under the new version — DB table
  renamed (not recreated, so consent history is preserved), settings
  and cookie list copied across, old options cleaned up.
* Backward compatible: [wa_cookie_policy], [wa_consent_settings_link],
  and [wa_regulatory_links] shortcodes still work as aliases, so an
  already-published policy page doesn't need editing. New sites/pages
  should use the alchemy_ prefixed names going forward.
* Known side effect: the consent cookie itself is renamed
  (wa_consent -> alchemy_consent), so existing visitors with a saved
  choice will see the banner once more after upgrading — a one-time
  re-prompt, not a data loss.
* Plugin URI / Author URI updated to https://websitealchemy.com.

= 1.5.1 =
Fixes from a WordPress.org Plugin Check run:
* Fixed: undefined-array-key warnings on several Settings > General
  fields when POSTed without a value — now default gracefully instead
  of triggering a PHP notice.
* Fixed: `policy_page_id` explicitly cast with absint() at the point
  it's passed to wp_dropdown_pages(), closing an output-escaping flag
  (wp_dropdown_pages() escapes internally, but Plugin Check can't see
  that, so this makes it explicit either way).
* Fixed: log tab and CSV export queries now use $wpdb->prepare() with
  the %i identifier placeholder instead of interpolating the table
  name directly — bumps the minimum WordPress version to 6.2, where
  %i support was added.
* Fixed: cookie-list save now unslashes $_POST['cookies'] before any
  other check touches it, resolving a "non-sanitized input" flag.
* Documented (via phpcs:ignore, not code changes) several Plugin
  Check findings that are false positives for this codebase: nonce
  checks that happen in a caller method the sniff can't see across,
  $wpdb->insert()/direct queries against this plugin's own table
  (no core API alternative exists), and the CSV export's use of
  php://output (a streamed HTTP response, not a filesystem file, so
  WP_Filesystem doesn't apply).

= 1.5.0 =
* Changed: geo-targeting is now three-tier instead of two. Strict
  (opt-in, blocking banner) and Light (auto-granted, opt-out button
  shown) unchanged in behaviour, but Light is now defined by a
  separate, editable country list (defaults to "US" — covering all 20
  US states with opt-out privacy laws, not just California; country-
  level detection can't isolate individual states anyway). New: Exempt
  tier for everything else — auto-granted with NO visible UI at all,
  for regions with no consent requirement (e.g. NZ, Australia).
* Added: revisit button color + opacity (normal and hover state)
  settings on the General tab. Background fades via rgba, not CSS
  opacity, so the icon itself stays crisp while just the pill fades.
  Defaults to 55% at rest, 100% on hover.
* Changed: consent log "source" values are now explicit / geo-light /
  geo-exempt (previously geo-default) — clearer three-way distinction
  in the audit trail.

= 1.4.1 =
Security hardening pass:
* Fixed: admin-post handlers (save_general, save_categories, save_cookies,
  save_geo, export_log) checked a nonce but not the user's capability —
  added current_user_can('manage_options') to each as defense-in-depth.
  Not exploitable through the normal UI (the settings page itself already
  requires manage_options to reach), but nonce checks alone don't verify
  capability, so this closes the gap properly rather than relying on that.
* Fixed: the public consent-save AJAX endpoint accepted any posted string
  as a "category" and stored it as-is. Now allow-listed against the three
  real categories (necessary/analytics/marketing) before use or storage.

= 1.4.0 =
* Added: optional geo-targeting (Geo Targeting tab). Strict countries
  get the normal blocking banner; everywhere else gets auto-granted
  consent with an immediate opt-out option. Detection failure always
  falls back to Strict.
* Added: "source" column (explicit vs. geo-default) on the consent
  log and CSV export, so geo-inferred consent is distinguishable from
  an actual visitor click.
* Added: automatic DB/settings upgrade check on load — sites updated
  by overwriting plugin files (not deactivate/reactivate) still pick
  up new DB columns and setting defaults.
* Changed: banner buttons now render in uppercase by default.

= 1.3.0 =
* Added: "Cookie/Privacy Policy page" picker on the General tab (page
  picker, not a URL field, so it resolves correctly per site). Renders
  as a "Learn more" link inside the banner message — previously the
  banner had no path to the policy page a visitor was actually
  consenting to.

= 1.2.0 =
* Added: [wa_regulatory_links] shortcode — renders the "right to complain
  to a supervisory authority" table (ICO, EDPB, OPC, CAI, CPPA) with
  verified official links, so it's maintained once in the plugin rather
  than copy-pasted per client policy.

= 1.1.0 =
* Added: dataLayer bridge (wp_head, priority 1) so GTM-managed tags
  outside Site Kit's reach — Bing UET, Facebook Pixel, Hotjar,
  Clarity — can respect the same consent choice.
* Added: "quick-add common service" helper on the Cookie List tab,
  pre-filling name/category/purpose/duration for common tools instead
  of a separate reference sheet.

= 1.0.0 =
* Initial release: banner, WP Consent API registration, consent logging
  with CSV export, cookie list admin, [wa_cookie_policy] shortcode.
