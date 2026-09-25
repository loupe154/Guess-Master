fetch("../../backend/api/session.php", {
    credentials: "same-origin"
})
    .then((response) => response.json())
    .then((data) => {
        if (!data.authenticated) {
            window.location.href = "../index.html?tab=login&error=Bitte zuerst einloggen";
            return;
        }

        const welcomeTitle = document.getElementById("welcome-title");
        const userEmail = document.getElementById("user-email");

        welcomeTitle.textContent = `Willkommen, ${data.user.name || "Benutzer"}`;
        userEmail.textContent = data.user.email || "";
    })
    .catch(() => {
        window.location.href = "../index.html?error=Session konnte nicht geladen werden";
    });
