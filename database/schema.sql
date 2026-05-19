-- =====================================================
-- Personal Finance Management - Database Schema
-- MySQL 5.7+ / MariaDB 10.3+
-- =====================================================

CREATE DATABASE IF NOT EXISTS `finance_app`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `finance_app`;

-- -----------------------------------------------------
-- Users
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `email`         VARCHAR(160) NOT NULL UNIQUE,
  `password`      VARCHAR(255) NOT NULL,
  `currency`      VARCHAR(8)   NOT NULL DEFAULT 'RM',
  `language`      VARCHAR(8)   NOT NULL DEFAULT 'en',
  `theme`         VARCHAR(16)  NOT NULL DEFAULT 'light',
  `remember_token` VARCHAR(255) NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX idx_users_email (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Categories
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `name`       VARCHAR(80) NOT NULL,
  `type`       ENUM('income','expense') NOT NULL DEFAULT 'expense',
  `icon`       VARCHAR(40) NULL,
  `color`      VARCHAR(20) NULL DEFAULT '#0d6efd',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX idx_categories_user (`user_id`),
  CONSTRAINT fk_categories_user FOREIGN KEY (`user_id`)
     REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Transactions
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `title`          VARCHAR(160) NOT NULL,
  `amount`         DECIMAL(14,2) NOT NULL,
  `type`           ENUM('income','expense') NOT NULL,
  `category`       VARCHAR(80) NOT NULL DEFAULT 'Other',
  `note`           TEXT NULL,
  `payment_method` VARCHAR(40) NOT NULL DEFAULT 'Cash',
  `date`           DATE NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX idx_tx_user        (`user_id`),
  INDEX idx_tx_date        (`date`),
  INDEX idx_tx_user_date   (`user_id`,`date`),
  INDEX idx_tx_user_type   (`user_id`,`type`),
  INDEX idx_tx_user_category (`user_id`,`category`),
  CONSTRAINT fk_tx_user FOREIGN KEY (`user_id`)
     REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Budgets
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `budgets` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`   INT UNSIGNED NOT NULL,
  `category`  VARCHAR(80) NOT NULL DEFAULT 'ALL',
  `amount`    DECIMAL(14,2) NOT NULL,
  `period`    ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY uniq_budget_user_cat_period (`user_id`,`category`,`period`),
  CONSTRAINT fk_budgets_user FOREIGN KEY (`user_id`)
     REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Default seed (optional demo account)
-- Email: demo@finance.local   Password: demo1234
-- -----------------------------------------------------
INSERT IGNORE INTO `users` (`id`,`name`,`email`,`password`,`currency`)
VALUES (1, 'Demo User','demo@finance.local',
        '$2y$10$wH7gI3LpQ3l7Q1f8nQk1m.uW2J5kY3J8nGm9b3Y5m5k4VbN9Cz9zG',
        'RM');
