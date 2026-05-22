<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Gate "manage-users": hanya admin yang boleh membuka modul manajemen pengguna.
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());

        // Gate "manage-subjects": hanya admin yang boleh menambah/ubah/hapus
        // mata pelajaran. User biasa tetap boleh MELIHAT daftar mata pelajaran
        // (cek baca terjadi di komponen masing-masing).
        Gate::define('manage-subjects', fn (User $user) => $user->isAdmin());
    }
}
