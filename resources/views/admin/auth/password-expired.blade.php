<x-layouts.app.base :title="'Reset Expired Password'">
    <x-slot name="header">
        <div class="bg-base-200 border-b border-base-300 p-4 text-center font-semibold">
            Administrator Access
        </div>
    </x-slot>

    <section class="min-h-[75vh] flex items-center justify-center px-4">
        <div class="card w-full max-w-md bg-base-200 shadow-2xl">
            <form method="POST" action="{{ route('admin.password.expired.update') }}" class="card-body space-y-4">
                @csrf
                <h1 class="text-2xl font-bold text-center">Password expired</h1>
                <p class="text-sm text-base-content/70 text-center">
                    {{ __('Choose a new password to continue') }}
                </p>

                <div class="rounded-lg bg-warning/10 border border-warning/30 p-3 text-sm text-warning">
                    Your previous password has expired. Please create a new one to regain access.
                </div>

                @if ($username ?? false)
                    <div>
                        <p class="text-xs uppercase tracking-wide text-base-content/60 mb-1">Username</p>
                        <p class="font-semibold">{{ $username }}</p>
                    </div>
                @endif

                <div>
                    <label for="password" class="label mb-1">
                        <span class="label-text font-semibold">New password</span>
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autofocus
                        class="input input-bordered w-full"
                    />
                    @error('password')
                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="label mb-1">
                        <span class="label-text font-semibold">Confirm new password</span>
                    </label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        class="input input-bordered w-full"
                    />
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    Update password and continue
                </button>
            </form>
        </div>
    </section>
</x-layouts.app.base>
