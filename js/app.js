// RapMarket.de - Main JavaScript Application

// App State
const appState = {
    currentUser: null,
    userPoints: 0,
    currentSection: 'home',
    events: [],
    leaderboard: [],
    userBets: []
};

// Sample Data
const sampleEvents = [
    {
        id: 1,
        title: "Capital Bra vs. Apache 207 - Streaming Battle",
        date: "2024-08-15",
        description: "Wer erreicht mehr Streams in der ersten Woche?",
        status: "active",
        options: [
            { id: 1, name: "Capital Bra", odds: 1.8 },
            { id: 2, name: "Apache 207", odds: 2.1 }
        ]
    },
    {
        id: 2,
        title: "18 Karat Album Release",
        date: "2024-08-20",
        description: "Wird das neue Album Platz 1 der Charts erreichen?",
        status: "active",
        options: [
            { id: 1, name: "Ja, Platz 1", odds: 1.5 },
            { id: 2, name: "Nein, nicht Platz 1", odds: 2.5 }
        ]
    },
    {
        id: 3,
        title: "Bonez MC Tour 2024",
        date: "2024-09-01",
        description: "Wie viele ausverkaufte Shows wird die Tour haben?",
        status: "active",
        options: [
            { id: 1, name: "Unter 10", odds: 3.0 },
            { id: 2, name: "10-20", odds: 1.8 },
            { id: 3, name: "Über 20", odds: 2.2 }
        ]
    }
];

const sampleLeaderboard = [
    { rank: 1, username: "RapKing2024", points: 15420, wins: 87 },
    { rank: 2, username: "HipHopFan", points: 12350, wins: 76 },
    { rank: 3, username: "BeatsLover", points: 11280, wins: 69 },
    { rank: 4, username: "GermanRapFan", points: 9870, wins: 58 },
    { rank: 5, username: "MusicExpert", points: 8450, wins: 52 }
];

// Initialize App
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    loadInitialData();
});

function initializeApp() {
    // Load user data from localStorage
    const savedUser = localStorage.getItem('rapmarket_user');
    if (savedUser) {
        appState.currentUser = JSON.parse(savedUser);
        appState.userPoints = appState.currentUser.points || 1000; // Startpunkte
        updateUserDisplay();
    }
    
    // Set initial points if new user
    if (!appState.currentUser) {
        appState.userPoints = 1000;
        updatePointsDisplay();
    }
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
            login();
        });
    }
}

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
    document.querySelector(`[data-section="${sectionName}"]`).classList.add('active');
    
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

function loadInitialData() {
    appState.events = sampleEvents;
    appState.leaderboard = sampleLeaderboard;
}

function loadEvents() {
    const container = document.getElementById('eventsContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    appState.events.forEach(event => {
        const eventCard = createEventCard(event);
        container.appendChild(eventCard);
    });
}

function createEventCard(event) {
    const card = document.createElement('div');
    card.className = 'col-md-6 col-lg-4 mb-4';
    
    const optionsHtml = event.options.map(option => 
        `<div class="bet-option" data-event-id="${event.id}" data-option-id="${option.id}">
            ${option.name} (${option.odds}x)
         </div>`
    ).join('');
    
    card.innerHTML = `
        <div class="event-card">
            <div class="event-title">${event.title}</div>
            <div class="event-date">
                <i class="fas fa-calendar-alt me-2"></i>
                ${formatDate(event.date)}
            </div>
            <div class="event-description">${event.description}</div>
            <div class="bet-options">
                ${optionsHtml}
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <input type="number" class="form-control me-2" placeholder="Punkte" min="10" max="${appState.userPoints}" id="bet-amount-${event.id}" style="width: 100px;">
                <button class="btn btn-primary" onclick="placeBet(${event.id})">
                    <i class="fas fa-coins me-1"></i>Setzen
                </button>
            </div>
        </div>
    `;
    
    // Add click listeners to bet options
    card.querySelectorAll('.bet-option').forEach(option => {
        option.addEventListener('click', function() {
            // Remove selection from siblings
            this.parentNode.querySelectorAll('.bet-option').forEach(opt => 
                opt.classList.remove('selected'));
            // Add selection to clicked option
            this.classList.add('selected');
        });
    });
    
    return card;
}

function loadLeaderboard() {
    const tableBody = document.getElementById('leaderboardTable');
    if (!tableBody) return;
    
    tableBody.innerHTML = '';
    
    appState.leaderboard.forEach(user => {
        const row = document.createElement('tr');
        
        let rankIcon = '';
        if (user.rank === 1) rankIcon = '<i class="fas fa-crown text-warning me-2"></i>';
        else if (user.rank === 2) rankIcon = '<i class="fas fa-medal text-secondary me-2"></i>';
        else if (user.rank === 3) rankIcon = '<i class="fas fa-medal text-warning me-2"></i>';
        
        row.innerHTML = `
            <td>${rankIcon}${user.rank}</td>
            <td>${user.username}</td>
            <td><span class="points-display">${user.points.toLocaleString()}</span></td>
            <td>${user.wins}</td>
        `;
        
        tableBody.appendChild(row);
    });
}

