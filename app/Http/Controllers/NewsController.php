<?php

namespace App\Http\Controllers;

use App\Services\NewsHeadlines;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    public function __invoke(NewsHeadlines $news): JsonResponse
    {
        return response()->json(['headlines' => $news->latest()]);
    }
}
