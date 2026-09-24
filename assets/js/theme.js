(() => {
    const storageKey = 'traintote-appearance';
    const root = document.documentElement;
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)');

    function preference() {
        return localStorage.getItem(storageKey) || 'system';
    }

    function applyTheme(mode = preference()) {
        const effective = mode === 'system' ? (systemDark.matches ? 'dark' : 'light') : mode;
        root.dataset.theme = effective;
        root.dataset.themePreference = mode;
        const select = document.getElementById('tt-theme-select');
        if (select) select.value = mode;
    }

    document.addEventListener('DOMContentLoaded', () => {
        applyTheme();
        const select = document.getElementById('tt-theme-select');
        if (select) select.addEventListener('change', () => {
            localStorage.setItem(storageKey, select.value);
            applyTheme(select.value);
        });
    });

    systemDark.addEventListener('change', () => {
        if (preference() === 'system') applyTheme('system');
    });
})();
