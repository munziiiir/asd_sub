<x-layouts.app.base :title="'Staff Sign In'">
    <x-slot name="header"></x-slot>

    <section class="min-h-[100vh] flex items-center justify-center">
            <div class="card w-full max-w-md md:bg-base-200 md:shadow-2xl p-8 lg:flex-row lg:max-w-3xl">
                <div class="text-center p-[1.5rem] lg:text-left lg:max-w-xs">
                    <p class="text-sm uppercase tracking-widest text-primary font-semibold">Staff Portal</p>
                    <p class="py-6 text-base-content/70">
                        Use your staff credentials to manage operations for your property.
                    </p>
                </div>
                <div class="divider lg:divider-horizontal hidden lg:flex"></div>
                <form method="POST" action="{{ route('staff.login') }}" class="card-body space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="label mb-1">
                            <span class="label-text font-semibold">Email</span>
                        </label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            class="input input-bordered w-full"
                        />
                        @error('email')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="label mb-1">
                            <span class="label-text font-semibold">Password</span>
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            class="input input-bordered w-full"
                        />
                        @error('password')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-between">
                        <label class="label cursor-pointer gap-3">
                            <span class="label-text">Remember me</span>
                            <input type="checkbox" name="remember" value="1" class="toggle toggle-primary" {{ old('remember') ? 'checked' : '' }}>
                        </label>
                        <button type="submit" class="btn btn-primary">
                            Sign in
                        </button>
                    </div>
                </form>
            </div>
    </section>
</x-layouts.app.base>
