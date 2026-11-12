DELETE FROM {PREFIX}indexnow_log
WHERE id <= (SELECT cutoff.id FROM (SELECT id FROM {PREFIX}indexnow_log ORDER BY id DESC LIMIT 1 OFFSET 200) AS cutoff)
