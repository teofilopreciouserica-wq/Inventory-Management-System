-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3309
-- Generation Time: May 14, 2026 at 08:09 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
 

DELIMITER $$
 
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_stock_summary` ()   BEGIN
    SELECT
        p.id,
        p.sku,
        p.name,
        c.name          AS category,
        p.unit,
        p.quantity      AS current_stock,
        p.reorder_level,
        p.unit_price,
        CASE
            WHEN p.quantity = 0            THEN 'Out of Stock'
            WHEN p.quantity <= p.reorder_level THEN 'Low Stock'
            ELSE 'In Stock'
        END             AS stock_status
    FROM products p
    JOIN categories c ON p.category_id = c.id
    ORDER BY p.name;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_stock_in` (IN `p_product_id` INT UNSIGNED, IN `p_user_id` INT UNSIGNED, IN `p_quantity` INT, IN `p_unit_cost` DECIMAL(10,2), IN `p_supplier` VARCHAR(150), IN `p_reference` VARCHAR(100), IN `p_notes` TEXT)   BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
     
    INSERT INTO stock_in (product_id, user_id, quantity, unit_cost, supplier, reference_no, notes)
    VALUES (p_product_id, p_user_id, p_quantity, p_unit_cost, p_supplier, p_reference, p_notes);
     
    UPDATE products
    SET quantity = quantity + p_quantity
    WHERE id = p_product_id;

    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_stock_out` (IN `p_product_id` INT UNSIGNED, IN `p_user_id` INT UNSIGNED, IN `p_quantity` INT, IN `p_reason` VARCHAR(50), IN `p_reference` VARCHAR(100), IN `p_notes` TEXT)   BEGIN
    DECLARE current_qty INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
     
    SELECT quantity INTO current_qty
    FROM products WHERE id = p_product_id FOR UPDATE;

    IF current_qty < p_quantity THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Insufficient stock for this transaction.';
    END IF;
     
    INSERT INTO stock_out (product_id, user_id, quantity, reason, reference_no, notes)
    VALUES (p_product_id, p_user_id, p_quantity, p_reason, p_reference, p_notes);
     
    UPDATE products
    SET quantity = quantity - p_quantity
    WHERE id = p_product_id;

    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_transaction_history` (IN `p_product_id` INT UNSIGNED, IN `p_date_from` DATE, IN `p_date_to` DATE)   BEGIN
    SELECT
        'Stock In'              AS type,
        si.id                  AS transaction_id,
        p.name                 AS product,
        si.quantity,
        si.transaction_at,
        u.username             AS handled_by,
        si.reference_no,
        si.notes
    FROM stock_in si
    JOIN products p ON p.id = si.product_id
    JOIN users    u ON u.id = si.user_id
    WHERE (p_product_id IS NULL OR si.product_id = p_product_id)
      AND DATE(si.transaction_at) BETWEEN p_date_from AND p_date_to

    UNION ALL

    SELECT
        'Stock Out'             AS type,
        so.id                  AS transaction_id,
        p.name                 AS product,
        so.quantity,
        so.transaction_at,
        u.username             AS handled_by,
        so.reference_no,
        so.notes
    FROM stock_out so
    JOIN products p ON p.id = so.product_id
    JOIN users    u ON u.id = so.user_id
    WHERE (p_product_id IS NULL OR so.product_id = p_product_id)
      AND DATE(so.transaction_at) BETWEEN p_date_from AND p_date_to

    ORDER BY transaction_at DESC;
END$$

DELIMITER ;

 

