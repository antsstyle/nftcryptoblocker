function dbSearch(id, type) {
    var input = document.getElementById(id);
    if (input === null) {
        return;
    } else {
        var searchstring = input.value;
        if (!searchstring.match(/^@?[A-Za-z0-9_]{1,15}$/)) {
            var resulttext = "Invalid search text. Twitter usernames are 1-15 characters (with or without the @).<br/><br/>";
            document.getElementById("searchresultstextdiv").innerHTML = resulttext;
            return;
        }
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange = function () {
            if (this.readyState === 4 && this.status === 200) {
                if (this.responseText === "") {
                    console.log("No response");
                } else if (this.responseText === "Invalid username") {
                    var resulttext = "Invalid search text. Twitter usernames are 1-15 characters (with or without the @).<br/><br/>";
                    document.getElementById("searchresultstextdiv").innerHTML = resulttext;
                } else {
                    var json = JSON.parse(this.responseText);
                    var resultcount = json.resultcount;
                    var resulttext = resultcount.toString();
                    resulttext = "Search finished. " + resulttext + " results found.<br/><br/>";
                    document.getElementById("searchresultstextdiv").innerHTML = resulttext;
                    document.getElementById("searchresultsdiv").innerHTML = json.tablestring;
                }
            }
        };
        var params = 'searchstring='.concat(searchstring).concat('&type=').concat(type);
        //Send the proper header information along with the request
        xmlhttp.open("POST", "src/ajax/dbsearch.php", true);
        xmlhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xmlhttp.send(params);
    }
}

function resetSearchTable() {
    document.getElementById("searchresultsdiv").innerHTML = document.getElementById("tablecachediv").innerHTML;
    document.getElementById("searchresultstextdiv").innerHTML = "";
}

function storeSearchResults() {
    document.getElementById("tablecachediv").innerHTML = document.getElementById("searchresultsdiv").innerHTML;
}