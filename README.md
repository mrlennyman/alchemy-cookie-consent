# Alchemy Consent

Self-hosted WordPress cookie consent banner — WP Consent API + Google Consent Mode v2, optional geo-targeting, no external cookie scanner. Built for the Website Alchemy client portfolio.

## Features

- Opt-in cookie banner with Necessary / Analytics / Marketing categories
- Registers with the [WP Consent API](https://wordpress.org/plugins/wp-consent-api/) so Google Site Kit's Consent Mode v2 picks it up automatically
- `window.dataLayer` bridge for wiring non-Google tags (Bing UET, Facebook Pixel, Hotjar, Clarity, etc.) through GTM
- Hand-maintained cookie list with a quick-add helper for common services — no automated scanner, no false positives
- Optional geo-targeting: Strict (opt-in banner), Light (auto-granted, visible opt-out), or Exempt (auto-granted, no UI) by country — off by default
- Consent log with CSV export for compliance audits
- `[alchemy_cookie_policy]`, `[alchemy_consent_settings_link]`, and `[alchemy_regulatory_links]` shortcodes

## Requirements

- WordPress 6.2+
- PHP 7.4+

## Installation

Download the latest release zip from the [Releases](../../releases) page and upload it via **Plugins → Add New → Upload Plugin**. This plugin isn't on WordPress.org — updates are delivered by checking this repo's releases directly, so once installed, new versions show up as a normal "Update available" notice on the Plugins page instead of requiring a manual re-upload.

## Documentation

See [`readme.txt`](readme.txt) for full setup instructions, the FAQ, the external-services disclosure (Cloudflare-based geo-detection), and the version changelog.

## License

GPLv2 or later.
