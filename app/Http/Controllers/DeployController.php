<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use App\Services\Deployer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeployController extends Controller
{
    public function index(Deployer $deployer): View
    {
        return view('deploy.index', [
            'enabled' => $deployer->enabled(),
            'status' => $deployer->status(),
            'active' => Deployment::active()->exists(),
            'deployments' => Deployment::latest('id')->limit(10)->get(),
        ]);
    }

    /**
     * Queue a deployment; the scheduler (which runs as the checkout's owner) picks it up within a minute.
     */
    public function store(Deployer $deployer): RedirectResponse
    {
        abort_unless($deployer->enabled(), 403);

        if (! Deployment::active()->exists()) {
            Deployment::create(['branch' => $deployer->status()['branch'] ?? null]);
        }

        return redirect()->route('deploy.index');
    }
}
