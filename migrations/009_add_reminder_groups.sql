-- Migration 009: User-defined reminder groups (inclusive/multi-label)

CREATE TABLE IF NOT EXISTS reminder_groups (
    ID        INT AUTO_INCREMENT PRIMARY KEY,
    user_id   INT NOT NULL,
    name      VARCHAR(100) NOT NULL,
    color     VARCHAR(7)   NOT NULL DEFAULT '#7d3c98',
    created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rg_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reminder_group_members (
    group_id  INT NOT NULL,
    email_id  INT NOT NULL,
    PRIMARY KEY (group_id, email_id),
    KEY idx_rgm_email (email_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
