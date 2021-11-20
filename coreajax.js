function getUserInformation(id) {
    if (id === null) {
        return;
    } else {
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange = function () {
            if (this.readyState === 4 && this.status === 200) {
                if (this.responseText === "") {

                } else {
                    var json = JSON.parse(this.responseText);
                    var blocklistSettings = json.blocklistsettings;
                    var automationSettings = json.automationsettings;

                    if (typeof blocklistSettings !== 'undefined' && blocklistSettings !== null) {
                        blocklistSettings.forEach(checkBlocklistSetting);
                    }

                    if (automationSettings.matchingphraseoperation === "Block") {
                        document.getElementById("block phrases").checked = true;
                    } else if (automationSettings.matchingphraseoperation === "Mute") {
                        document.getElementById("mute phrases").checked = true;
                    } else {
                        document.getElementById("noaction phrases").checked = true;
                    }


                    if (automationSettings.urlsoperation === "Block") {
                        document.getElementById("block urls").checked = true;
                    } else if (automationSettings.urlsoperation === "Mute") {
                        document.getElementById("mute urls").checked = true;
                    } else {
                        document.getElementById("noaction urls").checked = true;
                    }


                    if (automationSettings.nftprofilepictureoperation === "Block") {
                        document.getElementById("block nftprofilepictures").checked = true;
                    } else if (automationSettings.nftprofilepictureoperation === "Mute") {
                        document.getElementById("mute nftprofilepictures").checked = true;
                    } else {
                        document.getElementById("noaction nftprofilepictures").checked = true;
                    }

                    if (automationSettings.cryptousernamesoperation === "Block") {
                        document.getElementById("block cryptousernames").checked = true;
                    } else if (automationSettings.cryptousernamesoperation === "Mute") {
                        document.getElementById("mute cryptousernames").checked = true;
                    } else {
                        document.getElementById("noaction cryptousernames").checked = true;
                    }

                    if (automationSettings.nftfollowersoperation === "Block") {
                        document.getElementById("block nftfollowers").checked = true;
                    } else if (automationSettings.nftfollowersoperation === "Mute") {
                        document.getElementById("mute nftfollowers").checked = true;
                    } else {
                        document.getElementById("noaction nftfollowers").checked = true;
                    }

                    if (automationSettings.centraldatabaseoperation === "Block") {
                        document.getElementById("block centraldatabase").checked = true;
                    } else if (automationSettings.centraldatabaseoperation === "Mute") {
                        document.getElementById("mute centraldatabase").checked = true;
                    } else {
                        document.getElementById("noaction centraldatabase").checked = true;
                    }

                    if (automationSettings.whitelistfollowings === "N") {
                        document.getElementById("disable followerwhitelist").checked = true;
                    } else {
                        document.getElementById("enable followerwhitelist").checked = true;
                    }

                }
            }
        };
        var params = 'userid='.concat(id);
        //Send the proper header information along with the request
        xmlhttp.open("POST", "ajaxgetuserinfo.php", true);
        xmlhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xmlhttp.send(params);
    }
}

function checkBlocklistSetting(value, index, array) {
    var name = value.name;
    var lastoperation = value.lastoperation;
    if (lastoperation === "Block") {
        let fieldName = "block ".concat(name);
        document.getElementById(fieldName).checked = true;
    } else if (lastoperation === "Mute") {
        let fieldName = "mute ".concat(name);
        document.getElementById(fieldName).checked = true;
    } else if (lastoperation === "Unblock") {
        let fieldName = "unblock ".concat(name);
        document.getElementById(fieldName).checked = true;
    } else if (lastoperation === "Unmute") {
        let fieldName = "unmute ".concat(name);
        document.getElementById(fieldName).checked = true;
    } else if (lastoperation === "Do nothing") {
        let fieldName = "noaction ".concat(name);
        document.getElementById(fieldName).checked = true;
    }
}