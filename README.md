# 🎤 RapMarket.de

Eine Community-basierte Wettplattform für deutsche Rap-Events. Setze Punkte auf deine Lieblingskünstler und sammle Erfolge in der Hip-Hop Community!

## ✨ Features

- 🎯 **Punktebasiertes Wettsystem** - Keine Echtgeld-Transaktionen
- 🎵 **Deutsche Rap-Events** - Streaming-Battles, Chart-Platzierungen, Tours
- 🏆 **Leaderboard & Rankings** - Vergleiche dich mit anderen Usern
- 👥 **Community-Features** - Diskussionen und Interaktion
- 🔐 **Sichere Authentifizierung** - Session-basiertes Login-System
- 📱 **Responsive Design** - Optimiert für alle Geräte

## 🚀 Installation

### Automatische Installation (Empfohlen)

1. **Dateien hochladen**
   ```bash
   git clone https://github.com/fabiantaboo/rapmarket.git
   cd rapmarket
   ```

2. **Setup-Wizard aufrufen**
   ```
   http://deine-domain.de/setup.php
   ```

3. **Setup durchlaufen**
   - Datenbank-Konfiguration eingeben
   - Admin-Account erstellen
   - Fertig! 🎉

### Manuelle Installation

1. **Konfiguration erstellen**
   ```bash
   cp config.example.php config.php
   ```

2. **config.php bearbeiten**
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'rapmarket');
   define('DB_USER', 'username');
   define('DB_PASS', 'password');
   ```

3. **Datenbank importieren**
   ```bash
   mysql -u username -p rapmarket < database.sql
   ```

## 🛠️ Technische Details

### Systemanforderungen

- **PHP:** 7.4 oder höher
- **MySQL:** 5.7+ oder MariaDB 10.2+
- **Webserver:** Apache/Nginx
- **Extensions:** PDO, PDO_MySQL, JSON

### Projektstruktur

```
rapmarket/
├── api/                    # REST API Endpoints
│   ├── auth.php           # Authentifizierung
│   ├── events.php         # Events & Wetten
│   └── leaderboard.php    # Rangliste
├── includes/              # PHP Core Files
│   ├── database.php       # Datenbankklasse
│   ├── auth.php          # Auth-System
│   └── functions.php     # Hilfsfunktionen
├── css/                   # Stylesheets
├── js/                    # JavaScript
├── setup/                 # Installation (nach Setup löschen)
├── config.example.php     # Konfigurationsvorlage
├── database.sql          # SQL Schema
└── index.html            # Hauptseite
```

### Sicherheitsfeatures

- ✅ **SQL Injection Protection** - Prepared Statements
- ✅ **XSS Protection** - Input Sanitization
- ✅ **CSRF Protection** - Token-basiert
- ✅ **Rate Limiting** - API-Schutz
- ✅ **Password Hashing** - BCrypt
- ✅ **Session Security** - Sichere Session-Verwaltung

## 🎮 Nutzung

### Für User
1. **Registrierung** - Kostenlos mit 1.000 Startpunkten
2. **Events durchstöbern** - Aktuelle Rap-Events entdecken
3. **Wetten platzieren** - Punkte auf Favoriten setzen
4. **Punkte sammeln** - Bei richtigen Tipps gewinnen
5. **Rangliste steigen** - Community-Ranking verbessern

### Für Admins
- Events erstellen und verwalten
- User-Verwaltung (in Entwicklung)
- Statistiken einsehen (in Entwicklung)
- System-Einstellungen (in Entwicklung)

## 🔧 Konfiguration

### Wichtige Einstellungen

```php
// Punktesystem
define('STARTING_POINTS', 1000);      // Startpunkte für neue User
define('MIN_BET_AMOUNT', 10);         // Mindest-Wetteinsatz
define('MAX_BET_AMOUNT', 1000);       // Maximal-Wetteinsatz

// Sicherheit
define('API_RATE_LIMIT', 100);        // API-Requests pro Stunde
define('MAX_LOGIN_ATTEMPTS', 5);      // Max. Login-Versuche

// Debug (nur Development)
define('ENABLE_DEBUG', false);
define('LOG_LEVEL', 'ERROR');
```

### Umgebungsvariablen

- `APP_ENV` - production/development
- `DB_*` - Datenbank-Konfiguration
- `JWT_SECRET` - JWT Verschlüsselung (wird automatisch generiert)

## 📊 API Dokumentation

### Authentifizierung

```javascript
// Login
POST /api/auth.php
{
  "action": "login",
  "username": "user123",
  "password": "password"
}

// Registrierung
POST /api/auth.php
{
  "action": "register",
  "username": "newuser",
  "email": "user@example.com",
  "password": "password"
}
```

### Events & Wetten

```javascript
// Events laden
GET /api/events.php?status=active

// Wette platzieren
POST /api/events.php
{
  "action": "place_bet",
  "event_id": 1,
  "option_id": 2,
  "amount": 50
}
```

### Rangliste

```javascript
// Punkte-Rangliste
GET /api/leaderboard.php?type=points&limit=50

// Gewinn-Rangliste
GET /api/leaderboard.php?type=winnings&limit=20
```

## 🛡️ Sicherheit

### Produktions-Deployment

1. **Setup-Verzeichnis löschen**
   ```bash
   rm -rf setup/
   rm setup.php
   ```

2. **Dateiberechtigungen setzen**
   ```bash
   chmod 644 *.php
   chmod 600 config.php
   chmod 755 logs/
   ```

3. **HTTPS aktivieren**
   - SSL-Zertifikat installieren
   - HTTP zu HTTPS weiterleiten

4. **Backup-Strategie**
   ```bash
   # Datenbank-Backup
   mysqldump -u user -p rapmarket > backup.sql
   
   # Dateien-Backup
   tar -czf rapmarket-backup.tar.gz .
   ```

## 🐛 Troubleshooting

### Häufige Probleme

**Database connection failed**
- Prüfe Datenbank-Credentials in `config.php`
- Stelle sicher, dass MySQL läuft
- Prüfe Firewall-Einstellungen

**Permission denied**
- Setze korrekte Dateiberechtigungen
- Prüfe Webserver-User (www-data, apache)

**API returns 500 error**
- Prüfe PHP Error Log
- Aktiviere Debug-Mode in Development
- Prüfe `logs/app.log`

### Debug-Mode aktivieren

```php
// In config.php für Development
define('APP_ENV', 'development');
define('ENABLE_DEBUG', true);
define('LOG_LEVEL', 'DEBUG');
```

## 🤝 Contributing

1. Fork das Repository
2. Feature Branch erstellen (`git checkout -b feature/amazing-feature`)
3. Changes committen (`git commit -m 'Add amazing feature'`)
4. Branch pushen (`git push origin feature/amazing-feature`)
5. Pull Request erstellen

## 📝 Changelog

### v1.0.0 (2024-01-XX)
- ✨ Vollständiges PHP Backend
- 🎨 Professionelles Design
- 🔐 Sicherheitssystem implementiert
- 🚀 Automatische Installation
- 📊 API-System komplett

## 📄 Lizenz

Dieses Projekt ist unter der MIT-Lizenz veröffentlicht. Siehe `LICENSE` für Details.

## 🙋‍♂️ Support

Bei Fragen oder Problemen:
- 📧 E-Mail: support@rapmarket.de
- 🐛 Issues: [GitHub Issues](https://github.com/fabiantaboo/rapmarket/issues)
- 💬 Discord: [RapMarket Community](https://discord.gg/rapmarket)

---

**Made with ❤️ for the German Hip-Hop Community**

🤖 *Generated with [Claude Code](https://claude.ai/code)*