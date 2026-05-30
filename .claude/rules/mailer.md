# Mailer - DKIM, S/MIME, TLS Hardening, and 8.1 Transport Options

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/doc/8.1/mailer.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

The security knobs the stub treats as best practice for any non-DEV environment that sends real email (TLS enforcement, framework-level DKIM, framework-level S/MIME) were introduced in the 7.3 line and are **unchanged on 8.1** (sections 1-4, verified against the 8.1 mailer docs). Symfony 8.1 adds three transport-specific options documented in section 5: SES port + tls, SendGrid `send_at` scheduled delivery, and Infobip `ipPoolId`.

## 1. Require TLS on SMTP (`require_tls=true`)

Forces the SMTP handshake to negotiate TLS (directly or via `STARTTLS`). If the remote does NOT support it, the transport throws `TransportException` instead of falling back to plaintext.

```env
# .env.local
MAILER_DSN='smtp://user:pass@smtp.example.com:587?require_tls=true'
```

Equivalent imperative form: call `setRequireTls(true)` on the `EsmtpTransport` instance.

Rule: **set `require_tls=true` in every non-DEV environment.** Without it, the mailer happily transmits credentials over plaintext when the server advertises it (mis-configured relays, MITM downgrade).

## 2. Global DKIM Signing

DKIM signs every outgoing message at the framework level - no per-Email code needed.

```yaml
# config/packages/mailer.yaml (or framework.yaml)
framework:
    mailer:
        dkim_signer:
            key: 'file://%kernel.project_dir%/var/certificates/dkim.pem'
            domain: 'example.com'
            select: 's1'         # the DNS selector you published as s1._domainkey.example.com
```

Rules:

1. **Private key path goes through `file://`** so the mailer reads it once at boot. Inline keys in env vars are NOT supported (and would leak in `printenv`).
2. **Selector MUST match the DNS TXT record.** The DNS TXT `<selector>._domainkey.<domain>` exposes the public key. Mismatch -> DKIM-fail header at the recipient.
3. **Rotate selectors instead of keys.** Publish a new `s2._domainkey` first, switch the config, then retire `s1` after 24-48 h to bridge cached resolvers.

## 3. Global S/MIME Sign and Encrypt

For B2B / regulated workflows that require S/MIME on every message:

```yaml
framework:
    mailer:
        smime_signer:
            key: '%kernel.project_dir%/var/certificates/smime.key'
            certificate: '%kernel.project_dir%/var/certificates/smime.crt'
            passphrase: ''   # empty unless the key is passphrase-protected

        smime_encrypter:
            repository: App\Security\LocalFileCertificateRepository
```

The encrypter resolves the recipient's certificate via a repository implementing `Symfony\Component\Mailer\Smime\SmimeCertificateRepositoryInterface`, whose `findCertificatePathFor(string $email): ?string` returns the path to the certificate for a given address - project-side code:

```php
namespace App\Security;

use Symfony\Component\Mailer\Smime\SmimeCertificateRepositoryInterface;

final readonly class LocalFileCertificateRepository implements SmimeCertificateRepositoryInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {}

    public function findCertificatePathFor(string $email): ?string
    {
        $hash = hash('xxh128', strtolower(trim($email)));
        $path = sprintf('%s/var/certificates/recipients/%s.crt', $this->projectDir, $hash);

        return file_exists($path) ? $path : null;
    }
}
```

Rules:

1. **Certificates live OUTSIDE git.** `var/certificates/` is gitignored by the stub. Real certs are mounted at deploy time or pulled from a secret store.
2. **Encryption requires a recipient cert.** When `findCertificatePathFor()` returns `null`, the mailer raises an exception instead of falling back to plaintext. Decide policy: pre-flight check, fallback to signed-only, or hard-fail.
3. **DKIM + S/MIME together is allowed.** They protect different layers (transport vs message body). When both are configured, the framework applies them in the right order automatically.

## 4. Test Environment

Tests should NOT actually sign / encrypt - they should NOT need real keys at all. Override under `when@test`:

```yaml
when@test:
    framework:
        mailer:
            dkim_signer: null
            smime_signer: null
            smime_encrypter: null
```

Plus the standard test mailer DSN (`MAILER_DSN=null://null` in `.env.test`) to avoid actual SMTP attempts.

## 5. Symfony 8.1 - Transport Options

Three additive transport options shipped in 8.1. Each is per-transport and opt-in - existing mailer configuration is unaffected.

### 5.1 Amazon SES - Port and TLS Options

Symfony 8.1 allows configuring the port (and TLS options) of the Amazon SES transport. The port is set directly in the `ses+smtp` DSN:

```env
# .env.local
MAILER_DSN='ses+smtp://USERNAME:PASSWORD@default:PORT'
```

Use it when the SES SMTP endpoint must run on a non-default port (e.g. a port-587 vs port-2587 choice to dodge an upstream block) or when the TLS behavior must be tuned for the relay. Before 8.1 the SES `ses+smtp` DSN did not accept a port. For the full set of available DSN tuning keys for your SES region, consult the 8.1 mailer docs - do NOT guess option names.

### 5.2 SendGrid - Scheduled Delivery (`send_at`)

The SendGrid transport now supports scheduling a message for future delivery through SendGrid's `send_at` API parameter. The message is handed to SendGrid immediately but held by SendGrid until the scheduled time.

Use it for "send this reminder at 09:00 the recipient's time" style flows where the application does not want to run its own scheduler / delayed-message queue just to defer an email. The value is a future point in time passed to SendGrid; consult the 8.1 mailer docs for the exact way to attach it to a message (transport-specific metadata / header) - the changelog confirms the capability ("scheduling delivery via the `send_at` API parameter") but the precise attachment API should be taken from the docs rather than assumed.

### 5.3 Infobip - `ipPoolId`

The Infobip mailer transport now supports an `ipPoolId` option, selecting which Infobip IP pool sends the message. Use it when an account has multiple sending IP pools (e.g. a transactional pool separate from a marketing pool) and a given message must go out through a specific one for deliverability / reputation reasons. As with the other two, take the exact option-attachment syntax from the 8.1 docs.

## 6. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `TransportException: STARTTLS extension not supported` | Server lacks TLS; `require_tls=true` enforced | Switch transport or relay. Do NOT remove `require_tls`. |
| Recipient marks message as "DKIM failed" | Selector mismatch / wrong domain / TXT record not propagated | Verify `dig +short s1._domainkey.example.com TXT`. |
| `RuntimeException: Cannot find S/MIME certificate for ...` | Repository returned `null` | Pre-validate recipients OR catch the exception and fall back to signed-only. |
| Passphrase prompt at container start | Encrypted key without `passphrase:` set | Provide the passphrase via env var: `passphrase: '%env(SMIME_PASSPHRASE)%'`. |
| SES transport rejects the port in the DSN | `symfony/mailer` < 8.1 (port not supported on `ses+smtp`) | Upgrade `symfony/mailer` to `^8.1`. |

## 7. Version Constraints

| Package | Required |
|---|---|
| `symfony/mailer` | `^8.1` (for the SES port / tls options, SendGrid `send_at` scheduled delivery, and Infobip `ipPoolId`; `require_tls=true`, framework-level DKIM and S/MIME signers / encrypter are carried from `^7.3`) |
| `phpseclib/phpseclib` | required by the DKIM signer when generating signatures |
