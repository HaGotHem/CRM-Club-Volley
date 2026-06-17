const API_BASE_URL = "http://localhost:8080/api";

async function apiGet(path) {
    const response = await fetch(`${API_BASE_URL}${path}`);
    const data = await response.json();
    if (!response.ok || data.success === false) {
        throw new Error(data.error || "Erreur API");
    }
    return data;
}

async function apiPost(path, payload) {
    const response = await fetch(`${API_BASE_URL}${path}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });
    const data = await response.json();
    if (!response.ok || data.success === false) {
        throw new Error(data.error || "Erreur API");
    }
    return data;
}