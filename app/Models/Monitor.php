<?php

namespace App\Models;

use App\Services\IncidentReporter;
use App\Services\Sql;
use App\Services\Telegram;
use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use HasFactory;

    public const TIMEOUT_SECONDS = 10;

    /** Responses below this status count as "up": an app answering 401/404 is alive, a 5xx is not. */
    public const FIRST_FAILING_STATUS = 500;

    /** A down alert is sent once this many checks in a row failed, so a single blip stays quiet. */
    public const ALERT_AFTER_FAILURES = 2;

    public const KEEP_HISTORY_DAYS = 30;

    protected $fillable = ['name', 'url', 'deploy_path'];

    protected static function booted(): void
    {
        static::creating(function (Monitor $monitor): void {
            $monitor->heat_key ??= Str::random(32);
        });
    }

    protected function casts(): array
    {
        return [
            'is_up' => 'boolean',
            'checked_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'alerted_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'domain_expires_at' => 'datetime',
            'expiry_checked_at' => 'datetime',
        ];
    }

    /**
     * Request every given monitor concurrently and store the outcome.
     *
     * @param  Collection<int, Monitor>  $monitors
     */
    public static function check(Collection $monitors): void
    {
        if ($monitors->isEmpty()) {
            return;
        }

        $results = Http::pool(fn (Pool $pool) => $monitors
            ->map(fn (Monitor $monitor) => $pool->as((string) $monitor->id)->timeout(self::TIMEOUT_SECONDS)->get($monitor->url))
            ->all());

        foreach ($monitors as $monitor) {
            $monitor->recordResult($results[(string) $monitor->id]);
        }
    }

    /**
     * @return HasMany<HeatClick, $this>
     */
    public function heatClicks(): HasMany
    {
        return $this->hasMany(HeatClick::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /** Tasks, pages and reminders tagged with this name show up on the site's hub page. */
    public function tagName(): string
    {
        return Tag::normalize($this->name)[0] ?? '';
    }

    public function isPublic(): bool
    {
        return $this->public_token !== null;
    }

    public function setPublic(bool $public): void
    {
        $this->forceFill(['public_token' => $public ? ($this->public_token ?? Str::lower(Str::random(16))) : null])->save();
    }

    public function recordResult(Response|Throwable $result): void
    {
        $isUp = $result instanceof Response && $result->status() < self::FIRST_FAILING_STATUS;
        $statusCode = $result instanceof Response ? $result->status() : null;
        $responseMs = $result instanceof Response ? (int) round(($result->handlerStats()['total_time'] ?? 0) * 1000) : null;
        $error = $result instanceof Response ? ($isUp ? null : 'HTTP '.$result->status()) : str($result->getMessage())->before(' (see ')->limit(120)->toString();

        $this->forceFill([
            'is_up' => $isUp,
            'status_code' => $statusCode,
            'response_ms' => $responseMs,
            'error' => $error,
            'checked_at' => now(),
            'status_changed_at' => $this->is_up === $isUp && $this->status_changed_at ? $this->status_changed_at : now(),
            'consecutive_failures' => $isUp ? 0 : $this->consecutive_failures + 1,
        ])->save();

        $this->checks()->create([
            'is_up' => $isUp,
            'status_code' => $statusCode,
            'response_ms' => $responseMs,
            'error' => $error,
            'checked_at' => now(),
        ]);

        $this->alertOnChange();
    }

    /**
     * Notify once when the app has been failing for a while, and once when it recovers.
     */
    private function alertOnChange(): void
    {
        if (! $this->is_up && $this->consecutive_failures >= self::ALERT_AFTER_FAILURES && $this->alerted_at === null) {
            $this->forceFill(['alerted_at' => now()])->save();
            $this->sendAlert("{$this->name} is DOWN ({$this->error}) · {$this->url}", '🔴');
            app(IncidentReporter::class)->open(Incident::DOWN, "{$this->name} is down", $this, "{$this->error} · {$this->url}", notify: false);
        } elseif ($this->is_up && $this->alerted_at !== null) {
            $this->forceFill(['alerted_at' => null])->save();
            $this->sendAlert("{$this->name} is back UP · {$this->url}", '🟢');
            app(IncidentReporter::class)->resolve(Incident::DOWN, $this, notify: false);
        }
    }

    private function sendAlert(string $message, string $icon): void
    {
        app(Telegram::class)->send($icon.' <b>Server health</b>'."\n".e($message));

        if ($webhook = config('services.monitor_alerts.webhook')) {
            try {
                Http::timeout(5)->post($webhook, ['text' => $message, 'content' => $message]);
            } catch (Throwable $e) {
                Log::warning('Monitor alert webhook failed', ['error' => $e->getMessage()]);
            }
        }

        $result = Process::run(['notify-send', '--app-name=Lore', '--urgency=critical', 'Server health', $message]);
        if ($result->failed()) {
            Log::info('Monitor desktop alert not delivered', ['error' => $result->errorOutput()]);
        }
    }

    /**
     * The last hour of checks for each monitor, for the tile sparklines.
     *
     * @param  Collection<int, Monitor>  $monitors
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, MonitorCheck>>
     */
    public static function sparklines(Collection $monitors): \Illuminate\Support\Collection
    {
        return MonitorCheck::whereIn('monitor_id', $monitors->modelKeys())
            ->where('checked_at', '>=', now()->subHour())
            ->orderBy('checked_at')
            ->get(['monitor_id', 'is_up', 'response_ms'])
            ->groupBy('monitor_id');
    }

    /** Share of checks in the last N days that were up, or null without data. */
    public function uptimePercent(int $days): ?float
    {
        $counts = $this->checks()->where('checked_at', '>=', now()->subDays($days))
            ->selectRaw('count(*) as total, sum(case when is_up then 1 else 0 end) as up')->toBase()->first();

        return $counts->total > 0 ? round($counts->up / $counts->total * 100, 2) : null;
    }

    /**
     * The heatmap key of the monitored site served on the given host, so Lore can track its own pages too.
     */
    public static function heatKeyForHost(string $host): ?string
    {
        return Cache::remember('heat-key:'.$host, 600, fn () => static::where('url', Sql::like(), '%://'.$host.'%')->value('heat_key') ?? '');
    }

    public function host(): string
    {
        return parse_url($this->url, PHP_URL_HOST) ?: $this->url;
    }
}
