<x-layouts.app.base :title="'Checkout ' . $reservation->code">
    <livewire:booking-payment :reservation="$reservation" />
</x-layouts.app.base>
