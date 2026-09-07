=== Alchemy Cookie Consent ===
Contributors: websitealchemy
Tags: cookie consent, gdpr, ccpa, cookie banner, consent mode
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.9.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, self-hosted cookie consent banner with Google Consent Mode v2 and optional geo-targeting.

== Description ==

Alchemy Cookie Consent shows an opt-in consent banner, blocks non-essential tracking
until a visitor accepts, logs each consent choice, and registers with the
WP Consent API so Site Kit's Consent Mode picks it up automatically.

Deliberately scoped: no auto cookie scanner (the cookie list is
hand-maintained per site), no multilingual auto-translation, no IAB TCF.
Optional geo-targeting (off by default) offers Strict/Light/Exempt
regional behaviour for sites that want it.

Also includes a High-Risk Consent prompt for session-recording/chat tools
(independent of geo tier — see "High-Risk Consent" below) and honors
Global Privacy Control as a valid CCPA/CPRA opt-out signal (see
"California Privacy (CCPA/CPRA)" below).

== Installation ==

1. Upload and activate the plugin.
2. Cookie Consent > Categories — turn on Marketing only if this site runs
   Google Ads or remarketing.
3. Cookie Consent > Style — match the banner's colors, font, and buttons
   to the site's own design. Optional; the defaults are a reasonable
   generic look on their own.
4. Cookie Consent > Cookie List — review/edit the seeded list for anything
   this specific site runs beyond the defaults.
5. Add the `[alchemy_cookie_policy]` shortcode to your Cookie/Privacy Policy
   page — it renders the current cookie list automatically.
6. In Site Kit > Settings > Admin Settings, turn on Consent Mode. It
   should detect Alchemy Cookie Consent via the WP Consent API without
   further configuration.
7. Test with GA4 DebugView or Tag Assistant before/after clicking
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

This plugin connects to two external services, both off by default.

**Google Fonts.** The Style tab's Heading/Body/Button font pickers
default to the system font stack, which loads nothing — but choosing
any other font on that list fetches its stylesheet from
fonts.googleapis.com, which exposes the visitor's IP address to Google
the same way any web request does. Only the fonts actually selected are
requested (heading/body/button sharing a choice combine into one
request). Provider policy: Google Fonts, https://policies.google.com/privacy.
Leave every Style tab font on its default "System" choice to avoid this
entirely.

This plugin also connects to one further external service, and only
when geo-targeting is turned on (Geo Targeting tab — off by default).

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

With geo-targeting and every Style tab font left at their defaults, this
plugin makes no external requests of any kind.

== Wiring a non-Google tag (e.g. Bing UET) through GTM ==

Site Kit's Consent Mode only signals Google's own tags. For anything
else routed through GTM, Alchemy Cookie Consent pushes the visitor's
choice to window.dataLayer as:

  { event: 'alchemy_cookie_consent_default' | 'alchemy_cookie_consent_update',
    alchemy_cookie_consent_necessary: true,
    alchemy_cookie_consent_analytics: true|false,
    alchemy_cookie_consent_marketing: true|false }

'alchemy_cookie_consent_default' fires immediately on every pageload (existing
choice, or all-false if none yet). 'alchemy_cookie_consent_update' fires when a
visitor makes a fresh choice. In GTM: add a Data Layer Variable for
the relevant alchemy_cookie_consent_* key, then a Custom Event trigger listening
for both event names, gated on that variable being true. Attach it to
the Bing UET tag (or Facebook Pixel, Hotjar, Clarity, etc.) instead of
the tag's default trigger.

== Geo Targeting ==

Off by default — enabling it per site (Geo Targeting tab) is a real
change in compliance posture, not a cosmetic option. When on, first-time
visitors are checked against the Strict and Light country lists via a
client-side fetch to https://www.cloudflare.com/cdn-cgi/trace (works
regardless of whether this specific site is on Cloudflare), then sorted
into one of three tiers:

1. **Strict** — country in the Strict list, or detection fails/times out
   (2.5s) — normal blocking banner, opt-in required, unchanged from
   non-geo behaviour. Detection failure always falls back to Strict; it
   never accidentally relaxes the banner for someone it couldn't identify.
