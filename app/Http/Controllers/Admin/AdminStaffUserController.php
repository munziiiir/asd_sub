<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\HandlesRowHighlighting;
use App\Http\Controllers\Admin\Concerns\HandlesSearch;
use App\Models\Hotel;
use App\Models\StaffUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminStaffUserController extends Controller
{
    use HandlesRowHighlighting;
    use HandlesSearch;

    private int $perPage = 10;

    public function index(Request $request): View
    {
        $roles = $this->roleOptions();
        $statuses = $this->statusOptions();

        $search = (string) $request->string('search');
        $roleFilter = (string) $request->string('role');
        $statusFilter = (string) $request->string('status');

        $roleFilter = array_key_exists($roleFilter, $roles) ? $roleFilter : null;
        $statusFilter = array_key_exists($statusFilter, $statuses) ? $statusFilter : null;

        $query = StaffUser::query()
            ->select('staff_users.*')
            ->with('hotel')
            ->leftJoin('hotels', 'hotels.id', '=', 'staff_users.hotel_id');

        if ($roleFilter) {
            $query->where('staff_users.role', $roleFilter);
        }

        if ($statusFilter) {
            $query->where('staff_users.employment_status', $statusFilter);
        }

        if ($search) {
            $this->applyFuzzySearch($query, $search, [
                'staff_users.name',
                'staff_users.email',
                'staff_users.role',
                'staff_users.employment_status',
                'hotels.name',
            ]);
        }

        $staffUsers = $query
            ->orderBy('staff_users.name')
            ->orderBy('staff_users.id')
            ->paginate($this->perPage)
            ->withQueryString();

        return view('admin.staffusers.index', [
            'staffUsers' => $staffUsers,
            'roles' => $roles,
            'statuses' => $statuses,
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.staffusers.create', [
            'hotels' => $this->hotels(),
            'roles' => $this->roleOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $staffUser = new StaffUser();
        $staffUser->forceFill($this->validatedData($request))->save();
        $page = $this->pageForStaffUser($staffUser);

        return $this->redirectWithHighlight(
            'admin.staffusers.index',
            ['page' => $page],
            $staffUser,
            'created',
            'Staff user created.'
        );
    }

    public function edit(StaffUser $staffuser): View
    {
        return view('admin.staffusers.edit', [
            'staffUser' => $staffuser,
            'hotels' => $this->hotels(),
            'roles' => $this->roleOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function update(Request $request, StaffUser $staffuser): RedirectResponse
    {
        $staffuser->forceFill($this->validatedData($request, $staffuser))->save();
        $page = $this->pageForStaffUser($staffuser);

        return $this->redirectWithHighlight(
            'admin.staffusers.index',
            ['page' => $page],
            $staffuser,
            'updated',
            'Staff user updated.'
        );
    }

    public function destroy(StaffUser $staffuser): RedirectResponse
    {
        $staffuser->delete();

        return redirect()
            ->route('admin.staffusers.index')
            ->with('status', 'Staff user removed.');
    }

    protected function validatedData(Request $request, ?StaffUser $staffUser = null): array
    {
        $roles = array_keys($this->roleOptions());
        $statuses = array_keys($this->statusOptions());

        $rules = [
            'hotel_id' => ['required', 'exists:hotels,id'],
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('staff_users', 'email')
                    ->ignore($staffUser?->id)
                    ->where(fn ($query) => $query->where('hotel_id', $request->input('hotel_id'))),
            ],
            'password' => [
                $staffUser ? 'nullable' : 'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
            'role' => ['required', 'string', 'max:100', Rule::in($roles)],
            'employment_status' => ['required', 'string', 'max:50', Rule::in($statuses)],
        ];

        $data = $request->validate($rules);

        if (empty($data['password'])) {
            unset($data['password']);
            unset($data['last_password_changed_at']);
        } else {
            $data['password'] = Hash::make($data['password']);
            $data['last_password_changed_at'] = now();
        }

        return $data;
    }

    protected function hotels()
    {
        return Hotel::orderBy('name')->get(['id', 'name']);
    }

    protected function roleOptions(): array
    {
        return [
            // 'admin' => 'Admin',
            'manager' => 'Manager',
            'frontdesk' => 'Front Desk',
        ];
    }

    protected function statusOptions(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'on_leave' => 'On leave',
        ];
    }

    private function pageForStaffUser(StaffUser $staffUser): int
    {
        $beforeCount = StaffUser::where('name', '<', $staffUser->name)->count();

        $tieBreakerCount = StaffUser::where('name', $staffUser->name)
            ->where('id', '<', $staffUser->id)
            ->count();

        $position = $beforeCount + $tieBreakerCount;

        return (int) floor($position / $this->perPage) + 1;
    }
}
