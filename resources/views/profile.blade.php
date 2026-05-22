<x-app-layout>
    <x-slot name="title">Profil</x-slot>

    <x-slot name="header">
        Profil Akun
    </x-slot>

    <div class="px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl space-y-6">

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <livewire:profile.update-profile-information-form />
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <livewire:profile.update-password-form />
            </section>

            <section class="rounded-xl border border-red-200 bg-white p-6 shadow-sm sm:p-8">
                <livewire:profile.delete-user-form />
            </section>

        </div>
    </div>
</x-app-layout>
