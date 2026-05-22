<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Manajemen Pengguna')]
class UserManager extends Component
{
    use WithPagination;

    /** Kata kunci pencarian (nama atau email). */
    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Filter role: '', 'user', 'admin'. */
    #[Url(as: 'role', except: '')]
    public string $roleFilter = '';

    /** Form state: id user yang sedang diedit, null bila create. */
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $role = User::ROLE_USER;

    /** State modal. */
    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    /** ID user yang akan dihapus (lewat modal konfirmasi). */
    public ?int $deletingId = null;

    public ?string $deletingName = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage-users'), 403);
    }

    /**
     * Mengembalikan paginator user terfilter. Computed agar di-refresh saat
     * search/roleFilter berubah.
     */
    #[Computed]
    public function users()
    {
        return User::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($this->roleFilter !== '', fn ($q) => $q->where('role', $this->roleFilter))
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Buka modal create.
     */
    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showFormModal = true;
    }

    /**
     * Buka modal edit.
     */
    public function openEdit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->password = '';
        $this->password_confirmation = '';
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    /**
     * Simpan (create atau update) user dari form modal.
     */
    public function save(): void
    {
        $isUpdate = $this->editingId !== null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'role' => ['required', Rule::in(User::ROLES)],
        ];

        // Password wajib saat create, opsional saat update.
        $rules['password'] = $isUpdate
            ? ['nullable', 'string', 'confirmed', PasswordRule::defaults()]
            : ['required', 'string', 'confirmed', PasswordRule::defaults()];

        $data = $this->validate($rules);

        // Cegah admin terakhir mendemote dirinya sendiri (kunci diri keluar).
        if ($isUpdate
            && $this->editingId === auth()->id()
            && $this->role !== User::ROLE_ADMIN
            && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            $this->addError('role', 'Tidak dapat menurunkan peran admin terakhir.');

            return;
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        if ($isUpdate) {
            User::find($this->editingId)->update($payload);
            session()->flash('status', 'Data pengguna berhasil diperbarui.');
        } else {
            $payload['password'] = Hash::make($data['password']);
            $payload['email_verified_at'] = now();
            User::create($payload);
            session()->flash('status', 'Pengguna baru berhasil ditambahkan.');
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    /**
     * Buka modal konfirmasi hapus.
     */
    public function confirmDelete(int $id): void
    {
        // Cegah self-delete di layer komponen (UI sudah disable, ini defense kedua).
        if ($id === auth()->id()) {
            session()->flash('error', 'Anda tidak dapat menghapus akun sendiri.');

            return;
        }

        $user = User::findOrFail($id);
        $this->deletingId = $user->id;
        $this->deletingName = $user->name;
        $this->showDeleteModal = true;
    }

    /**
     * Eksekusi penghapusan.
     */
    public function delete(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        if ($this->deletingId === auth()->id()) {
            session()->flash('error', 'Anda tidak dapat menghapus akun sendiri.');
            $this->showDeleteModal = false;

            return;
        }

        $user = User::find($this->deletingId);

        // Cegah penghapusan admin terakhir.
        if ($user && $user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            session()->flash('error', 'Tidak dapat menghapus admin terakhir.');
            $this->showDeleteModal = false;
            $this->deletingId = null;

            return;
        }

        $user?->delete();

        session()->flash('status', "Pengguna {$this->deletingName} telah dihapus.");
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = null;
    }

    /**
     * Tutup modal form dan reset state.
     */
    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = null;
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->role = User::ROLE_USER;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.admin.user-manager');
    }
}
