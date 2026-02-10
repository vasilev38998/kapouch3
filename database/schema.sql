SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL UNIQUE,
  role ENUM('user','barista','manager','admin') NOT NULL DEFAULT 'user',
  ref_code VARCHAR(32) NOT NULL UNIQUE,
  referred_by_user_id BIGINT UNSIGNED NULL,
  birthday DATE NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_users_referred_by FOREIGN KEY (referred_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS otp_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts_left INT NOT NULL,
  sent_at DATETIME NOT NULL,
  ip VARCHAR(45) NULL,
  sms_status VARCHAR(50) NULL,
  sms_message_id VARCHAR(100) NULL,
  INDEX idx_otp_phone(phone),
  INDEX idx_otp_sent(sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  address VARCHAR(255) NULL,
  `2gis_url` VARCHAR(255) NULL,
  yandex_url VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  staff_user_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  status ENUM('created','reversed','cancelled') NOT NULL DEFAULT 'created',
  meta_json TEXT NULL,
  idempotency_key VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_orders_staff FOREIGN KEY (staff_user_id) REFERENCES users(id),
  CONSTRAINT fk_orders_location FOREIGN KEY (location_id) REFERENCES locations(id),
  UNIQUE KEY uniq_orders_idempotency (idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cashback_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  type ENUM('earn','spend','adjust','reversal') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  created_by_staff_id BIGINT UNSIGNED NULL,
  meta_json TEXT NULL,
  idempotency_key VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_cb_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_cb_order FOREIGN KEY (order_id) REFERENCES orders(id),
  CONSTRAINT fk_cb_staff FOREIGN KEY (created_by_staff_id) REFERENCES users(id),
  INDEX idx_cb_user_created(user_id, created_at),
  INDEX idx_cb_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stamp_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  delta INT NOT NULL,
  reason VARCHAR(50) NOT NULL,
  created_by_staff_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_st_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_st_order FOREIGN KEY (order_id) REFERENCES orders(id),
  CONSTRAINT fk_st_staff FOREIGN KEY (created_by_staff_id) REFERENCES users(id),
  INDEX idx_st_user_created(user_id, created_at),
  INDEX idx_st_order(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS loyalty_state (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  stamps INT NOT NULL DEFAULT 0,
  reward_available TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_loyalty_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rewards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  value DECIMAL(10,2) NULL,
  status ENUM('active','redeemed','expired') NOT NULL DEFAULT 'active',
  expires_at DATETIME NULL,
  meta_json TEXT NULL,
  idempotency_key VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  redeemed_at DATETIME NULL,
  CONSTRAINT fk_rewards_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promocodes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL UNIQUE,
  type ENUM('stamps','cashback_fixed','cashback_boost_percent','reward') NOT NULL,
  value DECIMAL(10,2) NOT NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  max_uses_total INT NULL,
  max_uses_per_user INT NULL,
  min_order_amount DECIMAL(10,2) NULL,
  location_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  meta_json TEXT NULL,
  CONSTRAINT fk_promocode_location FOREIGN KEY (location_id) REFERENCES locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promocode_redemptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  promocode_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  redeemed_at DATETIME NOT NULL,
  CONSTRAINT fk_pr_promocode FOREIGN KEY (promocode_id) REFERENCES promocodes(id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_pr_order FOREIGN KEY (order_id) REFERENCES orders(id),
  INDEX idx_pr_promocode_user(promocode_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS missions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL,
  config_json TEXT NOT NULL,
  reward_json TEXT NOT NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mission_progress (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  progress_json TEXT NOT NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  last_updated_at DATETIME NOT NULL,
  CONSTRAINT fk_mp_mission FOREIGN KEY (mission_id) REFERENCES missions(id),
  CONSTRAINT fk_mp_user FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE KEY uniq_mission_user (mission_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(50) NOT NULL,
  target_id BIGINT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  status ENUM('ok','error') NOT NULL,
  message TEXT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
  INDEX idx_audit_actor_created(actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fraud_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(50) NOT NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_fraud_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS qr_nonces (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nonce VARCHAR(64) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NOT NULL,
  used_at DATETIME NOT NULL,
  CONSTRAINT fk_qr_nonce_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS push_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  endpoint VARCHAR(500) NOT NULL UNIQUE,
  p256dh VARCHAR(255) NULL,
  auth VARCHAR(255) NULL,
  content_encoding VARCHAR(20) NULL,
  user_agent VARCHAR(255) NULL,
  permission ENUM('default','granted','denied') NOT NULL DEFAULT 'default',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL,
  last_seen_at DATETIME NULL,
  last_success_at DATETIME NULL,
  CONSTRAINT fk_push_sub_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_push_sub_user(user_id),
  INDEX idx_push_sub_seen(last_seen_at),
  INDEX idx_push_sub_permission(permission, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS push_campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  url VARCHAR(255) NULL,
  target_role ENUM('all','user','barista','manager','admin') NOT NULL DEFAULT 'all',
  recipients_count INT NOT NULL DEFAULT 0,
  clicks_count INT NOT NULL DEFAULT 0,
  status ENUM('scheduled','sent') NOT NULL DEFAULT 'sent',
  scheduled_for DATETIME NULL,
  sent_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_push_campaign_actor FOREIGN KEY (created_by_user_id) REFERENCES users(id),
  INDEX idx_push_campaign_status_time(status, scheduled_for)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS push_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  url VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  url VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_user_notif_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_user_notif_campaign FOREIGN KEY (campaign_id) REFERENCES push_campaigns(id),
  INDEX idx_user_notif_user_read(user_id, is_read, created_at),
  INDEX idx_user_notif_campaign(campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS menu_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) NOT NULL DEFAULT 'Напитки',
  price DECIMAL(10,2) NOT NULL,
  description TEXT NULL,
  image_url VARCHAR(500) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_sold_out TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_menu_category(category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_menu_favorites (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  menu_item_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_menu_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_menu_fav_item FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_menu_favorite_user_item (user_id, menu_item_id),
  INDEX idx_menu_favorites_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS payment_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(50) NOT NULL,
  external_order_id VARCHAR(100) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  status ENUM('created','paid','failed','cancelled') NOT NULL DEFAULT 'created',
  payload_json TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE KEY uniq_payment_provider_order (provider, external_order_id),
  INDEX idx_payments_user_created (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS qr_short_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_qr_short_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_qr_short_expires(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
