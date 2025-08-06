# InnoDIGI License Server

PHP-gebaseerde licentie server voor InnoDIGI applicaties met bestandsopslag.

## Installatie

1. **Configuratie:**
   - Bewerk `config.php`:
   - Stel een veilige `ADMIN_KEY` in
   - Wijzig `ENCRYPTION_SECRET` naar een unieke waarde
   - Configureer SMTP instellingen voor email notificaties
   - Upload bestanden naar je webserver

2. **Beveiliging:**
   - Zorg dat de `/data` map niet direct toegankelijk is via web
   - Gebruik HTTPS voor productie
   - Houd `ADMIN_KEY` en `ENCRYPTION_SECRET` geheim

## SMTP Configuratie

Voor betrouwbare email delivery kun je SMTP configureren in `config.php`:

```php
// SMTP instellingen
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURITY', 'tls');
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

**Ondersteunde providers:**
- **Gmail:** smtp.gmail.com:587 (TLS) - Gebruik app-specific password
- **Outlook:** smtp-mail.outlook.com:587 (TLS)
- **Yahoo:** smtp.mail.yahoo.com:587 (TLS)
- **Custom SMTP:** Configureer je eigen mailserver

**Fallback:** Als SMTP faalt, wordt automatisch PHP mail() gebruikt.

## Gebruik

### Admin Interface
Bezoek `admin.html` om licenties te beheren:
- Licenties aanmaken
- Status wijzigen (actief/inactief)
- Vervaldatums instellen
- Licenties verwijderen

### API Endpoints

**Licentie Verificatie:**
```bash
curl -X POST https://www.innodigi.nl/api/license.php \
  -H "Content-Type: application/json" \
  -d '{"action":"verify","license_key":"ID-XXXXXXXX-XXXXXXXX"}'
```

**Admin Acties:**
```bash
curl -X POST https://www.innodigi.nl/api/license.php \
  -H "Content-Type: application/json" \
  -d '{"action":"admin","sub_action":"list","admin_key":"your-admin-key"}'
```

## Bestandsopslag
- Licenties worden versleuteld opgeslagen in `/data` map
- Bestandsnamen zijn gehashed voor extra beveiliging
- `.htaccess` voorkomt directe toegang tot data bestanden
- AES-256-CBC encryptie voor alle licentiegegevens
- Geen database vereist

## Licentie Types
- **trial**: 30 dagen proefperiode
- **standard**: Standaard licentie
- **premium**: Premium functies
- **enterprise**: Enterprise licentie

## Beveiliging
- Gebruik HTTPS voor productie
- Wijzig `ENCRYPTION_SECRET` naar een unieke waarde
- Houd de `ADMIN_KEY` geheim
- Data map is beveiligd tegen directe toegang