document.addEventListener("DOMContentLoaded", function () {
    var openButtons = document.querySelectorAll("[data-open-modal]");
    var closeButtons = document.querySelectorAll("[data-close-modal]");
    var editButtons = document.querySelectorAll("[data-open-edit]");
    var deleteButtons = document.querySelectorAll("[data-open-delete]");
    var questionSearch = document.getElementById("questionSearch");
    var questionsTable = document.getElementById("questionsTable");
    var typeSelectors = document.querySelectorAll("[data-question-type]");

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

    function hideBox(id) {
        var box = document.getElementById(id);

        if (box) {
            box.style.display = "none";
            box.classList.add("hidden");
        }
    }

    function showBox(id) {
        var box = document.getElementById(id);

        if (box) {
            box.style.display = "grid";
            box.classList.remove("hidden");
        }
    }

    function hideQuestionBoxes(prefix) {
        hideBox(prefix + "_multiple_choice_box");
        hideBox(prefix + "_true_false_box");
        hideBox(prefix + "_identification_box");
        hideBox(prefix + "_essay_box");
    }

    function syncQuestionType(prefix) {
        var selector = document.getElementById(prefix + "_question_type");

        if (!selector) {
            return;
        }

        hideQuestionBoxes(prefix);

        if (selector.value === "multiple_choice") {
            showBox(prefix + "_multiple_choice_box");
        }

        if (selector.value === "true_false") {
            showBox(prefix + "_true_false_box");
        }

        if (selector.value === "identification") {
            showBox(prefix + "_identification_box");
        }

        if (selector.value === "essay") {
            showBox(prefix + "_essay_box");
        }
    }

    function clearEditChoices() {
        for (var i = 1; i <= 4; i++) {
            var choiceText = document.getElementById("edit_choice_text_" + i);
            var correctChoice = document.getElementById("edit_correct_choice_" + i);

            if (choiceText) {
                choiceText.value = "";
            }

            if (correctChoice) {
                correctChoice.checked = i === 1;
            }
        }

        var trueRadio = document.getElementById("edit_correct_tf_true");
        var falseRadio = document.getElementById("edit_correct_tf_false");
        var identificationInput = document.getElementById("edit_correct_identification");

        if (trueRadio) {
            trueRadio.checked = true;
        }

        if (falseRadio) {
            falseRadio.checked = false;
        }

        if (identificationInput) {
            identificationInput.value = "";
        }
    }

    function fillEditChoices(questionType, choices) {
        clearEditChoices();

        if (questionType === "multiple_choice") {
            for (var i = 0; i < choices.length; i++) {
                var position = parseInt(choices[i].position, 10);
                var choiceInput = document.getElementById("edit_choice_text_" + position);
                var correctInput = document.getElementById("edit_correct_choice_" + position);
                var text = choices[i].raw_text ? choices[i].raw_text : choices[i].text;

                if (choiceInput) {
                    choiceInput.value = text;
                }

                if (correctInput && parseInt(choices[i].is_correct, 10) === 1) {
                    correctInput.checked = true;
                }
            }
        }

        if (questionType === "true_false") {
            for (var j = 0; j < choices.length; j++) {
                var tfText = String(choices[j].text).toLowerCase();
                var tfCorrect = parseInt(choices[j].is_correct, 10) === 1;

                if (tfText === "true" && tfCorrect) {
                    document.getElementById("edit_correct_tf_true").checked = true;
                }

                if (tfText === "false" && tfCorrect) {
                    document.getElementById("edit_correct_tf_false").checked = true;
                }
            }
        }

        if (questionType === "identification") {
            for (var k = 0; k < choices.length; k++) {
                if (parseInt(choices[k].is_correct, 10) === 1) {
                    var identificationInput = document.getElementById("edit_correct_identification");

                    if (identificationInput) {
                        identificationInput.value = choices[k].text;
                    }
                }
            }
        }
    }

    for (var a = 0; a < openButtons.length; a++) {
        openButtons[a].addEventListener("click", function () {
            openModal(this.getAttribute("data-open-modal"));
            syncQuestionType("add");
        });
    }

    for (var b = 0; b < closeButtons.length; b++) {
        closeButtons[b].addEventListener("click", function () {
            closeModals();
        });
    }

    for (var c = 0; c < typeSelectors.length; c++) {
        typeSelectors[c].addEventListener("change", function () {
            syncQuestionType(this.getAttribute("data-question-type"));
        });
    }

    for (var d = 0; d < editButtons.length; d++) {
        editButtons[d].addEventListener("click", function () {
            var choices = [];

            try {
                choices = JSON.parse(this.getAttribute("data-choices"));
            } catch (error) {
                choices = [];
            }

            document.getElementById("edit_question_id").value = this.getAttribute("data-id");
            document.getElementById("edit_question_text").value = this.getAttribute("data-text");
            document.getElementById("edit_question_type").value = this.getAttribute("data-type");
            document.getElementById("edit_points").value = this.getAttribute("data-points");

            syncQuestionType("edit");
            fillEditChoices(this.getAttribute("data-type"), choices);

            openModal("editQuestionModal");
        });
    }

    for (var e = 0; e < deleteButtons.length; e++) {
        deleteButtons[e].addEventListener("click", function () {
            document.getElementById("delete_question_id").value = this.getAttribute("data-id");
            document.getElementById("delete_question_text").textContent = this.getAttribute("data-text");
            openModal("deleteQuestionModal");
        });
    }

    var modalBackdrops = document.querySelectorAll(".modal-backdrop");

    for (var f = 0; f < modalBackdrops.length; f++) {
        modalBackdrops[f].addEventListener("click", function (event) {
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

    if (questionSearch && questionsTable) {
        questionSearch.addEventListener("keyup", function () {
            var filter = questionSearch.value.toLowerCase();
            var rows = questionsTable.querySelectorAll("tbody tr");

            for (var g = 0; g < rows.length; g++) {
                var rowText = rows[g].textContent.toLowerCase();

                if (rowText.indexOf(filter) > -1) {
                    rows[g].style.display = "";
                } else {
                    rows[g].style.display = "none";
                }
            }
        });
    }

    syncQuestionType("add");
    syncQuestionType("edit");
});