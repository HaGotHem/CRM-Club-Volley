async function loadContacts() {
    const tableBody = document.querySelector("#contacts-table-body");

    tableBody.innerHTML = `
        <tr>
            <td colspan="5" class="p-4 text-center text-gray-500">Chargement...</td>
        </tr>
    `;

    try {
        const result = await apiGet("/contacts");

        tableBody.innerHTML = "";

        result.data.forEach((contact) => {
            const row = document.createElement("tr");
            row.className = "border-b hover:bg-gray-50 cursor-pointer";
            row.innerHTML = `
                <td class="p-3">${contact.first_name}</td>
                <td class="p-3">${contact.last_name}</td>
                <td class="p-3">${contact.email}</td>
                <td class="p-3">${contact.phone ?? ""}</td>
                <td class="p-3">
                    <span class="px-2 py-1 rounded text-xs font-semibold
                        ${contact.source === 'weezevent' ? 'bg-blue-100 text-blue-700' :
                          contact.source === 'brevo' ? 'bg-green-100 text-green-700' :
                          'bg-gray-100 text-gray-700'}">
                        ${contact.source ?? ""}
                    </span>
                </td>
            `;
            row.onclick = () => {
                window.location.href = `contact-detail.html?id=${contact.id}`;
            };
            tableBody.appendChild(row);
        });
    } catch (error) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="p-4 text-center text-red-600">
                    Erreur : ${error.message}
                </td>
            </tr>
        `;
    }
}

document.addEventListener("DOMContentLoaded", loadContacts);