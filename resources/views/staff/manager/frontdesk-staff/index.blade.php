<x-layouts.app.base :title="'Front Desk Team'">
    <x-slot name="header">
        <x-staff.header
            title="Front Desk Hub"
            titleColor="secondary"
        >
            <x-slot name="actions">
                <a href="{{ route('staff.frontdesk') }}" class="btn btn-ghost btn-sm md:btn-md">
                    &larr; Back to hub
                </a>
            </x-slot>
        </x-staff.header>
    </x-slot>

    @php
        $statusMeta = [
            'active' => ['label' => 'Active', 'class' => 'badge-success'],
            'inactive' => ['label' => 'Inactive', 'class' => 'badge-ghost'],
            'on_leave' => ['label' => 'On leave', 'class' => 'badge-warning'],
        ];
    @endphp

    <div class="bg-base-200 pt-4">
        <div class="mx-auto flex max-w-6xl items-center gap-2 px-4 text-sm text-base-content/70 flex-wrap">
            <a href="{{ route('staff.frontdesk') }}" class="link text-secondary font-semibold">Front Desk Hub</a>
            <span class="text-base-content/50">→</span>
            <span>Manager</span>
            <span class="text-base-content/50">→</span>
            <span>Front desk staff</span>
        </div>
    </div>

    <section class="bg-base-200 py-6">
        <div class="mx-auto max-w-6xl px-4 space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Front desk staff</h1>
                    <p class="text-sm text-base-content/70">Manage front desk staff for {{ $hotelName ?? 'your hotel' }}.</p>
                </div>
                <a href="{{ route('staff.manager.frontdesk-staff.create') }}" class="btn btn-primary btn-sm md:btn-md">
                    Add staff
                </a>
            </div>

            <form method="GET" action="{{ route('staff.manager.frontdesk-staff.index') }}" class="card bg-base-100 shadow overflow-x-auto">
                <div class="card-body">
                    <div class="flex flex-nowrap items-end gap-4 min-w-[720px]">
                        <label class="flex flex-col gap-1 flex-1 min-w-[320px]">
                            <span class="label-text font-semibold">Search</span>
                            <input
                                id="search"
                                type="search"
                                name="search"
                                value="{{ $filters['search'] ?? '' }}"
                                class="input input-bordered w-full"
                                placeholder="Search by name or email"
                            >
                        </label>
                        <label class="flex flex-col gap-1 w-56">
                            <span class="label-text font-semibold">Employment status</span>
                            <select name="status" class="select select-bordered w-full">
                                <option value="">Employment status</option>
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm md:btn-md">Apply</button>
                            @if (($filters['search'] ?? '') || ($filters['status'] ?? null))
                                <a href="{{ route('staff.manager.frontdesk-staff.index') }}" class="btn btn-ghost btn-sm md:btn-md">Reset</a>
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            <div class="card bg-base-100 shadow">
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Last login</th>
                                    <th class="text-right"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($staffUsers as $staff)
                                    <tr>
                                        <td class="font-semibold">{{ $staff->name }}</td>
                                        <td>{{ $staff->email }}</td>
                                        <td>
                                            @php
                                                $status = $staff->employment_status;
                                                $badge = $status ? ($statusMeta[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)), 'class' => 'badge-ghost']) : null;
                                            @endphp
                                            @if ($badge)
                                                <span class="badge badge-sm {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-sm text-base-content/70">
                                            {{ $staff->last_login_at ? $staff->last_login_at->diffForHumans() : 'Never' }}
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('staff.manager.frontdesk-staff.edit', $staff) }}" class="btn btn-xs">Edit</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-base-content/70">No front desk accounts yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($staffUsers instanceof \Illuminate\Pagination\AbstractPaginator)
                        <div class="p-4">
                            {{ $staffUsers->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.app.base>
