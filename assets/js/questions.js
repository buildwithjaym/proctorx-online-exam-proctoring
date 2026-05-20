(function () {
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

    function syncQuestionType(prefix) {
        var selector = document.getElementById(prefix + "_question_type");
        var multipleBox = document.getElementById(prefix + "_multiple_choice_box");
        var trueFalseBox = document.getElementById(prefix + "_true_false_box");

        if (!selector || !multipleBox || !trueFalseBox) {
            return;
        }

        if (selector.value === "true_false") {
            multipleBox.classList.add("hidden");
            trueFalseBox.classList.remove("hidden");
        } else {
            multipleBox.classList.remove("hidden");
            trueFalseBox.classList.add("hidden");
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

        if (trueRadio) {
            trueRadio.checked = true;
        }

        if (falseRadio) {
            falseRadio.checked = false;
        }
    }

    function fillEditChoices(questionType, choices) {
        clearEditChoices();

        if (questionType === "multiple_choice") {
            for (var i = 0; i < choices.length; i++) {
                var position = parseInt(choices[i].position, 10);
                var choiceInput = document.getElementById("edit_choice_text_" + position);
                var correctInput = document.getElementById("edit_correct_choice_" + position);

                if (choiceInput) {
                    choiceInput.value = choices[i].text;
                }

                if (correctInput && parseInt(choices[i].is_correct, 10) === 1) {
                    correctInput.checked = true;
                }
            }
        }

        if (questionType === "true_false") {
            for (var j = 0; j < choices.length; j++) {
                var text = String(choices[j].text).toLowerCase();
                var isCorrect = parseInt(choices[j].is_correct, 10) === 1;

                if (text === "true" && isCorrect) {
                    document.getElementById("edit_correct_tf_true").checked = true;
                }

                if (text === "false" && isCorrect) {
                    document.getElementById("edit_correct_tf_false").checked = true;
                }
            }
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

    for (var k = 0; k < typeSelectors.length; k++) {
        typeSelectors[k].addEventListener("change", function () {
            syncQuestionType(this.getAttribute("data-question-type"));
        });
    }

    for (var l = 0; l < editButtons.length; l++) {
        editButtons[l].addEventListener("click", function () {
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

    for (var m = 0; m < deleteButtons.length; m++) {
        deleteButtons[m].addEventListener("click", function () {
            document.getElementById("delete_question_id").value = this.getAttribute("data-id");
            document.getElementById("delete_question_text").textContent = this.getAttribute("data-text");
            openModal("deleteQuestionModal");
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

    if (questionSearch && questionsTable) {
        questionSearch.addEventListener("keyup", function () {
            var filter = questionSearch.value.toLowerCase();
            var rows = questionsTable.querySelectorAll("tbody tr");

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

    syncQuestionType("add");
    syncQuestionType("edit");
})();