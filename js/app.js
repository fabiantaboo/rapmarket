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
    authMode: 'login', // 'login' oder 'register'
    currentBetFilter: 'all',
    currentCategory: 'all',
    currentEventFilter: 'live'
};

// API Base URL - Verwende auth_v3.php mit verbessertem Login
const API_BASE = 'api/';
const AUTH_ENDPOINT = 'auth_v3.php'; // Verbesserte Version mit besserer Fehlerbehandlung

// Initialize App
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    setupRouting();
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

// URL Routing
function setupRouting() {
    // Handle initial URL
    handleRoute();
    
    // Handle browser back/forward
    window.addEventListener('popstate', handleRoute);
}

function handleRoute() {
    const hash = window.location.hash.slice(1) || 'home';
    navigateToSection(hash, false);
}

function updateURL(sectionName) {
    const newURL = sectionName === 'home' ? 
        window.location.pathname : 
        `${window.location.pathname}#${sectionName}`;
    
    if (window.location.href !== window.location.origin + newURL) {
        history.pushState({ section: sectionName }, '', newURL);
    }
}

// Navigation
function navigateToSection(sectionName, updateHistory = true) {
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
    
    // Update URL
    if (updateHistory) {
        updateURL(sectionName);
    }
    
    // Update page title
    updatePageTitle(sectionName);
    
    // Load section specific data
    switch(sectionName) {
        case 'events':
            loadEvents();
            setupEventFilters();
            break;
        case 'leaderboard':
            loadLeaderboard();
            break;
        case 'community':
            loadCommunity();
            break;
        case 'profile':
            loadProfile();
            break;
    }
}

function updatePageTitle(sectionName) {
    const titles = {
        'home': 'RapMarket.de - Deine Hip-Hop Community',
        'events': 'Events - RapMarket.de',
        'leaderboard': 'Rangliste - RapMarket.de',
        'community': 'Community - RapMarket.de',
        'profile': 'Mein Profil - RapMarket.de'
    };
    
    document.title = titles[sectionName] || titles['home'];
}

// Events Functions
async function loadEvents() {
    const container = document.getElementById('eventsContainer');
    if (!container) return;
    
    setLoading(true);
    container.innerHTML = '<div class="loading-placeholder"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Lade Events...</p></div>';
    
    try {
        const response = await apiRequest('events_v2.php?status=active');
        appState.events = response.events;
        
        updateEventCounts();
        displayFilteredEvents();
        
    } catch (error) {
        container.innerHTML = '<div class="alert alert-danger">Fehler beim Laden der Events: ' + error.message + '</div>';
    } finally {
        setLoading(false);
    }
}

function setupEventFilters() {
    // Category filters
    document.querySelectorAll('.category-item').forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all items
            document.querySelectorAll('.category-item').forEach(i => i.classList.remove('active'));
            
            // Add active class to clicked item
            this.classList.add('active');
            
            // Update current category
            appState.currentCategory = this.getAttribute('data-category');
            
            // Re-display events
            displayFilteredEvents();
            
            console.log('Category changed to:', appState.currentCategory);
        });
    });
    
    // Event status filters
    document.querySelectorAll('input[name="eventFilter"]').forEach(filter => {
        filter.addEventListener('change', function() {
            if (this.checked) {
                appState.currentEventFilter = this.id.replace('filter', '').toLowerCase();
                displayFilteredEvents();
                
                console.log('Event filter changed to:', appState.currentEventFilter);
            }
        });
    });
}

function displayFilteredEvents() {
    const container = document.getElementById('eventsContainer');
    if (!container || !appState.events) return;
    
    let filteredEvents = [...appState.events];
    
    // Filter by category
    if (appState.currentCategory !== 'all') {
        filteredEvents = filteredEvents.filter(event => 
            event.category === appState.currentCategory
        );
    }
    
    // Filter by status
    if (appState.currentEventFilter !== 'all') {
        filteredEvents = filteredEvents.filter(event => {
            const now = new Date().getTime();
            const startTime = new Date(event.start_date).getTime();
            const endTime = new Date(event.end_date).getTime();
            
            switch (appState.currentEventFilter) {
                case 'live':
                    return event.status === 'active' && startTime <= now && endTime > now;
                case 'upcoming':
                    return event.status === 'active' && startTime > now;
                default:
                    return true;
            }
        });
    }
    
    // Clear container
    container.innerHTML = '';
    
    // Display filtered events
    if (filteredEvents.length === 0) {
        container.innerHTML = `
            <div class="no-events-placeholder">
                <div class="text-center text-muted">
                    <i class="fas fa-search fa-3x mb-3"></i>
                    <h4>Keine Events gefunden</h4>
                    <p>Versuche einen anderen Filter oder eine andere Kategorie.</p>
                </div>
            </div>
        `;
    } else {
        filteredEvents.forEach(event => {
            const eventCard = createEventCard(event);
            container.appendChild(eventCard);
        });
    }
    
    // Update statistics
    updateLiveStats(filteredEvents);
}

