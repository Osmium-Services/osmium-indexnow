SELECT id, url, url_count, reason, http_code, detail, sent_at
FROM {PREFIX}indexnow_log
ORDER BY id DESC
LIMIT :limit
