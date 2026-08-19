-- migration.sql
-- ─────────────────────────────────────────
-- একবারই রান করুন (phpMyAdmin বা mysql client দিয়ে)।
-- এটাকে অটো-রান করার দরকার নেই — dashboard-এ এই মাইগ্রেশন লজিক আর ইনলাইন রাখা হয়নি (production practice)।

ALTER TABLE sys_dps_accounts
    ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL DEFAULT NULL COMMENT 'uploads/accounts/ ফোল্ডারের ফাইলনেম',
    ADD COLUMN IF NOT EXISTS next_deposit_date DATE NULL DEFAULT NULL;

-- daily_cron_log টেবিল না থাকলে তৈরি করুন
CREATE TABLE IF NOT EXISTS daily_cron_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_date DATE NOT NULL UNIQUE,
    loans_processed TINYINT(1) NOT NULL DEFAULT 0,
    dps_processed TINYINT(1) NOT NULL DEFAULT 0,
    total_interest DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