function updateEventCounts() {
    const categories = ['all', 'battle', 'charts', 'streaming', 'tour', 'awards'];
    
    categories.forEach(category => {
        const count = category === 'all' 
            ? appState.events.length 
            : appState.events.filter(event => event.category === category).length;
        
        const countElement = document.getElementById(`count-${category}`);
        if (countElement) {
            countElement.textContent = count;
        }
    });
}

function updateLiveStats(events = null) {
    const eventsToUse = events || appState.events;
    
    // Total events
    const totalEventsElement = document.getElementById('totalEvents');
    if (totalEventsElement) {
        totalEventsElement.textContent = eventsToUse.length;
    }
    
    // Total bets
    const totalBets = eventsToUse.reduce((sum, event) => sum + (event.bet_count || 0), 0);
    const totalBetsElement = document.getElementById('totalBets');
    if (totalBetsElement) {
        totalBetsElement.textContent = totalBets.toLocaleString();
    }
    
    // Total volume
    const totalVolume = eventsToUse.reduce((sum, event) => sum + (event.total_bets_amount || 0), 0);
    const totalVolumeElement = document.getElementById('totalVolume');
    if (totalVolumeElement) {
        totalVolumeElement.textContent = `${(totalVolume / 1000).toFixed(1)}K`;
    }
}

function createEventCard(event) {
    const card = document.createElement('div');
    card.className = 'betting-event-card mb-3';
    
    // Event Status für Sportwetten-Design
    let statusInfo = getEventStatus(event);
    
    // Sportwetten-Style Options mit verschiedenen Wettarten
    const bettingMarkets = createBettingMarkets(event);
    
    // Event Popularity/Volume
    const totalVolume = event.total_bets_amount || 0;
    const totalBets = event.bet_count || 0;
    
    card.innerHTML = `
        <div class="sportsbook-event">
            <!-- Event Header -->
            <div class="event-header-sport">
                <div class="event-info">
                    <div class="event-category">
                        ${getCategoryIcon(event.category)} ${escapeHtml(event.category?.toUpperCase() || 'RAP')}
                    </div>
                    <h4 class="event-title-sport">${escapeHtml(event.title)}</h4>
                    <div class="event-meta-sport">
                        <span class="event-time">
                            <i class="fas fa-clock me-1"></i>
                            ${event.formatted_event_date || 'TBD'}
                        </span>
                        <span class="event-volume">
                            <i class="fas fa-chart-bar me-1"></i>
                            ${totalVolume.toLocaleString()} Punkte
                        </span>
                        <span class="event-participants">
                            <i class="fas fa-users me-1"></i>
                            ${totalBets} Wetten
                        </span>
                    </div>
                </div>
                <div class="event-status-sport">
                    ${statusInfo.badge}
                    ${statusInfo.isLive ? '<div class="live-indicator"></div>' : ''}
                </div>
            </div>
            
            <!-- Betting Markets -->
            <div class="betting-markets">
                ${bettingMarkets}
            </div>
            
            <!-- Quick Bet Section -->
            <div class="quick-bet-section" style="display: none;" id="quick-bet-${event.id}">
                <div class="selected-bet-info">
                    <span class="selected-option" id="selected-option-${event.id}">-</span>
                    <span class="selected-odds" id="selected-odds-${event.id}">-</span>
                </div>
                <div class="bet-input-row">
                    <input type="number" 
                           class="bet-amount-input-sport" 
                           placeholder="Einsatz" 
                           min="${event.min_bet || 10}" 
                           max="${Math.min(event.max_bet || 1000, appState.userPoints)}" 
                           id="bet-amount-${event.id}">
                    <span class="potential-win" id="potential-win-${event.id}">
                        Gewinn: <strong>-</strong>
                    </span>
                    <button class="btn-place-bet" 
                            onclick="placeBet(${event.id})" 
                            ${!appState.currentUser ? 'disabled' : ''}
                            id="bet-btn-${event.id}">
                        Setzen
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Add interactive functionality
    const eventCard = card.querySelector('.event-card');
    const betAmountInput = card.querySelector(`#bet-amount-${event.id}`);
    const potentialWinDisplay = card.querySelector(`#potential-win-${event.id}`);
    
    // Bet option selection
    card.querySelectorAll('.bet-option').forEach(option => {
        option.addEventListener('click', function() {
            if (!appState.currentUser) {
                showAlert('🔐 Bitte logge dich ein, um zu setzen!', 'warning');
                return;
            }
            
            // Remove selection from siblings
            this.parentNode.querySelectorAll('.bet-option').forEach(opt => 
                opt.classList.remove('selected'));
            
            // Add selection to clicked option
            this.classList.add('selected');
            
            // Update potential winnings
            updatePotentialWinnings(event.id);
        });
    });
    
    // Update potential winnings on amount change
    if (betAmountInput) {
        betAmountInput.addEventListener('input', () => updatePotentialWinnings(event.id));
    }
    
    // Setup interactions for sportsbook design
    setupSportsbookInteractions(card, event);
    
    return card;
}

