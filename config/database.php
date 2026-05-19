<?php
/**
 * Database configuration & PDO bootstrap.
 *
 * Adjust the constants below to match your hosting environment.
 * The first time the app runs against an empty schema it will
 * auto-create all required tables (see Database::ensureSchema).
 */

declare(strict_types=1);

// ---- Environment-specific overrides (NOT committed) ----
// On a production server, create `config/database.local.php` with the real
// credentials and any constant you want to override. The example file
// `database.local.example.php` shows the format.
if (is_file(__DIR__ . '/database.local.php')) {
    require __DIR__ . '/database.local.php';
}

// ---- Defaults (used unless an override defined the constant first) ----
defined('DB_HOST')    || define('DB_HOST',    getenv('DB_HOST') ?: '127.0.0.1');
defined('DB_PORT')    || define('DB_PORT',    getenv('DB_PORT') ?: '3306');
defined('DB_NAME')    || define('DB_NAME',    getenv('DB_NAME') ?: 'finance_app');
defined('DB_USER')    || define('DB_USER',    getenv('DB_USER') ?: 'root');
defined('DB_PASS')    || define('DB_PASS',    getenv('DB_PASS') ?: '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

defined('APP_NAME')         || define('APP_NAME',        'FinTrack');
defined('APP_VERSION')      || define('APP_VERSION',     '1.0.0');
defined('APP_TIMEZONE')     || define('APP_TIMEZONE',    'Asia/Kuala_Lumpur');
defined('APP_BASE_URL')     || define('APP_BASE_URL',    '/Calculator');     // '' on a domain root
defined('SESSION_LIFETIME') || define('SESSION_LIFETIME', 60 * 60 * 8);      // 8 hours

date_default_timezone_set(APP_TIMEZONE);

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            // Ensure database exists, then connect to it.
            $bootstrap = new PDO($dsnNoDb, DB_USER, DB_PASS, $opts);
            $bootstrap->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` '
                . 'DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci');

            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
            self::ensureSchema(self::$pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<h3>Database connection failed.</h3><pre>'
                . htmlspecialchars($e->getMessage()) . '</pre>');
        }

        return self::$pdo;
    }

    /**
     * Auto-creates all required tables on first run.
     */
    private static function ensureSchema(PDO $pdo): void
    {
        $statements = [
            "CREATE TABLE IF NOT EXISTS `users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(120) NOT NULL,
                `email` VARCHAR(160) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `currency` VARCHAR(8) NOT NULL DEFAULT 'RM',
                `language` VARCHAR(8) NOT NULL DEFAULT 'en',
                `theme` VARCHAR(16) NOT NULL DEFAULT 'light',
                `remember_token` VARCHAR(255) NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX idx_users_email (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `categories` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(80) NOT NULL,
                `type` ENUM('income','expense') NOT NULL DEFAULT 'expense',
                `icon` VARCHAR(40) NULL,
                `color` VARCHAR(20) NULL DEFAULT '#0d6efd',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX idx_categories_user (`user_id`),
                CONSTRAINT fk_categories_user FOREIGN KEY (`user_id`)
                  REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `transactions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(160) NOT NULL,
                `amount` DECIMAL(14,2) NOT NULL,
                `type` ENUM('income','expense') NOT NULL,
                `category` VARCHAR(80) NOT NULL DEFAULT 'Other',
                `note` TEXT NULL,
                `payment_method` VARCHAR(40) NOT NULL DEFAULT 'Cash',
                `date` DATE NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX idx_tx_user (`user_id`),
                INDEX idx_tx_date (`date`),
                INDEX idx_tx_user_date (`user_id`,`date`),
                INDEX idx_tx_user_type (`user_id`,`type`),
                INDEX idx_tx_user_category (`user_id`,`category`),
                CONSTRAINT fk_tx_user FOREIGN KEY (`user_id`)
                  REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `budgets` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `category` VARCHAR(80) NOT NULL DEFAULT 'ALL',
                `amount` DECIMAL(14,2) NOT NULL,
                `period` ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY uniq_budget_user_cat_period (`user_id`,`category`,`period`),
                CONSTRAINT fk_budgets_user FOREIGN KEY (`user_id`)
                  REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
    }
}
