# Licensing

**Source:** §38 (plan.txt)

---

## License Model

Each client site requires a license. Licenses are tied to a domain and have an expiry date.

---

## License Entity

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | PK |
| client_id | FK | → Clients |
| site_id | FK | → Projects |
| domain | string | Licensed domain |
| key | string | Unique license key |
| status | string | active, expired, revoked |
| expires_at | timestamp | License expiry |
| platform_version | string | Platform version at issuance |
| features | json | Enabled features |

---

## License Key Format

```
XXXX-XXXX-XXXX-XXXX
```

Example: `NEWS-2026-ABCD-1234`

---

## Verification Protocol

### Periodic Verification
Client sites verify their license every 24 hours:

```http
POST https://platform.example.com/api/v1/verify
Content-Type: application/json

{
  "license_key": "NEWS-2026-ABCD-1234",
  "site_id": 1,
  "domain": "example.com",
  "platform_version": "3.2.1"
}
```

### Response (Valid)
```json
{
  "valid": true,
  "expires_at": "2027-09-30T00:00:00Z",
  "features": ["comments", "video", "newsletter"]
}
```

### Response (Invalid)
```json
{
  "valid": false,
  "reason": "expired",
  "expired_at": "2026-09-01T00:00:00Z"
}
```

---

## Verification Behavior

| Scenario | Behavior |
|----------|----------|
| Platform reachable, license valid | Cache result for 24h |
| Platform reachable, license invalid | Show warning, grace period 7 days |
| Platform unreachable | Fail open (site stays up), retry in 1h |
| License expired | Show warning, grace period 7 days |
| License revoked | Show warning, grace period 7 days |

**Rule:** Do not make every page request dependent on the central license server. Use periodic verification.

---

## Feature Flags

Licenses control feature availability:

```json
{
  "features": {
    "comments": true,
    "video": true,
    "newsletter": false
  }
}
```

---

## License Generation

```php
// Generate a new license
$license = License::create([
    'client_id' => $client->id,
    'site_id' => $project->id,
    'domain' => $project->domain,
    'key' => LicenseKeyGenerator::generate(),
    'status' => 'active',
    'expires_at' => now()->addYear(),
    'platform_version' => Platform::VERSION,
    'features' => ['comments', 'video'],
]);
```

---

## Related Documents

- [Backend Overview](../backend/overview.md)
- [Entities](../backend/entities.md)
- [API](../backend/api.md)
- [Deployment Overview](../deployment/overview.md)
