<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Reminder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Looks up when each site's SSL certificate and domain registration expire, keeps countdown reminders for them
 * and raises an incident when one is about to lapse.
 */
class ExpiryWatcher
{
    /** Reminders are created this many days before expiry. */
    public const REMINDER_DAYS = [30, 14, 7];

    public const SSL_INCIDENT_DAYS = 7;

    public const DOMAIN_INCIDENT_DAYS = 14;

    /** Monitors checked more recently than this are skipped, so the command can run hourly. */
    public const RECHECK_AFTER_HOURS = 20;

    public function __construct(private IncidentReporter $incidents) {}

    /**
     * @param  iterable<int, Monitor>  $monitors
     */
    public function checkAll(iterable $monitors): int
    {
        $domains = [];
        $checked = 0;

        foreach ($monitors as $monitor) {
            if ($monitor->expiry_checked_at?->gt(now()->subHours(self::RECHECK_AFTER_HOURS))) {
                continue;
            }

            $host = $monitor->host();
            $registrable = $this->registrableDomain($host);
            if ($registrable !== null) {
                $domains[$registrable] ??= $this->domainExpiry($registrable);
            }

            $this->apply(
                $monitor,
                str_starts_with($monitor->url, 'https://') ? $this->sslExpiry($host) : null,
                $registrable !== null ? $domains[$registrable] : null,
            );
            $checked++;
        }

        return $checked;
    }

    public function apply(Monitor $monitor, ?CarbonImmutable $sslExpiresAt, ?CarbonImmutable $domainExpiresAt): void
    {
        $monitor->forceFill([
            'ssl_expires_at' => $sslExpiresAt ?? $monitor->ssl_expires_at,
            'domain_expires_at' => $domainExpiresAt ?? $monitor->domain_expires_at,
            'expiry_checked_at' => now(),
        ])->save();

        $this->track($monitor, 'SSL certificate', $monitor->ssl_expires_at, Incident::SSL_EXPIRING, self::SSL_INCIDENT_DAYS);
        $this->track($monitor, 'domain', $monitor->domain_expires_at, Incident::DOMAIN_EXPIRING, self::DOMAIN_INCIDENT_DAYS);
    }

    /** The certificate's expiry for a host, read without trusting it so expired certificates are still reported. */
    public function sslExpiry(string $host): ?CarbonImmutable
    {
        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $socket = @stream_socket_client("ssl://{$host}:443", $errorCode, $errorMessage, 10, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            return null;
        }

        $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
        fclose($socket);
        $validTo = $certificate ? (openssl_x509_parse($certificate)['validTo_time_t'] ?? null) : null;

        return $validTo ? CarbonImmutable::createFromTimestamp($validTo) : null;
    }

    /** The registration expiry from the public RDAP service, or null when the registry does not publish one. */
    public function domainExpiry(string $domain): ?CarbonImmutable
    {
        try {
            $response = Http::timeout(10)->acceptJson()->get('https://rdap.org/domain/'.$domain);
        } catch (Throwable) {
            return null;
        }

        $event = collect($response->successful() ? ($response->json('events') ?? []) : [])->firstWhere('eventAction', 'expiration');

        return isset($event['eventDate']) ? CarbonImmutable::parse($event['eventDate']) : null;
    }

    /** "app.example.co.uk" becomes "example.co.uk"; IP addresses and bare hosts have no registrable domain. */
    public function registrableDomain(string $host): ?string
    {
        $labels = explode('.', strtolower($host));
        if (count($labels) < 2 || filter_var($host, FILTER_VALIDATE_IP) || in_array(end($labels), ['test', 'local', 'localhost', 'internal'], true)) {
            return null;
        }

        $secondLevel = count($labels) >= 3 && strlen(end($labels)) === 2 && in_array($labels[count($labels) - 2], ['co', 'com', 'org', 'net', 'ac', 'gov'], true);

        return implode('.', array_slice($labels, $secondLevel ? -3 : -2));
    }

    private function track(Monitor $monitor, string $what, ?CarbonInterface $expiresAt, string $kind, int $incidentDays): void
    {
        if ($expiresAt === null) {
            return;
        }

        $this->syncReminders($monitor, $what, $expiresAt);

        $daysLeft = (int) floor(now()->diffInDays($expiresAt, false));
        if ($daysLeft <= $incidentDays) {
            $when = $daysLeft < 0 ? 'has expired' : "expires in {$daysLeft} day".($daysLeft === 1 ? '' : 's');
            $this->incidents->open($kind, "{$monitor->name}: {$what} {$when}", $monitor, 'Expires '.$expiresAt->format('j M Y').'.');
        } else {
            $this->incidents->resolve($kind, $monitor);
        }
    }

    private function syncReminders(Monitor $monitor, string $what, CarbonInterface $expiresAt): void
    {
        $expires = CarbonImmutable::instance($expiresAt);
        $prefix = "{$monitor->name}: {$what} expires ";
        $current = $prefix.$expires->format('j M Y');

        $existing = Reminder::where('title', Sql::like(), '%: '.$what.' expires %')->get()->filter(fn (Reminder $r) => str_starts_with($r->title, $prefix));
        // A renewed certificate or domain has a new date, which makes the old countdown obsolete.
        $existing->filter(fn (Reminder $r) => $r->completed_at === null && ! str_starts_with($r->title, $current))->each->delete();
        $titles = $existing->filter(fn (Reminder $r) => str_starts_with($r->title, $current))->pluck('title');

        $dueAt = $expires->isPast()
            ? ['expired' => now()]
            : collect(self::REMINDER_DAYS)->mapWithKeys(fn (int $days) => ["{$days} days left" => $expires->subDays($days)->setTime(9, 0)])->all();

        // Only the most urgent countdown that is already due is created, so a site found at 10 days gets one reminder, not three.
        $passed = collect($dueAt)->filter(fn ($due) => $due->lte(now()));
        $mostUrgentPassed = $passed->keys()->last();

        foreach ($dueAt as $label => $due) {
            $title = "{$current} ({$label})";
            if ($titles->contains($title) || ($due->lte(now()) && $label !== $mostUrgentPassed)) {
                continue;
            }
            Reminder::create(['title' => $title, 'notes' => 'Renew it before it lapses.', 'due_at' => $due->lte(now()) ? now() : $due]);
        }
    }
}