// Helper Functions für Sportwetten-Design
function getEventStatus(event) {
    const now = new Date().getTime();
    const startTime = new Date(event.start_date).getTime();
    const endTime = new Date(event.end_date).getTime();
    
    if (event.status === 'active' && startTime <= now && endTime > now) {
        return {
            badge: '<span class="live-badge">🔴 LIVE</span>',
            isLive: true,
            status: 'live'
        };
    } else if (event.status === 'active' && startTime > now) {
        const timeUntilStart = Math.floor((startTime - now) / (1000 * 60 * 60)); // hours
        return {
            badge: `<span class="upcoming-badge">⏰ in ${timeUntilStart}h</span>`,
            isLive: false,
            status: 'upcoming'
        };
    } else if (event.status === 'active') {
        return {
            badge: '<span class="active-badge">✅ AKTIV</span>',
            isLive: false,
            status: 'active'
        };
    } else {
        return {
            badge: '<span class="ended-badge">⏹ BEENDET</span>',
            isLive: false,
            status: 'ended'
        };
    }
}

function getCategoryIcon(category) {
    const icons = {
        'battle': '<i class="fas fa-fist-raised"></i>',
        'charts': '<i class="fas fa-chart-line"></i>',
        'streaming': '<i class="fas fa-play"></i>',
        'tour': '<i class="fas fa-microphone"></i>',
        'awards': '<i class="fas fa-trophy"></i>',
        'general': '<i class="fas fa-music"></i>'
    };
    return icons[category] || icons.general;
}

function createBettingMarkets(event) {
    if (!event.options || event.options.length === 0) {
        return '<div class="no-markets">Keine Wettmärkte verfügbar</div>';
    }
    
    // Gruppiere Options nach Wettart (falls vorhanden)
    const markets = groupBettingOptions(event.options);
    
    return Object.keys(markets).map(marketName => {
        const options = markets[marketName];
        const optionsHtml = options.map(option => 
            `<div class="betting-odd" 
                  data-event-id="${event.id}" 
                  data-option-id="${option.id}" 
                  data-odds="${option.odds}"
                  data-option-text="${escapeHtml(option.option_text)}">
                <div class="odd-label">${escapeHtml(option.option_text)}</div>
                <div class="odd-value">${option.odds}</div>
            </div>`
        ).join('');
        
        return `
            <div class="betting-market">
                <div class="market-header">
                    <span class="market-name">${marketName}</span>
                    <span class="market-count">${options.length} Optionen</span>
                </div>
                <div class="market-odds">
                    ${optionsHtml}
                </div>
            </div>
        `;
    }).join('');
}

function groupBettingOptions(options) {
    // Einfache Gruppierung - später kann das erweitert werden
    return {
        'Hauptwette': options
    };
}

