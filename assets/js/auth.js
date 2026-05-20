const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("password");
const loginForm = document.querySelector(".login-card");
const loginBtn = document.getElementById("loginBtn");

if (togglePassword && passwordInput) {
    togglePassword.addEventListener("click", function () {
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            togglePassword.textContent = "Hide";
        } else {
            passwordInput.type = "password";
            togglePassword.textContent = "Show";
        }
    });
}

if (loginForm && loginBtn) {
    loginForm.addEventListener("submit", function () {
        loginBtn.disabled = true;
        loginBtn.textContent = "Signing in...";
    });
}