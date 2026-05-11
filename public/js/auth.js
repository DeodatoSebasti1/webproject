// /urban/public/js/auth.js - Sistema de autenticação e favoritos

// Função global de notificações (usada por todo o sistema)
function showToast(message, type = 'info') {
    if (window.UrbanPreferences?.canUseNotifications && !window.UrbanPreferences.canUseNotifications()) {
        if (type === 'error') {
            console.warn(message);
        }
        return;
    }

    if (!$('#toast-container').length) {
        $('body').append(`
            <div id="toast-container" aria-live="polite" aria-atomic="true"></div>
        `);
    }
    
    const icons = {
        success: 'fas fa-circle-check',
        error: 'fas fa-circle-exclamation',
        warning: 'fas fa-triangle-exclamation',
        info: 'fas fa-circle-info'
    };
    
    const toast = $(`
        <div class="toast show ut-toast" data-type="${type}" role="alert">
            <div class="toast-body d-flex align-items-center">
                <i class="${icons[type] || icons.info} me-2"></i>
                <span class="flex-grow-1">${message}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    
    $('#toast-container').append(toast);
    
    setTimeout(() => {
        toast.fadeOut(300, () => toast.remove());
    }, 4000);
}

class AuthManager {
    constructor() {
        this.currentUser = this.getStoredUser();
        this.token = localStorage.getItem('auth_token');
        this.isVerifying = false;
        this.localUsageKey = 'urban_usage_events_v1';
        this.dataCache = {
            favorites: { items: null, fetchedAt: 0, controller: null, promise: null },
            history: { items: null, fetchedAt: 0, controller: null, promise: null }
        };
        this.activeUserDataView = null;
        this.cacheTtlMs = 45000;
        this.init();
    }
    
    init() {
        if (this.currentUser) {
            this.updateUI();
        }

        if (this.token || this.hasCookieSession()) {
            this.verifySession();
        } else if (this.currentUser) {
            this.clearAuthData();
        }
        this.setupEventListeners();
    }

    getStoredUser() {
        try {
            const raw = localStorage.getItem('user_data');
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            console.warn('Não foi possível ler user_data do localStorage:', error);
            return null;
        }
    }

    hasCookieSession() {
        return document.cookie.includes('urban_auth_token=');
    }
    
    setupEventListeners() {
        $(document).on('submit', '#loginForm', (e) => {
            e.preventDefault();
            this.login();
        });
        
        $(document).on('submit', '#registerForm', (e) => {
            e.preventDefault();
            this.register();
        });
        
        $('#logoutBtn').on('click', () => {
            this.logout();
        });
        
        $(document).on('click', '.favorite-btn', (e) => {
            e.preventDefault();
            this.toggleFavorite($(e.currentTarget));
        });

        $(document).on('click', '[data-auth-switch]', (e) => {
            e.preventDefault();
            this.switchAuthView($(e.currentTarget).data('auth-switch') || 'login');
        });
    }

    getCurrentUserKey() {
        return this.currentUser?.email || this.currentUser?.id || 'guest';
    }

    getDashboardUrl(user = this.currentUser) {
        return (user?.role || 'user') === 'admin'
            ? 'admin.php'
            : 'dashboard.php';
    }

    readLocalUsageEvents() {
        try {
            const raw = localStorage.getItem(this.localUsageKey);
            const events = raw ? JSON.parse(raw) : [];
            return Array.isArray(events) ? events : [];
        } catch (error) {
            console.warn('Não foi possível ler eventos locais de uso:', error);
            return [];
        }
    }

    writeLocalUsageEvents(events) {
        try {
            localStorage.setItem(this.localUsageKey, JSON.stringify(events.slice(-300)));
        } catch (error) {
            console.warn('Não foi possível guardar eventos locais de uso:', error);
        }
    }

    storeUsageEvent(routeData = {}) {
        if (!routeData || !routeData.origin_name || !routeData.destination_name) {
            return;
        }

        const events = this.readLocalUsageEvents();
        events.push({
            user_key: this.getCurrentUserKey(),
            searched_at: new Date().toISOString(),
            origin_name: routeData.origin_name,
            destination_name: routeData.destination_name,
            origin_lat: routeData.origin_lat ?? null,
            origin_lon: routeData.origin_lon ?? null,
            destination_lat: routeData.destination_lat ?? null,
            destination_lon: routeData.destination_lon ?? null,
            route_name: routeData.route_name || routeData.line || null,
            line: routeData.line || routeData.route_name || null,
            duration: Number(routeData.duration || routeData.total_time || 0) || null,
            duration_text: routeData.duration_text || null,
            transfers: Number(routeData.transfers || 0) || 0,
            stop_count: Number(routeData.stop_count || routeData.stops || 0) || 0,
            departure_time: routeData.departure_time || null,
            arrival_time: routeData.arrival_time || null,
            source: 'local_device'
        });
        this.writeLocalUsageEvents(events);
    }

    setInputState(selector, state = null) {
        const $field = $(selector);
        if (!$field.length) return;
        $field.removeClass('is-invalid is-valid');
        if (state === 'invalid') {
            $field.addClass('is-invalid');
        } else if (state === 'valid') {
            $field.addClass('is-valid');
        }
    }

    setFieldError(selector, message = '') {
        const $field = $(selector);
        if (!$field.length) return;

        const field = $field.get(0);
        if (field) {
            field.setCustomValidity(message || '');
        }

        const $feedback = $field.siblings('.invalid-feedback').first();
        if ($feedback.length && message) {
            $feedback.text(message);
        }

        this.setInputState(selector, message ? 'invalid' : 'valid');
    }

    clearFieldError(selector) {
        const $field = $(selector);
        if (!$field.length) return;

        const field = $field.get(0);
        if (field) {
            field.setCustomValidity('');
        }
    }

    isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
    }

    validateLoginFields() {
        const email = $('#loginEmail').val().trim();
        const password = $('#loginPassword').val();
        let isValid = true;

        this.clearFieldError('#loginEmail');
        this.clearFieldError('#loginPassword');

        if (!email) {
            this.setFieldError('#loginEmail', 'O email é obrigatório.');
            isValid = false;
        } else if (!this.isValidEmail(email)) {
            this.setFieldError('#loginEmail', 'Introduza um email válido.');
            isValid = false;
        } else {
            this.setInputState('#loginEmail', 'valid');
        }

        if (!password) {
            this.setFieldError('#loginPassword', 'A password é obrigatória.');
            isValid = false;
        } else if (password.length < 6) {
            this.setFieldError('#loginPassword', 'A password deve ter pelo menos 6 caracteres.');
            isValid = false;
        } else {
            this.setInputState('#loginPassword', 'valid');
        }

        return isValid;
    }

    validateRegisterFields() {
        const name = $('#registerName').val().trim();
        const email = $('#registerEmail').val().trim();
        const password = $('#registerPassword').val();
        const confirmPassword = $('#registerConfirmPassword').val();
        let isValid = true;

        ['#registerName', '#registerEmail', '#registerPassword', '#registerConfirmPassword'].forEach((selector) => {
            this.clearFieldError(selector);
        });

        if (!name) {
            this.setFieldError('#registerName', 'O nome é obrigatório.');
            isValid = false;
        } else if (name.length < 2 || name.length > 80) {
            this.setFieldError('#registerName', 'O nome deve ter entre 2 e 80 caracteres.');
            isValid = false;
        } else {
            this.setInputState('#registerName', 'valid');
        }

        if (!email) {
            this.setFieldError('#registerEmail', 'O email é obrigatório.');
            isValid = false;
        } else if (!this.isValidEmail(email)) {
            this.setFieldError('#registerEmail', 'Introduza um email válido.');
            isValid = false;
        } else {
            this.setInputState('#registerEmail', 'valid');
        }

        if (!password) {
            this.setFieldError('#registerPassword', 'A password é obrigatória.');
            isValid = false;
        } else if (password.length < 6 || password.length > 72) {
            this.setFieldError('#registerPassword', 'A password deve ter entre 6 e 72 caracteres.');
            isValid = false;
        } else {
            this.setInputState('#registerPassword', 'valid');
        }

        if (!confirmPassword) {
            this.setFieldError('#registerConfirmPassword', 'Confirme a password.');
            isValid = false;
        } else if (password !== confirmPassword) {
            this.setFieldError('#registerConfirmPassword', 'As passwords não coincidem.');
            this.setFieldError('#registerPassword', 'As passwords não coincidem.');
            isValid = false;
        } else if (isValid) {
            this.setInputState('#registerConfirmPassword', 'valid');
        }

        return isValid;
    }

    resetAuthValidation() {
        ['#loginEmail', '#loginPassword', '#registerName', '#registerEmail', '#registerPassword', '#registerConfirmPassword']
            .forEach((selector) => {
                this.clearFieldError(selector);
                this.setInputState(selector);
            });
    }

    setSubmitState(formSelector, isLoading, loadingLabel, defaultLabel) {
        const $button = $(`${formSelector} button[type="submit"]`);
        if (!$button.length) return;

        if (isLoading) {
            $button.prop('disabled', true).attr('aria-busy', 'true').html(`<span class="loading-spinner me-2"></span>${loadingLabel}`);
            return;
        }

        $button.prop('disabled', false).removeAttr('aria-busy').html(defaultLabel);
    }
    
    async login() {
        const email = $('#loginEmail').val().trim();
        const password = $('#loginPassword').val();
        this.resetAuthValidation();
        
        if (!this.validateLoginFields()) {
            this.showError('Corrija os campos de login antes de continuar.');
            return;
        }
        
        try {
            this.setSubmitState('#loginForm', true, 'A entrar...', 'Entrar');
            const response = await fetch('/urban/public/api/auth?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                this.setInputState('#loginEmail', 'valid');
                this.setInputState('#loginPassword', 'valid');
                this.setAuthData(data.user, data.token);
                this.showSuccess('Login realizado com sucesso!');
                this.closeAuthModal();
                this.updateUI();
                if (document.body.classList.contains('ut-page-dashboard')) {
                    window.location.reload();
                }
            } else {
                this.setFieldError('#loginEmail', 'Credenciais inválidas.');
                this.setFieldError('#loginPassword', 'Credenciais inválidas.');
                this.showError(data.message || 'Erro ao fazer login.');
            }
        } catch (error) {
            console.warn('Login error:', error);
            this.showError('Erro de conexão. Tente novamente.');
        } finally {
            this.setSubmitState('#loginForm', false, 'A entrar...', 'Entrar');
        }
    }
    
    async register() {
        const name = $('#registerName').val().trim();
        const email = $('#registerEmail').val().trim();
        const password = $('#registerPassword').val();
        const confirmPassword = $('#registerConfirmPassword').val();
        this.resetAuthValidation();
        
        if (!this.validateRegisterFields()) {
            this.showError('Corrija os campos do registo antes de continuar.');
            return;
        }
        
        try {
            this.setSubmitState('#registerForm', true, 'A criar conta...', 'Registar');
            const response = await fetch('/urban/public/api/auth?action=register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, email, password })
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                this.setInputState('#registerName', 'valid');
                this.setInputState('#registerEmail', 'valid');
                this.setInputState('#registerPassword', 'valid');
                this.setInputState('#registerConfirmPassword', 'valid');
                this.setAuthData(data.user, data.token);
                this.showSuccess('Registo realizado com sucesso!');
                this.closeAuthModal();
                this.updateUI();
                if (document.body.classList.contains('ut-page-dashboard')) {
                    window.location.reload();
                }
            } else {
                this.setFieldError('#registerEmail', data.message || 'Não foi possível criar a conta.');
                this.showError(data.message || 'Erro ao fazer registo.');
            }
        } catch (error) {
            console.warn('Register error:', error);
            this.showError('Erro de conexão. Tente novamente.');
        } finally {
            this.setSubmitState('#registerForm', false, 'A criar conta...', 'Registar');
        }
    }
    
    async logout() {
        try {
            if (this.token) {
                await fetch('/urban/public/api/auth?action=logout', {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${this.token}` }
                });
            } else if (this.hasCookieSession()) {
                await fetch('/urban/public/api/auth?action=logout', {
                    method: 'POST'
                });
            }
            
            this.clearAuthData();
            this.showSuccess('Logout realizado com sucesso!');
            this.updateUI();
            if (document.body.classList.contains('ut-page-dashboard')) {
                window.location.href = 'index.php';
            }
            
        } catch (error) {
            console.warn('Logout error:', error);
            this.clearAuthData();
            this.updateUI();
        }
    }
    
    async verifySession() {
        if (!this.token && !this.hasCookieSession()) return false;
        
        if (this.isVerifying) {
            return false;
        }
        
        this.isVerifying = true;
        
        try {
            const requestOptions = this.token
                ? { headers: { 'Authorization': `Bearer ${this.token}` } }
                : {};
            const response = await fetch('/urban/public/api/auth?action=verify', {
                ...requestOptions
            });
            
            const data = await response.json();
            
            if (data.status === 'success') {
                this.currentUser = data.user;
                this.updateUI();
                this.isVerifying = false;
                return true;
            } else {
                this.clearAuthData();
                this.isVerifying = false;
                return false;
            }
        } catch (error) {
            console.warn('Session verification error:', error);
            this.clearAuthData();
            this.isVerifying = false;
            return false;
        }
    }
    
    setAuthData(user, token) {
        this.currentUser = user;
        this.token = token;
        localStorage.setItem('auth_token', token);
        localStorage.setItem('user_data', JSON.stringify(user));
        document.cookie = `urban_auth_token=${encodeURIComponent(token)}; path=/; max-age=${60 * 60 * 24 * 30}; SameSite=Lax`;
    }
    
    clearAuthData() {
        this.currentUser = null;
        this.token = null;
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        document.cookie = 'urban_auth_token=; path=/; max-age=0; SameSite=Lax';
        this.invalidateUserDataCache();
    }
    
    updateUI() {
        if (this.currentUser) {
            $('#authNavSection').hide();
            $('#userNavSection, #dashboardNavItem').show();
            $('#userName').text(this.currentUser.name);
            $('#userEmail').text(this.currentUser.email);
            $('#logoutBtn').show();
            $('[data-dashboard-link]').attr('href', this.getDashboardUrl());

            if ($('#favoritesPageList').length) {
                this.loadFavorites();
            }
            if ($('#historyPageList').length) {
                this.loadHistory();
            }
        } else {
            $('#authNavSection').show();
            $('#userNavSection, #dashboardNavItem, #userDataSection').hide();
            $('#logoutBtn').hide();
            $('[data-dashboard-link]').attr('href', 'dashboard.php');
            this.invalidateUserDataCache();
        }

        if (typeof window.syncSettingsAuthState === 'function') {
            window.syncSettingsAuthState();
        }
    }

    getFavoritesContainer() {
        if (this.activeUserDataView === 'favorites' && $('#favoritesModalList').length) {
            return $('#favoritesModalList');
        }

        return $('#favoritesPageList');
    }

    getHistoryContainer() {
        if (this.activeUserDataView === 'history' && $('#historyModalList').length) {
            return $('#historyModalList');
        }

        return $('#historyPageList');
    }
    
    showAuthModal(type = 'login') {
        this.ensureAuthModal();
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('authModal'));
        modal.show();
        this.switchAuthView(type);
    }
    
    closeAuthModal() {
        const modalElement = document.getElementById('authModal');
        if (modalElement) {
            bootstrap.Modal.getOrCreateInstance(modalElement).hide();
        }
        $('#loginForm, #registerForm').each(function() {
            this.reset();
        });
        this.resetAuthValidation();
    }

    switchAuthView(type = 'login') {
        if (type === 'register') {
            bootstrap.Tab.getOrCreateInstance(document.getElementById('registerTab')).show();
            return;
        }
        bootstrap.Tab.getOrCreateInstance(document.getElementById('loginTab')).show();
    }

    ensureAuthModal() {
        if ($('#authModal').length) return;

        $('body').append(`
            <div class="modal fade ut-auth-modal" id="authModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Autenticação</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="ut-auth-intro">
                                <h6>Aceda à sua mobilidade</h6>
                                <p class="mb-0 small text-muted">Guarde favoritos, recupere histórico e personalize a experiência UrbanTraffic.</p>
                            </div>
                            <ul class="nav nav-tabs" id="authTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="loginTab" data-bs-toggle="tab" data-bs-target="#login" type="button">Login</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="registerTab" data-bs-toggle="tab" data-bs-target="#register" type="button">Criar conta</button>
                                </li>
                            </ul>
                            <div class="tab-content mt-3">
                                <div class="tab-pane fade show active" id="login">
                                    <form id="loginForm">
                                        <div class="mb-3">
                                            <label for="loginEmail" class="form-label">Email</label>
                                            <input type="email" class="form-control ut-input" id="loginEmail" required maxlength="120" autocomplete="email">
                                            <div class="invalid-feedback">Introduza um email válido.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="loginPassword" class="form-label">Password</label>
                                            <input type="password" class="form-control ut-input" id="loginPassword" required minlength="6" maxlength="72" autocomplete="current-password">
                                            <div class="invalid-feedback">Introduza a sua password.</div>
                                        </div>
                                        <button type="submit" class="btn btn-urbano ut-btn ut-btn-primary w-100">Entrar</button>
                                    </form>
                                    <p class="ut-auth-switch text-center mt-3 mb-0">
                                        Ainda não tens conta?
                                        <a href="#" data-auth-switch="register">Criar conta</a>
                                    </p>
                                </div>
                                <div class="tab-pane fade" id="register">
                                    <form id="registerForm">
                                        <div class="mb-3">
                                            <label for="registerName" class="form-label">Nome</label>
                                            <input type="text" class="form-control ut-input" id="registerName" required minlength="2" maxlength="80" autocomplete="name">
                                            <div class="invalid-feedback">O nome deve ter entre 2 e 80 caracteres.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="registerEmail" class="form-label">Email</label>
                                            <input type="email" class="form-control ut-input" id="registerEmail" required maxlength="120" autocomplete="email">
                                            <div class="invalid-feedback">Introduza um email válido.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="registerPassword" class="form-label">Password</label>
                                            <input type="password" class="form-control ut-input" id="registerPassword" required minlength="6" maxlength="72" autocomplete="new-password">
                                            <div class="invalid-feedback">A password deve ter entre 6 e 72 caracteres.</div>
                                            <small class="text-muted">Mínimo 6 caracteres</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="registerConfirmPassword" class="form-label">Confirmar Password</label>
                                            <input type="password" class="form-control ut-input" id="registerConfirmPassword" required minlength="6" maxlength="72" autocomplete="new-password">
                                            <div class="invalid-feedback">A confirmação tem de coincidir com a password.</div>
                                        </div>
                                        <button type="submit" class="btn btn-urbano ut-btn ut-btn-primary w-100">Criar conta</button>
                                    </form>
                                    <p class="ut-auth-switch text-center mt-3 mb-0">
                                        Já tens conta?
                                        <a href="#" data-auth-switch="login">Entrar</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `);
    }
    
    showError(message) {
        if (typeof showToast === 'function') {
            showToast(message, 'error');
        } else {
            console.warn(message);
        }
    }
    
    showSuccess(message) {
        if (typeof showToast === 'function') {
            showToast(message, 'success');
        } else {
            console.log(message);
        }
    }

    invalidateUserDataCache(type = null) {
        const keys = type ? [type] : ['favorites', 'history'];
        keys.forEach((key) => {
            const cache = this.dataCache[key];
            if (!cache) return;
            if (cache.controller) {
                cache.controller.abort();
            }
            cache.items = null;
            cache.fetchedAt = 0;
            cache.controller = null;
            cache.promise = null;
        });
    }

    getListLoadingHtml(label) {
        return `
            <div class="ut-loading text-muted py-3">
                <div class="spinner-border spinner-border-sm text-success" role="status" aria-hidden="true"></div>
                <span>A carregar ${label}...</span>
            </div>
        `;
    }

    getListErrorHtml(message = 'Não foi possível carregar agora.') {
        return `<div class="ut-alert ut-alert-warning py-2 mb-0">${message}</div>`;
    }

    async parseJsonResponse(response) {
        const rawText = await response.text();

        if (!rawText) {
            throw new Error('Resposta vazia do servidor.');
        }

        try {
            return JSON.parse(rawText);
        } catch (error) {
            console.warn('Resposta inválida do servidor:', rawText);
            throw new Error('O servidor devolveu uma resposta inválida.');
        }
    }

    async fetchUserList(type, url) {
        if (!this.currentUser || !this.token) {
            return [];
        }

        const cache = this.dataCache[type];
        const now = Date.now();
        if (cache.items && (now - cache.fetchedAt) < this.cacheTtlMs) {
            return cache.items;
        }

        if (cache.promise) {
            return cache.promise;
        }

        if (cache.controller) {
            cache.controller.abort();
        }

        const controller = new AbortController();
        cache.controller = controller;
        cache.promise = (async () => {
            try {
                const response = await fetch(url, {
                    headers: this.token ? { 'Authorization': `Bearer ${this.token}` } : {},
                    signal: controller.signal
                });
                const data = await this.parseJsonResponse(response);

                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || 'Erro ao carregar dados.');
                }

                const items = Array.isArray(data[type]) ? data[type] : [];
                cache.items = items;
                cache.fetchedAt = Date.now();
                return items;
            } finally {
                cache.controller = null;
                cache.promise = null;
            }
        })();

        return cache.promise;
    }
    
    async toggleFavorite($btn) {
        if (!this.currentUser) {
            this.showAuthModal('login');
            return;
        }
        
        const routeData = $btn.data('route');
        const isActive = $btn.hasClass('active');
        
        try {
            if (isActive) {
                const response = await fetch('/urban/public/api/user?action=remove_favorite', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(this.token ? { 'Authorization': `Bearer ${this.token}` } : {})
                    },
                    body: JSON.stringify({
                        route_name: routeData.route_name,
                        origin_name: routeData.origin_name,
                        destination_name: routeData.destination_name
                    })
                });
                const data = await response.json();

                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || 'Não foi possível remover o favorito.');
                }
                
                $btn.removeClass('active').html('<i class="far fa-heart"></i>');
                this.invalidateUserDataCache('favorites');
                this.showSuccess('Rota removida dos favoritos');
                
            } else {
                const response = await fetch('/urban/public/api/user?action=add_favorite', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(this.token ? { 'Authorization': `Bearer ${this.token}` } : {})
                    },
                    body: JSON.stringify({
                        route_name: routeData.route_name,
                        origin_name: routeData.origin_name,
                        destination_name: routeData.destination_name,
                        origin_lat: routeData.origin_lat,
                        origin_lon: routeData.origin_lon,
                        destination_lat: routeData.destination_lat,
                        destination_lon: routeData.destination_lon,
                        route_data: JSON.stringify(routeData)
                    })
                });
                const data = await response.json();

                if (!response.ok || data.status !== 'success') {
                    throw new Error(data.message || 'Não foi possível adicionar o favorito.');
                }
                
                $btn.addClass('active').html('<i class="fas fa-heart"></i>');
                this.invalidateUserDataCache('favorites');
                this.showSuccess('Rota adicionada aos favoritos');
            }
            
        } catch (error) {
            console.warn('Favorite toggle error:', error);
            this.showError('Erro ao atualizar favoritos');
        }
    }
    
    async loadFavorites() {
        if (!this.currentUser) return [];

        try {
            const favorites = await this.fetchUserList('favorites', '/urban/public/api/user?action=favorites&limit=10');
            this.displayFavorites(favorites);
            return favorites;
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.warn('Load favorites error:', error);
                this.getFavoritesContainer().html(this.getListErrorHtml('Não foi possível carregar os favoritos. Tenta novamente daqui a pouco.'));
            }
            return [];
        }
    }
    
    displayFavorites(favorites) {
        const $favoritesList = this.getFavoritesContainer();
        $favoritesList.empty();
        
        if (favorites.length === 0) {
            $favoritesList.html('<div class="ut-empty-state"><i class="fas fa-heart"></i><p class="text-muted mb-0">Ainda não tens rotas favoritas</p></div>');
            return;
        }
        
        favorites.forEach(fav => {
            const $item = $(`
                <div class="favorite-item ut-list-card card mb-2">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">${fav.route_name}</h6>
                                <small class="text-muted">
                                    <i class="fas fa-map-marker-alt"></i> ${fav.origin_name} 
                                    <i class="fas fa-arrow-right mx-1"></i> 
                                    ${fav.destination_name}
                                </small>
                            </div>
                            <button class="btn ut-btn ut-btn-secondary ut-btn-sm use-favorite-btn" 
                                    data-origin="${fav.origin_name}" 
                                    data-destination="${fav.destination_name}"
                                    data-origin-lat="${fav.origin_lat}"
                                    data-origin-lon="${fav.origin_lon}"
                                    data-destination-lat="${fav.destination_lat}"
                                    data-destination-lon="${fav.destination_lon}">
                                Usar
                            </button>
                        </div>
                    </div>
                </div>
            `);
            $favoritesList.append($item);
        });
    }
    
    async addToHistory(routeData) {
        try {
            this.storeUsageEvent(routeData);
            const headers = { 'Content-Type': 'application/json' };
            if (this.token) {
                headers.Authorization = `Bearer ${this.token}`;
            }

            await fetch('/urban/public/api/user?action=add_history', {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    origin_name: routeData.origin_name,
                    destination_name: routeData.destination_name,
                    origin_lat: routeData.origin_lat,
                    origin_lon: routeData.origin_lon,
                    destination_lat: routeData.destination_lat,
                    destination_lon: routeData.destination_lon
                })
            });
            this.invalidateUserDataCache('history');
        } catch (error) {
            console.warn('Add to history error:', error);
        }
    }
    
    async loadHistory() {
        if (!this.currentUser) return [];

        try {
            const history = await this.fetchUserList('history', '/urban/public/api/user?action=history&limit=10');
            this.displayHistory(history);
            return history;
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.warn('Load history error:', error);
                this.getHistoryContainer().html(this.getListErrorHtml('Não foi possível carregar o histórico. Tenta novamente daqui a pouco.'));
            }
            return [];
        }
    }

    async showFavorites() {
        if (!this.currentUser) {
            this.showAuthModal();
            this.showError('Faça login para ver os favoritos.');
            return;
        }

        if (document.body.classList.contains('ut-page-dashboard') && document.getElementById('dashboardFavoritesSection')) {
            document.getElementById('dashboardFavoritesSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        this.ensureUserDataModal('Favoritos', 'favoritesModalList');
        this.activeUserDataView = 'favorites';
        $('#favoritesModalList').html(this.getListLoadingHtml('favoritos'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('userDataModal')).show();
        await this.loadFavorites();
    }

    async showHistory() {
        if (!this.currentUser) {
            this.showAuthModal();
            this.showError('Faça login para ver o histórico.');
            return;
        }

        if (document.body.classList.contains('ut-page-dashboard') && document.getElementById('dashboardHistorySection')) {
            document.getElementById('dashboardHistorySection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        this.ensureUserDataModal('Histórico', 'historyModalList');
        this.activeUserDataView = 'history';
        $('#historyModalList').html(this.getListLoadingHtml('histórico'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('userDataModal')).show();
        await this.loadHistory();
    }

    ensureUserDataModal(title, listId) {
        if (!$('#userDataModal').length) {
            $('body').append(`
                <div class="modal fade" id="userDataModal" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="userDataModalTitle"></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div id="favoritesModalList"></div>
                                <div id="historyModalList"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        }

        $('#userDataModalTitle').text(title);
        $('#favoritesModalList, #historyModalList').hide();
        $(`#${listId}`).show();
        $('#userDataModal').off('hidden.bs.modal.userData').on('hidden.bs.modal.userData', () => {
            this.activeUserDataView = null;
        });
    }
    
    displayHistory(history) {
        const $historyList = this.getHistoryContainer();
        $historyList.empty();
        
        if (history.length === 0) {
            $historyList.html('<div class="ut-empty-state"><i class="fas fa-clock-rotate-left"></i><p class="text-muted mb-0">Ainda não tens histórico de pesquisas</p></div>');
            return;
        }
        
        history.forEach(item => {
            const $item = $(`
                <div class="history-item ut-list-card small p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-history text-muted me-2"></i>
                            ${item.origin_name} <i class="fas fa-arrow-right mx-1"></i> ${item.destination_name}
                        </div>
                        <button class="btn ut-btn ut-btn-secondary ut-btn-sm use-history-btn" 
                                data-origin="${item.origin_name}" 
                                data-destination="${item.destination_name}"
                                data-origin-lat="${item.origin_lat}"
                                data-origin-lon="${item.origin_lon}"
                                data-destination-lat="${item.destination_lat}"
                                data-destination-lon="${item.destination_lon}">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                    <small class="text-muted">${new Date(item.searched_at).toLocaleString('pt-PT')}</small>
                </div>
            `);
            $historyList.append($item);
        });
    }
    
    isAuthenticated() {
        return this.currentUser !== null;
    }
    
    getCurrentUser() {
        return this.currentUser;
    }
    
    getToken() {
        return this.token;
    }
}

// Inicializar o AuthManager
const authManager = new AuthManager();

$(document).on('click', '.use-favorite-btn, .use-history-btn', function() {
    const $btn = $(this);
    const origin = $btn.data('origin');
    const destination = $btn.data('destination');
    const originLat = $btn.data('origin-lat');
    const originLon = $btn.data('origin-lon');
    const destinationLat = $btn.data('destination-lat');
    const destinationLon = $btn.data('destination-lon');
    
    window.location.href = `results.php?fromLat=${originLat}&fromLon=${originLon}&toLat=${destinationLat}&toLon=${destinationLon}&origin=${encodeURIComponent(origin)}&dest=${encodeURIComponent(destination)}`;
});

window.authManager = authManager;