CREATE TABLE `audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(10) UNSIGNED DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

 
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_value`, `new_value`, `ip_address`, `logged_at`) VALUES
(1, 1, 'STOCK_IN', 'stock_in', 1, NULL, '{\"product_id\": 1, \"quantity\": 50, \"unit_cost\": 49.98, \"supplier\": \"Panda\", \"reference_no\": \"\"}', NULL, '2026-05-11 10:37:40'),
(2, NULL, 'QUANTITY_CHANGE', 'products', 1, '{\"quantity\": 0}', '{\"quantity\": 50}', NULL, '2026-05-11 10:37:40'),
(3, 1, 'STOCK_OUT', 'stock_out', 1, NULL, '{\"product_id\": 1, \"quantity\": 7, \"reason\": \"transfer\", \"reference_no\": \"\"}', NULL, '2026-05-11 12:06:15'),
(4, NULL, 'QUANTITY_CHANGE', 'products', 1, '{\"quantity\": 50}', '{\"quantity\": 43}', NULL, '2026-05-11 12:06:15'),
(5, 1, 'STOCK_IN', 'stock_in', 2, NULL, '{\"product_id\": 2, \"quantity\": 50, \"unit_cost\": 70.00, \"supplier\": \"Victory\", \"reference_no\": \"SI-20260511-0002\"}', NULL, '2026-05-11 15:18:40'),
(6, NULL, 'QUANTITY_CHANGE', 'products', 2, '{\"quantity\": 0}', '{\"quantity\": 50}', NULL, '2026-05-11 15:18:40'),
(7, 1, 'STOCK_OUT', 'stock_out', 2, NULL, '{\"product_id\": 1, \"quantity\": 9, \"reason\": \"sold\", \"reference_no\": \"SO-20260512-0001\"}', NULL, '2026-05-12 02:16:43'),
(8, NULL, 'QUANTITY_CHANGE', 'products', 1, '{\"quantity\": 43}', '{\"quantity\": 34}', NULL, '2026-05-12 02:16:43'),
(9, 1, 'STOCK_OUT', 'stock_out', 3, NULL, '{\"product_id\": 1, \"quantity\": 28, \"reason\": \"damaged\", \"reference_no\": \"SO-20260512-0002\"}', NULL, '2026-05-12 02:39:21'),
(10, NULL, 'QUANTITY_CHANGE', 'products', 1, '{\"quantity\": 34}', '{\"quantity\": 6}', NULL, '2026-05-12 02:39:21'),
(11, 1, 'STOCK_IN', 'stock_in', 3, NULL, '{\"product_id\": 3, \"quantity\": 100, \"unit_cost\": 70000.00, \"supplier\": \"MeMysElf\", \"reference_no\": \"SI-20260512-0001\"}', NULL, '2026-05-12 02:44:21'),
(12, NULL, 'QUANTITY_CHANGE', 'products', 3, '{\"quantity\": 0}', '{\"quantity\": 100}', NULL, '2026-05-12 02:44:21');

 
CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
 

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Electronics', 'Electronic devices and accessories', '2026-05-10 16:06:13'),
(2, 'Office Supply', 'Stationery and office items', '2026-05-10 16:06:13'),
(3, 'Furniture', 'Office and home furniture', '2026-05-10 16:06:13');

 

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `sku` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'pcs',
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 10,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

\

INSERT INTO `products` (`id`, `category_id`, `name`, `sku`, `description`, `unit`, `quantity`, `reorder_level`, `unit_price`, `created_at`, `updated_at`) VALUES
(1, 2, 'Ballpen', 'BP-00001', '', '1/1/1', 6, 10, 1000.00, '2026-05-11 04:47:12', '2026-05-12 02:39:21'),
(2, 2, 'Yellow Pad', 'YP-00001', '', '20/1/1', 50, 10, 70.00, '2026-05-11 15:17:58', '2026-05-11 15:18:40'),
(3, 1, 'IPhone Pro Max 17', 'IP-00001', '', '10/10/10', 100, 10, 80000.00, '2026-05-12 02:43:33', '2026-05-12 02:44:21');


