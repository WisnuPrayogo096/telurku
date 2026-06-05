<?php
require_once 'config.php';
requireLogin();

$stats = getDashboardStats($conn);
extract($stats);

// Get chart data
$dailyChart = getDailyChartData($conn, 30);
$monthlyChart = getMonthlyChartData($conn, 12);

$pageTitle = 'Dashboard - Toko Rahmat Jaya';
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require_once 'includes/head.php';
$navTitle = 'Dashboard';
$showUserGreeting = true;
$showProfilLink = true;
$showLogout = true;
require_once 'includes/navbar.php';
?>

<div class="app-container">
    <div class="app-alert app-alert-info">
        <i class="ph ph-clock text-xl text-brand shrink-0"></i>
        <span>Sesi login aktif <strong>30 hari</strong>. Setelah itu, masukkan username &amp; password lagi.</span>
    </div>

    <div class="stat-grid">
        <div class="stat-card stat-card--blue">
            <div class="stat-card-icon"><i class="ph ph-currency-circle-dollar"></i></div>
            <div class="stat-card-label">Penjualan Hari Ini</div>
            <div class="stat-card-value"><?php echo formatRupiah($total_today); ?></div>
            <div class="stat-card-hint">Total transaksi hari ini</div>
        </div>
        <div class="stat-card stat-card--green">
            <div class="stat-card-icon"><i class="ph ph-trend-up"></i></div>
            <div class="stat-card-label">Keuntungan Hari Ini</div>
            <div class="stat-card-value <?php echo $total_keuntungan_today < 0 ? 'text-red-600' : ''; ?>"><?php echo formatRupiah($total_keuntungan_today); ?></div>
            <div class="stat-card-hint">Penjualan − modal (HPP)</div>
        </div>
        <div class="stat-card stat-card--purple">
            <div class="stat-card-icon"><i class="ph ph-package"></i></div>
            <div class="stat-card-label">Jumlah Item Stok</div>
            <div class="stat-card-value"><?php echo $total_stok; ?> <span class="text-lg font-semibold text-slate-500">item</span></div>
            <div class="stat-card-hint">Jenis barang terdaftar</div>
        </div>
        <div class="stat-card stat-card--orange">
            <div class="stat-card-icon"><i class="ph ph-calendar"></i></div>
            <div class="stat-card-label">Penjualan Bulan Ini</div>
            <div class="stat-card-value"><?php echo formatRupiah($total_month); ?></div>
            <div class="stat-card-hint"><?php echo date('F Y'); ?></div>
        </div>
        <div class="stat-card stat-card--teal">
            <div class="stat-card-icon"><i class="ph ph-chart-line-up"></i></div>
            <div class="stat-card-label">Keuntungan Bulan Ini</div>
            <div class="stat-card-value <?php echo $total_keuntungan_month < 0 ? 'text-red-600' : ''; ?>"><?php echo formatRupiah($total_keuntungan_month ?? 0); ?></div>
            <div class="stat-card-hint">Total Penjualan − HPP</div>
        </div>
        <div class="stat-card stat-card--rose">
            <div class="stat-card-icon"><i class="ph ph-wallet"></i></div>
            <div class="stat-card-label">Total Aset (Beli)</div>
            <div class="stat-card-value text-xl sm:text-2xl"><?php echo formatRupiah($aset_beli); ?></div>
            <div class="stat-card-hint">Nilai modal stok saat ini</div>
        </div>
        <div class="stat-card stat-card--indigo">
            <div class="stat-card-icon"><i class="ph ph-chart-bar"></i></div>
            <div class="stat-card-label">Potensi Omset (Jual)</div>
            <div class="stat-card-value text-xl sm:text-2xl"><?php echo formatRupiah($aset_jual); ?></div>
            <div class="stat-card-hint">Estimasi jika stok habis terjual</div>
        </div>
    </div>

    <h2 class="text-lg font-bold text-slate-800 mb-3 mt-6 flex items-center gap-2">
        <i class="ph ph-chart-pie-slice text-brand"></i> Analisis Penjualan
    </h2>

    <div class="chart-grid">
        <!-- Daily Sales & Profit Chart -->
        <div class="chart-panel full-width">
            <div class="chart-panel-title"><i class="ph ph-chart-line"></i> Penjualan & Keuntungan (30 Hari Terakhir)</div>
            <div class="chart-canvas-wrap" style="max-height: 320px;">
                <canvas id="chartDailyTrend"></canvas>
            </div>
        </div>

        <!-- Monthly Sales & Profit Chart -->
        <div class="chart-panel full-width">
            <div class="chart-panel-title"><i class="ph ph-chart-line"></i> Penjualan & Keuntungan (12 Bulan Terakhir)</div>
            <div class="chart-canvas-wrap" style="max-height: 320px;">
                <canvas id="chartMonthlyTrend"></canvas>
            </div>
        </div>

        <!-- <div class="chart-panel">
            <div class="chart-panel-title"><i class="ph ph-trophy"></i> Top 5 Barang Terlaris</div>
            <div class="chart-canvas-wrap">
                <canvas id="chartTopBarang"></canvas>
            </div>
        </div>

        <div class="chart-panel">
            <div class="chart-panel-title"><i class="ph ph-chart-pie"></i> Komposisi Kategori Barang</div>
            <div class="chart-canvas-wrap">
                <canvas id="chartKategori"></canvas>
            </div>
        </div>

        <div class="chart-panel">
            <div class="chart-panel-title"><i class="ph ph-trend-up"></i> Tren Keuntungan Harian</div>
            <div class="chart-canvas-wrap">
                <canvas id="chartKeuntungan"></canvas>
            </div>
        </div>

        <div class="chart-panel">
            <div class="chart-panel-title"><i class="ph ph-users"></i> Tipe Pelanggan (Estimasi)</div>
            <div class="chart-canvas-wrap">
                <canvas id="chartPelanggan"></canvas>
            </div>
        </div> -->
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
        Chart.defaults.color = '#64748b';
        const brandColor = '#0ea5e9';
        const successColor = '#22c55e';
        const gridColor = '#e2e8f0';

        // Daily Trend Chart (30 hari)
        new Chart(document.getElementById('chartDailyTrend'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dailyChart['labels']); ?>,
                datasets: [{
                        label: 'Penjualan (Rp)',
                        data: <?php echo json_encode($dailyChart['sales_data']); ?>,
                        borderColor: brandColor,
                        backgroundColor: 'rgba(14, 165, 233, 0.08)',
                        borderWidth: 2.5,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: brandColor,
                        pointBorderWidth: 0,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Keuntungan (Rp)',
                        data: <?php echo json_encode($dailyChart['profit_data']); ?>,
                        borderColor: successColor,
                        backgroundColor: 'rgba(34, 197, 94, 0.08)',
                        borderWidth: 2.5,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: successColor,
                        pointBorderWidth: 0,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: {
                            color: gridColor
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Monthly Trend Chart (12 bulan)
        new Chart(document.getElementById('chartMonthlyTrend'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthlyChart['labels']); ?>,
                datasets: [{
                        label: 'Penjualan (Rp)',
                        data: <?php echo json_encode($monthlyChart['sales_data']); ?>,
                        borderColor: brandColor,
                        backgroundColor: 'rgba(14, 165, 233, 0.08)',
                        borderWidth: 2.5,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: brandColor,
                        pointBorderWidth: 0,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Keuntungan (Rp)',
                        data: <?php echo json_encode($monthlyChart['profit_data']); ?>,
                        borderColor: successColor,
                        backgroundColor: 'rgba(34, 197, 94, 0.08)',
                        borderWidth: 2.5,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: successColor,
                        pointBorderWidth: 0,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: {
                            color: gridColor
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // 2. Chart Top 5 Barang
        new Chart(document.getElementById('chartTopBarang'), {
            type: 'bar',
            data: {
                labels: ['Beras 5kg', 'Minyak 2L', 'Gula 1kg', 'Indomie', 'Telur 1kg'],
                datasets: [{
                    label: 'Terjual',
                    data: [45, 82, 60, 150, 40],
                    backgroundColor: [brandColor, successColor, '#f59e0b', '#f43f5e', '#a855f7'],
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // 3. Chart Komposisi Kategori
        new Chart(document.getElementById('chartKategori'), {
            type: 'doughnut',
            data: {
                labels: ['Sembako', 'Minuman', 'Snack', 'Rokok', 'Lainnya'],
                datasets: [{
                    data: [40, 20, 15, 15, 10],
                    backgroundColor: [brandColor, successColor, '#f59e0b', '#f43f5e', '#a855f7'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // 4. Chart Tren Keuntungan Harian
        new Chart(document.getElementById('chartKeuntungan'), {
            type: 'bar',
            data: {
                labels: ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
                datasets: [{
                    label: 'Keuntungan (Rp)',
                    data: [350000, 480000, 420000, 560000, 510000, 720000, 850000],
                    backgroundColor: successColor,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // 5. Chart Tipe Pelanggan
        new Chart(document.getElementById('chartPelanggan'), {
            type: 'pie',
            data: {
                labels: ['Umum', 'Grosir', 'Langganan'],
                datasets: [{
                    data: [65, 20, 15],
                    backgroundColor: [brandColor, '#f59e0b', '#6366f1'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>