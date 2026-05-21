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
            url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/id.json'
        }
    };

    window.initDefaultDataTable = function(selector, options) {
        return $(selector).DataTable($.extend(true, {}, defaultOptions, options || {}));
    };
})(window, jQuery);
