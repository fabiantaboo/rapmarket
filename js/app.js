// RapMarket.de - Main JavaScript Application

// App State
const appState = {
    currentUser: null,
    userPoints: 0,
    currentSection: 'home',
    events: [],
    leaderboard: [],
    userBets: [],
    loading: false,
    authMode: 'login' // 'login' oder 'register'
};

// API Base URL - Verwende auth_v3.php mit verbessertem Login
const API_BASE = 'api/';
const AUTH_ENDPOINT = 'auth_v3.php'; // Verbesserte Version mit besserer Fehlerbehandlung

// Initialize App
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    checkUserSession();
});

function initializeApp() {
    console.log('RapMarket.de initialized');
}

function setupEventListeners() {
    // Navigation
    document.querySelectorAll('[data-section]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const section = this.getAttribute('data-section');
            navigateToSection(section);
        });
    });
    
    // Login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleLogin();
        });
    }
}

// API Helper Functions
async function apiRequest(endpoint, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    const config = { ...defaultOptions, ...options };
    
    if (config.body && typeof config.body === 'object') {
        config.body = JSON.stringify(config.body);
    }
    
    try {
        const response = await fetch(API_BASE + endpoint, config);
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'API Fehler aufgetreten');
        }
        
        return data;
    } catch (error) {
        console.error('API Request failed:', error);
        throw error;
    }
}

// Authentication Functions
async function checkUserSession() {
    try {
        const response = await apiRequest(AUTH_ENDPOINT, {
            method: 'POST',
            body: { action: 'check_session' }
        });
        
        if (response.logged_in) {
            appState.currentUser = response.user;
            appState.userPoints = response.user.points;
            updateUserDisplay();
        }
    } catch (error) {
        console.error('Session check failed:', error);
    }
}

async function handleLogin() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const rememberMe = document.getElementById('rememberMe').checked;
    
    if (!username || !password) {
        showAlert('Bitte fülle alle Felder aus!', 'danger');
        return;
    }
    
    setLoading(true);
    
    try {
        const response = await apiRequest(AUTH_ENDPOINT, {
            method: 'POST',
            body: {
                action: 'login',
                username: username,
                password: password,
                remember_me: rememberMe
            }
        });
        
        appState.currentUser = response.user;
        appState.userPoints = response.user.points;
        
        updateUserDisplay();
        closeModal('loginModal');
        
        // Reset form
        document.getElementById('loginForm').reset();
        
        showAlert(response.message, 'success');
        
    } catch (error) {
        showAlert(error.message, 'danger');
    } finally {
        setLoading(false);
    }
}

async function handleRegister() {
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email') ? document.getElementById('email').value.trim() : '';
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword') ? document.getElementById('confirmPassword').value : password;
    
    if (!username || !password || (email && !email)) {
        showAlert('Bitte fülle alle Felder aus!', 'danger');
        return;
    }
    
    if (password !== confirmPassword) {
        showAlert('Passwörter stimmen nicht überein!', 'danger');
        return;
    }
    
    setLoading(true);
    
    try {
        const response = await apiRequest(AUTH_ENDPOINT, {
            method: 'POST',
            body: {
                action: 'register',
                username: username,
                email: email || `${username}@example.com`, // Fallback für Demo
                password: password,
                confirm_password: confirmPassword
            }
        });
        
        appState.currentUser = response.user;
        appState.userPoints = response.user.points;
        
        updateUserDisplay();
        closeModal('loginModal');
        
        // Reset form
        document.getElementById('loginForm').reset();
        
        showAlert(response.message, 'success');
        
    } catch (error) {
        showAlert(error.message, 'danger');
    } finally {
        setLoading(false);
    }
}

