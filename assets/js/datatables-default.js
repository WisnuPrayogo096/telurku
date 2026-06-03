(function(window, $) {
    'use strict';

    const defaultOptions = {
        pageLength: 25,
        order: [
            [0, 'asc']
        ],
        info: false,
        lengthChange: false,
        responsive: true,
        autoWidth: false,
        processing: true,
        stateSave: true,
        language: {
            emptyTable: 'Tidak ada data',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Menampilkan 0 sampai 0 dari 0 data',
            infoFiltered: '(difilter dari _MAX_ total data)',
            loadingRecords: 'Memuat...',
            processing: 'Memproses...',
            search: 'Cari:',
            zeroRecords: 'Data tidak ditemukan',
            paginate: {
                first: 'Pertama',
                last: 'Terakhir',
                next: 'Berikutnya',
                previous: 'Sebelumnya'
            },
            aria: {
                sortAscending: ': aktifkan untuk urut naik',
                sortDescending: ': aktifkan untuk urut turun'
            }
        }
    };

    window.initDefaultDataTable = function(selector, options) {
        const $table = $(selector);
        if (!$table.length) {
            return null;
        }
        // Baris placeholder colspan tidak punya jumlah kolom yang sama dengan thead
        if ($table.find('tbody tr td[colspan]').length) {
            return null;
        }
        return $table.DataTable($.extend(true, {}, defaultOptions, options || {}));
    };
})(window, jQuery);
