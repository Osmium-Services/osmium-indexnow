-- Submissions per day for the last 7 days, accepted (200 or 202) against failed, with how many URLs each covered.
SELECT
    DATE(sent_at) AS day,
    SUM(http_code IN (200, 202)) AS accepted,
    SUM(http_code NOT IN (200, 202)) AS failed,
    SUM(CASE WHEN http_code IN (200, 202) THEN url_count ELSE 0 END) AS accepted_urls
FROM {PREFIX}indexnow_log
WHERE sent_at >= (NOW() - INTERVAL 7 DAY)
GROUP BY DATE(sent_at)
ORDER BY day DESC
