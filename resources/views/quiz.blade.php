<x-layouts.app title="Quiz yourself">
    <h1 class="text-2xl font-semibold">Quiz yourself 🧠</h1>
    @if($question === null)
        <p class="mt-4 text-sm text-zinc-400">No question yet. Quiz questions come from lines in your pages that contain a <code>`code term`</code>, a <strong>**bold term**</strong> or a number.</p>
    @else
        <p class="mt-2 text-sm text-zinc-400">From <a href="{{ route('pages.show', $question['page_id']) }}" class="underline">{{ $question['title'] }}</a>. Fill in the blank.</p>
        <blockquote class="mt-4 border-l-4 border-zinc-600 bg-zinc-900 p-4 text-lg">{{ $question['text'] }}</blockquote>
        @if($result === null)
            <form method="POST" action="{{ route('quiz.answer') }}" class="mt-4 flex flex-wrap gap-2">
                @csrf
                <input name="answer" autofocus autocomplete="off" maxlength="200" placeholder="Your answer" aria-label="Your answer" class="w-72 max-w-full rounded-md border border-zinc-700 px-3 py-1.5">
                <button class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Check</button>
                <a href="{{ route('quiz') }}" class="self-center text-sm text-zinc-400 hover:text-zinc-100">Skip</a>
            </form>
        @else
            <p class="mt-4 text-lg {{ $result['correct'] ? 'text-emerald-400' : 'text-red-400' }}">{{ $result['correct'] ? '✅ Correct!' : '❌ Not quite.' }}</p>
            <p class="mt-1 text-sm text-zinc-300">The answer was <strong class="text-zinc-100">{{ $question['answer'] }}</strong>@unless($result['correct'] || $result['given'] === '') (you said “{{ $result['given'] }}”)@endunless.</p>
            <a href="{{ route('quiz') }}" class="mt-4 inline-block rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Next question</a>
        @endif
    @endif
</x-layouts.app>
