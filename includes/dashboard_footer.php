    </main>
</div>

<script>
(function () {
    var shell = document.getElementById("dashboardShell");
    var menuToggle = document.getElementById("menuToggle");
    var mobileBackdrop = document.getElementById("mobileBackdrop");

    function openMenu() {
        if (shell) {
            shell.classList.add("sidebar-open");
            document.body.classList.add("menu-open");
        }
    }

    function closeMenu() {
        if (shell) {
            shell.classList.remove("sidebar-open");
            document.body.classList.remove("menu-open");
        }
    }

    if (menuToggle) {
        menuToggle.addEventListener("click", function () {
            if (shell.classList.contains("sidebar-open")) {
                closeMenu();
            } else {
                openMenu();
            }
        });
    }

    if (mobileBackdrop) {
        mobileBackdrop.addEventListener("click", function () {
            closeMenu();
        });
    }

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeMenu();
        }
    });
})();
</script>

</body>
</html>