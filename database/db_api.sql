CREATE DATABASE db_api
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE db_api;

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_categories_name (name)
);

INSERT INTO products (name, price, stock) VALUES
('Kopi Arabika', 85000, 24),
('Kopi Robusta', 65000, 30),
('Kopi Liberika', 72000, 15),
('Teh Hitam', 30000, 50),
('Teh Hijau', 35000, 40),
('Gula Aren', 20000, 60),
('Susu UHT', 18000, 80),
('Cokelat Bubuk', 45000, 25),
('Madu Hutan', 90000, 12),
('Biskuit Gandum', 15000, 100),
('Roti Tawar', 14000, 45),
('Selai Nanas', 22000, 33),
('Mentega', 28000, 28),
('Keju Cheddar', 38000, 20);

INSERT INTO categories (name, description) VALUES
('Minuman', 'Kopi, teh, dan minuman siap seduh'),
('Makanan ringan', 'Biskuit dan camilan'),
('Bahan dapur', 'Bahan untuk memasak dan membuat kue'),
('Produk susu', NULL);
