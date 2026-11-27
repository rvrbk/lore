<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateMcpToken extends Command
{
    protected $signature = 'lore:mcp-token {email : Email of the approved user the token acts as} {--name=mcp : Label for the token}';

    protected $description = 'Create a bearer token for the Lore MCP server';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if ($user === null || ! $user->isApproved()) {
            $this->error('No approved user with that email.');

            return self::FAILURE;
        }

        $this->line($user->createToken((string) $this->option('name'))->plainTextToken);
        $this->comment('Shown once. Use it as "Authorization: Bearer <token>" against '.url('/mcp/lore'));

        return self::SUCCESS;
    }
}
