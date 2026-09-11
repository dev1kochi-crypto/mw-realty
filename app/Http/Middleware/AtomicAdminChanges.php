<?php

namespace App\Http\Middleware;

use App\Services\ManagedFiles;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtomicAdminChanges
{
    public function handle(Request $request, Closure $next)
    {
        $name = $request->route()?->getName() ?? '';
        if ($request->isMethodSafe() || !preg_match('/^(cms|portal)\./', $name)
            || preg_match('/(login|logout|password\.|notifications\.)/', $name)) {
            return $next($request);
        }
        $files = app(ManagedFiles::class);
        $files->begin();
        $committed = false;
        DB::beginTransaction();
        try {
            $response = $next($request);
            if ($response->getStatusCode() >= 400) {
                DB::rollBack();
                return $response;
            }
            $actor = auth('cms')->user() ?? auth('portal')->user();
            if ($actor) {
                DB::table('admin_audit_logs')->insert([
                    'actor_type' => $actor->getMorphClass(), 'actor_id' => $actor->getKey(),
                    'action' => $name, 'target' => json_encode($request->route()->originalParameters()),
                    'created_at' => now(),
                ]);
            }
            DB::commit();
            $committed = true;
            return $response;
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $e;
        } finally {
            $files->finish($committed);
        }
    }
}
