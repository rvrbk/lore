<x-layouts.app title="Chrome extension">
    <h1 class="text-2xl font-semibold">Chrome extension</h1>
    <p class="mt-1 text-sm text-zinc-400">Fills logins from your vault on the site you are on. Works in Chrome and other Chromium browsers (Edge, Brave, Arc, Vivaldi).</p>

    <section class="mt-4 max-w-2xl space-y-4 border border-zinc-800 bg-zinc-900 p-4 text-sm">
        <p><a href="{{ route('extension.download') }}" class="inline-block rounded border px-3 py-1.5 hover:bg-zinc-800">Download the extension (zip)</a></p>

        <h2 class="font-medium">Install</h2>
        <ol class="list-decimal space-y-2 pl-5 text-zinc-400">
            <li>Unzip the download to a folder you will keep, for example <code>~/lore-vault-extension</code>. Chrome reads the extension from that folder every time it starts, so do not delete or move it afterwards.</li>
            <li>In Chrome, type <code>chrome://extensions</code> in the address bar (Edge: <code>edge://extensions</code>, Brave: <code>brave://extensions</code>).</li>
            <li>Switch on <strong>Developer mode</strong> (top right).</li>
            <li>Press <strong>Load unpacked</strong> and choose the unzipped <code>lore-vault-extension</code> folder, the one that has <code>manifest.json</code> directly inside it.</li>
            <li>Press the puzzle-piece icon in the toolbar and pin <strong>Lore Vault</strong> so the icon and its badge stay visible.</li>
        </ol>

        <h2 class="font-medium">Use it</h2>
        <ol class="list-decimal space-y-2 pl-5 text-zinc-400">
            <li>Unlock the vault in a Lore tab first (two-factor code and password). The extension uses that same session and locks when the vault does.</li>
            <li>Open a login page. The icon shows how many saved logins match the site. An orange <code>!</code> means you are signed out or the vault is locked.</li>
            <li>Click the icon and press <strong>Fill</strong> next to a login. Use the search box to pick a login saved for another site; it asks before filling it there.</li>
            <li>Optional: open the extension's Settings (right-click the icon, then Options) to change the Lore address or turn on automatic fill when exactly one login matches.</li>
        </ol>

        <h2 class="font-medium">Update or remove</h2>
        <ul class="list-disc space-y-2 pl-5 text-zinc-400">
            <li>Update: download the zip again, replace the files in your folder, then press the reload arrow on the extension's card in <code>chrome://extensions</code>.</li>
            <li>Remove: press <strong>Remove</strong> on its card. Nothing but the Lore address and settings is stored in the browser.</li>
        </ul>

        <p class="text-xs text-zinc-500">Chrome shows a "Disable developer mode extensions" notice on startup for extensions installed this way. Dismiss it; it does not turn the extension off.</p>
    </section>
</x-layouts.app>
