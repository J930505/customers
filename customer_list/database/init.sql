SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS customer_list
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE customer_list;

CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    password_plain VARCHAR(255) DEFAULT NULL,
    is_engineer TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    notify_date DATE DEFAULT NULL,
    billing_time VARCHAR(50) DEFAULT NULL,
    item_no INT DEFAULT NULL,
    customer_code VARCHAR(100) DEFAULT NULL,
    customer_name VARCHAR(255) DEFAULT NULL,
    monthly_fee DECIMAL(10,2) DEFAULT NULL,
    stationery VARCHAR(255) DEFAULT NULL,
    invoice VARCHAR(255) DEFAULT NULL,
    receipt_one VARCHAR(255) DEFAULT NULL,
    reply_mail VARCHAR(255) DEFAULT NULL,
    months_count INT DEFAULT NULL,
    company_tax_id VARCHAR(100) DEFAULT NULL,
    tax_code VARCHAR(100) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    accountant VARCHAR(100) DEFAULT NULL,
    receipt_two VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    billing_detail_zhongshan TEXT DEFAULT NULL,
    delivery_method VARCHAR(100) DEFAULT NULL,
    contact_address VARCHAR(255) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    deleted_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    KEY idx_customer_code (customer_code),
    KEY idx_notify_date (notify_date),
    KEY idx_customer_name (customer_name),
    KEY idx_company_tax_id (company_tax_id),
    KEY idx_tax_code (tax_code),
    KEY idx_accountant (accountant),
    KEY idx_contact_address (contact_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_logs (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    action_type VARCHAR(20) NOT NULL COMMENT 'create/update/delete/restore',
    old_data JSON DEFAULT NULL,
    new_data JSON DEFAULT NULL,
    operated_by INT DEFAULT NULL,
    operated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_customer_id (customer_id),
    KEY idx_action_type (action_type),
    KEY idx_operated_at (operated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_logs (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    imported_by INT DEFAULT NULL,
    success_count INT NOT NULL DEFAULT 0,
    skipped_count INT NOT NULL DEFAULT 0,
    replaced_count INT NOT NULL DEFAULT 0,
    failed_count INT NOT NULL DEFAULT 0,
    error_file_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
