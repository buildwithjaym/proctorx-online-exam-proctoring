document.addEventListener("DOMContentLoaded", function () {
    var searchInput = document.getElementById("assignedExamSearch");
    var filterInput = document.getElementById("assignedExamFilter");
    var list = document.getElementById("assignedExamList");

    function filterAssignedExams() {
        if (!list) {
            return;
        }

        var searchValue = searchInput ? searchInput.value.toLowerCase() : "";
        var filterValue = filterInput ? filterInput.value : "all";
        var cards = list.querySelectorAll(".assigned-exam-card");

        for (var i = 0; i < cards.length; i++) {
            var text = cards[i].textContent.toLowerCase();
            var status = cards[i].getAttribute("data-status");
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
        searchInput.addEventListener("keyup", filterAssignedExams);
    }

    if (filterInput) {
        filterInput.addEventListener("change", filterAssignedExams);
    }
});