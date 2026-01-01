@php
    $flashBackup = session('_highlight_payload', []);
    $cookiePayload = json_decode(request()->cookie('admin_highlight', 'null'), true) ?: [];
    $status = session('status') ?? ($flashBackup['status'] ?? $cookiePayload['status'] ?? null);
    $error = session('error') ?? null;
@endphp

@if ($status || $error || $errors?->any())
    @php
        $isError = $error || $errors?->any();
        $message = $error ?? $status ?? $errors->first();
        session()->forget('_highlight_payload');
        \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget('admin_highlight'));
    @endphp
    @pushOnce('toast-scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('toast', () => ({
                    visible: true,
                    timer: null,
                    duration: 5000,
                    hovering: false,
                    show() {
                        this.visible = true;
                        this.start();
                    },
                    start() {
                        this.clear();
                        this.timer = setTimeout(() => this.close(), this.duration);
                    },
                    pause() {
                        this.clear();
                    },
                    resume() {
                        this.start();
                    },
                    clear() {
                        if (this.timer) {
                            clearTimeout(this.timer);
                            this.timer = null;
                        }
                    },
                    close() {
                        this.visible = false;
                        this.clear();
                    },
                }));
            });
        </script>
    @endPushOnce
    <div
        x-data="toast"
        x-init="show()"
        class="toast z-[9999] pointer-events-none"
    >
        <div
            x-show="visible"
            x-transition:enter="transform duration-200 ease-out"
            x-transition:enter-start="opacity-0 scale-95 translate-y-1"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transform duration-200 ease-in"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-1"
            @mouseenter="hovering = true; pause()"
            @mouseleave="hovering = false; resume()"
            class="alert {{ $isError ? 'alert-soft alert-error' : 'alert-soft alert-success' }} shadow text-sm md:text-base font-bold max-w-2xl w-full pr-0 pointer-events-auto group"
        >
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
                    @if ($isError)
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    @endif
                </svg>
                <span class="flex-1">{{ $message }}</span>
                <div
                    class="flex justify-end overflow-hidden transition-all duration-150 ease-out"
                    :style="hovering ? 'width: 1.75rem; margin-left: 0.25rem;' : 'width: 0; margin-left: 0;'"
                >
                    <button
                        type="button"
                        x-show="hovering"
                        x-transition:enter="duration-150 ease-out"
                        x-transition:enter-start="opacity-0 translate-x-2"
                        x-transition:enter-end="opacity-100 translate-x-0"
                        x-transition:leave="duration-150 ease-in"
                        x-transition:leave-start="opacity-100 translate-x-0"
                        x-transition:leave-end="opacity-0 translate-x-2"
                        x-cloak
                        class="btn btn-ghost btn-xs btn-circle btn-icon h-6 w-6 min-h-0"
                        @click="close()"
                    >
                        ✕
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
