// preferences.js - Preferências globais UrbanTraffic
(function() {
    const DARK_MODE_KEY = 'urban_dark_mode';
    const LANGUAGE_KEY = 'urban_language';
    const SETTINGS_STORAGE_KEY = 'userSettings';

    const translations = {
        pt: {
            navDirections: 'Direções',
            navLines: 'Linhas',
            navDashboard: 'Dashboard',
            navSettings: 'Configurações',
            navLogin: 'Login',
            navRegister: 'Registar',
            navLogout: 'Sair',
            navFavorites: 'Favoritos',
            navHistory: 'Histórico',
            resultsNewSearch: 'Nova Pesquisa',
            resultsSuggestedRoutes: 'Percursos sugeridos',
            resultsStartRoute: 'Iniciar Trajeto',
            resultsRealtime: 'Tempo Real',
            resultsActiveBuses: 'Autocarros ativos:',
            resultsLastUpdate: 'Última atualização:',
            resultsSource: 'Fonte:',
            settingsPageTitle: 'Configurações',
            settingsPageSubtitle: 'Personalize a sua experiência na aplicação',
            adminTitle: 'Monitorização do UrbanTraffic',
            adminSubtitle: 'Utilizadores, pesquisas, favoritos, cache e indicadores de uso para demonstração e relatório final.',
            indexHeroTitle: 'Planeie a sua viagem',
            indexHeroSubtitle: 'Origem, destino e horários numa experiência rápida, clara e pronta para o dia a dia.',
            indexRealtimeData: 'Tempo real',
            indexPlanTrip: 'Planeie a sua viagem',
            indexOrigin: 'Origem',
            indexDestination: 'Destino',
            indexDate: 'Data',
            indexSearch: 'Pesquisar',
            linesTitle: 'Linhas Carris Metropolitana'
        },
        en: {
            navDirections: 'Directions',
            navLines: 'Lines',
            navDashboard: 'Dashboard',
            navSettings: 'Settings',
            navLogin: 'Login',
            navRegister: 'Register',
            navLogout: 'Sign out',
            navFavorites: 'Favorites',
            navHistory: 'History',
            resultsNewSearch: 'New Search',
            resultsSuggestedRoutes: 'Suggested Routes',
            resultsStartRoute: 'Start Route',
            resultsRealtime: 'Realtime',
            resultsActiveBuses: 'Active buses:',
            resultsLastUpdate: 'Last update:',
            resultsSource: 'Source:',
            settingsPageTitle: 'Settings',
            settingsPageSubtitle: 'Customize your experience in the app',
            adminTitle: 'UrbanTraffic Monitoring',
            adminSubtitle: 'Users, searches, favorites, cache and usage indicators for demo and final report.',
            indexHeroTitle: 'Plan your trip',
            indexHeroSubtitle: 'Origin, destination and schedules in a fast, clear experience built for everyday travel.',
            indexRealtimeData: 'Realtime',
            indexPlanTrip: 'Plan your trip',
            indexOrigin: 'Origin',
            indexDestination: 'Destination',
            indexDate: 'Date',
            indexSearch: 'Search',
            linesTitle: 'Carris Metropolitana Lines'
        }
    };

    function getLanguage() {
        const settings = readSettings();
        const language = settings.language || localStorage.getItem(LANGUAGE_KEY) || 'pt';
        return translations[language] ? language : 'pt';
    }

    function isDarkMode() {
        const settings = readSettings();
        if (typeof settings.darkMode === 'boolean') {
            return settings.darkMode;
        }
        return localStorage.getItem(DARK_MODE_KEY) === 'true';
    }

    function readSettings() {
        let settings = {};
        try {
            settings = JSON.parse(localStorage.getItem(SETTINGS_STORAGE_KEY) || '{}') || {};
        } catch (error) {
            settings = {};
        }

        const language = localStorage.getItem(LANGUAGE_KEY);
        const darkMode = localStorage.getItem(DARK_MODE_KEY);

        if (!settings.language && language) {
            settings.language = language;
        }

        if (typeof settings.darkMode !== 'boolean' && darkMode !== null) {
            settings.darkMode = darkMode === 'true';
        }

        return {
            language: settings.language || 'pt',
            darkMode: !!settings.darkMode,
            notifications: settings.notifications !== false,
            location: settings.location !== false,
            dataSaver: !!settings.dataSaver
        };
    }

    function getRealtimeRefreshMs() {
        return getPreferences().dataSaver ? 20000 : 10000;
    }

    function getAnimationEnabled() {
        return !getPreferences().dataSaver;
    }

    function canUseNotifications() {
        return getPreferences().notifications !== false;
    }

    function canUseLocation() {
        return getPreferences().location !== false;
    }

    function getPreferences() {
        return readSettings();
    }

    function t(key, language = getLanguage()) {
        return translations[language]?.[key] || translations.pt[key] || key;
    }

    function applyTheme(darkMode = isDarkMode()) {
        document.documentElement.classList.toggle('dark-mode', !!darkMode);
        document.body?.classList.toggle('dark-mode', !!darkMode);
    }

    function applyLanguage(language = getLanguage()) {
        document.documentElement.setAttribute('data-language', language);
        document.documentElement.lang = language === 'en' ? 'en' : 'pt';
        applyTranslations(document, language);
    }

    function applyTranslations(root = document, language = getLanguage()) {
        root.querySelectorAll?.('[data-i18n]').forEach((element) => {
            const key = element.getAttribute('data-i18n');
            const text = t(key, language);

            if (element.hasAttribute('data-i18n-placeholder')) {
                element.setAttribute('placeholder', text);
                return;
            }

            element.textContent = text;
        });
    }

    function setPreferences(nextPreferences = {}) {
        const current = readSettings();
        const merged = {
            ...current,
            ...nextPreferences
        };

        if (Object.prototype.hasOwnProperty.call(nextPreferences, 'darkMode')) {
            localStorage.setItem(DARK_MODE_KEY, nextPreferences.darkMode ? 'true' : 'false');
        }

        if (Object.prototype.hasOwnProperty.call(nextPreferences, 'language')) {
            localStorage.setItem(LANGUAGE_KEY, translations[nextPreferences.language] ? nextPreferences.language : 'pt');
        }

        localStorage.setItem(SETTINGS_STORAGE_KEY, JSON.stringify(merged));

        const preferences = getPreferences();
        applyTheme(preferences.darkMode);
        applyLanguage(preferences.language);
        window.dispatchEvent(new Event('urbanPreferencesChanged'));
        return preferences;
    }

    function init() {
        const preferences = getPreferences();
        applyTheme(preferences.darkMode);
        applyLanguage(preferences.language);
    }

    window.UrbanPreferences = {
        getPreferences,
        canUseNotifications,
        canUseLocation,
        getRealtimeRefreshMs,
        getAnimationEnabled,
        applyTheme,
        applyLanguage,
        applyTranslations,
        setPreferences,
        t
    };

    window.addEventListener('urbanPreferencesChanged', function() {
        const preferences = getPreferences();
        applyTheme(preferences.darkMode);
        applyLanguage(preferences.language);
    });

    window.addEventListener('storage', function(event) {
        if (event.key === DARK_MODE_KEY || event.key === LANGUAGE_KEY) {
            init();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
