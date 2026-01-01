<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

trait HandlesRowHighlighting
{
    /**
     * Redirect back to a route with flash data that can be used to highlight a row.
     *
     * @param  array<string,mixed>  $routeParams
     * @param  array<string,mixed>  $flash
     */
    protected function redirectWithHighlight(string $routeName, array $routeParams, Model $model, string $action, ?string $status = null, array $flash = []): RedirectResponse
    {
        $payload = array_merge($flash, [
            'highlight_id' => $model->getKey(),
            'highlight_action' => $action,
        ]);

        if ($status) {
            $payload['status'] = $status;
        }

        // Keep a non-flash backup in case flash data is dropped for any reason.
        session()->put('_highlight_payload', $payload);

        // Also queue an encrypted, short-lived cookie as an extra fallback.
        Cookie::queue(cookie()->make('admin_highlight', json_encode($payload), 5));

        return redirect()
            ->route($routeName, $routeParams)
            ->with($payload);
    }
}
