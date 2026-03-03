-- Ruckclean Lockers Tables

-- Locker configuration
CREATE TABLE IF NOT EXISTS `PREFIX_rk_locker` (
    `id_locker` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `ttlock_lock_id` VARCHAR(128) DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `position` INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_locker`),
    KEY `idx_active_position` (`active`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Locker assignments to orders
CREATE TABLE IF NOT EXISTS `PREFIX_rk_locker_assignment` (
    `id_assignment` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_locker` INT(10) UNSIGNED NOT NULL,
    `id_order` INT(10) UNSIGNED NOT NULL,
    `status` ENUM('assigned', 'ready', 'collected', 'expired', 'cancelled') NOT NULL DEFAULT 'assigned',
    `pin_code` VARCHAR(32) DEFAULT NULL,
    `pin_valid_from` DATETIME DEFAULT NULL,
    `pin_valid_until` DATETIME DEFAULT NULL,
    `date_assigned` DATETIME NOT NULL,
    `date_ready` DATETIME DEFAULT NULL,
    `date_collected` DATETIME DEFAULT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_assignment`),
    KEY `idx_locker` (`id_locker`),
    KEY `idx_order` (`id_order`),
    KEY `idx_status` (`status`),
    UNIQUE KEY `uk_order` (`id_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Event log for audit trail
CREATE TABLE IF NOT EXISTS `PREFIX_rk_locker_log` (
    `id_log` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_locker` INT(10) UNSIGNED DEFAULT NULL,
    `id_order` INT(10) UNSIGNED DEFAULT NULL,
    `id_assignment` INT(10) UNSIGNED DEFAULT NULL,
    `event_type` VARCHAR(64) NOT NULL,
    `event_data` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `id_employee` INT(10) UNSIGNED DEFAULT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_log`),
    KEY `idx_locker` (`id_locker`),
    KEY `idx_order` (`id_order`),
    KEY `idx_date` (`date_add`),
    KEY `idx_event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default 4 lockers
INSERT INTO `PREFIX_rk_locker` (`name`, `location`, `active`, `position`, `date_add`, `date_upd`) VALUES
('Locker 1', 'Posición A', 1, 1, NOW(), NOW()),
('Locker 2', 'Posición B', 1, 2, NOW(), NOW()),
('Locker 3', 'Posición C', 1, 3, NOW(), NOW()),
('Locker 4', 'Posición D', 1, 4, NOW(), NOW());
