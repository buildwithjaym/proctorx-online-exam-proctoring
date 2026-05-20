(function () {
    var openButtons = document.querySelectorAll("[data-open-modal]");
    var closeButtons = document.querySelectorAll("[data-close-modal]");
    var editButtons = document.querySelectorAll("[data-open-edit]");
    var deleteButtons = document.querySelectorAll("[data-open-delete]");
    var assignButtons = document.querySelectorAll("[data-open-assign]");
    var classSearch = document.getElementById("classSearch");
    var classesTable = document.getElementById("classesTable");
    var studentAssignSearch = document.getElementById("studentAssignSearch");
    var selectAllStudents = document.getElementById("selectAllStudents");
    var clearAllStudents = document.getElementById("clearAllStudents");

    function openModal(id) {
        var modal = document.getElementById(id);

        if (modal) {
            modal.classList.add("show");
            document.body.classList.add("modal-open");
        }
    }

    function closeModals() {
        var modals = document.querySelectorAll(".modal-backdrop");

        for (var i = 0; i < modals.length; i++) {
            modals[i].classList.remove("show");
        }

        document.body.classList.remove("modal-open");
    }

    function getStudentCheckboxes() {
        return document.querySelectorAll("[data-student-checkbox]");
    }

    function resetStudentCheckboxes() {
        var checkboxes = getStudentCheckboxes();

        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }
    }

    function markAssignedStudents(assignedIds) {
        var checkboxes = getStudentCheckboxes();

        for (var i = 0; i < checkboxes.length; i++) {
            var value = parseInt(checkboxes[i].value, 10);
            checkboxes[i].checked = assignedIds.indexOf(value) !== -1;
        }
    }

    for (var i = 0; i < openButtons.length; i++) {
        openButtons[i].addEventListener("click", function () {
            openModal(this.getAttribute("data-open-modal"));
        });
    }

    for (var j = 0; j < closeButtons.length; j++) {
        closeButtons[j].addEventListener("click", function () {
            closeModals();
        });
    }

    for (var k = 0; k < editButtons.length; k++) {
        editButtons[k].addEventListener("click", function () {
            document.getElementById("edit_class_id").value = this.getAttribute("data-id");
            document.getElementById("edit_class_name").value = this.getAttribute("data-name");
            document.getElementById("edit_section").value = this.getAttribute("data-section");
            document.getElementById("edit_school_year").value = this.getAttribute("data-school-year");
            document.getElementById("edit_status").value = this.getAttribute("data-status");
            openModal("editClassModal");
        });
    }

    for (var l = 0; l < deleteButtons.length; l++) {
        deleteButtons[l].addEventListener("click", function () {
            document.getElementById("delete_class_id").value = this.getAttribute("data-id");
            document.getElementById("delete_class_title").textContent = this.getAttribute("data-title");
            openModal("deleteClassModal");
        });
    }

    for (var m = 0; m < assignButtons.length; m++) {
        assignButtons[m].addEventListener("click", function () {
            var assignedIds = [];

            try {
                assignedIds = JSON.parse(this.getAttribute("data-assigned"));
            } catch (error) {
                assignedIds = [];
            }

            resetStudentCheckboxes();
            markAssignedStudents(assignedIds);

            document.getElementById("assign_class_id").value = this.getAttribute("data-id");
            document.getElementById("assign_class_title").textContent = "Manage Students - " + this.getAttribute("data-title");

            if (studentAssignSearch) {
                studentAssignSearch.value = "";
                var items = document.querySelectorAll(".student-check-item");

                for (var a = 0; a < items.length; a++) {
                    items[a].style.display = "";
                }
            }

            openModal("assignStudentsModal");
        });
    }

    var modalBackdrops = document.querySelectorAll(".modal-backdrop");

    for (var n = 0; n < modalBackdrops.length; n++) {
        modalBackdrops[n].addEventListener("click", function (event) {
            if (event.target === this) {
                closeModals();
            }
        });
    }

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeModals();
        }
    });

    if (classSearch && classesTable) {
        classSearch.addEventListener("keyup", function () {
            var filter = classSearch.value.toLowerCase();
            var rows = classesTable.querySelectorAll("tbody tr");

            for (var p = 0; p < rows.length; p++) {
                var rowText = rows[p].textContent.toLowerCase();

                if (rowText.indexOf(filter) > -1) {
                    rows[p].style.display = "";
                } else {
                    rows[p].style.display = "none";
                }
            }
        });
    }

    if (studentAssignSearch) {
        studentAssignSearch.addEventListener("keyup", function () {
            var filter = studentAssignSearch.value.toLowerCase();
            var items = document.querySelectorAll(".student-check-item");

            for (var q = 0; q < items.length; q++) {
                var itemText = items[q].textContent.toLowerCase();

                if (itemText.indexOf(filter) > -1) {
                    items[q].style.display = "";
                } else {
                    items[q].style.display = "none";
                }
            }
        });
    }

    if (selectAllStudents) {
        selectAllStudents.addEventListener("click", function () {
            var checkboxes = getStudentCheckboxes();

            for (var r = 0; r < checkboxes.length; r++) {
                checkboxes[r].checked = true;
            }
        });
    }

    if (clearAllStudents) {
        clearAllStudents.addEventListener("click", function () {
            resetStudentCheckboxes();
        });
    }
})();