function setupSportsbookInteractions(card, event) {
    // Betting odds click handlers
    card.querySelectorAll('.betting-odd').forEach(odd => {
        odd.addEventListener('click', function() {
            // Remove previous selections
            card.querySelectorAll('.betting-odd').forEach(o => o.classList.remove('selected'));
            
            // Add selection to clicked odd
            this.classList.add('selected');
            
            // Show quick bet section
            const quickBetSection = card.querySelector(`#quick-bet-${event.id}`);
            const selectedOption = card.querySelector(`#selected-option-${event.id}`);
            const selectedOdds = card.querySelector(`#selected-odds-${event.id}`);
            
            if (quickBetSection && selectedOption && selectedOdds) {
                quickBetSection.style.display = 'block';
                selectedOption.textContent = this.getAttribute('data-option-text');
                selectedOdds.textContent = this.getAttribute('data-odds') + 'x';
                
                // Update potential winnings
                updatePotentialWinnings(event.id);
            }
        });
    });
    
    // Bet amount input handler
    const betAmountInput = card.querySelector(`#bet-amount-${event.id}`);
    if (betAmountInput) {
        betAmountInput.addEventListener('input', () => updatePotentialWinnings(event.id));
    }
}

function updatePotentialWinnings(eventId) {
    const selectedOption = document.querySelector(`[data-event-id="${eventId}"].selected`);
    const amountInput = document.getElementById(`bet-amount-${eventId}`);
    const winDisplay = document.getElementById(`potential-win-${eventId}`);
    
    if (selectedOption && amountInput && winDisplay) {
        const amount = parseFloat(amountInput.value) || 0;
        const odds = parseFloat(selectedOption.getAttribute('data-odds')) || 1;
        const potentialWin = Math.round(amount * odds);
        
        if (amount > 0) {
            winDisplay.innerHTML = `Gewinn: <strong>+${potentialWin.toLocaleString()}</strong>`;
        } else {
            winDisplay.innerHTML = `Gewinn: <strong>-</strong>`;
        }
    }
}

