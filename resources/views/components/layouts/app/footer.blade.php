<footer class="bg-base-200 text-base-content mt-16">
    <div class="max-w-6xl mx-auto px-6 py-12 grid grid-cols-1 md:grid-cols-4 gap-8">
        <div class="space-y-2">
            <h3 class="text-xl font-bold">Lexiqa</h3>
            <p class="text-sm text-base-content/70">Effortless hotel operations from bookings to front desk.</p>
        </div>
        <div>
            <h4 class="font-semibold mb-3">Explore</h4>
            <ul class="space-y-2 text-sm">
                <li><a href="{{ route('home') }}#room-types" class="link">Room Types</a></li>
                <li><a href="{{ route('home') }}#locations" class="link">Locations</a></li>
                <li><a href="/hotels" class="link">All Hotels</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold mb-3">Company</h4>
            <ul class="space-y-2 text-sm">
                <li><a href="/about" class="link">About</a></li>
                <li><a href="/careers" class="link">Careers</a></li>
                <li><a href="/contact" class="link">Contact</a></li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold mb-3">Get in touch</h4>
            <ul class="space-y-2 text-sm">
                <li><a href="mailto:hello@lexiqa.test" class="link">hello@lexiqa.test</a></li>
                <li><a href="tel:+440000000000" class="link">+44 00 0000 0000</a></li>
                <li class="flex gap-3">
                    <a href="https://twitter.com" class="link">Twitter</a>
                    <a href="https://www.linkedin.com" class="link">LinkedIn</a>
                    <a href="https://www.instagram.com" class="link">Instagram</a>
                </li>
            </ul>
        </div>
    </div>
    <div class="border-t border-base-300/70">
        <div class="max-w-6xl mx-auto px-6 py-4 text-sm text-base-content/60 flex flex-wrap justify-between gap-3">
            <span>© {{ date('Y') }} Lexiqa. All rights reserved.</span>
            <span class="space-x-4">
                <a href="/terms" class="link">Terms</a>
                <a href="/privacy" class="link">Privacy</a>
            </span>
        </div>
    </div>
</footer>
