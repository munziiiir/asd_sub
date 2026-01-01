<?php

namespace Tests\Feature\Staff;

use App\Models\Charge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffBillingModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function makeBillingContext(): array
    {
        $hotel = $this->createHotel();
        $roomType = $this->createRoomType($hotel);
        $room = $this->createRoom($hotel, $roomType);
        $customer = $this->createCustomerUser();
        $reservation = $this->createReservationWithRoom($hotel, $customer, $room, [
            'status' => 'Confirmed',
            'nightly_rate' => 150,
        ]);
        $staff = $this->createStaff('frontdesk', $hotel);

        return [$staff, $reservation];
    }

    public function test_staff_can_view_folio_index_and_details(): void
    {
        [$staff, $reservation] = $this->makeBillingContext();

        $this->actingAs($staff, 'staff')
            ->get(route('staff.billing.index'))
            ->assertOk()
            ->assertSee($reservation->code);

        $this->actingAs($staff, 'staff')
            ->get(route('staff.billing.show', $reservation))
            ->assertOk()
            ->assertSee($reservation->code);
    }

    public function test_staff_can_add_charge_to_open_folio(): void
    {
        [$staff, $reservation] = $this->makeBillingContext();

        $this->actingAs($staff, 'staff')
            ->post(route('staff.billing.charges.store', $reservation), [
                'charge_code' => 'spa_access',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('charges', [
            'description' => 'Spa Access (Per Person, Per Day)',
        ]);
    }

    public function test_staff_cannot_add_duplicate_charge(): void
    {
        [$staff, $reservation] = $this->makeBillingContext();

        $this->actingAs($staff, 'staff')
            ->post(route('staff.billing.charges.store', $reservation), ['charge_code' => 'spa_access'])
            ->assertRedirect();

        $this->actingAs($staff, 'staff')
            ->post(route('staff.billing.charges.store', $reservation), ['charge_code' => 'spa_access'])
            ->assertSessionHasErrors('charge_code');
    }

    public function test_staff_cannot_add_charge_to_closed_folio(): void
    {
        [$staff, $reservation] = $this->makeBillingContext();
        $folio = $reservation->folios()->firstOrCreate([
            'folio_no' => $reservation->code . '-F-0001',
            'status' => 'Closed',
        ]);

        $this->actingAs($staff, 'staff')
            ->post(route('staff.billing.charges.store', $reservation), ['charge_code' => 'spa_access'])
            ->assertSessionHasErrors();

        $this->assertEquals('Closed', $folio->fresh()->status);
    }
}
