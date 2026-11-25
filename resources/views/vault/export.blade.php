<x-layouts.app title="Export the vault">
    <h1 class="text-2xl font-semibold">Export the vault</h1>
    <p class="mt-1 text-sm"><a href="{{ route('vault.index') }}" class="text-zinc-400 underline hover:text-zinc-100">Back to the vault</a></p>

    @if(session('error'))<p class="mt-3 text-sm text-red-400">{{ session('error') }}</p>@endif

    <section class="mt-4 max-w-2xl space-y-2 border border-red-500/60 bg-red-500/10 p-4 text-sm" role="alert">
        <p class="font-semibold text-red-300">Read this before you download</p>
        <ul class="list-disc space-y-1 pl-5 text-zinc-300">
            <li>The file contains <strong>every password in your vault</strong>. Anyone who gets it can use them.</li>
            <li>A plain CSV is not protected at all. It stays on your disk, in your downloads folder, in the trash, in file sync and in computer backups until it is securely deleted.</li>
            <li>Never email it, upload it, paste it in a chat, or open it in Excel or Google Sheets. Import it straight into a password manager, then delete it.</li>
            <li>Only download on a computer you trust. Each download is logged with the time and your IP address, and you can do 3 per hour.</li>
            <li>Your login has to be safe too: anyone who can sign in as you and knows your password can make this download.</li>
        </ul>
    </section>

    <form method="POST" action="{{ route('vault.export.download') }}" autocomplete="off" class="mt-6 max-w-2xl space-y-4 text-sm">
        @csrf
        <fieldset class="space-y-2">
            <legend class="text-zinc-400">Format</legend>
            <label class="flex items-start gap-2"><input type="radio" name="format" value="csv" @checked(old('format', 'csv') === 'csv') class="mt-1 accent-indigo-500"><span><strong>CSV, not encrypted</strong><span class="block text-xs text-zinc-500">Bitwarden's import format; most password managers can read it.</span></span></label>
            <label class="flex items-start gap-2 {{ $canExportEncrypted ? '' : 'opacity-50' }}"><input type="radio" name="format" value="encrypted" @checked(old('format') === 'encrypted') @disabled(! $canExportEncrypted) class="mt-1 accent-indigo-500"><span><strong>Encrypted</strong>@unless($canExportEncrypted) (not set up: add <code>VAULT_PUBLIC_KEY</code> to the server's .env)@endunless<span class="block text-xs text-zinc-500">Locked with your public key. Only your private key, kept on your own computer, can open it. Lose that key and the file is useless.</span></span></label>
            @error('format')<p class="text-red-400">{{ $message }}</p>@enderror
        </fieldset>

        <label class="block"><span class="text-zinc-400">Your password</span>
            <input type="password" name="password" required autocomplete="current-password" class="mt-1 w-full max-w-sm rounded-md border border-zinc-700 px-3 py-2">
            @error('password')<span class="mt-1 block text-red-400">{{ $message }}</span>@enderror
        </label>

        <label class="flex items-start gap-2"><input type="checkbox" name="acknowledge" value="1" class="mt-1 accent-indigo-500"><span>I understand the risks and will delete the file after I have used it.</span></label>
        @error('acknowledge')<p class="text-red-400">{{ $message }}</p>@enderror

        <button class="rounded border px-3 py-1.5 hover:bg-zinc-800">Download</button>
    </form>

    <section class="mt-8 max-w-2xl space-y-2 border border-zinc-800 bg-zinc-900 p-4 text-sm">
        <h2 class="font-semibold">Opening the encrypted download</h2>
        <p class="text-zinc-400">The file is called <code>lore-vault-YYYY-MM-DD.csv.sealed</code>. It is locked with a public key and can only be opened with the matching <strong>private key</strong>, a small text file (usually <code>~/.lore-vault.key</code>) that never leaves your own computer. Without that key nobody can open it, not even Lore.</p>
        <ol class="list-decimal space-y-1 pl-5 text-zinc-300">
            <li><a href="{{ route('vault.export.decrypt-tool') }}" class="underline">Download the decrypt tool</a> (one HTML file, no install) and open it from your computer in Chrome, Edge, Firefox or Safari.</li>
            <li>Choose the <code>.sealed</code> file and your private key file, then press the button. It runs offline inside the page and saves a plain CSV (<code>lore-vault.csv</code>).</li>
            <li>Import that CSV into your password manager, then delete it and the <code>.sealed</code> file.</li>
        </ol>
        <p class="text-xs text-zinc-500">Prefer a terminal? With the Lore repository and PHP's sodium extension: <code>php scripts/vault-decrypt.php file.csv.sealed &gt; vault.csv</code>. If it says it could not decrypt, the file was locked with a different key than the one you used.</p>
    </section>
</x-layouts.app>
