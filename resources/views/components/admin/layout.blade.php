@props(['title' => 'Admin'])

<x-layouts.app.base :title="$title">
    <x-slot name="header">
        <div class="navbar bg-base-200 shadow-sm">
            <div class="navbar-start">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost normal-case text-xl font-bold">
                    Admin Console
                </a>
            </div>
            <div class="navbar-center hidden md:flex">
                <ul class="menu menu-horizontal px-1">
                    <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li><a href="{{ route('admin.users.index') }}">Admins</a></li>
                    <li><a href="{{ route('admin.hotels.index') }}">Hotels</a></li>
                    <li><a href="{{ route('admin.staffusers.index') }}">Staff Users</a></li>
                </ul>
            </div>
            <div class="navbar-end gap-3 pr-2">
                <div class="hidden md:block text-sm text-base-content/70">
                    {{ auth('admin')->user()?->name ?? 'Admin' }}
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline">Logout</button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
        {{ $slot }}
    </div>

    @php
        $highlightFallback = session('_highlight_payload', []);
        $cookiePayload = json_decode(request()->cookie('admin_highlight', 'null'), true) ?: [];
        $highlightId = session('highlight_id') ?? $highlightFallback['highlight_id'] ?? $cookiePayload['highlight_id'] ?? null;
        $highlightAction = session('highlight_action') ?? $highlightFallback['highlight_action'] ?? $cookiePayload['highlight_action'] ?? null;
    @endphp

    @if ($highlightId)
        @php
            session()->forget('_highlight_payload');
            \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('admin_highlight'));
        @endphp
        @push('toast-scripts')
            <script>
                window.__adminHighlight = {
                    id: @json($highlightId),
                    action: @json($highlightAction),
                };
                document.addEventListener('DOMContentLoaded', () => {
                    const meta = window.__adminHighlight || {};
                    const id = meta.id;
                    const action = meta.action;
                    if (!id) return;

                    const baseClass = 'row-highlight';
                    const actionClass = action ? `${baseClass}--${action}` : null;

                    const findRowAndHighlight = (attempts = 0) => {
                        const row = document.querySelector(`[data-highlight-id="${id}"]`);
                        if (row) {
                            row.classList.add(baseClass);
                            if (actionClass) {
                                row.classList.add(actionClass);
                            }
                            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            setTimeout(() => {
                                row.classList.remove(baseClass);
                                if (actionClass) {
                                    row.classList.remove(actionClass);
                                }
                            }, 2200);
                        } else if (attempts < 10) {
                            requestAnimationFrame(() => findRowAndHighlight(attempts + 1));
                        }
                    };

                    findRowAndHighlight();
                });
            </script>
        @endpush
    @endif
</x-layouts.app.base>
