<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use App\Services\Deployer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeployController extends Controller
{
    public function index(Deployer $deployer): View
    {
        $active = Deployment::active()->pluck('path')->all();

        return view('deploy.index', [
            'enabled' => $deployer->enabled(),
            'sites' => collect($deployer->targets())->map(fn (array $target) => $target + [
                'status' => $deployer->status($target['path']),
                'active' => in_array($target['path'], $active, true),
            ]),
            'deployments' => Deployment::latest('id')->limit(15)->get(),
        ]);
    }

    /**
     * Queue deployments (one site, or every registered site); the scheduler, which runs as the checkouts' owner,
     * picks them up within a minute. Lore itself is queued last so the other sites deploy with stable code.
     */
    public function store(Request $request, Deployer $deployer): RedirectResponse
    {
        abort_unless($deployer->enabled(), 403);

        $targets = collect($deployer->targets());
        $path = $request->input('path');
        if ($path !== null) {
            $targets = $targets->where('path', $path);
            abort_if($targets->isEmpty(), 422);
        }

        $busy = Deployment::active()->pluck('path')->all();
        $ordered = $targets->sortBy(fn (array $target) => $target['path'] === base_path() ? 1 : 0)->values();
        foreach ($ordered as $target) {
            if (! in_array($target['path'], $busy, true)) {
                Deployment::create(['site' => $target['name'], 'path' => $target['path'], 'branch' => $deployer->status($target['path'])['branch'] ?? null]);
            }
        }

        return redirect()->route('deploy.index');
    }
}
