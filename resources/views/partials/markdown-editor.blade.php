@php
    $buttons = [
        'bold' => ['B', 'Bold (Ctrl+B)', 'font-bold'],
        'italic' => ['I', 'Italic (Ctrl+I)', 'italic'],
        'heading' => ['H', 'Heading', ''],
        'link' => ['Link', 'Link', ''],
        'wiki' => ['[[ ]]', 'Link to another page', ''],
        'code' => ['</>', 'Code', 'font-mono'],
        'ul' => ['• List', 'Bullet list', ''],
        'ol' => ['1. List', 'Numbered list', ''],
        'task' => ['☐ Task', 'Checklist item', ''],
        'quote' => ['❝', 'Quote', ''],
    ];
@endphp
<div data-md-editor data-preview-url="{{ route('markdown.preview') }}" data-upload-url="{{ route('uploads.store') }}" data-space="{{ $space->slug ?? '' }}" class="space-y-2">
    <div class="flex flex-wrap items-center gap-1 text-xs">
        @foreach($buttons as $action => [$label, $title, $extra])
            <button type="button" data-md="{{ $action }}" title="{{ $title }}" class="border border-zinc-700 px-2 py-1 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100 {{ $extra }}">{{ $label }}</button>
        @endforeach
        <button type="button" data-md="attach" title="Attach an image or file (or paste / drop one)" class="border border-zinc-700 px-2 py-1 text-zinc-300 hover:bg-zinc-800 hover:text-zinc-100">Attach</button>
        <span data-md-status class="ml-auto text-zinc-500"></span>
    </div>
    <div class="grid gap-3 lg:grid-cols-2">
        <textarea name="body" rows="{{ $rows ?? 18 }}" placeholder="Write in Markdown…  Paste an image to attach it." class="min-h-72 w-full rounded-md border border-zinc-700 px-3 py-2 font-mono text-sm">{{ $value ?? '' }}</textarea>
        <div data-md-preview class="md min-h-72 overflow-auto border border-zinc-800 bg-zinc-900 p-4 text-sm"></div>
    </div>
    <input type="file" data-md-file hidden accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,.txt,.md,.csv,.zip,.docx,.xlsx,.pptx">
</div>
