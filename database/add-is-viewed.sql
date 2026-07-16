ALTER TABLE custom_printing_requests ADD COLUMN is_viewed TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
