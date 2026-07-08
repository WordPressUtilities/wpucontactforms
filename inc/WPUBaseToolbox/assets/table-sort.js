document.addEventListener("DOMContentLoaded", function() {
    'use strict';
    Array.prototype.forEach.call(document.querySelectorAll('table.wpubasetoolbox-table-sort'), wpubasetoolbox_table_sort_init);
});

/* ----------------------------------------------------------
  Table sort
---------------------------------------------------------- */

function wpubasetoolbox_table_sort_init($table) {
    'use strict';
    var $ths = $table.querySelectorAll('thead th');
    if (!$ths.length) {
        return;
    }

    Array.prototype.forEach.call($ths, function($th, colIndex) {
        $th.setAttribute('data-sort-dir', '');
        $th.style.cursor = 'pointer';
        $th.addEventListener('click', function() {
            var _currentDir = $th.getAttribute('data-sort-dir');
            var _newDir = _currentDir === 'asc' ? 'desc' : 'asc';

            /* Reset all TH */
            Array.prototype.forEach.call($ths, function($t) {
                $t.setAttribute('data-sort-dir', '');
            });

            $th.setAttribute('data-sort-dir', _newDir);
            wpubasetoolbox_table_sort_rows($table, colIndex, _newDir);
        });
    });
}

function wpubasetoolbox_table_sort_rows($table, colIndex, dir) {
    'use strict';
    var $tbody = $table.querySelector('tbody');
    if (!$tbody) {
        return;
    }

    var $rows = Array.prototype.slice.call($tbody.querySelectorAll('tr'));

    $rows.sort(function(rowA, rowB) {
        var cellA = rowA.querySelectorAll('td')[colIndex];
        var cellB = rowB.querySelectorAll('td')[colIndex];
        if (!cellA || !cellB) {
            return 0;
        }

        var valA = (cellA.getAttribute('data-sort-value') || cellA.textContent).trim();
        var valB = (cellB.getAttribute('data-sort-value') || cellB.textContent).trim();

        var numA = parseFloat(valA.replace(/[^\d.,-]/g, '').replace(',', '.'));
        var numB = parseFloat(valB.replace(/[^\d.,-]/g, '').replace(',', '.'));
        var isNumeric = !isNaN(numA) && !isNaN(numB);

        var cmp = isNumeric ? (numA - numB) : valA.localeCompare(valB);
        return dir === 'asc' ? cmp : -cmp;
    });

    Array.prototype.forEach.call($rows, function($row) {
        $tbody.appendChild($row);
    });
}
