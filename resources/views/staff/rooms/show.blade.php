<x-layouts.app.base :title="'Room '.$room->number">
    <x-slot name="header">
        <x-staff.header
            title="Room {{ $room->number }}"
            titleColor="primary"
            description="{{ $room->roomType?->name ?? 'Room details' }}"
        >
            <x-slot name="actions">
                <a href="{{ route('staff.rooms.index') }}" class="btn btn-ghost btn-sm md:btn-md">
                    &larr; Back to rooms
                </a>
            </x-slot>
        </x-staff.header>
    </x-slot>

    <section class="bg-base-200 py-8">
        @php
            $activeTz = $viewerTimezone ?? $hotelTimezone ?? config('app.timezone');
        @endphp
        <div class="mx-auto max-w-5xl px-4 space-y-6">
            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm text-base-content/70">Room number</p>
                            <p class="text-xl font-semibold">{{ $room->number }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-outline">{{ $room->status ?? 'Unknown' }}</span>
                            @if ($pendingCleaning && $pendingCleaning->revert_at)
                                <span class="badge badge-ghost text-xs">
                                    Reverts {{ $pendingCleaning->revert_at?->timezone($activeTz)?->format('M d, Y g:i A') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-sm text-base-content/70">Type</p>
                            <p class="font-medium">{{ $room->roomType?->name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-base-content/70">Floor</p>
                            <p class="font-medium">{{ $room->floor ?? '—' }}</p>
                        </div>
                    </div>

                    @if ($currentReservation)
                        <div class="alert alert-outline alert-info">
                            <div>
                                <p class="font-semibold">Linked reservation</p>
                                <p class="text-sm text-base-content/70 flex flex-col gap-1">
                                    <span>
                                        {{ $currentReservation->code }}
                                        @if ($currentReservation->customer?->name)
                                            · {{ $currentReservation->customer->name }}
                                        @endif
                                        — <a class="link" href="{{ route('staff.reservations.show', $currentReservation) }}">view reservation</a>
                                    </span>
                                    <span>
                                        {{ $currentReservation->check_in_date?->format('M d') }} → {{ $currentReservation->check_out_date?->format('M d') }}
                                        ({{ $currentReservation->status }})
                                    </span>
                                </p>
                            </div>
                        </div>
                    @endif

                    @if ($lastOccupied && (! $currentReservation || $lastOccupied->id !== $currentReservation->id))
                        <div class="alert alert-ghost">
                            <div>
                                <p class="font-semibold">Last occupied</p>
                                <p class="text-sm text-base-content/70">
                                    {{ $lastOccupied->code }}
                                    @if ($lastOccupied->customer?->name)
                                        · {{ $lastOccupied->customer->name }}
                                    @endif
                                    — <a class="link" href="{{ route('staff.reservations.show', $lastOccupied) }}">view reservation</a>
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-5">
                    <div>
                        <h2 class="card-title">Housekeeping actions</h2>
                        <p class="text-sm text-base-content/70">Manual changes allowed: Cleaning or Out of Service. All other states are system-managed.</p>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-error">
                            <div class="flex flex-col">
                                <span class="font-semibold">Unable to update</span>
                                <ul class="list-disc list-inside text-sm">
                                    @foreach ($errors->all() as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="rounded-lg border border-base-200 p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-semibold">Set to Cleaning</p>
                                    <p class="text-sm text-base-content/70">Will auto-revert to the previous status at end of day.</p>
                                </div>
                            </div>

                            @if ($cleaningBlockedReason)
                                <div class="alert alert-warning">
                                    <div>{{ $cleaningBlockedReason }}</div>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('staff.rooms.update', $room) }}" class="space-y-3">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status_action" value="cleaning">

                                <div class="form-control">
                                    <label for="assigned_staff_id" class="label">
                                        <span class="label-text font-semibold">Assign to</span>
                                    </label>
                                    <select
                                        id="assigned_staff_id"
                                        name="assigned_staff_id"
                                        class="select select-bordered w-full"
                                        @disabled(! $canMarkCleaning)
                                    >
                                        <option value="">Select staff</option>
                                        @foreach ($assignees as $assignee)
                                            <option value="{{ $assignee->id }}" @selected(old('assigned_staff_id') == $assignee->id)>
                                                {{ $assignee->name }} @if ($assignee->role === 'manager') (Manager) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('assigned_staff_id')
                                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="form-control">
                                    <label for="note_cleaning" class="label">
                                        <span class="label-text font-semibold">Notes (optional)</span>
                                    </label>
                                    <textarea
                                        id="note_cleaning"
                                        name="note"
                                        class="textarea textarea-bordered"
                                        rows="2"
                                        placeholder="E.g., Deep clean requested"
                                        @disabled(! $canMarkCleaning)
                                    >{{ old('note') }}</textarea>
                                    @error('note')
                                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <p class="text-sm text-base-content/70">
                                    Auto reverts
                                    @if ($pendingCleaning && $pendingCleaning->revert_at)
                                        at {{ $pendingCleaning->revert_at?->timezone($activeTz)?->format('M d, Y g:i A') }} ({{ $activeTz }})
                                    @else
                                        at end of {{ $viewerToday }} ({{ $activeTz }})
                                    @endif
                                    back to {{ $pendingCleaning?->revert_to_status ?? 'its prior status' }}.
                                </p>

                                <button
                                    type="submit"
                                    class="btn btn-primary w-full"
                                    @disabled(! $canMarkCleaning)
                                >
                                    Mark as Cleaning
                                </button>
                            </form>
                        </div>

                        <div class="rounded-lg border border-base-200 p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-semibold">Set to Out of Service</p>
                                    <p class="text-sm text-base-content/70">Only allowed from Available or Reserved.</p>
                                </div>
                            </div>

                            <p class="text-sm text-base-content/70">
                                If reserved, the system will try to shift the booking to another room of the same type. If no room is free, you&apos;ll need to move the reservation first.
                            </p>

                            <form method="POST" action="{{ route('staff.rooms.update', $room) }}" class="space-y-3">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status_action" value="out_of_service">

                                <div class="form-control">
                                    <label for="note_oos" class="label">
                                        <span class="label-text font-semibold">Notes (optional)</span>
                                    </label>
                                    <textarea
                                        id="note_oos"
                                        name="note"
                                        class="textarea textarea-bordered"
                                        rows="2"
                                        placeholder="Maintenance reason"
                                        @disabled(! $canMarkOutOfService)
                                    >{{ old('note') }}</textarea>
                                </div>

                                <button
                                    type="submit"
                                    class="btn btn-warning w-full"
                                    @disabled(! $canMarkOutOfService)
                                >
                                    Mark as Out of Service
                                </button>
                            </form>

                            @unless ($canMarkOutOfService)
                                <p class="text-sm text-error">Out of Service is only available when the room is Available or Reserved.</p>
                            @endunless
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="card-title">Status history</h2>
                            <p class="text-sm text-base-content/70">Recent manual changes for auditing.</p>
                        </div>
                        <span class="badge badge-ghost text-xs">Last {{ $statusLogs->count() }} entries</span>
                    </div>

                    @if ($statusLogs->isEmpty())
                        <p class="text-sm text-base-content/70">No status changes recorded yet.</p>
                    @else
                        <div class="divide-y divide-base-200">
                            @foreach ($statusLogs as $log)
                                <div class="grid gap-2 py-3 md:grid-cols-4 md:items-center">
                                    <div>
                                        <p class="font-semibold">
                                            {{ $log->created_at?->timezone($activeTz)?->format('M d, Y g:i A') }}
                                        </p>
                                        <p class="text-xs text-base-content/60">{{ $log->context ?? 'status change' }}</p>
                                    </div>
                                    <div>
                                        <p class="font-semibold">
                                            {{ $log->previous_status ?? '—' }} → {{ $log->new_status }}
                                        </p>
                                        @if ($log->reverted_at)
                                            <p class="text-xs text-base-content/60">
                                                Reverted {{ $log->reverted_at?->timezone($activeTz)?->format('M d, Y g:i A') }}
                                            </p>
                                        @elseif($log->revert_at)
                                            <p class="text-xs text-base-content/60">
                                                Reverts {{ $log->revert_at?->timezone($activeTz)?->format('M d, Y g:i A') }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="text-sm text-base-content/80">
                                        <p>By: {{ $log->changedBy?->name ?? 'System' }}</p>
                                        @if ($log->assignedStaff)
                                            <p>Assigned: {{ $log->assignedStaff->name }}</p>
                                        @endif
                                        @if ($log->reservation)
                                            <p>Reservation: {{ $log->reservation->code }}</p>
                                        @endif
                                    </div>
                                    <div class="text-sm text-base-content/80">
                                        <p>{{ $log->note ?? '—' }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.app.base>