async function handleLogout() {
    try {
        await apiRequest(AUTH_ENDPOINT, {
            method: 'POST',
            body: { action: 'logout' }
        });
        
        appState.currentUser = null;
        appState.userPoints = 0;
        
        updateUserDisplay();
        showAlert('Erfolgreich abgemeldet!', 'success');
        
        // Navigate to home
        navigateToSection('home');
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

// Navigation
function navigateToSection(sectionName) {
    // Hide all sections
    document.querySelectorAll('.section-content, .hero-section').forEach(section => {
        section.classList.add('d-none');
    });
    
    // Show target section
    const targetSection = document.getElementById(sectionName);
    if (targetSection) {
        targetSection.classList.remove('d-none');
    }
    
    // Update navigation
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    document.querySelector(`[data-section="${sectionName}"]`)?.classList.add('active');
    
    appState.currentSection = sectionName;
    
    // Load section specific data
    switch(sectionName) {
        case 'events':
            loadEvents();
            break;
        case 'leaderboard':
            loadLeaderboard();
            break;
        case 'community':
            loadCommunity();
            break;
    }
}

// Events Functions
async function loadEvents() {
    const container = document.getElementById('eventsContainer');
    if (!container) return;
    
    setLoading(true);
    
    try {
        const response = await apiRequest('events.php?status=active');
        appState.events = response.events;
        
        container.innerHTML = '';
        
        response.events.forEach(event => {
            const eventCard = createEventCard(event);
            container.appendChild(eventCard);
        });
        
    } catch (error) {
        container.innerHTML = '<div class="col-12"><div class="alert alert-danger">Fehler beim Laden der Events: ' + error.message + '</div></div>';
    } finally {
        setLoading(false);
    }
}

function createEventCard(event) {
    const card = document.createElement('div');
    card.className = 'col-md-6 col-lg-4 mb-4';
    
    const optionsHtml = event.options.map(option => 
        `<div class="bet-option" data-event-id="${event.id}" data-option-id="${option.id}" data-odds="${option.odds}">
            ${option.option_text} (${option.odds}x)
         </div>`
    ).join('');
    
    card.innerHTML = `
        <div class="event-card">
            <div class="event-title">${escapeHtml(event.title)}</div>
            <div class="event-date">
                <i class="fas fa-calendar-alt me-2"></i>
                ${escapeHtml(event.formatted_start_date)}
            </div>
            <div class="event-description">${escapeHtml(event.description)}</div>
            <div class="bet-options">
                ${optionsHtml}
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <input type="number" class="form-control me-2" placeholder="Punkte" min="${event.min_bet || 10}" max="${Math.min(event.max_bet || 1000, appState.userPoints)}" id="bet-amount-${event.id}" style="width: 120px;">
                <button class="btn btn-primary" onclick="placeBet(${event.id})" ${!appState.currentUser ? 'disabled' : ''}>
                    <i class="fas fa-coins me-1"></i>Setzen
                </button>
            </div>
            ${!appState.currentUser ? '<small class="text-muted">Login erforderlich</small>' : ''}
        </div>
    `;
    
    // Add click listeners to bet options
    card.querySelectorAll('.bet-option').forEach(option => {
        option.addEventListener('click', function() {
            if (!appState.currentUser) {
                showAlert('Bitte logge dich ein, um zu setzen!', 'warning');
                return;
            }
            
            // Remove selection from siblings
            this.parentNode.querySelectorAll('.bet-option').forEach(opt => 
                opt.classList.remove('selected'));
            // Add selection to clicked option
            this.classList.add('selected');
        });
    });
    
    return card;
}

async function placeBet(eventId) {
    if (!appState.currentUser) {
        showAlert('Bitte logge dich ein, um zu setzen!', 'danger');
        return;
    }
    
    const selectedOption = document.querySelector(`[data-event-id="${eventId}"].selected`);
    const betAmountInput = document.getElementById(`bet-amount-${eventId}`);
    
    if (!selectedOption) {
        showAlert('Bitte wähle eine Option!', 'danger');
        return;
    }
    
    const betAmount = parseInt(betAmountInput.value);
    if (!betAmount || betAmount < 10) {
        showAlert('Mindestbetrag: 10 Punkte!', 'danger');
        return;
    }
    
    if (betAmount > appState.userPoints) {
        showAlert('Nicht genügend Punkte!', 'danger');
        return;
    }
    
    setLoading(true);
    
    try {
        const response = await apiRequest('events.php', {
            method: 'POST',
            body: {
                action: 'place_bet',
                event_id: eventId,
                option_id: selectedOption.getAttribute('data-option-id'),
                amount: betAmount
            }
        });
        
        // Update points
        appState.userPoints = response.new_points;
        updatePointsDisplay();
        
        showAlert(response.message, 'success');
        
        // Reset form
        selectedOption.classList.remove('selected');
        betAmountInput.value = '';
        
        // Update bet amount max value
        betAmountInput.max = Math.min(1000, appState.userPoints);
        
    } catch (error) {
        showAlert(error.message, 'danger');
    } finally {
        setLoading(false);
    }
}

// Leaderboard Functions
async function loadLeaderboard() {
    const tableBody = document.getElementById('leaderboardTable');
    if (!tableBody) return;
    
    setLoading(true);
    
    try {
        const response = await apiRequest('leaderboard.php?type=points&limit=50');
        
        tableBody.innerHTML = '';
        
        response.leaderboard.forEach(user => {
            const row = document.createElement('tr');
            
            let rankIcon = '';
            if (user.rank === 1) rankIcon = '<i class="fas fa-crown text-warning me-2"></i>';
            else if (user.rank === 2) rankIcon = '<i class="fas fa-medal text-secondary me-2"></i>';
            else if (user.rank === 3) rankIcon = '<i class="fas fa-medal text-warning me-2"></i>';
            
            row.innerHTML = `
                <td>${rankIcon}${user.rank}</td>
                <td>${escapeHtml(user.username)}</td>
                <td><span class="points-display">${user.formatted_points}</span></td>
                <td>${user.wins}</td>
                <td>${user.win_rate}%</td>
            `;
            
            // Highlight current user
            if (appState.currentUser && user.id === appState.currentUser.id) {
                row.classList.add('table-warning');
            }
            
            tableBody.appendChild(row);
        });
        
    } catch (error) {
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Fehler beim Laden der Rangliste: ' + error.message + '</td></tr>';
    } finally {
        setLoading(false);
    }
}

// Community Functions (placeholder)
function loadCommunity() {
    const discussionsContainer = document.getElementById('discussionsContainer');
    const onlineUsers = document.getElementById('onlineUsers');
    
    if (discussionsContainer) {
        discussionsContainer.innerHTML = `
            <div class="text-center text-muted">
                <i class="fas fa-comments fa-3x mb-3"></i>
                <p>Community-Features kommen bald!</p>
            </div>
        `;
    }
    
    if (onlineUsers) {
        onlineUsers.innerHTML = `
            <div class="text-center text-muted">
                <i class="fas fa-users fa-2x mb-2"></i>
                <p>Online-Status kommt bald!</p>
            </div>
        `;
    }
}

// UI Helper Functions
function updateUserDisplay() {
    const loginLink = document.querySelector('[data-bs-target="#loginModal"]');
    if (appState.currentUser && loginLink) {
        loginLink.innerHTML = `<i class="fas fa-user me-1"></i>${escapeHtml(appState.currentUser.username)}`;
        loginLink.setAttribute('href', '#');
        loginLink.removeAttribute('data-bs-toggle');
        loginLink.removeAttribute('data-bs-target');
        loginLink.onclick = (e) => {
            e.preventDefault();
            handleLogout();
        };
    } else if (loginLink) {
        loginLink.innerHTML = '<i class="fas fa-user me-1"></i>Login';
        loginLink.setAttribute('data-bs-toggle', 'modal');
        loginLink.setAttribute('data-bs-target', '#loginModal');
        loginLink.onclick = null;
    }
    updatePointsDisplay();
}

function updatePointsDisplay() {
    const pointsElement = document.getElementById('userPoints');
    if (pointsElement) {
        pointsElement.textContent = appState.userPoints.toLocaleString();
    }
}

function setLoading(loading) {
    appState.loading = loading;
    const loadingElements = document.querySelectorAll('.loading-spinner');
    loadingElements.forEach(el => {
        el.style.display = loading ? 'inline-block' : 'none';
    });
}

function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function closeModal(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Authentication UI Functions
function toggleAuthMode() {
    appState.authMode = appState.authMode === 'login' ? 'register' : 'login';
    updateAuthModalUI();
}

function updateAuthModalUI() {
    const modalTitle = document.getElementById('authModalTitle');
    const emailField = document.getElementById('emailField');
    const confirmPasswordField = document.getElementById('confirmPasswordField');
    const toggleBtn = document.getElementById('toggleModeBtn');
    const submitBtn = document.getElementById('authSubmitBtn');
    const emailInput = document.getElementById('email');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    
    if (appState.authMode === 'register') {
        modalTitle.textContent = 'Registrierung';
        emailField.style.display = 'block';
        confirmPasswordField.style.display = 'block';
        toggleBtn.textContent = 'Bereits registriert? Login';
        submitBtn.textContent = 'Registrieren';
        submitBtn.className = 'btn btn-success';
        
        // Required für Registrierung
        emailInput.setAttribute('required', '');
        confirmPasswordInput.setAttribute('required', '');
    } else {
        modalTitle.textContent = 'Login';
        emailField.style.display = 'none';
        confirmPasswordField.style.display = 'none';
        toggleBtn.textContent = 'Noch kein Account? Registrieren';
        submitBtn.textContent = 'Login';
        submitBtn.className = 'btn btn-primary';
        
        // Nicht required für Login
        emailInput.removeAttribute('required');
        confirmPasswordInput.removeAttribute('required');
    }
}

function handleAuth() {
    if (appState.authMode === 'login') {
        handleLogin();
    } else {
        handleRegister();
    }
}

// Initialize Auth Modal on first open
document.addEventListener('DOMContentLoaded', function() {
    const loginModal = document.getElementById('loginModal');
    if (loginModal) {
        loginModal.addEventListener('show.bs.modal', function() {
            updateAuthModalUI();
        });
    }
});

// Global functions for HTML onclick attributes
window.placeBet = placeBet;
window.handleLogin = handleLogin;
window.handleRegister = handleRegister;
window.login = handleLogin; // Backward compatibility
window.register = handleRegister; // Backward compatibility
window.toggleAuthMode = toggleAuthMode;
window.handleAuth = handleAuth;