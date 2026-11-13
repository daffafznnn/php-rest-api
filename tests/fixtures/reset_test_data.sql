TRUNCATE TABLE products;
TRUNCATE TABLE categories;

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
