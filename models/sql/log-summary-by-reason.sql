-- How many submissions in the last 7 days came from each reason.
SELECT reason, COUNT(*) AS submissions
FROM {PREFIX}indexnow_log
WHERE sent_at >= (NOW() - INTERVAL 7 DAY)
GROUP BY reason
ORDER BY submissions DESC
LIMIT 10
