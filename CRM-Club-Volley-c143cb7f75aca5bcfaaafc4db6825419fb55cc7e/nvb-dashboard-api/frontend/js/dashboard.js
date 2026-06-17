async function loadDashboard() {
    try {
        const result = await apiGet("/stats/dashboard");
        const stats = result.data;

        document.getElementById("total-contacts").textContent = stats.total_contacts;
        document.getElementById("weezevent-contacts").textContent = stats.weezevent_contacts;
        document.getElementById("brevo-contacts").textContent = stats.brevo_contacts;
        document.getElementById("new-contacts").textContent = stats.new_contacts_7days;
    } catch (error) {
        console.error("Erreur dashboard:", error.message);
    }
}

document.addEventListener("DOMContentLoaded", loadDashboard);