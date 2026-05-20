document.addEventListener("DOMContentLoaded", function () {
    var shell = document.querySelector(".take-exam-shell");

    if (!shell) {
        return;
    }

    var logUrl = shell.getAttribute("data-log-url");
    var csrfToken = shell.getAttribute("data-csrf");
    var attemptId = shell.getAttribute("data-attempt-id");
    var fullscreenRequired = shell.getAttribute("data-fullscreen-required") === "1";
    var lastActivity = Date.now();
    var inactivityLogged = false;
    var eventCooldowns = {};

    function nowSeconds() {
        return Math.floor(Date.now() / 1000);
    }

    function canLog(eventType, cooldownSeconds) {
        var current = nowSeconds();

        if (!eventCooldowns[eventType]) {
            eventCooldowns[eventType] = 0;
        }

        if (current - eventCooldowns[eventType] < cooldownSeconds) {
            return false;
        }

        eventCooldowns[eventType] = current;
        return true;
    }

    function logEvent(eventType, metadata, cooldownSeconds) {
        if (!logUrl || !attemptId || !csrfToken) {
            return;
        }

        if (!canLog(eventType, cooldownSeconds)) {
            return;
        }

        var formData = new FormData();

        formData.append("csrf_token", csrfToken);
        formData.append("attempt_id", attemptId);
        formData.append("event_type", eventType);
        formData.append("metadata", JSON.stringify(metadata));

        fetch(logUrl, {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        }).catch(function () {});
    }

    function resetActivity() {
        lastActivity = Date.now();
        inactivityLogged = false;
    }

    document.addEventListener("visibilitychange", function () {
        if (document.hidden) {
            logEvent("tab_switch", {
                page: window.location.href,
                hidden_at: new Date().toISOString()
            }, 8);
        }
    });

    window.addEventListener("blur", function () {
        logEvent("window_blur", {
            page: window.location.href,
            blurred_at: new Date().toISOString()
        }, 10);
    });

    document.addEventListener("copy", function () {
        logEvent("copy_attempt", {
            page: window.location.href,
            copied_at: new Date().toISOString()
        }, 5);
    });

    document.addEventListener("paste", function () {
        logEvent("paste_attempt", {
            page: window.location.href,
            pasted_at: new Date().toISOString()
        }, 5);
    });

    document.addEventListener("contextmenu", function (event) {
        event.preventDefault();

        logEvent("right_click", {
            page: window.location.href,
            right_clicked_at: new Date().toISOString()
        }, 5);
    });

    document.addEventListener("fullscreenchange", function () {
        if (fullscreenRequired && !document.fullscreenElement) {
            logEvent("fullscreen_exit", {
                page: window.location.href,
                exited_at: new Date().toISOString()
            }, 8);
        }
    });

    document.addEventListener("mousemove", resetActivity);
    document.addEventListener("keydown", resetActivity);
    document.addEventListener("click", resetActivity);
    document.addEventListener("scroll", resetActivity);
    document.addEventListener("touchstart", resetActivity);

    setInterval(function () {
        var inactiveSeconds = Math.floor((Date.now() - lastActivity) / 1000);

        if (inactiveSeconds >= 60 && !inactivityLogged) {
            inactivityLogged = true;

            logEvent("inactivity", {
                inactive_seconds: inactiveSeconds,
                page: window.location.href,
                logged_at: new Date().toISOString()
            }, 60);
        }
    }, 10000);

    var fullscreenButton = document.getElementById("enterFullscreenBtn");

    if (fullscreenButton) {
        fullscreenButton.addEventListener("click", function () {
            var root = document.documentElement;

            if (root.requestFullscreen) {
                root.requestFullscreen();
            }
        });
    }
});