function loadCommunity() {
    const discussionsContainer = document.getElementById('discussionsContainer');
    const onlineUsers = document.getElementById('onlineUsers');
    
    if (discussionsContainer) {
        discussionsContainer.innerHTML = `
            <div class="mb-3">
                <div class="d-flex align-items-center mb-2">
                    <strong>RapKing2024</strong>
                    <span class="badge bg-primary ms-2">Top User</span>
                    <small class="text-muted ms-auto">vor 5 Min</small>
                </div>
                <p>Was denkt ihr über das neue Capital Bra Album? Wird es wieder Platz 1?</p>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-thumbs-up me-1"></i>12
                    </button>
                    <button class="btn btn-sm btn-outline-secondary">Antworten</button>
                </div>
            </div>
            <hr>
            <div class="mb-3">
                <div class="d-flex align-items-center mb-2">
                    <strong>HipHopFan</strong>
                    <small class="text-muted ms-auto">vor 12 Min</small>
                </div>
                <p>Apache 207 Tour Tickets sind ausverkauft! Wer hat welche ergattert?</p>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-thumbs-up me-1"></i>8
                    </button>
                    <button class="btn btn-sm btn-outline-secondary">Antworten</button>
                </div>
            </div>
        `;
    }
    
    if (onlineUsers) {
        onlineUsers.innerHTML = `
            <div class="d-flex align-items-center mb-2">
                <div class="bg-success rounded-circle me-2" style="width: 8px; height: 8px;"></div>
                <span>RapKing2024</span>
            </div>
            <div class="d-flex align-items-center mb-2">
                <div class="bg-success rounded-circle me-2" style="width: 8px; height: 8px;"></div>
                <span>HipHopFan</span>
            </div>
            <div class="d-flex align-items-center mb-2">
                <div class="bg-warning rounded-circle me-2" style="width: 8px; height: 8px;"></div>
                <span>BeatsLover</span>
            </div>
            <div class="d-flex align-items-center mb-2">
                <div class="bg-success rounded-circle me-2" style="width: 8px; height: 8px;"></div>
                <span>GermanRapFan</span>
            </div>
        `;
    }
}

function placeBet(eventId) {
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
    
    // Place bet
    const optionId = selectedOption.getAttribute('data-option-id');
    const event = appState.events.find(e => e.id == eventId);
    const option = event.options.find(o => o.id == optionId);
    
    const bet = {
        eventId: eventId,
        optionId: optionId,
        amount: betAmount,
        odds: option.odds,
        eventTitle: event.title,
        optionName: option.name,
        timestamp: new Date()
    };
    
    appState.userBets.push(bet);
    appState.userPoints -= betAmount;
    
    updatePointsDisplay();
    saveUserData();
    
    showAlert(`Wette platziert: ${betAmount} Punkte auf "${option.name}"!`, 'success');
    
    // Reset form
    selectedOption.classList.remove('selected');
    betAmountInput.value = '';
}

function login() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    if (!username || !password) {
        showAlert('Bitte fülle alle Felder aus!', 'danger');
        return;
    }
    
    // Simple login simulation
    appState.currentUser = {
        username: username,
        points: appState.userPoints,
        joinDate: new Date()
    };
    
    updateUserDisplay();
    saveUserData();
    
    // Close modal
    const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
    loginModal.hide();
    
    showAlert(`Willkommen, ${username}!`, 'success');
}

function register() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    if (!username || !password) {
        showAlert('Bitte fülle alle Felder aus!', 'danger');
        return;
    }
    
    // Simple registration simulation
    appState.currentUser = {
        username: username,
        points: 1000, // Startpunkte
        joinDate: new Date()
    };
    
    appState.userPoints = 1000;
    
    updateUserDisplay();
    saveUserData();
    
    // Close modal
    const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
    loginModal.hide();
    
    showAlert(`Account erstellt! Willkommen, ${username}!`, 'success');
}

function updateUserDisplay() {
    const loginLink = document.querySelector('[data-bs-target="#loginModal"]');
    if (appState.currentUser && loginLink) {
        loginLink.innerHTML = `<i class="fas fa-user me-1"></i>${appState.currentUser.username}`;
        loginLink.setAttribute('href', '#');
        loginLink.removeAttribute('data-bs-toggle');
        loginLink.removeAttribute('data-bs-target');
        loginLink.onclick = logout;
    }
    updatePointsDisplay();
}

function updatePointsDisplay() {
    const pointsElement = document.getElementById('userPoints');
    if (pointsElement) {
        pointsElement.textContent = appState.userPoints.toLocaleString();
    }
}

function logout() {
    appState.currentUser = null;
    localStorage.removeItem('rapmarket_user');
    
    const loginLink = document.querySelector('.navbar-nav .nav-link[href="#"]');
    if (loginLink && loginLink.textContent.includes('fa-user')) {
        loginLink.innerHTML = '<i class="fas fa-user me-1"></i>Login';
        loginLink.setAttribute('data-bs-toggle', 'modal');
        loginLink.setAttribute('data-bs-target', '#loginModal');
        loginLink.onclick = null;
    }
    
    showAlert('Erfolgreich abgemeldet!', 'success');
}

function saveUserData() {
    if (appState.currentUser) {
        appState.currentUser.points = appState.userPoints;
        appState.currentUser.bets = appState.userBets;
        localStorage.setItem('rapmarket_user', JSON.stringify(appState.currentUser));
    }
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
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

function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        weekday: 'long'
    };
    return date.toLocaleDateString('de-DE', options);
}

// Add some fun animations
function addBeatAnimation() {
    const logo = document.getElementById('logo');
    if (logo) {
        logo.classList.add('beat-animation');
    }
}

// Initialize beat animation
setTimeout(addBeatAnimation, 1000);