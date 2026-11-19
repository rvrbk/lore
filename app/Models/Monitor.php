<?php

namespace App\Models;

use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
    use HasFactory;

    public const TIMEOUT_SECONDS = 10;

    /** Responses below this status count as "up": an app answering 401/404 is alive, a 5xx is not. */
    public const FIRST_FAILING_STATUS = 500;

    protected $fillable = ['name', 'url'];

    protected function casts(): array
    {
        return [
            'is_up' => 'boolean',
            'checked_at' => 'datetime',
            'status_changed_at' => 'datetime',
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

    public function recordResult(Response|Throwable $result): void
    {
        $isUp = $result instanceof Response && $result->status() < self::FIRST_FAILING_STATUS;

        $this->forceFill([
            'is_up' => $isUp,
            'status_code' => $result instanceof Response ? $result->status() : null,
            'response_ms' => $result instanceof Response ? (int) round(($result->handlerStats()['total_time'] ?? 0) * 1000) : null,
            'error' => $result instanceof Response ? ($isUp ? null : 'HTTP '.$result->status()) : str($result->getMessage())->before(' (see ')->limit(120)->toString(),
            'checked_at' => now(),
            'status_changed_at' => $this->is_up === $isUp && $this->status_changed_at ? $this->status_changed_at : now(),
        ])->save();
    }

    public function host(): string
    {
        return parse_url($this->url, PHP_URL_HOST) ?: $this->url;
    }
}
