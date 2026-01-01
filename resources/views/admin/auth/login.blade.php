<x-layouts.app.base :title="'Admin Login'">
    <x-slot name="header">
        <div class="bg-base-200 border-b border-base-300 p-4 text-center font-semibold">
            Administrator Access
        </div>
    </x-slot>

    <section class="min-h-[75vh] flex items-center justify-center px-4">
        <div class="card w-full max-w-md bg-base-200 shadow-2xl">
            <form method="POST" action="{{ route('admin.login.store') }}" class="card-body space-y-4">
                @csrf
                <h1 class="text-2xl font-bold text-center">Sign in</h1>
                <p class="text-sm text-base-content/70 text-center">Use your admin username and password.</p>

                <div>
                    <label for="username" class="label mb-1">
                        <span class="label-text font-semibold">Username</span>
                    </label>
                    <input
                        id="username"
                        name="username"
                        type="text"
                        value="{{ old('username') }}"
                        required
                        autofocus
                        class="input input-bordered w-full"
                    />
                    @error('username')
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

                <div class="flex items-center justify-between">
                    <label class="label cursor-pointer gap-3">
                        <span class="label-text">Remember me</span>
                        <input type="checkbox" name="remember" value="1" class="toggle toggle-primary" {{ old('remember') ? 'checked' : '' }}>
                    </label>
                    <button type="submit" class="btn btn-primary">
                        Login
                    </button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.app.base>
