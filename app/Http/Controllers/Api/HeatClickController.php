<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeatClick;
use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HeatClickController extends Controller
{
    /**
     * Receives a click from a site's tracker script (sent as text/plain JSON so browsers skip the CORS preflight).
     * Always answers 204: the beacon never reads the response, and an unknown key must not be distinguishable.
     */
    public function __invoke(Request $request): Response
    {
        $headers = ['Access-Control-Allow-Origin' => '*'];
        $payload = json_decode($request->getContent(), true);
        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'k' => ['required', 'string', 'max:40'],
            'p' => ['required', 'string', 'max:500'],
            'x' => ['required', 'integer', 'between:0,'.HeatClick::X_SCALE],
            'y' => ['required', 'integer', 'between:0,60000'],
            'w' => ['required', 'integer', 'between:1,10000'],
        ]);
        if ($validator->fails()) {
            return response()->noContent(204, $headers);
        }
        $data = $validator->validated();

        $monitor = Monitor::where('heat_key', $data['k'])->first();
        if ($monitor === null || ! $this->originMatches($request, $monitor)) {
            return response()->noContent(204, $headers);
        }

        HeatClick::create([
            'monitor_id' => $monitor->id,
            'path' => Str::start(Str::before($data['p'], '?'), '/'),
            'x' => $data['x'],
            'y' => $data['y'],
            'device' => $data['w'] < HeatClick::MOBILE_BELOW_PX ? 'mobile' : 'desktop',
        ]);

        return response()->noContent(204, $headers);
    }

    /** A browser always sends Origin on a beacon POST; it has to be the monitored site (www. ignored). */
    private function originMatches(Request $request, Monitor $monitor): bool
    {
        $origin = $request->headers->get('Origin');
        if ($origin === null) {
            return true;
        }

        return Str::of((string) parse_url($origin, PHP_URL_HOST))->lower()->chopStart('www.')->toString()
            === Str::of($monitor->host())->lower()->chopStart('www.')->toString();
    }
}
