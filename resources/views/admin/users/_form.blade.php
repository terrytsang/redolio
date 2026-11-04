@php
    $editedUser = $editedUser ?? null;
@endphp

<div>
    <label for="name" class="text-xs font-medium text-muted">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $editedUser?->name) }}" required
           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
    @error('name') <p class="mt-1 text-xs text-bad">{{ $message }}</p> @enderror
</div>

<div>
    <label for="email" class="text-xs font-medium text-muted">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $editedUser?->email) }}" required
           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
    @error('email') <p class="mt-1 text-xs text-bad">{{ $message }}</p> @enderror
</div>

<div>
    <label for="company_name" class="text-xs font-medium text-muted">Company (optional)</label>
    <input id="company_name" name="company_name" type="text" value="{{ old('company_name', $editedUser?->company_name) }}"
           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
    @error('company_name') <p class="mt-1 text-xs text-bad">{{ $message }}</p> @enderror
</div>

<div>
    <label for="role" class="text-xs font-medium text-muted">Role</label>
    <select id="role" name="role" class="mt-1.5 w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
        <option value="user" @selected(old('role', $editedUser?->role ?? 'user') === 'user')>User</option>
        <option value="admin" @selected(old('role', $editedUser?->role) === 'admin')>Admin</option>
    </select>
    @error('role') <p class="mt-1 text-xs text-bad">{{ $message }}</p> @enderror
</div>

<div>
    <label for="password" class="text-xs font-medium text-muted">
        {{ $editedUser ? 'New password (leave blank to keep current)' : 'Password' }}
    </label>
    <input id="password" name="password" type="password" {{ $editedUser ? '' : 'required' }}
           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
    @error('password') <p class="mt-1 text-xs text-bad">{{ $message }}</p> @enderror
</div>

<div>
    <label for="password_confirmation" class="text-xs font-medium text-muted">Confirm password</label>
    <input id="password_confirmation" name="password_confirmation" type="password"
           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
</div>
