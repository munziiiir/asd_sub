<?php

use App\Models\CustomerUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app.base')] class extends Component {
    public ?CustomerUser $customer = null;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $avatarUrl = '';

    public array $editing = [
        'name' => false,
        'email' => false,
        'password' => false,
        'phone' => false,
    ];

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->customer = CustomerUser::firstOrCreate(['user_id' => $user->id], ['name' => $user->name, 'email' => $user->email]);

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) ($this->customer->phone ?? '');
        $this->avatarUrl = (string) ($this->customer->avatar ?? '');
    }

    public function toggle(string $field): void
    {
        if (!array_key_exists($field, $this->editing)) {
            return;
        }

        $this->editing[$field] = !$this->editing[$field];

        if (!$this->editing[$field] && $field === 'password') {
            $this->reset('current_password', 'password', 'password_confirmation');
        }
    }

    public function saveName(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $user->fill(['name' => $validated['name']])->save();
        $this->customer?->fill(['name' => $validated['name']])->save();

        $this->editing['name'] = false;
        $this->dispatch('account-updated');
    }

    public function saveEmail(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill(['email' => $validated['email']]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->customer?->fill(['email' => $validated['email']])->save();

        $this->editing['email'] = false;
        $this->dispatch('account-updated');
    }

    public function savePhone(): void
    {
        $validated = $this->validate([
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $this->customer?->fill(['phone' => $this->nullable($validated['phone'])])->save();
        $this->editing['phone'] = false;
        $this->dispatch('account-updated');
    }

    public function savePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->editing['password'] = false;

        $this->dispatch('password-updated');
    }

    public function saveAvatar(string $dataUrl): void
    {
        if (!str_starts_with($dataUrl, 'data:image/')) {
            return;
        }

        [$meta, $content] = explode(',', $dataUrl, 2);
        $mime = str_replace(['data:', ';base64'], '', $meta);

        $extension = match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            default => 'png',
        };

        $binary = base64_decode($content);

        $filename = 'avatars/user-' . Auth::id() . '-' . now()->timestamp . '.' . $extension;
        Storage::disk('public')->put($filename, $binary);

        $url = Storage::url($filename);

        $this->customer ??= CustomerUser::firstOrCreate(['user_id' => Auth::id()]);
        $this->customer->forceFill(['avatar' => $url])->save();

        $this->avatarUrl = $url;
        $this->dispatch('avatar-updated', url: $url);
    }

    public function deleteAvatar(): void
    {
        $this->customer ??= CustomerUser::firstOrCreate(['user_id' => Auth::id()]);

        $current = (string) ($this->customer->avatar ?? '');

        // If the current avatar is a local /storage/... URL, attempt to delete the underlying file.
        if ($current !== '' && str_starts_with($current, '/storage/')) {
            $path = ltrim(str_replace('/storage/', '', $current), '/');
            Storage::disk('public')->delete($path);
        }

        $this->customer->forceFill(['avatar' => null])->save();
        $this->avatarUrl = '';

        $this->dispatch('avatar-updated', url: '');
    }

    private function nullable(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}; ?>

<x-settings.layout :customer="$customer" :heading="__('Account')" :subheading="__('Review your profile, avatar, and sign-in security')">
    <div class="space-y-6">
        <div class="bg-base-100 border border-base-300/60 rounded-2xl p-4 space-y-4">
            <div class="flex flex-col items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-base-content">Profile photo</h2>
                    <p class="text-sm text-base-content/70">Upload a photo and crop it to fit.</p>
                </div>

                <div x-data="avatarCropper(@js($avatarUrl))" class="w-full" x-cloak>
                    <div class="flex items-end justify-between w-full gap-6">
                        <!-- Actions under the heading -->
                        <template x-if="preview">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" class="btn btn-ghost btn-sm" @click="triggerFile">Change photo</button>
                                <button type="button" class="btn btn-ghost btn-sm text-error" @click="clearPhoto">Remove photo</button>
                            </div>
                        </template>

                        <template x-if="!preview">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" class="btn btn-primary btn-sm" @click="triggerFile">Upload photo</button>
                                <span class="text-xs text-base-content/60">(PNG, JPG, GIF)</span>
                            </div>
                        </template>

                        <div class="flex items-center gap-2 text-xs text-base-content/60">
                            <x-action-message on="avatar-updated" class="text-success">Saved.</x-action-message>
                            <span wire:loading wire:target="saveAvatar,deleteAvatar" class="loading loading-dots loading-xs text-primary"></span>
                        </div>

                        <!-- Preview aligned to the right edge of the card (always shown) -->
                        <div class="shrink-0">
                            <template x-if="preview">
                                <div class="w-16 h-16 rounded-full overflow-hidden border border-base-300/70">
                                    <img :src="preview" alt="Profile photo" class="w-full h-full object-cover">
                                </div>
                            </template>
                            <template x-if="!preview">
                                <div class="w-16 h-16 rounded-full overflow-hidden border border-base-300/70">
                                    @php
                                        $avatar = $customer?->avatar ?: "https://ui-avatars.com/api/?name=" . urlencode($customer?->name ?? $user?->name ?? '') . "&background=0D8ABC&color=fff";
                                    @endphp
                                    <img src="{{ $avatar }}" alt="Profile photo" class="w-full h-full object-cover">
                                </div>
                            </template>
                        </div>
                    </div>

                    <input type="file" accept="image/*" class="hidden" x-ref="fileInput" @change="onFileChange">

                    <template x-if="showModal">
                        <div class="fixed inset-0 z-50 flex items-center justify-center px-4"
                            @keydown.escape.window="closeModal">
                            <div class="absolute inset-0 bg-base-content/60" @click="closeModal"></div>
                            <div class="relative bg-base-100 border border-base-300/70 rounded-2xl shadow-xl max-w-3xl w-full p-4 md:p-6 focus:outline-none"
                                tabindex="-1" x-ref="modal">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-base-content">Crop your photo</h3>
                                        <p class="text-sm text-base-content/70">Drag to reposition, zoom to fit the
                                            circular mask.</p>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-[1fr,240px] md:items-center">
                                    <div class="relative mx-auto w-full max-w-[520px] aspect-square bg-base-200 rounded-2xl overflow-hidden"
                                        @mousedown="startDrag" @mousemove="onDrag" @mouseup="stopDrag"
                                        @mouseleave="stopDrag" @wheel.prevent="onWheel"
                                        @touchstart.prevent="onTouchStart" @touchmove.prevent="onTouchMove"
                                        @touchend.prevent="onTouchEnd" data-crop-area>
                                        <template x-if="modalPreview">
                                            <img :src="modalPreview" alt="Crop preview"
                                                class="absolute select-none max-w-none max-h-none"
                                                :style="`width:${displayWidth()}px; height:${displayHeight()}px; left:50%; top:50%; transform: translate(-50%, -50%) translate(${offsetX}px, ${offsetY}px);`"
                                                draggable="false" />
                                        </template>
                                        <div
                                            class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                            <div class="absolute rounded-xl border-2 border-dashed border-primary/40"
                                                :style="`width:${cropSize}px; height:${cropSize}px;`"></div>
                                            <div class="relative rounded-full border-2 border-primary/70"
                                                :style="`width:${cropSize}px; height:${cropSize}px;`">
                                                <div
                                                    class="absolute inset-0 rounded-full border border-primary/30 shadow-[0_0_0_999px_rgba(0,0,0,0.55)]">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <p class="text-sm text-base-content/70">Scroll or pinch to zoom. Drag to
                                            reposition.</p>
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="btn btn-primary" @click="save"
                                                :disabled="saving">Save</button>
                                            <button type="button" class="btn btn-ghost"
                                                @click="closeModal">Cancel</button>
                                            <span class="loading loading-dots loading-sm text-primary"
                                                x-show="saving"></span>
                                        </div>
                                        <p class="text-xs text-error" x-text="error" x-show="error"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="bg-base-100 border border-base-300/60 rounded-2xl p-4 space-y-4">
            <div class="flex flex-col items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-base-content">Identity</h2>
                    <p class="text-sm text-base-content/70">Name and email appear on bookings and receipts.</p>
                </div>

                <div class="divide-y divide-base-300/70 w-full">
                    <div class="py-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-base-content/70">Full name</p>
                            <p class="text-base font-semibold text-base-content">{{ $name }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($editing['name'])
                                <input type="text" wire:model="name" class="input input-sm input-bordered"
                                    autocomplete="name">
                                <button type="button" class="btn btn-primary btn-sm"
                                    wire:click="saveName">Save</button>
                                <button type="button" class="btn btn-ghost btn-sm"
                                    wire:click="toggle('name')">Cancel</button>
                            @else
                                <button type="button" class="btn btn-ghost btn-sm"
                                    wire:click="toggle('name')">Edit</button>
                            @endif
                        </div>
                    </div>

                    <div class="py-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-base-content/70">Email</p>
                            <p class="text-base font-semibold text-base-content">{{ $email }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($editing['email'])
                                <input type="email" wire:model="email" class="input input-sm input-bordered"
                                    autocomplete="email">
                                <button type="button" class="btn btn-primary btn-sm"
                                    wire:click="saveEmail">Save</button>
                                <button type="button" class="btn btn-ghost btn-sm"
                                    wire:click="toggle('email')">Cancel</button>
                            @else
                                <button type="button" class="btn btn-ghost btn-sm"
                                    wire:click="toggle('email')">Edit</button>
                            @endif
                        </div>
                    </div>

                    <div class="py-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-base-content/70">Phone</p>
                            <p class="text-base font-semibold text-base-content">{{ $phone ?: 'Not set' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($editing['phone'])
                                <input type="text" wire:model.lazy="phone" class="input input-sm input-bordered"
                                    placeholder="+44 1234 567890" autocomplete="tel">
                                <button type="button" class="btn btn-primary btn-sm"
                                    wire:click="savePhone">Save</button>
                                <button type="button" class="btn btn-ghost btn-sm"
                                    wire:click="toggle('phone')">Cancel</button>
                            @else
                                <button type="button" class="btn btn-ghost btn-sm"
                                    wire:click="toggle('phone')">Edit</button>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 text-xs text-base-content/60">
                    <x-action-message on="account-updated" class="text-success">Saved.</x-action-message>
                    <span wire:loading wire:target="saveName,saveEmail,savePhone"
                        class="loading loading-dots loading-xs text-primary"></span>
                </div>
            </div>
        </div>

        <div class="bg-base-100 border border-base-300/60 rounded-2xl p-4 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-base-content">Password</h2>
                    <p class="text-sm text-base-content/70">Keep your sign-in secure.</p>
                </div>
                <span class="badge badge-outline text-xs">Secure</span>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-base-content/70">Current status</p>
                    <p class="font-semibold text-base-content">Password is set</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($editing['password'])
                        <div class="grid gap-2">
                            <input type="password" wire:model="current_password"
                                class="input input-sm input-bordered" placeholder="Current password"
                                autocomplete="current-password">
                            <input type="password" wire:model="password" class="input input-sm input-bordered"
                                placeholder="New password" autocomplete="new-password">
                            <input type="password" wire:model="password_confirmation"
                                class="input input-sm input-bordered" placeholder="Confirm new password"
                                autocomplete="new-password">
                            <div class="flex items-center gap-2">
                                <button type="button" class="btn btn-primary btn-sm"
                                    wire:click="savePassword">Save</button>
                                <button type="button" class="btn btn-ghost btn-sm"
                                    wire:click="toggle('password')">Cancel</button>
                            </div>
                        </div>
                    @else
                        <button type="button" class="btn btn-ghost btn-sm"
                            wire:click="toggle('password')">Change</button>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs text-base-content/60">
                <x-action-message on="password-updated" class="text-success">Password updated.</x-action-message>
                <span wire:loading wire:target="savePassword"
                    class="loading loading-dots loading-xs text-primary"></span>
            </div>
        </div>
    </div>
    </div>
</x-settings.layout>

<script>
    function avatarCropper(existing = '') {
        return {
            preview: existing || '',
            modalPreview: existing || '',
            naturalWidth: 0,
            naturalHeight: 0,
            zoom: 1,
            minZoom: 1,
            maxZoom: 3,
            offsetX: 0,
            offsetY: 0,
            dragging: false,
            start: {
                x: 0,
                y: 0
            },
            baseScale: 1,
            cropSize: 288,
            hasImage: !!existing,
            showModal: false,
            saving: false,
            error: '',
            reset() {
                this.preview = existing || '';
                this.modalPreview = existing || '';
                this.zoom = 1;
                this.minZoom = 1;
                this.offsetX = 0;
                this.offsetY = 0;
                this.dragging = false;
                this.hasImage = !!existing;
                this.showModal = false;
                this.saving = false;
                this.error = '';
            },
            triggerFile() {
                // Reset the input so selecting the same file again still fires a change event.
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                this.$refs.fileInput?.click();
            },
            onFileChange(event) {
                const [file] = event.target.files || [];
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    alert('Please choose an image file.');
                    return;
                }
                this.error = '';
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.modalPreview = e.target.result;
                    const img = new Image();
                    img.onload = () => {
                        this.naturalWidth = img.width;
                        this.naturalHeight = img.height;
                        const coverScale = Math.max(this.cropSize / img.width, this.cropSize / img.height);
                        // Slightly overscale by default so users have some pan room, similar to common mobile avatar croppers.
                        const slack = 1.15;
                        this.baseScale = coverScale * slack;
                        this.minZoom = this.computeMinZoom();
                        this.zoom = 1;
                        this.offsetX = 0;
                        this.offsetY = 0;
                        this.hasImage = true;
                        this.showModal = true;
                        this.$nextTick(() => {
                            this.$refs.modal?.focus();
                            // Wait for layout + image paint so clamping/centering uses the correct rendered sizes.
                            requestAnimationFrame(() => {
                                requestAnimationFrame(() => {
                                    this.clampOffsets();
                                });
                            });
                        });
                    };
                    img.onerror = () => {
                        this.error = 'Could not read this image.';
                    };
                    img.src = this.modalPreview;
                };
                reader.readAsDataURL(file);
            },
            displayWidth() {
                return this.naturalWidth * this.baseScale * this.zoom || this.cropSize;
            },
            displayHeight() {
                return this.naturalHeight * this.baseScale * this.zoom || this.cropSize;
            },
            computeMinZoom() {
                // Allow zooming out until the image would no longer fully cover the clamp box.
                // At the minimum, at least one dimension equals cropSize (two edges hit the clamp).
                const baseW = this.naturalWidth * this.baseScale; // rendered width at zoom=1
                const baseH = this.naturalHeight * this.baseScale; // rendered height at zoom=1
                if (!baseW || !baseH) return 0.01;
                return Math.max(this.cropSize / baseW, this.cropSize / baseH, 0.01);
            },
            startDrag(event) {
                if (!this.modalPreview) return;
                this.dragging = true;
                this.start = {
                    x: event.clientX ?? event.touches?.[0]?.clientX ?? 0,
                    y: event.clientY ?? event.touches?.[0]?.clientY ?? 0
                };
            },
            onDrag(event) {
                if (!this.dragging) return;
                const x = event.clientX ?? event.touches?.[0]?.clientX ?? 0;
                const y = event.clientY ?? event.touches?.[0]?.clientY ?? 0;
                this.offsetX += x - this.start.x;
                this.offsetY += y - this.start.y;
                this.start = {
                    x,
                    y
                };
                this.clampOffsets();
            },
            stopDrag() {
                this.dragging = false;
            },
            clampOffsets() {
                const dispW = this.displayWidth();
                const dispH = this.displayHeight();
                const limitX = Math.max(0, (dispW - this.cropSize) / 2);
                const limitY = Math.max(0, (dispH - this.cropSize) / 2);
                this.offsetX = Math.min(limitX, Math.max(-limitX, this.offsetX));
                this.offsetY = Math.min(limitY, Math.max(-limitY, this.offsetY));
            },
            setZoom(nextZoom, center = null) {
                if (!this.modalPreview) return;
                this.minZoom = this.computeMinZoom();
                const clamped = Math.min(this.maxZoom, Math.max(this.minZoom, nextZoom));
                const prevZoom = this.zoom;
                if (clamped === prevZoom) return;
                const rect = this.$refs?.modal?.querySelector('[data-crop-area]')?.getBoundingClientRect();
                const maskOffsetX = rect ? (rect.width - this.cropSize) / 2 : 0;
                const maskOffsetY = rect ? (rect.height - this.cropSize) / 2 : 0;
                const cx = (center?.x ?? (rect ? rect.width / 2 : this.cropSize / 2)) - maskOffsetX;
                const cy = (center?.y ?? (rect ? rect.height / 2 : this.cropSize / 2)) - maskOffsetY;
                const base = {
                    x: this.cropSize / 2,
                    y: this.cropSize / 2
                };
                const factor = clamped / prevZoom;
                this.offsetX = (this.offsetX + (cx - base.x)) * factor - (cx - base.x);
                this.offsetY = (this.offsetY + (cy - base.y)) * factor - (cy - base.y);
                this.zoom = clamped;
                this.clampOffsets();
            },
            onWheel(event) {
                const rect = event.currentTarget.getBoundingClientRect();
                const point = {
                    x: event.clientX - rect.left,
                    y: event.clientY - rect.top
                };
                // Slower wheel/trackpad zoom for finer control.
                // Use small multiplicative steps so trackpads don’t feel “jumpy”.
                const step = event.deltaY < 0 ? 1.03 : 0.97;
                this.setZoom(this.zoom * step, point);
            },
            pinchDistance(touches) {
                if (touches.length < 2) return 0;
                const [a, b] = touches;
                return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
            },
            onTouchStart(event) {
                if (event.touches.length === 1) {
                    this.startDrag(event.touches[0]);
                } else if (event.touches.length === 2) {
                    this.dragging = false;
                    this.pinchStart = this.pinchDistance(event.touches);
                }
            },
            onTouchMove(event) {
                if (event.touches.length === 1 && this.dragging) {
                    this.onDrag(event.touches[0]);
                } else if (event.touches.length === 2) {
                    const dist = this.pinchDistance(event.touches);
                    if (this.pinchStart) {
                        const delta = dist / this.pinchStart;
                        this.setZoom(this.zoom * delta);
                    }
                    this.pinchStart = dist;
                }
            },
            onTouchEnd(event) {
                this.stopDrag();
                this.pinchStart = null;
            },
            crop() {
                if (!this.modalPreview) return null;
                this.clampOffsets();
                return new Promise((resolve) => {
                    const canvas = document.createElement('canvas');
                    const size = 512;
                    const scaleFactor = this.baseScale * this.zoom;
                    const dispW = this.displayWidth();
                    const dispH = this.displayHeight();
                    const startX = ((dispW - this.cropSize) / 2 - this.offsetX) / scaleFactor;
                    const startY = ((dispH - this.cropSize) / 2 - this.offsetY) / scaleFactor;
                    const cropSize = this.cropSize / scaleFactor;
                    const img = new Image();
                    img.onload = () => {
                        const ctx = canvas.getContext('2d');
                        canvas.width = size;
                        canvas.height = size;
                        ctx.clearRect(0, 0, size, size);
                        ctx.drawImage(img, startX, startY, cropSize, cropSize, 0, 0, size, size);
                        resolve(canvas.toDataURL('image/png'));
                    };
                    img.onerror = () => resolve(null);
                    img.src = this.modalPreview;
                });
            },
            async save() {
                this.saving = true;
                const dataUrl = await this.crop();
                if (!dataUrl) {
                    this.error = 'Could not crop this image.';
                    this.saving = false;
                    return;
                }
                await this.$wire.saveAvatar(dataUrl);
                this.preview = dataUrl;
                this.modalPreview = dataUrl;
                this.showModal = false;
                this.saving = false;
            },
            async clearPhoto() {
                this.saving = true;
                this.error = '';
                try {
                    await this.$wire.deleteAvatar();
                    this.preview = '';
                    this.modalPreview = '';
                    this.hasImage = false;
                    this.showModal = false;
                    this.zoom = 1;
                    this.offsetX = 0;
                    this.offsetY = 0;

                    // Allow re-selecting the same file after clearing.
                    if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                } finally {
                    this.saving = false;
                }
            },
            closeModal() {
                this.showModal = false;
                this.error = '';
                this.dragging = false;
                this.pinchStart = null;

                // Revert modal state to the currently saved preview (or empty if none).
                this.modalPreview = this.preview || '';
                this.zoom = 1;
                this.offsetX = 0;
                this.offsetY = 0;

                // Allow re-selecting the same file after cancelling.
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
            }
        };
    }
</script>
