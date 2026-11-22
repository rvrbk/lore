<?php

namespace App\Http\Controllers;

use App\Services\Quiz;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function show(Quiz $quiz, Request $request): View
    {
        $question = $quiz->question();
        $request->session()->put('quiz', $question);

        return view('quiz', ['question' => $question, 'result' => null]);
    }

    public function answer(Request $request, Quiz $quiz): View
    {
        $data = $request->validate(['answer' => ['nullable', 'string', 'max:200']]);
        $question = $request->session()->pull('quiz');
        abort_if($question === null, 404);

        return view('quiz', [
            'question' => $question,
            'result' => ['given' => (string) ($data['answer'] ?? ''), 'correct' => $quiz->isCorrect((string) ($data['answer'] ?? ''), $question['answer'])],
        ]);
    }
}
