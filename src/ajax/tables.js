function compare(a, b) {
    if (!Number.isNaN(Date.parse(a))) {
        return Date.parse(a) > Date.parse(b);
    } else if (!Number.isNaN(parseInt(a))) {
        return parseInt(a) > parseInt(b);
    } 
    return a.toLowerCase() > b.toLowerCase();
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
                rows[i].getElementsByTagName("TD")[n].innerHTML = rows[pivotIndex].getElementsByTagName("TD")[n].innerHTML;
                rows[pivotIndex].getElementsByTagName("TD")[n].innerHTML = rowValue;
                pivotIndex++;
            }
        } else {
            if (compare(pivotValue, rowValue)) {
                rows[i].getElementsByTagName("TD")[n].innerHTML = rows[pivotIndex].getElementsByTagName("TD")[n].innerHTML;
                rows[pivotIndex].getElementsByTagName("TD")[n].innerHTML = rowValue;
                pivotIndex++;
            }
        }

    }
    
    let pivotIndexValue = rows[pivotIndex].getElementsByTagName("TD")[n].innerHTML;

    rows[pivotIndex].getElementsByTagName("TD")[n].innerHTML = rows[end].getElementsByTagName("TD")[n].innerHTML;
    rows[end].getElementsByTagName("TD")[n].innerHTML = pivotIndexValue;
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