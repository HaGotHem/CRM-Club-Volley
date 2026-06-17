// Client API du backend Slim.
// L'URL est relative : le reverse-proxy route "/api" vers le conteneur backend,
// donc le front et l'API sont sur la même origine (pas de souci de CORS).
const API_BASE_URL = "/api";

export async function apiGet(path) {
    const response = await fetch(`${API_BASE_URL}${path}`);
    const data = await response.json();
    if (!response.ok || data.success === false) {
        throw new Error(data.error || "Erreur API");
    }
    return data;
}

export async function apiPost(path, payload) {
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
