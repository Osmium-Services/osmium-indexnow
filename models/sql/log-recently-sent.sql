SELECT id FROM {PREFIX}indexnow_log
WHERE url = :url AND url_count = 1 AND reason = :reason AND http_code IN (200, 202)
AND sent_at > (NOW() - INTERVAL 10 MINUTE)
LIMIT 1
