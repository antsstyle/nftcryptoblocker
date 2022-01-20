function compare(a, b) {
    if (!Number.isNaN(Date.parse(a))) {
        return Date.parse(a) > Date.parse(b);
    } else if (!Number.isNaN(parseInt(a))) {
        return parseInt(a) > parseInt(b);
    }
    return a.toLowerCase() > b.toLowerCase();
}

function swapRows(row1, row2) {
    let count = row1.length;
    for (let i = 0; i < count; i++) {
        let temp = row1[i].innerHTML;
        row1[i].innerHTML = row2[i].innerHTML;
        row2[i].innerHTML = temp;
    }
}

function checkTableSorted(n, tableID) {
    table = document.getElementById(tableID);
    let rows = table.rows;
    let sortedAsc = true;
    for (let i = 1; i < rows.length - 1; i++) {
        let x = rows[i].getElementsByTagName("TD")[n];
        let y = rows[i + 1].getElementsByTagName("TD")[n];
        if (compare(x.innerHTML, y.innerHTML)) {
            sortedAsc = false;
            break;
        }
    }
    return sortedAsc;
}

function sortTable(n, tableID) {
    table = document.getElementById(tableID);
    if (checkTableSorted(n, tableID)) {
        quickSort(table.rows, 1, table.rows.length - 1, n, true);
    } else {
        quickSort(table.rows, 1, table.rows.length - 1, n, false);
    }

}

function partition(rows, start, end, n, descorder) {
    const pivotValue = rows[end].getElementsByTagName("TD")[n].innerHTML;
    let pivotIndex = start;
    for (let i = start; i < end; i++) {
        let rowValue = rows[i].getElementsByTagName("TD")[n].innerHTML;
        if (descorder) {
            if (compare(rowValue, pivotValue)) {
                swapRows(rows[i].getElementsByTagName("TD"), rows[pivotIndex].getElementsByTagName("TD"));
                pivotIndex++;
            }
        } else {
            if (compare(pivotValue, rowValue)) {
                swapRows(rows[i].getElementsByTagName("TD"), rows[pivotIndex].getElementsByTagName("TD"));
                pivotIndex++;
            }
        }

    }

    swapRows(rows[pivotIndex].getElementsByTagName("TD"), rows[end].getElementsByTagName("TD"));
    return pivotIndex;
}

function quickSort(arr, start, end, n, descorder) {
    if (start >= end) {
        return;
    }
    let index = partition(arr, start, end, n, descorder);

    if (start < index - 1) {
        quickSort(arr, start, index - 1, n, descorder);
    }
    if (index < end) {
        quickSort(arr, index + 1, end, n, descorder);
    }

}