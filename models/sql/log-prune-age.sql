DELETE FROM {PREFIX}indexnow_log WHERE sent_at < (NOW() - INTERVAL 30 DAY)
