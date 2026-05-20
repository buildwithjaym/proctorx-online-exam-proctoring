document.addEventListener("DOMContentLoaded", function () {
    var shell = document.querySelector(".take-exam-shell");
    var timer = document.getElementById("examTimer");
    var form = document.getElementById("examForm");
    var autoSubmit = document.getElementById("autoSubmit");
    var submitButton = document.getElementById("submitExamButton");
    var saveTimer = null;

    if (!shell || !timer || !form) {
        return;
    }

    var saveUrl = shell.getAttribute("data-save-url");
    var csrfToken = shell.getAttribute("data-csrf");
    var attemptId = shell.getAttribute("data-attempt-id");
    var remaining = parseInt(timer.getAttribute("data-remaining"), 10);

    function formatTime(seconds) {
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var secs = seconds % 60;

        if (hours > 0) {
            return String(hours).padStart(2, "0") + ":" + String(minutes).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
        }

        return String(minutes).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
    }

    function updateTimer() {
        if (remaining <= 0) {
            timer.textContent = "00:00";

            if (autoSubmit) {
                autoSubmit.value = "1";
            }

            form.submit();
            return;
        }

        timer.textContent = formatTime(remaining);

        if (remaining <= 300) {
            timer.parentElement.classList.add("timer-danger");
        } else if (remaining <= 600) {
            timer.parentElement.classList.add("timer-warning");
        }

        remaining--;
    }

    function wordCount(value) {
        value = value.trim();

        if (value === "") {
            return 0;
        }

        return value.split(/\s+/).length;
    }

    function updateWordCounter(element) {
        var counterId = element.getAttribute("data-word-counter");

        if (!counterId) {
            return;
        }

        var counter = document.getElementById(counterId);

        if (counter) {
            counter.textContent = wordCount(element.value) + " words";
        }
    }

    function setSaveStatus(questionId, text, statusClass) {
        var status = document.getElementById("save-status-" + questionId);

        if (!status) {
            return;
        }

        status.textContent = text;
        status.classList.remove("saved");
        status.classList.remove("error");

        if (statusClass !== "") {
            status.classList.add(statusClass);
        }
    }

    function saveAnswer(element) {
        var questionId = element.getAttribute("data-question-id");
        var questionType = element.getAttribute("data-question-type");
        var formData = new FormData();

        formData.append("csrf_token", csrfToken);
        formData.append("attempt_id", attemptId);
        formData.append("question_id", questionId);
        formData.append("question_type", questionType);

        if (questionType === "multiple_choice" || questionType === "true_false") {
            if (!element.checked) {
                return;
            }

            formData.append("choice_id", element.value);
            formData.append("answer_text", "");
        } else {
            formData.append("choice_id", "");
            formData.append("answer_text", element.value);
        }

        setSaveStatus(questionId, "Saving answer...", "");

        fetch(saveUrl, {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.success) {
                setSaveStatus(questionId, "Answer saved", "saved");
            } else {
                setSaveStatus(questionId, data.message, "error");
            }
        })
        .catch(function () {
            setSaveStatus(questionId, "Unable to save answer", "error");
        });
    }

    function debounceSave(element) {
        clearTimeout(saveTimer);

        saveTimer = setTimeout(function () {
            saveAnswer(element);
        }, 500);
    }

    var inputs = document.querySelectorAll("[data-answer-input]");

    for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].tagName.toLowerCase() === "textarea") {
            updateWordCounter(inputs[i]);
        }

        inputs[i].addEventListener("change", function () {
            updateWordCounter(this);
            saveAnswer(this);
        });

        inputs[i].addEventListener("keyup", function () {
            updateWordCounter(this);
            debounceSave(this);
        });
    }

    if (submitButton) {
        submitButton.addEventListener("click", function (event) {
            var confirmSubmit = confirm("Are you sure you want to submit your exam? You cannot edit your answers after submitting.");

            if (!confirmSubmit) {
                event.preventDefault();
            }
        });
    }

    updateTimer();
    setInterval(updateTimer, 1000);
});