DELIMITER $$
CREATE TRIGGER `trg_after_product_update` AFTER UPDATE ON `products` FOR EACH ROW BEGIN
    IF OLD.quantity <> NEW.quantity THEN
        INSERT INTO audit_log (action, table_name, record_id, old_value, new_value)
        VALUES (
            'QUANTITY_CHANGE',
            'products',
            NEW.id,
            JSON_OBJECT('quantity', OLD.quantity),
            JSON_OBJECT('quantity', NEW.quantity)
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_before_product_update` BEFORE UPDATE ON `products` FOR EACH ROW BEGIN
    IF NEW.quantity < 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Product quantity cannot go below zero.';
    END IF;
END
$$
DELIMITER ;


CREATE TABLE `stock_in` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL CHECK (`quantity` > 0),
  `unit_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `supplier` varchar(150) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `transaction_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

 

INSERT INTO `stock_in` (`id`, `product_id`, `user_id`, `quantity`, `unit_cost`, `supplier`, `reference_no`, `notes`, `transaction_at`) VALUES
(1, 1, 1, 50, 49.98, 'Panda', '', '', '2026-05-11 10:37:40'),
(2, 2, 1, 50, 70.00, 'Victory', 'SI-20260511-0002', '', '2026-05-11 15:18:40'),
(3, 3, 1, 100, 70000.00, 'MeMysElf', 'SI-20260512-0001', '', '2026-05-12 02:44:21');

 
DELIMITER $$
CREATE TRIGGER `trg_after_stock_in` AFTER INSERT ON `stock_in` FOR EACH ROW BEGIN
    INSERT INTO audit_log (user_id, action, table_name, record_id, new_value)
    VALUES (
        NEW.user_id,
        'STOCK_IN',
        'stock_in',
        NEW.id,
        JSON_OBJECT(
            'product_id',   NEW.product_id,
            'quantity',     NEW.quantity,
            'unit_cost',    NEW.unit_cost,
            'supplier',     NEW.supplier,
            'reference_no', NEW.reference_no
        )
    );
END
$$
DELIMITER ;

 
CREATE TABLE `stock_out` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL CHECK (`quantity` > 0),
  `reason` enum('sold','damaged','returned','transfer','other') NOT NULL DEFAULT 'sold',
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `transaction_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

 
INSERT INTO `stock_out` (`id`, `product_id`, `user_id`, `quantity`, `reason`, `reference_no`, `notes`, `transaction_at`) VALUES
(1, 1, 1, 7, 'transfer', '', '', '2026-05-11 12:06:15'),
(2, 1, 1, 9, 'sold', 'SO-20260512-0001', '', '2026-05-12 02:16:43'),
(3, 1, 1, 28, 'damaged', 'SO-20260512-0002', '', '2026-05-12 02:39:21');

 
DELIMITER $$
CREATE TRIGGER `trg_after_stock_out` AFTER INSERT ON `stock_out` FOR EACH ROW BEGIN
    INSERT INTO audit_log (user_id, action, table_name, record_id, new_value)
    VALUES (
        NEW.user_id,
        'STOCK_OUT',
        'stock_out',
        NEW.id,
        JSON_OBJECT(
            'product_id',   NEW.product_id,
            'quantity',     NEW.quantity,
            'reason',       NEW.reason,
            'reference_no', NEW.reference_no
        )
    );
END
$$
DELIMITER ;

 
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
 

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'admin', 'admin@inventory.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-05-10 16:06:14');

 
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_user` (`user_id`);

 
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

 
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `fk_product_category` (`category_id`);
 
ALTER TABLE `stock_in`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stockin_product` (`product_id`),
  ADD KEY `fk_stockin_user` (`user_id`);

 
ALTER TABLE `stock_out`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stockout_product` (`product_id`),
  ADD KEY `fk_stockout_user` (`user_id`);
 
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);
 
ALTER TABLE `audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

 
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
 
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

 
ALTER TABLE `stock_in`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

 
ALTER TABLE `stock_out`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
 
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

 
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
 
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;
 
ALTER TABLE `stock_in`
  ADD CONSTRAINT `fk_stockin_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stockin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

 
ALTER TABLE `stock_out`
  ADD CONSTRAINT `fk_stockout_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stockout_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
