<?php

namespace App\Services;

use App\Models\Monitor;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Wires a site's GitHub repository to Lore's deploy webhook: a repository secret holding the site's deploy
 * token plus a workflow that calls the webhook on every push to main or master.
 */
class GitHubDeployHook
{
    public const WORKFLOW_PATH = '.github/workflows/lore-deploy.yml';

    public const SECRET_NAME = 'LORE_DEPLOY_TOKEN';

    public function isConfigured(): bool
    {
        return filled(config('services.github.token'));
    }

    /** The "owner/name" of the checkout's origin remote on GitHub, or null when it is not a GitHub checkout. */
    public function repositoryFor(string $path): ?string
    {
        $result = Process::run(['git', 'config', '--file', $path.'/.git/config', '--get', 'remote.origin.url']);
        if ($result->failed()) {
            return null;
        }

        return preg_match('#github\.com[:/]([A-Za-z0-9._-]+/[A-Za-z0-9._-]+?)(?:\.git)?/?$#', trim($result->output()), $matches) ? $matches[1] : null;
    }

    /**
     * Store the secret and commit the workflow to the repository's default branch.
     *
     * @return string The repository that was set up.
     *
     * @throws RuntimeException When anything is missing or GitHub refuses.
     */
    public function install(Monitor $monitor): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Set GITHUB_DEPLOY_TOKEN in .env first.');
        }
        if (! extension_loaded('sodium')) {
            throw new RuntimeException('The PHP sodium extension is needed to store the GitHub secret.');
        }
        if ($monitor->deploy_path === null) {
            throw new RuntimeException("{$monitor->name} has no server folder.");
        }
        $repository = $this->repositoryFor($monitor->deploy_path)
            ?? throw new RuntimeException("{$monitor->deploy_path} has no GitHub origin remote.");

        if ($monitor->deploy_token === null) {
            $monitor->forceFill(['deploy_token' => Str::random(48)])->save();
        }

        $this->storeSecret($repository, $monitor->deploy_token);
        $this->commitWorkflow($repository, $this->workflow($monitor));

        return $repository;
    }

    public function workflow(Monitor $monitor): string
    {
        $url = rtrim((string) config('app.url'), '/').route('api.deploy.hook', $monitor, false);

        return <<<YAML
        name: Deploy through Lore

        on:
          push:
            branches: [main, master]

        jobs:
          deploy:
            runs-on: ubuntu-latest
            steps:
              - name: Ask Lore to deploy
                env:
                  LORE_DEPLOY_TOKEN: \${{ secrets.LORE_DEPLOY_TOKEN }}
                run: |
                  curl --fail-with-body --silent --show-error --retry 3 -X POST \\
                    -H "Authorization: Bearer \$LORE_DEPLOY_TOKEN" "{$url}"

        YAML;
    }

    private function storeSecret(string $repository, string $token): void
    {
        $key = $this->github()->get("/repos/{$repository}/actions/secrets/public-key");
        if ($key->failed()) {
            throw new RuntimeException("GitHub refused access to {$repository} secrets (HTTP {$key->status()}).");
        }

        $encrypted = sodium_crypto_box_seal($token, base64_decode((string) $key->json('key')));
        $stored = $this->github()->put("/repos/{$repository}/actions/secrets/".self::SECRET_NAME, [
            'encrypted_value' => base64_encode($encrypted),
            'key_id' => $key->json('key_id'),
        ]);
        if ($stored->failed()) {
            throw new RuntimeException("Could not store the secret on {$repository} (HTTP {$stored->status()}).");
        }
    }

    private function commitWorkflow(string $repository, string $contents): void
    {
        $url = "/repos/{$repository}/contents/".self::WORKFLOW_PATH;
        $existing = $this->github()->get($url);
        if ($existing->successful() && base64_decode(str_replace("\n", '', (string) $existing->json('content'))) === $contents) {
            return;
        }

        $committed = $this->github()->put($url, array_filter([
            'message' => 'Deploy through Lore on push',
            'content' => base64_encode($contents),
            'sha' => $existing->successful() ? $existing->json('sha') : null,
        ]));
        if ($committed->failed()) {
            throw new RuntimeException("Could not commit the workflow to {$repository} (HTTP {$committed->status()}).");
        }
    }

    private function github(): PendingRequest
    {
        return Http::baseUrl('https://api.github.com')
            ->withToken((string) config('services.github.token'))
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(15);
    }
}
