-- How many rows the log holds, the oldest and the last accepted submission.
SELECT
    COUNT(*) AS rows_kept,
    MIN(sent_at) AS oldest,
    MAX(CASE WHEN http_code IN (200, 202) THEN sent_at END) AS last_accepted
FROM {PREFIX}indexnow_log
