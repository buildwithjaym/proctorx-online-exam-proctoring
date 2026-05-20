document.addEventListener("DOMContentLoaded", function () {
    var searchInput = document.getElementById("monitorSearch");
    var filterInput = document.getElementById("monitorFilter");
    var list = document.getElementById("studentMonitorList");
    var refreshButton = document.getElementById("refreshMonitorBtn");

    function filterMonitorCards() {
        if (!list) {
            return;
        }

        var searchValue = searchInput ? searchInput.value.toLowerCase() : "";
        var filterValue = filterInput ? filterInput.value : "all";
        var cards = list.querySelectorAll(".student-monitor-card");

        for (var i = 0; i < cards.length; i++) {
            var text = cards[i].textContent.toLowerCase();
            var status = cards[i].getAttribute("data-monitor-status");
            var matchesSearch = text.indexOf(searchValue) > -1;
            var matchesFilter = false;

            if (filterValue === "all") {
                matchesFilter = true;
            }

            if (filterValue !== "all" && status.indexOf(filterValue) > -1) {
                matchesFilter = true;
            }

            if (matchesSearch && matchesFilter) {
                cards[i].style.display = "";
            } else {
                cards[i].style.display = "none";
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener("keyup", filterMonitorCards);
    }

    if (filterInput) {
        filterInput.addEventListener("change", filterMonitorCards);
    }

    if (refreshButton) {
        refreshButton.addEventListener("click", function () {
            window.location.reload();
        });
    }

    setInterval(function () {
        window.location.reload();
    }, 30000);
});