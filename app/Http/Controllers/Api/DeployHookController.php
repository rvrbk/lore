<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Monitor;
use App\Services\Deployer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeployHookController extends Controller
{
    /**
     * Called by a site's GitHub workflow after a push: queues a deployment, which the scheduler then runs and
     * which shows up under Deploy. A push while a deploy is still waiting is already covered by that deploy.
     */
    public function __invoke(Request $request, Monitor $monitor, Deployer $deployer): JsonResponse
    {
        abort_unless($deployer->enabled(), 403);
        abort_unless(
            $monitor->deploy_token !== null && $monitor->deploy_path !== null && hash_equals($monitor->deploy_token, (string) $request->bearerToken()),
            403,
        );

        if (Deployment::where('path', $monitor->deploy_path)->where('status', Deployment::REQUESTED)->exists()) {
            return response()->json(['queued' => false, 'reason' => 'A deployment is already waiting.']);
        }

        $deployment = Deployment::create(['site' => $monitor->name, 'path' => $monitor->deploy_path, 'branch' => $deployer->branch($monitor->deploy_path)]);

        return response()->json(['queued' => true, 'deployment' => $deployment->id], 202);
    }
}
