document.addEventListener("DOMContentLoaded", function () {
    var searchInput = document.getElementById("examSearch");
    var statusFilter = document.getElementById("examFilter");
    var examList = document.getElementById("studentExamList");

    function filterExams() {
        if (!examList) {
            return;
        }

        var searchValue = searchInput ? searchInput.value.toLowerCase() : "";
        var filterValue = statusFilter ? statusFilter.value : "all";
        var cards = examList.querySelectorAll(".student-exam-card");

        for (var i = 0; i < cards.length; i++) {
            var text = cards[i].textContent.toLowerCase();
            var status = cards[i].getAttribute("data-status");
            var matchesSearch = text.indexOf(searchValue) > -1;
            var matchesFilter = false;

            if (filterValue === "all") {
                matchesFilter = true;
            }

            if (filterValue === "available" && (status === "available" || status === "progress")) {
                matchesFilter = true;
            }

            if (filterValue === "upcoming" && status === "upcoming") {
                matchesFilter = true;
            }

            if (filterValue === "completed" && status === "completed") {
                matchesFilter = true;
            }

            if (filterValue === "closed" && (status === "closed" || status === "draft" || status === "neutral")) {
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
        searchInput.addEventListener("keyup", filterExams);
    }

    if (statusFilter) {
        statusFilter.addEventListener("change", filterExams);
    }
});