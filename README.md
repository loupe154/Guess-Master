# GuessMaster

GuessMaster ist ein PHP/MySQL-Webprojekt fuer visuelle Ratespiele im Browser. Die App bietet Login, Profil mit Avatar, Avatar-Shop, Singleplayer-Rankings und Online-PvP mit Raumcode.

Die Anwendung startet ueber:

```text
frontend/index.html
```

## Funktionen

- Registrierung und Login mit PHP-Sessions
- Singleplayer-Modi:
  - Guess the Game mit RAWG-Daten
  - Guess the Movie mit TMDB-Daten
  - Guess the Location mit Unsplash-Bildern und lokalen Fallbacks
- Online-PvP mit Raumcode, Ready-System, Chat, Timer und Rundenwertung
- PvP- und Singleplayer-Leaderboards
- Profilseite mit In-Game-Name und Avatar
- Avatar-Shop mit Coins und freischaltbaren Items
- Automatische Anlage mehrerer Zusatz-Tabellen durch die Backend-Routen

## Voraussetzungen

- XAMPP oder ein vergleichbarer lokaler PHP/MySQL-Stack
- PHP 8.2 oder neuer empfohlen
- MariaDB/MySQL
- Internetverbindung fuer RAWG, TMDB und Unsplash
- API-Keys fuer RAWG, TMDB und Unsplash

## Projektstruktur

```text
frontend/
  index.html              Hauptseite der App
  css/style.css           Styling
  js/app.js               Frontend-Logik fuer Navigation, Games, PvP, Profil und Shop
  assets/                 Logo und Rank-Icons

backend/
  api/                    Login, Registrierung, Session, API-Router und DB-Verbindung
  config/api_keys.php     API-Key-Konfiguration
  routes/                 Backend-Routen fuer Games, Movies, Locations, PvP, Profil, Shop und Rankings
  data/                   SQL-Dateien und lokale Fallback-/Hint-Daten
  utils/                  Auth- und Response-Helfer
```

## Installation

1. Projekt in den XAMPP-Webroot legen, zum Beispiel:

```text
C:\xampp\htdocs\dashboard\Webap\FreMi185-Heith335-Loupe154_AufagbeProjekt
```

2. Apache und MySQL im XAMPP Control Panel starten.

3. Datenbank `guessgames` in phpMyAdmin oder per MySQL erstellen:

```sql
CREATE DATABASE guessgames CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE guessgames;
```

4. Basistabelle `users` anlegen:

