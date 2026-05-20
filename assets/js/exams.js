(function () {
    var openButtons = document.querySelectorAll("[data-open-modal]");
    var closeButtons = document.querySelectorAll("[data-close-modal]");
    var editButtons = document.querySelectorAll("[data-open-edit]");
    var deleteButtons = document.querySelectorAll("[data-open-delete]");
    var studentButtons = document.querySelectorAll("[data-open-students]");
    var proctorButtons = document.querySelectorAll("[data-open-proctors]");
    var examSearch = document.getElementById("examSearch");
    var examsTable = document.getElementById("examsTable");

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

    function setCheckboxValue(id, value) {
        var element = document.getElementById(id);

        if (element) {
            element.checked = value === "1";
        }
    }

    function getCheckboxes(selector) {
        return document.querySelectorAll(selector);
    }

    function resetCheckboxes(selector) {
        var checkboxes = getCheckboxes(selector);

        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }
    }

    function markAssigned(selector, assignedIds) {
        var checkboxes = getCheckboxes(selector);

        for (var i = 0; i < checkboxes.length; i++) {
            var value = parseInt(checkboxes[i].value, 10);
            checkboxes[i].checked = assignedIds.indexOf(value) !== -1;
        }
    }

    function filterList(input, itemSelector) {
        if (!input) {
            return;
        }

        input.addEventListener("keyup", function () {
            var filter = input.value.toLowerCase();
            var items = document.querySelectorAll(itemSelector);

            for (var i = 0; i < items.length; i++) {
                var text = items[i].textContent.toLowerCase();

                if (text.indexOf(filter) > -1) {
                    items[i].style.display = "";
                } else {
                    items[i].style.display = "none";
                }
            }
        });
    }

    function selectAll(selector) {
        var checkboxes = getCheckboxes(selector);

        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = true;
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
            document.getElementById("edit_exam_id").value = this.getAttribute("data-id");
            document.getElementById("edit_title").value = this.getAttribute("data-title");
            document.getElementById("edit_subject").value = this.getAttribute("data-subject");
            document.getElementById("edit_description").value = this.getAttribute("data-description");
            document.getElementById("edit_duration_minutes").value = this.getAttribute("data-duration");
            document.getElementById("edit_start_datetime").value = this.getAttribute("data-start");
            document.getElementById("edit_end_datetime").value = this.getAttribute("data-end");
            document.getElementById("edit_max_attempts").value = this.getAttribute("data-max-attempts");
            document.getElementById("edit_status").value = this.getAttribute("data-status");

            setCheckboxValue("edit_randomize_questions", this.getAttribute("data-randomize"));
            setCheckboxValue("edit_show_result", this.getAttribute("data-show-result"));
            setCheckboxValue("edit_webcam_required", this.getAttribute("data-webcam"));
            setCheckboxValue("edit_fullscreen_required", this.getAttribute("data-fullscreen"));

            openModal("editExamModal");
        });
    }

    for (var l = 0; l < deleteButtons.length; l++) {
        deleteButtons[l].addEventListener("click", function () {
            var attempts = parseInt(this.getAttribute("data-attempts"), 10);
            var note = document.getElementById("delete_exam_note");

            document.getElementById("delete_exam_id").value = this.getAttribute("data-id");
            document.getElementById("delete_exam_title").textContent = this.getAttribute("data-title");

            if (note) {
                if (attempts > 0) {
                    note.textContent = "This exam already has attempts. It will be archived instead of deleted.";
                } else {
                    note.textContent = "This exam has no attempts yet. It will be permanently deleted.";
                }
            }

            openModal("deleteExamModal");
        });
    }

    for (var m = 0; m < studentButtons.length; m++) {
        studentButtons[m].addEventListener("click", function () {
            var assignedIds = [];

            try {
                assignedIds = JSON.parse(this.getAttribute("data-assigned"));
            } catch (error) {
                assignedIds = [];
            }

            resetCheckboxes("[data-student-checkbox]");
            markAssigned("[data-student-checkbox]", assignedIds);

            document.getElementById("assign_students_exam_id").value = this.getAttribute("data-id");
            document.getElementById("assign_students_title").textContent = "Assign Students - " + this.getAttribute("data-title");

            openModal("assignStudentsModal");
        });
    }

    for (var n = 0; n < proctorButtons.length; n++) {
        proctorButtons[n].addEventListener("click", function () {
            var assignedIds = [];

            try {
                assignedIds = JSON.parse(this.getAttribute("data-assigned"));
            } catch (error) {
                assignedIds = [];
            }

            resetCheckboxes("[data-proctor-checkbox]");
            markAssigned("[data-proctor-checkbox]", assignedIds);

            document.getElementById("assign_proctors_exam_id").value = this.getAttribute("data-id");
            document.getElementById("assign_proctors_title").textContent = "Assign Proctors - " + this.getAttribute("data-title");

            openModal("assignProctorsModal");
        });
    }

    var modalBackdrops = document.querySelectorAll(".modal-backdrop");

    for (var o = 0; o < modalBackdrops.length; o++) {
        modalBackdrops[o].addEventListener("click", function (event) {
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

    if (examSearch && examsTable) {
        examSearch.addEventListener("keyup", function () {
            var filter = examSearch.value.toLowerCase();
            var rows = examsTable.querySelectorAll("tbody tr");

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

    filterList(document.getElementById("studentAssignSearch"), "#studentCheckList .check-item");
    filterList(document.getElementById("proctorAssignSearch"), "#proctorCheckList .check-item");

    var selectAllStudents = document.getElementById("selectAllStudents");
    var clearAllStudents = document.getElementById("clearAllStudents");
    var selectAllProctors = document.getElementById("selectAllProctors");
    var clearAllProctors = document.getElementById("clearAllProctors");

    if (selectAllStudents) {
        selectAllStudents.addEventListener("click", function () {
            selectAll("[data-student-checkbox]");
        });
    }

    if (clearAllStudents) {
        clearAllStudents.addEventListener("click", function () {
            resetCheckboxes("[data-student-checkbox]");
        });
    }

    if (selectAllProctors) {
        selectAllProctors.addEventListener("click", function () {
            selectAll("[data-proctor-checkbox]");
        });
    }

    if (clearAllProctors) {
        clearAllProctors.addEventListener("click", function () {
            resetCheckboxes("[data-proctor-checkbox]");
        });
    }
})();