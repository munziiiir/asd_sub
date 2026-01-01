<?php

namespace Tests\Feature\Staff;

use App\Livewire\Staff\CheckIo\CheckOutForm;
use App\Models\Charge;
use App\Models\Folio;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckOutFormTest extends TestCase
{
    use RefreshDatabase;

    protected function makeReservationDueToday(): array
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType, ['number' => '101']);
        $customer = $this->createCustomerUser();

        $reservation = $this->createReservationWithRoom($hotel, $customer, $room, [
            'status' => 'CheckedIn',
            'check_in_date' => now()->subDays(2)->toDateString(),
            'check_out_date' => now()->toDateString(),
            'nightly_rate' => 180,
        ]);

        return [$hotel, $roomType, $room, $reservation];
    }

    public function test_checked_in_departures_for_today_are_listed_for_staff_hotel(): void
    {
        [$hotel, , , $reservation] = $this->makeReservationDueToday();
        $staff = $this->createStaff('frontdesk', $hotel);

        Livewire::actingAs($staff, 'staff')
            ->test(CheckOutForm::class)
            ->assertSet('hotelId', $hotel->id)
            ->assertSee($reservation->code)
            ->assertSee('Select checked-in guest')
            ->assertSet('departureOptions.0.code', $reservation->code);
    }

    public function test_departures_from_other_hotels_are_not_listed(): void
    {
        [$hotel, , , $reservation] = $this->makeReservationDueToday();
        $otherHotel = $this->createHotel();
        $otherRoomType = $this->createRoomType($otherHotel);
        $otherRoom = $this->createRoom($otherHotel, $otherRoomType, ['number' => '202']);
        $otherCustomer = $this->createCustomerUser();
        $otherReservation = $this->createReservationWithRoom($otherHotel, $otherCustomer, $otherRoom, [
            'status' => 'CheckedIn',
            'check_in_date' => now()->subDays(1)->toDateString(),
            'check_out_date' => now()->toDateString(),
        ]);

        $staff = $this->createStaff('frontdesk', $hotel);

        Livewire::actingAs($staff, 'staff')
            ->test(CheckOutForm::class)
            ->assertSet('departureOptions.0.code', $reservation->code)
            ->assertDontSee($otherReservation->code); // Verify other hotel's reservation is not listed
    }

    public function test_save_closes_balanced_folio_without_extra_payment(): void
    {
        [$hotel, , $room, $reservation] = $this->makeReservationDueToday();
        $reservation->update(['status' => 'CheckedIn']);

        $folio = Folio::create([
            'reservation_id' => $reservation->id,
            'folio_no' => $reservation->code . '-F-0001',
            'status' => 'Open',
        ]);

        // Balanced ledger: 100 in charges, 100 in payments.
        Charge::create([
            'folio_id' => $folio->id,
            'post_date' => now()->toDateString(),
            'description' => 'Test room charge',
            'qty' => 1,
            'unit_price' => 100,
            'tax_amount' => 0,
            'total_amount' => 100,
        ]);

        Payment::create([
            'folio_id' => $folio->id,
            'method' => 'Card',
            'amount' => 100,
            'txn_ref' => 'TEST-TXN',
            'paid_at' => now(),
        ]);

        $staff = $this->createStaff('frontdesk', $hotel);

        Livewire::actingAs($staff, 'staff')
            ->test(CheckOutForm::class)
            ->set('reservationId', $reservation->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('check_outs', [
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
        ]);

        $this->assertEquals('CheckedOut', Reservation::find($reservation->id)->status);
        $this->assertEquals('Closed', $folio->fresh()->status);
    }

    public function test_save_requires_payment_method_when_balance_due(): void
    {
        [$hotel, , , $reservation] = $this->makeReservationDueToday();
        $reservation->update(['status' => 'CheckedIn']);

        $folio = Folio::create([
            'reservation_id' => $reservation->id,
            'folio_no' => $reservation->code . '-F-0002',
            'status' => 'Open',
        ]);

        Charge::create([
            'folio_id' => $folio->id,
            'post_date' => now()->toDateString(),
            'description' => 'Balance due',
            'qty' => 1,
            'unit_price' => 50,
            'tax_amount' => 0,
            'total_amount' => 50,
        ]);

        $staff = $this->createStaff('frontdesk', $hotel);

        Livewire::actingAs($staff, 'staff')
            ->test(CheckOutForm::class)
            ->set('reservationId', $reservation->id)
            ->call('save')
            ->assertHasErrors(['finalPaymentMethod' => 'required']);
    }
}
