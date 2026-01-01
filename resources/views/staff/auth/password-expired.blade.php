<x-layouts.app.base :title="'Reset Expired Password'">
    <x-slot name="header"></x-slot>

    <section class="min-h-[100vh] flex items-center justify-center">
        <div class="card w-full max-w-md md:bg-base-200 md:shadow-2xl p-8 lg:flex-row lg:max-w-3xl">
            <div class="text-center p-[1.5rem] lg:text-left lg:max-w-xs">
                <p class="text-sm uppercase tracking-widest text-primary font-semibold">Staff Portal</p>
                <p class="py-6 text-base-content/70">
                    Your password has expired. Create a new one to continue.
                </p>
            </div>
            <div class="divider lg:divider-vertical hidden lg:flex"></div>
            <form method="POST" action="{{ route('staff.password.expired.update') }}" class="card-body space-y-4">
                @csrf

                @if ($email ?? false)
                    <div>
                        <p class="text-xs uppercase tracking-wide text-base-content/60 mb-1">Email</p>
                        <p class="font-semibold break-all">{{ $email }}</p>
                    </div>
                @endif

                <div class="rounded-lg bg-warning/10 border border-warning/30 p-3 text-sm text-warning">
                    Your previous password has expired. Please create a new one to regain access.
                </div>

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