2. **Light** — country in the Light list (defaults to the whole US,
   covering the 20 states with opt-out privacy laws — country-level
   detection can't isolate individual states) — enabled categories are
   granted automatically and logged with source "geo-light", with the
   cookie-settings button available immediately to opt out.
3. **Exempt** — everyone else — enabled categories are granted
   automatically and logged with source "geo-exempt", with *nothing*
   shown at all, not even the button. For regions with no consent
   requirement. (A visitor who sends a Global Privacy Control signal
   gets the visible opt-out button even in this tier — see "California
   Privacy (CCPA/CPRA)" below.)

A visitor's own past choice always overrides geo logic on repeat
visits — this only runs when no consent cookie exists yet.

Default Strict list: EU/EEA + UK + Canada (all provinces, not just
Quebec — country-level detection can't reliably isolate Quebec, and
applying Law 25's standard nationwide is the safer default). Edit either
list per site if a client's situation differs; anything in neither list
falls through to Exempt.

== High-Risk Consent ==

Session-recording tools (Hotjar, Microsoft Clarity) and live chat
widgets raise a different legal question from the general Necessary/
Analytics/Marketing categories: a wave of CIPA (California Invasion of
Privacy Act, Penal Code §631/632, and more recently the §638.51
"pen register" theory) lawsuits argue that capturing keystrokes, mouse
movement, or chat content before a visitor consents is itself
unauthorized interception — a question of consent *timing*, not
visitor location. Current case law is genuinely split on whether the
1967 statute applies to modern web tools; this feature reduces a
specific, identified risk pattern, it doesn't guarantee anything.

Flag a row "High-risk" on the Cookie List tab (the quick-add presets for
Hotjar, Clarity, and the generic live-chat entry default to flagged,
with pre-written visitor notices; Bing UET and Facebook Pixel are left
unflagged by default, since they're named more loosely in the relevant
case law — a per-client judgement call either way) and a compact,
always-ask prompt appears for every visitor, independent of Strict/
Light/Exempt tier, the first time they're on the site. It never
overlaps with the main banner — it only ever appears once the main
banner is out of the way (already decided, or the visitor has an
existing general consent choice). Declining or accepting is tracked by
its own cookie (`alchemy_cookie_consent_highrisk`) and its own dataLayer signal
(`alchemy_cookie_consent_highrisk`), separate from the general
Necessary/Analytics/Marketing state — gate a GTM trigger for Hotjar/
Clarity/chat on this variable, not the general `alchemy_cookie_consent_analytics`
one, even though those tools may also be categorised Analytics for the
cookie policy table.

== California Privacy (CCPA/CPRA) ==

Two features specifically target California's opt-out (not opt-in)
privacy framework, on top of the Light geo-tier already covering all US
traffic by default:

**Global Privacy Control (GPC).** If a visitor's browser or extension
sends the GPC signal (`navigator.globalPrivacyControl`), it's honored
immediately as a valid opt-out of sale/sharing — no click required, per
CPPA guidance. Concretely: it never grants anything (an opt-out signal
isn't opt-in consent, so Strict-tier visitors still see the normal
blocking banner and must make an actual choice), it only ever suppresses
Marketing — the plugin's closest equivalent to CPRA's "sale/sharing"
concept — everywhere that category would otherwise be auto-granted or
offered via "Accept All". Logged with source "gpc" in the consent log,
distinguishable from an actual click.

**`[alchemy_privacy_choices]` shortcode.** Renders a "Your Privacy
Choices" link with a generic two-tone toggle icon, intended for a
client's site footer per CPRA's "Do Not Sell or Share My Personal
Information" expectation. One click immediately opts out (same effect
as GPC: strips Marketing from whatever's currently granted) without
needing to reopen and navigate the full banner. The icon rendered is a
generic approximation, not the official CPPA artwork — swap it for the
official asset directly in the shortcode's markup if pixel-exact
regulatory icon match matters for a given client.

Neither feature makes an external request — both are purely a browser
property read and the same first-party AJAX endpoint every other choice
already goes through.

== Style Tab ==

Every visual aspect of the banner in one place — Cookie Consent > Style
— rather than scattered across other tabs or requiring theme CSS
overrides (the banner's own CSS uses !important throughout specifically
so a host theme's generic button/container styles can't leak through).
Applies to the main banner, the standalone High-Risk prompt, and the
revisit button alike (they share the same underlying CSS variables).
Grouped into five sections:

* **Layout** — Bar (the classic full-width bottom bar) or Card (a
  rounded card anchored to a bottom corner, with a heading and icon —
  closer to what most visitors expect from a modern cookie prompt),
  plus which corner for Card. A curated, mostly-fixed structure rather
  than more individually tunable fields; colors/fonts/buttons below
  still apply on top of whichever one is chosen.
* **Heading** — font, weight, size, and color for the Card layout's
  heading (set on the General tab; not shown at all in Bar layout).
* **Body text** — font, weight, size, and color for the banner message
  and category labels, in both layouts.
* **Buttons** — font and weight, the accent color (Accept button,
  links), a genuine hover state for every button (previously none
  existed), button text color, the outline button's border/text color
  and hover fill, corner radius, font size, and padding.
* **Container** — background color, top border color, padding, and a
  configurable drop shadow (color, opacity, blur, or switched off
  entirely).
* **Revisit button** — color and opacity at rest/on hover.

Heading/Body/Button fonts are chosen independently from a curated list
of ~20 Google Fonts (plus two "system" options that load nothing) with
their own weight, matching the equivalent system in the Alchemy Forms
plugin — deliberately the same list and mechanism, so an admin managing
several client sites sees a familiar, consistent choice in both.
Colors use WordPress's own color picker rather than plain hex fields.
Every field has a sensible default matching what the banner already
looked like before this tab existed (system fonts throughout, Bar
layout), so nothing changes in appearance until a client's site is
actually customised here.

== Changelog ==

= 1.9.2 =
* Fixed: the category checkboxes (Necessary/Analytics/Marketing) were
  visible on every pageview instead of only after clicking Customize —
  an author CSS rule's unconditional `display: flex` was silently
  overriding the browser's own `[hidden]` handling regardless of
  specificity, a pre-existing bug dating back to the banner's original
  markup, not something introduced by the Style/Layout work. Also the
  direct cause of the Card layout looking taller than its content
  needed — that always-visible row was adding real height.
* Changed: the Customize button now has a visible border and hover
  fill (matching the Reject button's "outline" treatment) instead of
  an underlined text-link style with no border — it was a real
  `<button>` all along, just styled in a way that read as a stray
  hyperlink rather than a third, lower-emphasis action.

= 1.9.1 =
* Fixed: Card layout's three buttons rendered as a full-width stack —
  changed to Accept + Reject side by side (matching the reference
  layout's two-button prominence) with Customize as a smaller row of
  its own, rather than three equal-weight stacked buttons.

= 1.9.0 =
* Fixed: banner CSS now uses !important throughout on the properties a
  host theme most commonly sets generically (button background/color/
  border/padding/font, container background/shadow), plus a
  box-sizing reset and -webkit-appearance:none on buttons — a theme's
  own button styling could otherwise leak through and override the
  banner's, since the previous CSS had no defense against that.
* Added: Layout section on the Style tab — Bar (unchanged full-width
  bottom bar) or Card (rounded corner card with a heading and icon,
  anchored bottom-left or bottom-right). See readme "Style Tab" section.
* Added: independent Heading/Body/Button font pickers (~20 curated
  Google Fonts + two no-load "System" options) each with their own
  weight, replacing the single font_preset/font_size/text_color trio —
  matches the equivalent system in the Alchemy Forms plugin. A site
  that already customised the old fields has them mapped onto Body
  (what they always styled) automatically; nothing reverts to a new
  default.
* Added: Heading text field (General tab) — only rendered when Layout
  is set to Card.
* Added: External Services disclosure for Google Fonts — off by
  default (every font defaults to "System"), only triggered if a
  client site's Style tab actually selects a Google-sourced font.

= 1.8.0 =
* Added: Style tab (Cookie Consent > Style) — font (with three optional
  Google Font presets), text size/color, button hover states (none
  existed before), button text/outline colors and hover fill, corner
  radius, padding, container background/border/padding, and a
  configurable drop shadow. Uses WordPress's native color picker
  instead of plain hex fields. See readme "Style Tab" section.
* Changed: Accent color and the three Revisit button settings moved
  from the General tab to the new Style tab — same settings, same
  storage, just relocated for a cleaner General tab.
* Fixed (incidentally): the Accept/Reject/Customize buttons never had
  a hover state at all in any prior version; they do now.

= 1.7.3 =
* Changed: GitHub repo renamed to match the plugin — now
  github.com/mrlennyman/alchemy-cookie-consent. Updated the
  buildUpdateChecker() URL to point at it directly rather than relying
  on GitHub's rename redirect long-term.

= 1.7.2 =
Full internal rename to match the 1.7.1 display name, now that this
plugin has no live install/data to preserve compatibility for (unlike
the 1.6.0 WA Consent -> Alchemy Consent rename, this one ships with no
migration path — a deliberate choice given the plugin isn't in use yet).
* Changed: plugin slug/folder and main file (alchemy-consent ->
  alchemy-cookie-consent), Text Domain, PHP class names (Alchemy_Consent_*
  -> Alchemy_Cookie_Consent_*), constants (ALCHEMY_CONSENT_* ->
  ALCHEMY_COOKIE_CONSENT_*).
* Changed: option names (alchemy_consent_settings, _cookie_list,
  _db_version -> alchemy_cookie_consent_*), the database table
  (wp_alchemy_consent_log -> wp_alchemy_cookie_consent_log), and both
  cookies (alchemy_consent, alchemy_consent_highrisk ->
  alchemy_cookie_consent, alchemy_cookie_consent_highrisk).
* Changed: AJAX action, all nonce actions, the localized JS object
  (alchemyConsentData -> alchemyCookieConsentData), every CSS class and
  element ID (alchemy-consent-* -> alchemy-cookie-consent-*), and every
  dataLayer event/variable name (alchemy_consent_default/update/
  necessary/analytics/marketing/highrisk -> alchemy_cookie_consent_*) —
  update any GTM triggers already wired to the old variable names.
* Changed: the [alchemy_consent_settings_link] shortcode is now
  [alchemy_cookie_consent_settings_link]. [alchemy_cookie_policy],
  [alchemy_regulatory_links], and [alchemy_privacy_choices] are
  unchanged — they never contained the old "alchemy_consent" token.
* Not changed: the GitHub repo is still named "alchemy-consent" (only
  the plugin's own local slug changed) — rename the repo too via
  Settings if full consistency there is wanted, and update the URL in
  the main plugin file's buildUpdateChecker() call to match.
* Note: no migration path from 1.7.1's data — a site with the old
  plugin active needs a clean deactivate + delete + fresh install of
  this version, same as any other slug change. Old options/table are
  left in place (harmless, unused) rather than auto-migrated.

= 1.7.1 =
* Changed: display name updated to "Alchemy Cookie Consent" (the
  WordPress admin menu shows the shorter "Cookie Consent" — full name
  on the settings page itself and everywhere else). This is a
  display-only rename: the plugin slug/folder (alchemy-cookie-consent), text
  domain, PHP class names, constants, option names, the database table,
  cookie names, shortcode names, the GitHub repo, and the update
  checker's slug are all unchanged, specifically to avoid repeating the
  disruption the WA Consent -> Alchemy Consent rename caused in 1.6.0.

= 1.7.0 =
* Added: High-Risk Consent — a separate always-ask prompt for session-
  recording tools (Hotjar, Clarity) and chat widgets, independent of
  the Strict/Light/Exempt geo tiers. New Cookie List columns (High-risk
  checkbox, Visitor notice), a dedicated cookie/dataLayer signal
  (alchemy_cookie_consent_highrisk), and a compact standalone prompt shown
  only once the main banner is out of the way. See readme "High-Risk
  Consent" section.
* Changed: Hotjar and Microsoft Clarity quick-add presets now default
  to High-risk flagged, with pre-written visitor notices. Bing UET and
  Facebook Pixel left unflagged by default (named more loosely in the
  relevant case law) — a per-client judgement call.
* Added: ajax_save_consent() now takes an explicit "scope" parameter
  (general vs highrisk) so a High-Risk-only decision can never
  overwrite a visitor's already-granted Necessary/Analytics/Marketing
  categories, and can't record a grant for a signal the site never
  actually asked about.
* Added: honors Global Privacy Control (navigator.globalPrivacyControl)
  as a CCPA/CPRA opt-out signal — suppresses Marketing everywhere it
  would otherwise be auto-granted or offered via Accept All, without
  requiring a click. Logged with its own "gpc" source.
* Added: [alchemy_privacy_choices] shortcode — a one-click "Do Not Sell
  or Share My Personal Information" link for a client's footer, per
  CPRA. See readme "California Privacy (CCPA/CPRA)" section.
* Fixed: the Geo Targeting readme section had gone stale describing the
  old two-tier design and "geo-default" source after the Strict/Light/
  Exempt split landed in 1.5.0 — rewritten to match current behaviour.

= 1.6.6 =
* Fixed: the GitHub repo had plugin files nested inside an extra
  alchemy-cookie-consent/ subfolder instead of sitting at the repo root.
  Combined with GitHub always wrapping a tag's downloaded zip in its
  own folder, this put alchemy-cookie-consent.php two levels deep, which the
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
  try/catch, unlike banner.js's equivalent — a malformed alchemy_cookie_consent
  cookie (DevTools edit, a colliding third-party script, a truncating
  proxy) would throw and silently skip the dataLayer.push, blocking every
  GTM tag gated on alchemy_cookie_consent_default (Bing UET, FB Pixel, Hotjar,
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
* Removed: the wa_consent -> alchemy_cookie_consent one-time migration
  (table rename, option copy) from Alchemy_Cookie_Consent_Activator — dead
  code once every install starts life as Alchemy Consent.
* Removed: [wa_cookie_policy], [wa_consent_settings_link], and
  [wa_regulatory_links] legacy shortcode aliases. Use the alchemy_
  prefixed names.
* Fixed: the front-end localized script data was still named
  `waConsentData` — the one leftover "wa"-prefixed identifier in the
  codebase, despite 1.6.0 renaming everything else specifically
  because WordPress.org flagged "wa" as a restricted term. Renamed to
  `alchemyCookieConsentData`.
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
  (wa_consent -> alchemy_cookie_consent), so existing visitors with a saved
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
