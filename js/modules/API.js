export class ApiService {
    static async fetchFilterOptions(field) {
        const response = await fetch(`api/filter_values.php?field=${encodeURIComponent(field)}&size=50`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    }

    static async search(filters) {
        const queryParams = new URLSearchParams(filters).toString();
        const apiUrl = `api/search.php?${queryParams}&include_stats=true`;
        const response = await fetch(apiUrl);
        if (!response.ok) {
            const errorData = await response.json().catch(() => null);
            throw new Error(errorData?.details || `HTTP error! status: ${response.status}`);
        }
        return await response.json();
    }

    static async fetchResearchers(query) {
        const response = await fetch(`api/researchers.php?q=${encodeURIComponent(query)}&size=20`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    }

    static async fetchStatistics() {
        const response = await fetch('api/search.php?include_stats=true&size=0');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    }
}
