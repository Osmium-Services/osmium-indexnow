-- The newest failed submissions. No URL: the reason, the answer and the time are enough to diagnose.
SELECT sent_at, reason, http_code, detail, url_count
FROM {PREFIX}indexnow_log
WHERE http_code NOT IN (200, 202)
ORDER BY id DESC
LIMIT :limit
