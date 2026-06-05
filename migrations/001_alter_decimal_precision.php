<?php

/**
 * Migration: Ubah tipe data DECIMAL(10,0) menjadi DECIMAL(10,2) untuk support angka desimal
 * 
 * File ini dijalankan SEKALI saja untuk mengubah struktur database existing.
 * Pastikan sudah di-backup database sebelum menjalankan migration ini.
 * 
 * Cara menjalankan:
 * 1. Buka browser ke: telurku/migrations/001_alter_decimal_precision.php
 * 2. Atau jalankan via terminal: php migrations/001_alter_decimal_precision.php
 */

require_once __DIR__ . '/../config.php';

$tables_to_modify = [
    // Tabel barang
    'barang' => [
        'harga_beli' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'harga_jual' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'harga_jual_renteng' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'harga_jual_pcs' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
    ],
    // Tabel stok_masuk
    'stok_masuk' => [
        'harga_beli' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'harga_jual' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
        'jumlah_tambah' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
    ],
    // Tabel stok_keluar
    'stok_keluar' => [
        'jumlah_kurang' => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
    ],
    // Tabel detail_penjualan
    'detail_penjualan' => [
        'harga_satuan' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'subtotal' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
    ],
    // Tabel penjualan
    'penjualan' => [
        'total_bayar' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
    ],
];

$success_count = 0;
$error_count = 0;
$errors = [];

echo "====================================\n";
echo "MIGRATION: Ubah Decimal Precision\n";
echo "====================================\n\n";

foreach ($tables_to_modify as $table => $columns) {
    // Cek apakah tabel ada
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (!$check_table || mysqli_num_rows($check_table) == 0) {
        echo "⚠️  Tabel '$table' tidak ditemukan, skip.\n";
        continue;
    }

    echo "📋 Modifying table: $table\n";

    foreach ($columns as $column => $new_type) {
        // Cek apakah kolom ada
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE '$column'");
        if (!$check_col || mysqli_num_rows($check_col) == 0) {
            echo "   ⚠️  Kolom '$column' tidak ditemukan, skip.\n";
            continue;
        }

        $col_info = mysqli_fetch_assoc($check_col);
        $current_type = $col_info['Type'];

        // Skip jika sudah DECIMAL(10,2) atau DECIMAL(12,2)
        if (strpos($current_type, 'DECIMAL(10,2)') !== false || strpos($current_type, 'DECIMAL(12,2)') !== false) {
            echo "   ✓ Kolom '$column' sudah DECIMAL(10,2) atau DECIMAL(12,2), skip.\n";
            continue;
        }

        // Jalankan ALTER TABLE
        $alter_sql = "ALTER TABLE $table MODIFY COLUMN $column $new_type";
        if (mysqli_query($conn, $alter_sql)) {
            echo "   ✅ Kolom '$column': $current_type → $new_type\n";
            $success_count++;
        } else {
            echo "   ❌ ERROR pada kolom '$column': " . mysqli_error($conn) . "\n";
            $errors[] = "Tabel $table, Kolom $column: " . mysqli_error($conn);
            $error_count++;
        }
    }

    echo "\n";
}

echo "====================================\n";
echo "HASIL MIGRATION\n";
echo "====================================\n";
echo "✅ Berhasil: $success_count kolom\n";
echo "❌ Gagal: $error_count kolom\n";

if (!empty($errors)) {
    echo "\nDetail Error:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\n✨ Migration selesai!\n";
echo "Sekarang form input bisa menerima angka dengan desimal (contoh: 23.7, 15.50, dll)\n";
