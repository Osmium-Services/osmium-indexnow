# Osmium IndexNow

Tells Bing, Yandex, Naver, Seznam and other [IndexNow](https://www.indexnow.org) search engines the moment a page is created, edited, moved, deleted or restored, instead of waiting for them to re-crawl. Google does not use IndexNow.

Admin: Traffic > IndexNow. A status line, one Turn on / Turn off button, an Advanced section (Send all pages again, the key file link, Make a new key) and a plain-English activity list.

## How it works

- **Hooks**: core fires `page.saved` and `page.removed` from the admin Pages area (needs a core with those events). The service sends the page's full URL, plus the old URL when an edit moved the page. Pages set to noindex are not announced when saved; a removed page always is. The same page saved again within 10 minutes of an accepted ping is not sent again.
- **Turning it on**: makes a 32-character key, writes `<web root>/<key>.txt` containing just the key, and on the live site sends every indexable page once. Every submission checks the key file is still there and rewrites it if a deploy dropped it. The key is public by design.
- **Live only**: nothing is sent from dev or staging (`environment` of `dev` or `staging`).
- **Never blocks a save**: one request, 3 s connect and 5 s total timeout, errors logged and swallowed.
- **Send all pages again** (Advanced): every indexable page from the sitemap query (up to 10,000 per request), once per 24 hours (the time and count are kept in the settings file). Only needed for pages added by something other than saving them in the admin. Blog and Shop pages announce themselves, since they save through the same Pages code.
- **Not covered**: text edited inside a theme's view files, and the Shop service switching its pages on or off (it writes to the pages table directly).

## Data

`indexnow_log` (prefixed): one row per submission, kept 30 days and at most 200 rows. Uninstalling keeps it, the settings file `app/config/services/osmium-indexnow.json.php` and the key file.

## Tests

`php vendor/phpunit.phar app/services/osmium-indexnow/tests` inside the dev container (copy the service there first).
