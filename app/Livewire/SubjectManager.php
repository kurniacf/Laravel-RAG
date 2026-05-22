<?php

namespace App\Livewire;

use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Mata Pelajaran')]
class SubjectManager extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Form state. */
    public ?int $editingId = null;

    public string $name = '';

    public ?string $icon = 'book';

    public string $color_hex = '#059669';

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public ?string $deletingName = null;

    /**
     * Helper: apakah user yang sedang login boleh mengelola (write).
     */
    public function canManage(): bool
    {
        return Gate::allows('manage-subjects');
    }

    #[Computed]
    public function subjects()
    {
        return Subject::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%';
                $q->where('name', 'like', $term);
            })
            ->orderBy('name')
            ->paginate(12);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->editingId = null;
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->authorizeManage();

        $subject = Subject::findOrFail($id);
        $this->editingId = $subject->id;
        $this->name = $subject->name;
        $this->icon = $subject->icon;
        $this->color_hex = $subject->color_hex;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $this->authorizeManage();

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'icon' => ['nullable', Rule::in(Subject::ICONS)],
            'color_hex' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];

        $data = $this->validate($rules);

        if ($this->editingId === null) {
            $data['slug'] = Subject::makeUniqueSlug($data['name']);
            Subject::create($data);
            session()->flash('status', 'Mata pelajaran berhasil ditambahkan.');
        } else {
            $subject = Subject::findOrFail($this->editingId);

            // Update slug bila name berubah.
            if ($subject->name !== $data['name']) {
                $data['slug'] = Subject::makeUniqueSlug($data['name'], $subject->id);
            }

            $subject->update($data);
            session()->flash('status', 'Mata pelajaran berhasil diperbarui.');
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();

        $subject = Subject::findOrFail($id);
        $this->deletingId = $subject->id;
        $this->deletingName = $subject->name;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $this->authorizeManage();

        if ($this->deletingId === null) {
            return;
        }

        Subject::find($this->deletingId)?->delete();

        session()->flash('status', "Mata pelajaran {$this->deletingName} telah dihapus.");
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = null;
    }

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
        $this->icon = 'book';
        $this->color_hex = '#059669';
        $this->resetErrorBag();
    }

    protected function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403, 'Anda tidak memiliki izin mengelola Mata Pelajaran.');
    }

    public function render(): View
    {
        return view('livewire.subject-manager');
    }
}
