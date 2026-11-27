<?php

namespace App\Support;

/**
 * Copy-paste setup for the MCP clients that can talk to Lore over HTTP with a bearer token.
 */
class McpClients
{
    /**
     * @return array<int, array{name: string, where: string, code: string}>
     */
    public static function snippets(string $server, string $url, string $token): array
    {
        $header = 'Bearer '.$token;
        $json = fn (array $data): string => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return [
            [
                'name' => 'Claude Code',
                'where' => 'Run in a terminal.',
                'code' => "claude mcp add --transport http {$server} {$url} --header \"Authorization: {$header}\"",
            ],
            [
                'name' => 'VS Code (GitHub Copilot)',
                'where' => 'Save as .vscode/mcp.json in your project, or run "MCP: Open User Configuration" for all projects. Then start the server from the file and use Copilot Chat in Agent mode.',
                'code' => $json(['servers' => [$server => ['type' => 'http', 'url' => $url, 'headers' => ['Authorization' => $header]]]]),
            ],
            [
                'name' => 'Copilot CLI',
                'where' => 'Add to ~/.copilot/mcp-config.json, or use /mcp add inside the CLI.',
                'code' => $json(['mcpServers' => [$server => ['type' => 'http', 'url' => $url, 'headers' => ['Authorization' => $header], 'tools' => ['*']]]]),
            ],
            [
                'name' => 'Cursor',
                'where' => 'Add to ~/.cursor/mcp.json (or .cursor/mcp.json in a project).',
                'code' => $json(['mcpServers' => [$server => ['url' => $url, 'headers' => ['Authorization' => $header]]]]),
            ],
            [
                'name' => 'Gemini CLI',
                'where' => 'Run in a terminal.',
                'code' => "gemini mcp add --transport http {$server} {$url} --header \"Authorization: {$header}\"",
            ],
            [
                'name' => 'Codex',
                'where' => 'Add to ~/.codex/config.toml.',
                'code' => "[mcp_servers.{$server}]\nurl = \"{$url}\"\nhttp_headers = { Authorization = \"{$header}\" }",
            ],
        ];
    }
}
