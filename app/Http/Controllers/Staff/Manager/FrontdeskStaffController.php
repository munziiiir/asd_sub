<?php

namespace App\Http\Controllers\Staff\Manager;

use App\Http\Controllers\Controller;
use App\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FrontdeskStaffController extends Controller
{
    private int $perPage = 10;

    /** @var array<string,string> */
    private array $statusOptions = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'on_leave' => 'On leave',
    ];

    public function index(Request $request): View
    {
        $manager = $this->manager($request);

        $search = (string) $request->string('search');
        $status = (string) $request->string('status');

        $query = StaffUser::query()
            ->where('hotel_id', $manager->hotel_id)
            ->where('role', 'frontdesk');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        if (array_key_exists($status, $this->statusOptions)) {
            $query->where('employment_status', $status);
        }

        $staffUsers = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($this->perPage)
            ->withQueryString();

        return view('staff.manager.frontdesk-staff.index', [
            'staffUsers' => $staffUsers,
            'statusOptions' => $this->statusOptions,
            'filters' => [
                'search' => $search,
                'status' => $status ?: null,
            ],
            'hotelName' => $manager->hotel?->name,
        ]);
    }

    public function create(Request $request): View
    {
        $manager = $this->manager($request);

        return view('staff.manager.frontdesk-staff.create', [
            'statusOptions' => $this->statusOptions,
            'hotelName' => $manager->hotel?->name,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $manager = $this->manager($request);

        $staffUser = new StaffUser();
        $staffUser->forceFill($this->validatedData($request, $manager))->save();

        $page = $this->pageForStaff($manager, $staffUser);

        return redirect()
            ->route('staff.manager.frontdesk-staff.index', ['page' => $page])
            ->with('status', 'Front desk staff account created.');
    }

    public function edit(Request $request, StaffUser $staff): View
    {
        $manager = $this->manager($request);
        $this->ensureManagedStaff($manager, $staff);

        return view('staff.manager.frontdesk-staff.edit', [
            'staffUser' => $staff,
            'statusOptions' => $this->statusOptions,
            'hotelName' => $manager->hotel?->name,
        ]);
    }

    public function update(Request $request, StaffUser $staff): RedirectResponse
    {
        $manager = $this->manager($request);
        $this->ensureManagedStaff($manager, $staff);

        $staff->forceFill($this->validatedData($request, $manager, $staff))->save();
        $page = $this->pageForStaff($manager, $staff);

        return redirect()
            ->route('staff.manager.frontdesk-staff.index', ['page' => $page])
            ->with('status', 'Front desk staff account updated.');
    }

    private function manager(Request $request): StaffUser
    {
        $manager = $request->user('staff');

        abort_unless($manager && $manager->role === 'manager', 403);

        return $manager;
    }

    private function ensureManagedStaff(StaffUser $manager, StaffUser $staff): void
    {
        if ($staff->hotel_id !== $manager->hotel_id || $staff->role !== 'frontdesk') {
            abort(404);
        }
    }

    private function validatedData(Request $request, StaffUser $manager, ?StaffUser $staffUser = null): array
    {
        $statuses = array_keys($this->statusOptions);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('staff_users', 'email')
                    ->ignore($staffUser?->id)
                    ->where(fn ($query) => $query->where('hotel_id', $manager->hotel_id)),
            ],
            'password' => [
                $staffUser ? 'nullable' : 'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
            'employment_status' => ['required', 'string', 'max:50', Rule::in($statuses)],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
            unset($data['last_password_changed_at']);
        } else {
            $data['password'] = Hash::make($data['password']);
            $data['last_password_changed_at'] = now();
        }

        return array_merge($data, [
            'hotel_id' => $manager->hotel_id,
            'role' => 'frontdesk',
        ]);
    }

    private function pageForStaff(StaffUser $manager, StaffUser $staffUser): int
    {
        $base = StaffUser::where('hotel_id', $manager->hotel_id)
            ->where('role', 'frontdesk');

        $beforeCount = (clone $base)
            ->where('name', '<', $staffUser->name)
            ->count();

        $tieBreakerCount = (clone $base)
            ->where('name', $staffUser->name)
            ->where('id', '<', $staffUser->id)
            ->count();

        $position = $beforeCount + $tieBreakerCount;

        return (int) floor($position / $this->perPage) + 1;
    }
}