```sql
CREATE TABLE users (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  email varchar(255) NOT NULL,
  password_hash varchar(255) NOT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

5. PvP-Tabellen importieren:

```sql
SOURCE backend/data/pvp_tables.sql;
```

Falls die PvP-Tabellen schon aus einer aelteren Version existieren, danach optional die Migration ausfuehren:

```sql
SOURCE backend/data/pvp_migration_ready_chat_ranking.sql;
```

6. Datenbankzugang in `backend/api/db.php` pruefen:

```php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'guessgames';
const DB_USER = 'root';
const DB_PASS = '';
```

7. API-Keys in `backend/config/api_keys.php` eintragen:

```php
define("TMDB_API_KEY", "dein_tmdb_key");
define("RAWG_API_KEY", "dein_rawg_key");
define("UNSPLASH_ACCESS_KEY", "dein_unsplash_key");
```

8. App im Browser oeffnen:

```text
http://localhost/dashboard/Webap/FreMi185_ GitHub nutzen_Git-Workflow_im_Team/FreMi185-Heith335-Loupe154_AufagbeProjekt/frontend/index.html
```

Der genaue Link haengt davon ab, wo der Projektordner unter `htdocs` liegt.

## Datenbank

Die einzige Tabelle, die vor der ersten Registrierung sicher vorhanden sein muss, ist `users`.

Mehrere Backend-Routen erweitern die Datenbank automatisch, wenn Spalten oder Tabellen fehlen. Das betrifft unter anderem Avatar-Felder, Shop-Daten, Singleplayer-Rankings und PvP-Daten.

Wichtige Tabellen:

- `users`
- `pvp_matches`
- `pvp_ready`
- `pvp_rounds`
- `pvp_answers`
- `pvp_chat_messages`
- `pvp_rankings`
- `single_rankings`
- `shop_items`
- `user_shop_items`
- `user_wallets`
- `monthly_rewards`
- `monthly_seasons`

## Nutzung

1. Account registrieren oder einloggen.
2. Ueber die Navigation einen Modus waehlen.
3. Im Singleplayer Runden und Schwierigkeit auswaehlen und Punkte speichern.
4. Fuer Online-PvP einen Raum erstellen und den Raumcode an einen zweiten eingeloggten Spieler weitergeben.
5. Im zweiten Browser mit einem anderen Account einloggen und dem Raum beitreten.
6. Beide Spieler klicken `Ready`, danach startet das Match.

Wichtig: PvP braucht zwei verschiedene Accounts. Zwei Browserfenster mit demselben Account zaehlen nicht als zwei Spieler.

## API-Routen

Geschuetzte App-Routen laufen ueber:

```text
backend/api/index.php?route=...
```

Aktuelle Routen:

- `random-game`
- `random-movie`
- `random-location`
- `profile`
- `profile-save`
- `shop`
- `shop-buy`
- `pvp-create`
- `pvp-join`
- `pvp-ready`
- `pvp-state`
- `pvp-suggest`
- `pvp-submit`
- `pvp-chat-list`
- `pvp-chat-send`
- `pvp-leaderboard`
- `pvp-leave`
- `pvp-post-match`
- `single-ranking`
- `single-submit`
- `single-leaderboard`

Login, Registrierung, Logout und Session-Check liegen separat unter:

- `backend/api/login.php`
- `backend/api/register.php`
- `backend/api/logout.php`
- `backend/api/session.php`

## PvP-Hinweise

- Ein Match beginnt mit `waiting`, wechselt nach Join zu `ready`, dann zu `starting` und danach zu `playing`.
- Runden werden serverseitig erstellt.
- Paralleles Polling wird abgefangen, damit Runden nicht doppelt erstellt werden.
- Wenn externe APIs nicht antworten, nutzt PvP Fallback-Daten.
- Nach dem Match werden Ranking-Aenderungen und Rank-Animationen angezeigt.

## Troubleshooting

### Registrierung meldet Datenbankfehler

- Pruefen, ob MySQL laeuft.
- Pruefen, ob die Datenbank `guessgames` existiert.
- Pruefen, ob die Tabelle `users` angelegt wurde.
- Zugangsdaten in `backend/api/db.php` kontrollieren.

### Nach jedem Seitenwechsel erscheint Login

- App ueber `localhost/.../frontend/index.html` oeffnen, nicht direkt als Datei.
- Browser-Cookies pruefen.
- Private Fenster vermeiden, wenn Cookies blockiert werden.
- `backend/api/session.php` im Browser testen.

### PvP-Spieler kommt nicht in die Lobby

- Beide Spieler muessen eingeloggt sein.
- Beide Spieler muessen unterschiedliche Accounts nutzen.
- Raumcode genau kopieren.
- Pruefen, ob die PvP-Tabellen importiert wurden.

### API-Bilder laden nicht

- API-Keys in `backend/config/api_keys.php` pruefen.
- Internetverbindung pruefen.
- Rate-Limits von RAWG, TMDB und Unsplash beachten.
- Locations und PvP haben Fallbacks, einzelne Singleplayer-API-Abfragen koennen trotzdem Fehler melden.

## Sicherheit

`backend/config/api_keys.php` enthaelt geheime API-Keys. Diese Datei sollte nicht oeffentlich mit echten Keys veroeffentlicht werden. Fuer ein echtes Deployment waeren Umgebungsvariablen besser als feste Werte im Code.

## Status

Aktueller Projektstand:

- Login und Registrierung sind angebunden.
- Session-Check und interne Navigation laufen ueber `frontend/index.html`.
- Singleplayer-Modi fuer Game, Movie und Location sind vorhanden.
- Online-PvP mit Lobby, Ready-System, Chat, Timer und Ranking ist vorhanden.
- Avatar-Profil und Shop sind vorhanden.
