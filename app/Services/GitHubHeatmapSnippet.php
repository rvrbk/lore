<?php

namespace App\Services;

use App\Models\Monitor;
use RuntimeException;

/**
 * Adds Lore's click tracker script tag to the layout files of a site's GitHub repository, so the site sends
 * its clicks to the Heatmaps page. Commits to the default branch, which the deploy hook then ships.
 */
class GitHubHeatmapSnippet
{
    /** Files that hold a site's <head>, in the places the existing sites keep them. */
    public const LAYOUT_FILES = [
        'resources/views/app.blade.php',
        'resources/views/layouts/app.blade.php',
        'resources/views/welcome.blade.php',
        'public/index.html',
        'index.html',
    ];

    public const MARKER = 'data-lore-heat';

    public function __construct(public GitHubDeployHook $github) {}

    public function tag(Monitor $monitor): string
    {
        $src = rtrim((string) config('app.url'), '/').'/heat.js';

        return '<script defer src="'.$src.'" data-key="'.$monitor->heat_key.'" '.self::MARKER.'></script>';
    }

    /**
     * @return list<string> The files that were changed (empty when the tag was already in place everywhere).
     *
     * @throws RuntimeException When the repository cannot be found or no layout has a <head>.
     */
    public function install(Monitor $monitor): array
    {
        if (! $this->github->isConfigured()) {
            throw new RuntimeException('Set GITHUB_DEPLOY_TOKEN in .env first.');
        }
        if ($monitor->deploy_path === null) {
            throw new RuntimeException("{$monitor->name} has no server folder, so its repository is unknown.");
        }
        $repository = $this->github->repositoryFor($monitor->deploy_path)
            ?? throw new RuntimeException("{$monitor->deploy_path} has no GitHub origin remote.");

        $found = false;
        $changed = [];
        foreach (self::LAYOUT_FILES as $file) {
            $response = $this->github->github()->get("/repos/{$repository}/contents/{$file}");
            if ($response->failed()) {
                continue;
            }
            $contents = base64_decode(str_replace("\n", '', (string) $response->json('content')));
            if (! str_contains($contents, '</head>')) {
                continue;
            }
            $found = true;

            $updated = $this->withTag($contents, $monitor);
            if ($updated === $contents) {
                continue;
            }
            $committed = $this->github->github()->put("/repos/{$repository}/contents/{$file}", [
                'message' => 'Send clicks to Lore heatmaps',
                'content' => base64_encode($updated),
                'sha' => $response->json('sha'),
            ]);
            if ($committed->failed()) {
                throw new RuntimeException("Could not commit {$file} to {$repository} (HTTP {$committed->status()}).");
            }
            $changed[] = $file;
        }

        if (! $found) {
            throw new RuntimeException("No layout with a </head> found in {$repository}; add the script tag by hand.");
        }

        return $changed;
    }

    /** The given HTML with the tracker tag in place: added before </head>, or replaced when it is already there. */
    public function withTag(string $html, Monitor $monitor): string
    {
        $tag = $this->tag($monitor);
        if (str_contains($html, self::MARKER)) {
            return (string) preg_replace('#<script[^>]*'.self::MARKER.'[^>]*></script>#', $tag, $html, 1);
        }

        return (string) preg_replace('#^([ \t]*)</head>#m', '$1    '.$tag."\n$1</head>", $html, 1);
    }
}
