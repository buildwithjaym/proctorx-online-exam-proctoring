(function () {
    var openButtons = document.querySelectorAll("[data-open-modal]");
    var closeButtons = document.querySelectorAll("[data-close-modal]");
    var editButtons = document.querySelectorAll("[data-open-edit]");
    var deleteButtons = document.querySelectorAll("[data-open-delete]");
    var searchInput = document.getElementById("proctorSearch");
    var table = document.getElementById("proctorsTable");

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
            document.getElementById("edit_proctor_id").value = this.getAttribute("data-id");
            document.getElementById("edit_full_name").value = this.getAttribute("data-name");
            document.getElementById("edit_email").value = this.getAttribute("data-email");
            document.getElementById("edit_username").value = this.getAttribute("data-username");
            document.getElementById("edit_status").value = this.getAttribute("data-status");
            openModal("editProctorModal");
        });
    }

    for (var l = 0; l < deleteButtons.length; l++) {
        deleteButtons[l].addEventListener("click", function () {
            document.getElementById("delete_proctor_id").value = this.getAttribute("data-id");
            document.getElementById("delete_proctor_name").textContent = this.getAttribute("data-name");
            openModal("deleteProctorModal");
        });
    }

    var modalBackdrops = document.querySelectorAll(".modal-backdrop");

    for (var m = 0; m < modalBackdrops.length; m++) {
        modalBackdrops[m].addEventListener("click", function (event) {
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

    if (searchInput && table) {
        searchInput.addEventListener("keyup", function () {
            var filter = searchInput.value.toLowerCase();
            var rows = table.querySelectorAll("tbody tr");

            for (var n = 0; n < rows.length; n++) {
                var rowText = rows[n].textContent.toLowerCase();

                if (rowText.indexOf(filter) > -1) {
                    rows[n].style.display = "";
                } else {
                    rows[n].style.display = "none";
                }
            }
        });
    }
})();