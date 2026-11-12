# Osmium IndexNow

Tells Bing, Yandex, Naver, Seznam and other [IndexNow](https://www.indexnow.org) search engines the moment a page is created, edited, moved, deleted or restored, instead of waiting for them to re-crawl. Google does not use IndexNow.

Admin: Traffic > IndexNow (on/off, key file link, Submit all pages, Make a new key, recent submissions).

## How it works

- **Hooks**: core fires `page.saved` and `page.removed` from the admin Pages area (needs a core with those events). The service sends the page's full URL, plus the old URL when an edit moved the page. Pages set to noindex are not announced when saved; a removed page always is.
- **Key file**: saving the settings makes a 32-character key and writes `<web root>/<key>.txt` containing just the key. Every submission checks the file is still there and rewrites it if a deploy dropped it. The key is public by design.
- **Live only**: nothing is sent from dev or staging (`environment` of `dev` or `staging`).
- **Never blocks a save**: one request, 3 s connect and 5 s total timeout, errors logged and swallowed.
- **Submit all pages**: sends every indexable page from the sitemap query (up to 10,000 per request), once per 24 hours (the time is kept in the settings file). It is needed once after turning the service on. Pages saved later announce themselves, including Blog and Shop pages, since they save through the same Pages code.
- **Not covered**: text edited inside a theme's view files, and the Shop service switching its pages on or off (it writes to the pages table directly).

## Data

`indexnow_log` (prefixed): one row per submission, kept 30 days and at most 200 rows. Uninstalling keeps it, the settings file `app/config/services/osmium-indexnow.json.php` and the key file.

## Tests

`php vendor/phpunit.phar app/services/osmium-indexnow/tests` inside the dev container (copy the service there first).
