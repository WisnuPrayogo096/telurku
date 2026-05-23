-- Pengurangan stok (keluar non-penjualan)
CREATE TABLE IF NOT EXISTS stok_keluar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    barang_id INT NOT NULL,
    jumlah_kurang DECIMAL(10,2) NOT NULL DEFAULT 0,
    keterangan VARCHAR(255) NOT NULL DEFAULT 'Keperluan pribadi',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE
);
