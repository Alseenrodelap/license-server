# InnoDIGI License Server

PHP-gebaseerde licentie server voor InnoDIGI applicaties met bestandsopslag.

## API Endpoints

### Licentie Verificatie

Verifieer een licentie door een POST-verzoek naar de server te sturen.

**URL:** `https://license-server.innodigi.nl/index.php`

**Methode:** `POST`

**Headers:**
- `Content-Type: application/json`

**Request Body:**
```json
{
  "action": "verify",
  "license_key": "ID-XXXXXXXX-XXXXXXXX"
}
```

**Response (Geldige Licentie):**
```json
{
  "valid": true,
  "message": "Licentie geldig",
  "expires_at": "2025-12-31 23:59:59",
  "license_type": "standard",
  "customer_name": "Klant Naam",
  "customer_email": "klant@voorbeeld.nl"
}
```

**Response (Ongeldige Licentie):**
```json
{
  "valid": false,
  "message": "Onbekende licentie sleutel"
}
```

**Response Velden:**
- `valid` (boolean): `true` als licentie geldig en actief is, `false` anders
- `message` (string): Beschrijving van de status
- `expires_at` (string/null): Vervaldatum in formaat "YYYY-MM-DD HH:MM:SS", of `null` voor onbeperkte licenties
- `license_type` (string): Type licentie (bijv. "trial", "standard", "premium")
- `customer_name` (string): Naam van de licentiehouder
- `customer_email` (string): E-mailadres van de licentiehouder

**Mogelijke Foutberichten:**
- "Geen licentie sleutel opgegeven" - Geen license_key in request
- "Onbekende licentie sleutel" - Licentie bestaat niet
- "Licentie gedeactiveerd" - Licentie status is niet actief
- "Licentie verlopen" - Huidige datum is na expires_at

**cURL Voorbeeld:**
```bash
curl -X POST https://uw-domein.nl/index.php \
  -H "Content-Type: application/json" \
  -d '{"action":"verify","license_key":"ID-12345678-ABCDEFGH"}'
```

**Implementatie Voorbeeld (JavaScript):**
```javascript
async function verifyLicense(licenseKey) {
    try {
        const response = await fetch('https://uw-domein.nl/index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'verify',
                license_key: licenseKey
            })
        });
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('License verification failed:', error);
        return { valid: false, message: 'Verificatie mislukt' };
    }
}
```

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

## Admin Interface

Bezoek `admin.html` om licenties te beheren:
- Licenties aanmaken, bewerken en verwijderen
- Status wijzigen (actief/inactief)
- Vervaldatums instellen
- E-mail templates configureren
- SMTP instellingen beheren