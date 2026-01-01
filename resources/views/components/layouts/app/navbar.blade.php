<header class="sticky top-0 z-30">
    <input type="checkbox" id="mobile-nav-toggle" class="hidden peer">
    <label for="mobile-nav-toggle" class="fixed inset-0 bg-black opacity-0 pointer-events-none peer-checked:opacity-20 peer-checked:pointer-events-auto lg:hidden transition"></label>

    <div class="navbar bg-base-100 shadow-sm w-full px-2">
        <div class="navbar-start">
            <div class="lg:hidden">
                <label for="mobile-nav-toggle" aria-label="open sidebar" class="btn btn-square btn-ghost">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-6 h-6 stroke-current"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </label>
            </div>
            <a class="text-[2rem] font-bold ml-3" href="/">LEXIQA</a>
        </div>
        <div class="navbar-center hidden lg:block">
            <ul class="menu menu-horizontal menu-lg">
                <li><a href="{{ route('home') }}" class="" data-home-link>Home</a></li>
                <li>
                    <details>
                        <summary>Browse</summary>
                        <ul class="p-4 mt-5 w-44 space-y-2">
                            <li><a href="{{ route('home') }}#room-types">Room Types</a></li>
                            <li><a href="{{ route('home') }}#locations">Locations</a></li>
                        </ul>
                    </details>
                </li>
                <li><a class="btn btn-primary" href="{{ route('booking.start') }}">Book Now</a></li>
                @auth
                    <li><a href="{{ route('bookings.index') }}">My Bookings</a></li>
                @endauth
                <li><a href="/" class="">About</a></li>
            </ul>
        </div>
        <div class="navbar-end flex items-center gap-2">
            @guest
                <a href="{{ route('login') }}" class="btn btn-success btn-ghost">Log in</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-ghost">Register</a>
            @endguest
            @auth
                <a class="btn btn-primary mr-3 lg:hidden" href="{{ route('booking.start') }}">Book Now</a>
                <div class="dropdown dropdown-end">
                    <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
                        <div class="w-10 rounded-full">
                            @php
                                $user = auth()->user();
                                $altText = $user->name;

                                // Fetch avatar from DB (CustomerUser) using the authenticated user id.
                                // We do this explicitly because the User->customer relation may not be defined.
                                $customer = \App\Models\CustomerUser::where('user_id', $user->id)->first();
                                $avatarUrl = $customer?->avatar;

                                // Fallback avatar (same logic as settings page)
                                $fallbackUrl = 'https://ui-avatars.com/api/?name=' . urlencode($altText) . '&background=random&color=fff';

                                // If no avatar in DB, start with fallback
                                if (!$avatarUrl) {
                                    $avatarUrl = $fallbackUrl;
                                }

                                // JS onerror fallback (in case stored file is missing/forbidden)
                                $oneError = 'this.onerror=null;this.src="' . $fallbackUrl . '";';
                            @endphp
                            <img
                            src="{{ $avatarUrl }}"
                            alt="{{ $altText }}"
                            onerror="{!! $oneError !!}"
                            />
                        </div>
                    </div>
                    <ul tabindex="0" class="menu menu-md dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52">
                        <div class="p-3">
                            <p class="font-bold">{{ auth()->user()->name }}</p>
                        </div>
                        <li>
                            <a href="{{ route('profile.edit') }}">
                                Profile
                            </a>
                        </li>
                        <li><a href="{{ route('bookings.index') }}">My Bookings</a></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-red-600">Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>
            @endauth
        </div>
    </div>

    <div class="lg:hidden absolute w-full bg-base-200 max-h-0 peer-checked:max-h-screen overflow-hidden transition-all duration-300 ease-in-out">
        <ul class="menu p-4 w-full">
            <li><a href="{{ route('home') }}" data-home-link>Home</a></li>
            <li><a href="{{ route('home') }}#room-types">Room Types</a></li>
            <li><a href="{{ route('home') }}#locations">Locations</a></li>
            @auth
                <li><a href="{{ route('bookings.index') }}">My Bookings</a></li>
            @endauth
            <li><a href="/">About</a></li>
        </ul>
    </div>
</header>