async function placeBet(eventId) {
    if (!appState.currentUser) {
        showAlert('🔐 Bitte logge dich ein, um zu setzen!', 'warning');
        return;
    }
    
    const selectedOption = document.querySelector(`[data-event-id="${eventId}"].selected`);
    const betAmountInput = document.getElementById(`bet-amount-${eventId}`);
    const betButton = document.getElementById(`bet-btn-${eventId}`);
    
    if (!selectedOption) {
        showAlert('🎯 Bitte wähle eine Wett-Option!', 'warning');
        return;
    }
    
    const betAmount = parseInt(betAmountInput.value);
    if (!betAmount || betAmount < 10) {
        showAlert('💰 Mindestbetrag: 10 Punkte!', 'warning');
        betAmountInput.focus();
        return;
    }
    
    if (betAmount > appState.userPoints) {
        showAlert('❌ Nicht genügend Punkte verfügbar!', 'danger');
        return;
    }
    
    // Loading state
    const originalButtonText = betButton.innerHTML;
    betButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Platziere Wette...';
    betButton.disabled = true;
    
    try {
        const response = await apiRequest('events_v2.php', {
            method: 'POST',
            body: {
                action: 'place_bet',
                event_id: eventId,
                option_id: selectedOption.getAttribute('data-option-id'),
                amount: betAmount
            }
        });
        
        // Update user points
        if (response.new_points !== undefined) {
            appState.userPoints = response.new_points;
            updatePointsDisplay();
        }
        
        // Success animation
        betButton.innerHTML = '<i class="fas fa-check me-2"></i>Wette platziert!';
        betButton.className = 'btn btn-success w-100';
        
        // Show success message with details
        const odds = selectedOption.getAttribute('data-odds');
        const potentialWin = Math.round(betAmount * odds);
        showAlert(`🚀 Wette erfolgreich! ${betAmount} Punkte gesetzt. Möglicher Gewinn: ${potentialWin.toLocaleString()} Punkte`, 'success');
        
        // Reset form after delay
        setTimeout(() => {
            selectedOption.classList.remove('selected');
            betAmountInput.value = '';
            document.getElementById(`potential-win-${eventId}`).textContent = '-';
            
            // Update available amounts
            const allAmountInputs = document.querySelectorAll('.bet-amount-input');
            allAmountInputs.forEach(input => {
                input.max = Math.min(1000, appState.userPoints);
            });
            
            // Disable further betting on this event
            betButton.innerHTML = '<i class="fas fa-lock me-2"></i>Wette platziert';
            betButton.disabled = true;
            betButton.className = 'btn btn-secondary w-100';
            
            // Disable all bet options for this event
            document.querySelectorAll(`[data-event-id="${eventId}"]`).forEach(option => {
                option.style.opacity = '0.5';
                option.style.pointerEvents = 'none';
            });
            
        }, 2000);
        
    } catch (error) {
        showAlert(`❌ ${error.message}`, 'danger');
        
        // Reset button
        betButton.innerHTML = originalButtonText;
        betButton.disabled = false;
        betButton.className = 'btn bet-button w-100';
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
    const profileNav = document.querySelector('[data-section="profile"]');
    
    if (appState.currentUser && loginLink) {
        loginLink.innerHTML = `<i class="fas fa-user me-1"></i>${escapeHtml(appState.currentUser.username)}`;
        loginLink.setAttribute('href', '#');
        loginLink.removeAttribute('data-bs-toggle');
        loginLink.removeAttribute('data-bs-target');
        loginLink.onclick = (e) => {
            e.preventDefault();
            handleLogout();
        };
        
        // Zeige Profil-Navigation
        if (profileNav) {
            profileNav.style.display = 'block';
        }
    } else if (loginLink) {
        loginLink.innerHTML = '<i class="fas fa-user me-1"></i>Login';
        loginLink.setAttribute('data-bs-toggle', 'modal');
        loginLink.setAttribute('data-bs-target', '#loginModal');
        loginLink.onclick = null;
        
        // Verstecke Profil-Navigation
        if (profileNav) {
            profileNav.style.display = 'none';
        }
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

// Profile Functions
async function loadProfile() {
    if (!appState.currentUser) {
        showAlert('🔐 Bitte logge dich ein, um dein Profil zu sehen!', 'warning');
        navigateToSection('home');
        return;
    }
    
    updateProfileDisplay();
    loadUserBets();
    loadUserStats();
    setupBetFilters();
}

function updateProfileDisplay() {
    const user = appState.currentUser;
    if (!user) return;
    
    document.getElementById('profileUsername').textContent = user.username;
    document.getElementById('profilePoints').textContent = user.points.toLocaleString();
    
    // Mitglied seit
    if (user.created_at) {
        const memberSince = new Date(user.created_at).toLocaleDateString('de-DE', {
            year: 'numeric',
            month: 'long'
        });
        document.getElementById('profileMemberSince').textContent = `Mitglied seit ${memberSince}`;
    }
}

async function loadUserStats() {
    try {
        console.log('Loading user stats...');
        const response = await apiRequest('auth_v3.php', {
            method: 'POST',
            body: { action: 'get_user_stats' }
        });
        
        console.log('User stats response:', response);
        
        if (response.stats) {
            const stats = response.stats;
            console.log('Stats data:', stats);
            
            document.getElementById('profileTotalBets').textContent = stats.total_bets || 0;
            document.getElementById('profileWins').textContent = stats.wins || 0;
            document.getElementById('profileLosses').textContent = stats.losses || 0;
            document.getElementById('profilePending').textContent = stats.pending || 0;
            document.getElementById('profileWinRate').textContent = `${stats.win_rate || 0}%`;
            document.getElementById('profileRank').textContent = stats.rank || '-';
        }
    } catch (error) {
        console.error('Failed to load user stats:', error);
    }
}

async function loadUserBets() {
    const container = document.getElementById('myBetsContainer');
    if (!container) return;
    
    try {
        console.log('Loading user bets...');
        const response = await apiRequest('events_v2.php', {
            method: 'POST',
            body: { action: 'get_user_bets' }
        });
        
        console.log('User bets response:', response);
        appState.userBets = response.bets || [];
        displayUserBets();
        
    } catch (error) {
        console.error('Error loading user bets:', error);
        container.innerHTML = `
            <div class="text-center text-danger">
                <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                <p>Fehler beim Laden deiner Wetten: ${error.message}</p>
                <small>Schaue in die Entwicklertools für weitere Details.</small>
            </div>
        `;
    }
}

function displayUserBets() {
    const container = document.getElementById('myBetsContainer');
    if (!container) return;
    
    let filteredBets = appState.userBets;
    
    // Filter anwenden
    if (appState.currentBetFilter !== 'all') {
        filteredBets = appState.userBets.filter(bet => {
            const status = bet.status || bet.bet_status || 'active';
            switch (appState.currentBetFilter) {
                case 'pending': return status === 'pending' || status === 'active';
                case 'won': return status === 'won' || status === 'winning';
                case 'lost': return status === 'lost' || status === 'losing';
                default: return true;
            }
        });
    }
    
    if (filteredBets.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted">
                <i class="fas fa-ticket-alt fa-3x mb-3"></i>
                <p>Keine Wetten gefunden</p>
                <small>Gehe zu den Events und platziere deine erste Wette!</small>
            </div>
        `;
        return;
    }
    
    container.innerHTML = filteredBets.map(bet => createBetCard(bet)).join('');
}

function createBetCard(bet) {
    let statusClass = '';
    let statusIcon = '';
    let statusText = '';
    
    // Behandle verschiedene mögliche Status-Werte
    const status = bet.status || bet.bet_status || 'active';
    
    switch (status) {
        case 'active':
        case 'pending':
            statusClass = 'warning';
            statusIcon = 'clock';
            statusText = 'Offen';
            break;
        case 'won':
        case 'winning':
            statusClass = 'success';
            statusIcon = 'check-circle';
            statusText = 'Gewonnen';
            break;
        case 'lost':
        case 'losing':
            statusClass = 'danger';
            statusIcon = 'times-circle';
            statusText = 'Verloren';
            break;
        default:
            statusClass = 'secondary';
            statusIcon = 'question';
            statusText = status;
    }
    
    const formattedDate = bet.formatted_date || new Date(bet.placed_at || bet.created_at).toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    const potentialWin = Math.round(bet.amount * bet.odds);
    const actualWin = (status === 'won' || status === 'winning') ? potentialWin : 0;
    
    return `
        <div class="bet-card mb-3">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="bet-event-title">${escapeHtml(bet.event_title)}</div>
                <span class="badge bg-${statusClass}">
                    <i class="fas fa-${statusIcon} me-1"></i>${statusText}
                </span>
            </div>
            
            <div class="bet-details">
                <div class="row">
                    <div class="col-md-8">
                        <div class="bet-option-chosen mb-2">
                            <strong>Deine Wahl:</strong> ${escapeHtml(bet.option_text)}
                        </div>
                        <div class="bet-meta">
                            <span class="me-3">
                                <i class="fas fa-coins text-warning me-1"></i>
                                Einsatz: <strong>${bet.amount.toLocaleString()} Punkte</strong>
                            </span>
                            <span class="me-3">
                                <i class="fas fa-chart-line text-info me-1"></i>
                                Quote: <strong>${bet.odds}x</strong>
                            </span>
                            <span>
                                <i class="fas fa-calendar text-muted me-1"></i>
                                ${formattedDate}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="bet-payout">
                            ${(status === 'active' || status === 'pending') ? 
                                `<div class="text-muted">Möglicher Gewinn</div>
                                 <div class="text-warning fs-5 fw-bold">+${potentialWin.toLocaleString()}</div>` :
                                (status === 'won' || status === 'winning') ?
                                `<div class="text-success">Gewinn erhalten</div>
                                 <div class="text-success fs-5 fw-bold">+${actualWin.toLocaleString()}</div>` :
                                `<div class="text-danger">Verlust</div>
                                 <div class="text-danger fs-5 fw-bold">-${bet.amount.toLocaleString()}</div>`
                            }
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function setupBetFilters() {
    const filterButtons = document.querySelectorAll('input[name="betFilter"]');
    filterButtons.forEach(button => {
        button.addEventListener('change', function() {
            if (this.checked) {
                appState.currentBetFilter = this.id.replace('filter', '').toLowerCase();
                displayUserBets();
            }
        });
    });
}


// Global functions for HTML onclick attributes
window.placeBet = placeBet;
window.handleLogin = handleLogin;
window.handleRegister = handleRegister;
window.login = handleLogin; // Backward compatibility
window.register = handleRegister; // Backward compatibility
window.toggleAuthMode = toggleAuthMode;
window.handleAuth = handleAuth;
window.loadProfile = loadProfile;