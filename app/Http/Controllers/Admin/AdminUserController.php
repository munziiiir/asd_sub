<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\HandlesRowHighlighting;
use App\Http\Controllers\Admin\Concerns\HandlesSearch;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    use HandlesRowHighlighting;
    use HandlesSearch;

    private int $perPage = 10;

    public function index(Request $request): View
    {
        $search = (string) $request->string('search');
        $statusFilter = (string) $request->string('status');
        $statusFilter = in_array($statusFilter, ['active', 'inactive'], true) ? $statusFilter : null;

        $query = AdminUser::query()->select('admin_users.*');

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search) {
            $this->applyFuzzySearch($query, $search, [
                'admin_users.name',
                'admin_users.username',
                "CASE WHEN admin_users.is_active = 1 THEN 'active' ELSE 'inactive' END",
            ]);
        }

        $admins = $query
            ->orderBy('admin_users.username')
            ->orderBy('admin_users.id')
            ->paginate($this->perPage)
            ->withQueryString();

        return view('admin.users.index', [
            'admins' => $admins,
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['last_password_changed_at'] = now();

        $admin = AdminUser::create($data);
        $page = $this->pageForAdminUser($admin);

        return $this->redirectWithHighlight(
            'admin.users.index',
            ['page' => $page],
            $admin,
            'created',
            'Admin user created.'
        );
    }

    public function edit(AdminUser $admin_user): View
    {
        return view('admin.users.edit', ['user' => $admin_user]);
    }

    public function update(UpdateAdminUserRequest $request, AdminUser $admin_user): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            $data['last_password_changed_at'] = now();
        } else {
            unset($data['password']);
            unset($data['last_password_changed_at']);
        }

        $data['is_active'] = $request->boolean('is_active', true);

        if ($admin_user->is($request->user('admin')) && $data['is_active'] === false) {
            return back()
                ->withInput()
                ->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        $admin_user->update($data);
        $page = $this->pageForAdminUser($admin_user);

        return $this->redirectWithHighlight(
            'admin.users.index',
            ['page' => $page],
            $admin_user,
            'updated',
            'Admin user updated.'
        );
    }

    public function destroy(Request $request, AdminUser $admin_user): RedirectResponse
    {
        if ($admin_user->is($request->user('admin'))) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $admin_user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Admin user removed.');
    }

    private function pageForAdminUser(AdminUser $adminUser): int
    {
        $beforeCount = AdminUser::where('username', '<', $adminUser->username)->count();

        $tieBreakerCount = AdminUser::where('username', $adminUser->username)
            ->where('id', '<', $adminUser->id)
            ->count();

        $position = $beforeCount + $tieBreakerCount;

        return (int) floor($position / $this->perPage) + 1;
    }
}
