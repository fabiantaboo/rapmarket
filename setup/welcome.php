<?php
// Welcome Step
?>
<div class="text-center">
    <i class="fas fa-magic fa-4x text-primary mb-4"></i>
    <h3>Willkommen zum RapMarket.de Setup!</h3>
    <p class="lead">Dieses Setup-Tool hilft dir dabei, RapMarket.de zu installieren und zu konfigurieren.</p>
</div>

<div class="row mt-4">
    <div class="col-md-4 text-center mb-3">
        <i class="fas fa-database fa-2x text-info mb-2"></i>
        <h6>Datenbank</h6>
        <small>Automatische Erstellung und Konfiguration der MySQL-Datenbank</small>
    </div>
    <div class="col-md-4 text-center mb-3">
        <i class="fas fa-user-shield fa-2x text-success mb-2"></i>
        <h6>Admin-Account</h6>
        <small>Erstelle deinen Administrator-Account für die Verwaltung</small>
    </div>
    <div class="col-md-4 text-center mb-3">
        <i class="fas fa-rocket fa-2x text-warning mb-2"></i>
        <h6>Start</h6>
        <small>Nach dem Setup ist RapMarket.de sofort einsatzbereit</small>
    </div>
</div>

<div class="alert alert-info mt-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Voraussetzungen:</strong><br>
    • PHP 7.4 oder höher<br>
    • MySQL 5.7 oder höher (oder MariaDB 10.2+)<br>
    • Apache/Nginx Webserver<br>
    • Schreibrechte im Projektverzeichnis
</div>

<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Wichtig:</strong> Stelle sicher, dass du die MySQL-Zugangsdaten bereit hast!
</div>

<div class="text-center mt-4">
    <a href="?step=database" class="btn btn-primary btn-lg">
        <i class="fas fa-arrow-right me-2"></i>Setup starten
    </a>
</div>