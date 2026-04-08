// App principal - Inicialização e configurações globais
const App = {
    config: {
        apiUrl: 'https://api.urbantraffic.com/v1',
        mapboxToken: 'pk.eyJ1IjoiZXhhbXBsZSIsImEiOiJja2V4YW1wbGV0b2tlbiJ9', // Substitua pelo seu token
        refreshInterval: 30000 // 30 segundos
    },
    
    state: {
        currentUser: null,
        currentLocation: null,
        favorites: [],
        searchHistory: [],
        theme: 'light'
    },
    
    init: function() {
        console.log('UrbanTraffic App iniciado');
        this.loadState();
        this.setupEventListeners();
        this.getCurrentLocation();
        this.startRealtimeUpdates();
        this.updateDateTime();
    },
    
    loadState: function() {
        // Carregar estado do localStorage
        this.state.favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
        this.state.searchHistory = JSON.parse(localStorage.getItem('searchHistory') || '[]');
        this.state.theme = localStorage.getItem('theme') || 'light';
        
        // Aplicar tema
        if (this.state.theme === 'dark') {
            document.body.classList.add('dark-theme');
        }
    },
    
    setupEventListeners: function() {
        // Botão de voltar ao topo
        window.addEventListener('scroll', this.toggleBackToTop);
        
        // Links suaves
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', this.smoothScroll);
        });
    },
    
    getCurrentLocation: function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                position => {
                    this.state.currentLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    this.emit('location:updated', this.state.currentLocation);
                },
                error => {
                    console.warn('Erro ao obter localização:', error.message);
                    this.showNotification('Não foi possível obter sua localização', 'warning');
                }
            );
        }
    },
    
    startRealtimeUpdates: function() {
        setInterval(() => {
            this.fetchRealtimeData();
        }, this.config.refreshInterval);
    },
    
    fetchRealtimeData: function() {
        // Simular busca de dados em tempo real
        console.log('Atualizando dados em tempo real...');
        // Aqui você faria uma chamada API real
    },
    
    updateDateTime: function() {
        const update = () => {
            const now = new Date();
            const dateStr = now.toLocaleDateString('pt-PT', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            const timeStr = now.toLocaleTimeString('pt-PT');
            
            document.querySelectorAll('.current-datetime').forEach(el => {
                el.innerHTML = `<i class="fas fa-calendar-alt me-2"></i>${dateStr} | ${timeStr}`;
            });
        };
        
        update();
        setInterval(update, 1000);
    },
    
    showNotification: function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert-modern alert-${type} fade-in`;
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${this.getIconForType(type)} me-3 fs-4"></i>
                <div class="flex-grow-1">${message}</div>
                <button class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    },
    
    getIconForType: function(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    },
    
    toggleBackToTop: function() {
        const btn = document.getElementById('backToTop');
        if (btn) {
            if (window.scrollY > 300) {
                btn.classList.add('show');
            } else {
                btn.classList.remove('show');
            }
        }
    },
    
    smoothScroll: function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    },
    
    addToFavorites: function(item) {
        if (!this.state.favorites.find(f => f.id === item.id)) {
            this.state.favorites.push(item);
            localStorage.setItem('favorites', JSON.stringify(this.state.favorites));
            this.showNotification('Adicionado aos favoritos', 'success');
        }
    },
    
    removeFromFavorites: function(itemId) {
        this.state.favorites = this.state.favorites.filter(f => f.id !== itemId);
        localStorage.setItem('favorites', JSON.stringify(this.state.favorites));
        this.showNotification('Removido dos favoritos', 'info');
    },
    
    toggleTheme: function() {
        this.state.theme = this.state.theme === 'light' ? 'dark' : 'light';
        document.body.classList.toggle('dark-theme');
        localStorage.setItem('theme', this.state.theme);
    },
    
    emit: function(eventName, data) {
        const event = new CustomEvent(eventName, { detail: data });
        document.dispatchEvent(event);
    }
};

// Inicializar quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => App.init());

// Exportar para uso global
window.App = App;