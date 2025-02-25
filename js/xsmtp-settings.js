document.addEventListener("DOMContentLoaded", function(event) {

	const passwordField  = document.getElementById("smtp-password");
	const togglePassword = document.getElementById("password-toggle-icon");

	togglePassword.addEventListener("click", function () {
		if (passwordField.type === "password") {
			passwordField.type = "text";
			togglePassword.classList.remove("dashicons-visibility");
			togglePassword.classList.add("dashicons-hidden");
		} else {
			passwordField.type = "password";
			togglePassword.classList.remove("dashicons-hidden");
			togglePassword.classList.add("dashicons-visibility");
		}
	});

});
