# Dhali Copyright

A block that renders a copyright line with a dynamically updated year —
`© 2026 · Client Name · All rights reserved. Designed by Dhali.` — with
an editable company name and a credit link back to Dhali.

Managed centrally by [Dhali Extension Manager](https://github.com/dhali-web/Dhali-Extension-Manager),
which installs and activates this plugin on a site from this repo directly.
It's also a fully standalone, valid WordPress plugin — you can install it
manually the normal way if you ever need to.

## What it does

Adds a "Dhali Copyright" block (`create-block/dhali-copyright`). It has
one attribute, `companyName`, editable from the block's settings panel.
The current year is computed at render time (`date('Y')`), so it never
needs manual updating.

## ⚠️ Known issue — hardcoded fallback name

`src/dhali-copyright/render.php` falls back to a hardcoded client name if
`companyName` is empty:

```php
$company_name = ! empty($attributes['companyName']) ? $attributes['companyName'] : 'twistnflip';
```

That's left over from the site this block was first built for. `block.json`
correctly declares the attribute's own default as the generic
`<Client Name>` — but that default only applies inside the editor when a
fresh instance of the block is inserted. The PHP fallback above is a
_separate_ safety net for whenever `companyName` is somehow empty at
render time, and it should also read a generic placeholder, not a real
former client's name. Fix before relying on this on a new site:

```php
$company_name = ! empty($attributes['companyName']) ? $attributes['companyName'] : '<Client Name>';
```

## Requirements

- WordPress 6.8+
- PHP 7.4+

## Usage

Insert the "Dhali Copyright" block anywhere — typically the footer
template part. Set the company name in the block's sidebar settings; the
year updates itself.

## Development

Scaffolded with `@wordpress/create-block`. Source lives in `src/`,
compiled output in `build/` — **both are committed to this repo**, since
Dhali Extension Manager installs directly from the repo's files and does
not run a build step of its own.

```bash
npm install
npm start     # dev build with watch
npm run build # production build — run this before committing
```

If you edit anything in `src/`, always run `npm run build` and commit the
updated `build/` output in the same commit — otherwise the site installs
whatever `build/` last had, not your source changes.

## Keeping this updated on sites where it's already installed

This plugin carries its own update-checker
(`includes/dhali-plugin-updater.php`) and declares an `Update URI:`
header pointing at this repo. Once installed on a site, that site checks
this repo's `master` branch and shows a normal "Update available" notice
in wp-admin — no separate deploy step needed for future versions.

To ship a new version: bump `Version:` in the header above, push to
`master`, done. Confirmed working end-to-end as of `0.2.0`.
