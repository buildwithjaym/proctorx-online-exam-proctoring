document.addEventListener("DOMContentLoaded", function () {
    var searchInput = document.getElementById("attemptLogSearch");
    var filterInput = document.getElementById("attemptLogFilter");
    var list = document.getElementById("attemptLogList");
    var refreshButton = document.getElementById("refreshAttemptBtn");

    function filterLogs() {
        if (!list) {
            return;
        }

        var searchValue = searchInput ? searchInput.value.toLowerCase() : "";
        var filterValue = filterInput ? filterInput.value : "all";
        var cards = list.querySelectorAll(".attempt-log-card");

        for (var i = 0; i < cards.length; i++) {
            var text = cards[i].textContent.toLowerCase();
            var severity = cards[i].getAttribute("data-severity");
            var matchesSearch = text.indexOf(searchValue) > -1;
            var matchesFilter = filterValue === "all" || severity === filterValue;

            if (matchesSearch && matchesFilter) {
                cards[i].style.display = "";
            } else {
                cards[i].style.display = "none";
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener("keyup", filterLogs);
    }

    if (filterInput) {
        filterInput.addEventListener("change", filterLogs);
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