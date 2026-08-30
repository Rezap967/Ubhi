# FE/sprint1.1-welcome_page

## Ringkasan Perubahan
Dokumentasi ini merangkum perubahan halaman Welcome dan pengembangan alur auth terbaru pada project Laravel + Inertia.js + Vue 3 (TypeScript). Perubahan mencakup penyesuaian UI halaman Welcome, slot actions, rute pendaftaran, serta update logo pada komponen auth.

## Sprint 1.1 - Welcome Page
### Daftar File yang Berubah
- `resources/js/pages/Welcome.vue` (modifikasi)
- `resources/js/layouts/Welcomelayout.vue` (modifikasi)
- `resources/js/components/welcome/WelcomeHeader.vue` (modifikasi)
- `resources/js/components/welcome/WelcomeActions.vue` (modifikasi)
- `resources/js/components/welcome/WelcomeDecoration.vue` (modifikasi)
- `resources/js/components/welcome/WelcomeIllustration.vue` (dihapus)

### Detail Perubahan Kode
#### `resources/js/pages/Welcome.vue`
- Menghapus import `WelcomeIllustration`.
- Memastikan template menggunakan `WelcomeLayout`, `WelcomeDecoration`, `WelcomeHeader`, dan `WelcomeActions`.
- Menambahkan slot kustom untuk tombol register dan login.

```vue
<template>
    <Head title="Welcome" />

    <WelcomeLayout>
        <template #background>
            <WelcomeDecoration />
        </template>

        <WelcomeHeader
            logo="/images/welcome/Logo_UbhiSir.png"
            app-name="UbhiSir POS"
        />

        <WelcomeActions>
            <template #register>
                <Link href="/register" class="...">Daftar Sekarang</Link>
            </template>
            <template #login>
                <Link href="/login" class="...">Masuk</Link>
            </template>
        </WelcomeActions>
    </WelcomeLayout>
</template>
```

#### `resources/js/layouts/Welcomelayout.vue`
- Meningkatkan layout card utama dengan `max-w-[660px]`.
- Menjaga tata letak centered dan mengoptimalkan padding.

```vue
<section
    class="relative z-10 w-full max-w-[660px] rounded-[28px] border border-white/70 bg-white/95 px-10 py-6 shadow-[0_24px_80px_rgba(15,23,42,0.16)] backdrop-blur-sm"
>
    <slot />
</section>
```

#### `resources/js/components/welcome/WelcomeHeader.vue`
- Memperbaiki spacing dan lebar teks deskripsi.
- Menjaga tampilan heading tetap ringkas dan mudah dibaca.

```vue
<div class="mt-3 space-y-2">
    <h1 class="text-4xl font-bold leading-[1.05] tracking-tight text-slate-900">
        Kelola Bisnis<br />Lebih Mudah
    </h1>
    <p class="mx-auto max-w-[440px] text-sm leading-6 text-slate-500">
        Sistem kasir modern untuk membantu Anda mengelola
        penjualan, stok, dan laporan bisnis dalam satu aplikasi.
    </p>
</div>
```

#### `resources/js/components/welcome/WelcomeActions.vue`
- Mengubah action menjadi slot bernama `register` dan `login`.
- Menyediakan fallback button ketika slot tidak diisi.

```vue
<slot name="register">
    <a
        :href="registerHref"
        class="flex h-12 w-full items-center justify-center rounded-xl bg-slate-900 text-sm font-semibold text-white transition-all duration-200 hover:bg-slate-800 hover:shadow-lg"
    >
        Daftar Sekarang
    </a>
</slot>
<slot name="login">
    <a
        :href="loginHref"
        class="flex h-12 w-full items-center justify-center rounded-xl border border-slate-300 bg-white text-sm font-semibold text-slate-700 transition-all duration-200 hover:border-slate-400 hover:bg-slate-50"
    >
        Masuk
    </a>
</slot>
```

#### `resources/js/components/welcome/WelcomeDecoration.vue`
- Menjaga dekorasi latar belakang sebagai elemen absolu yang responsif.

```vue
<div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
    <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-blue-200/30 blur-3xl" />
    <div class="absolute -bottom-24 -right-24 h-80 w-80 rounded-full bg-slate-300/20 blur-3xl" />
    <div class="absolute left-1/2 top-1/3 h-56 w-56 -translate-x-1/2 rounded-full bg-indigo-100/20 blur-3xl" />
</div>
```

#### `resources/js/components/welcome/WelcomeIllustration.vue` (dihapus)
- Komponen ilustrasi dihapus karena tidak lagi digunakan dalam halaman Welcome.
- Semua referensi import di `Welcome.vue` juga dihapus.

## Sprint 1.2 - Auth Flow & Register Store
### Daftar File yang Berubah / Ditambahkan
- `routes/web.php` (modifikasi)
- `resources/js/components/welcome/WelcomeActions.vue` (modifikasi)
- `resources/js/components/Auth/AuthLogo.vue` (modifikasi)
- `resources/js/layouts/AuthLayout.vue` (penambahan)
- `resources/js/pages/Auth/Login.vue` (penambahan)
- `resources/js/pages/Auth/ForgotPassword.vue` (penambahan)
- `resources/js/pages/Auth/ResetPassword.vue` (penambahan)
- `resources/js/pages/Auth/Register.vue` (penambahan)
- `resources/js/pages/Auth/RegisterStore.vue` (penambahan)

### Detail Perubahan Kode
#### `routes/web.php`
- Menambahkan route `GET` untuk halaman auth: `/login`, `/forgot-password`, `/reset-password`, `/register`, dan `/register-store`.
- Menambahkan route `POST /register` sebagai endpoint pendaftaran sementara yang langsung redirect ke `register-store`.

```php
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/login', function () {
    return Inertia::render('Auth/Login', [
        'status' => null,
        'error' => null,
        'canResetPassword' => true,
    ]);
})->name('login');

Route::get('/register', function () {
    return Inertia::render('Auth/Register', [
        'status' => null,
        'error' => null,
    ]);
})->name('register');

Route::post('/register', function () {
    return redirect()->route('register.store');
})->name('register.submit');

Route::get('/register-store', function () {
    return Inertia::render('Auth/RegisterStore', [
        'status' => null,
        'error' => null,
    ]);
})->name('register.store');
```

##### Mengapa
- Rute pendaftaran diperlukan agar form `form.post('/register')` dari frontend mendapatkan endpoint backend.
- Rute `register-store` disiapkan sebagai langkah onboarding berikutnya setelah pendaftaran.

#### `resources/js/components/Auth/AuthLogo.vue`
- Mengganti background logo dari hitam (`bg-slate-900`) menjadi putih (`bg-white`).
- Memastikan logo ditampilkan sebagai image dari `/images/welcome/logo_UbhiSir.png`.

```vue
<div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-white shadow-lg">
    <img
        src="/images/welcome/logo_UbhiSir.png"
        alt="UbhiSir Logo"
        class="h-14 w-14 rounded-3xl object-cover"
    />
</div>
```

##### Mengapa
- Menyesuaikan tampilan auth agar lebih bersih dan tidak kontras terlalu gelap pada halaman login/register.

#### `resources/js/layouts/AuthLayout.vue`
- Menambahkan layout global untuk halaman auth.
- Menyediakan wrapper `main` dengan background gradient ringan dan area konten centered.

```vue
<template>
    <main class="relative min-h-screen overflow-hidden bg-slate-50">
        <div class="pointer-events-none absolute -left-24 -top-28 h-80 w-80 rounded-full bg-sky-200/60 blur-3xl" />
        <div class="pointer-events-none absolute -right-24 -bottom-28 h-80 w-80 rounded-full bg-indigo-200/60 blur-3xl" />

        <div class="relative flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
            <slot />
        </div>
    </main>
</template>
```

##### Mengapa
- Layout auth terpusat membantu konsistensi tampilan antara halaman login, register, dan reset password.

#### `resources/js/pages/Auth/Register.vue`
- Menambahkan halaman pendaftaran user.
- Menggunakan `useForm()` dari `@inertiajs/vue3` untuk binding form dan error handling.
- Melakukan `form.post('/register')` dan `router.visit('/register-store')` setelah sukses.

```ts
const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    authError.value = '';

    form.post('/register', {
        preserveScroll: true,
        onError: () => {
            if (!form.errors.name && !form.errors.email && !form.errors.password && !form.errors.password_confirmation) {
                authError.value = 'Pendaftaran gagal. Silakan cek kembali data yang Anda masukkan.';
            }
        },
        onSuccess: () => {
            router.visit('/register-store');
        },
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
```

##### Mengapa
- Menyediakan jalur pendaftaran frontend yang akan segera dikaitkan ke backend.
- Mengarahkan pengguna ke halaman onboarding toko setelah pendaftaran.

#### `resources/js/pages/Auth/RegisterStore.vue`
- Menambahkan halaman onboarding pendaftaran toko.
- Menggunakan `useForm()` dengan field `logo`, `store_name`, `address`, `phone`, `email`, `timezone`, dan `currency`.
- Menyediakan preview upload logo dan validasi error.

```ts
const form = useForm<{
    logo: File | null;
    store_name: string;
    address: string;
    phone: string;
    email: string;
    timezone: string;
    currency: string;
}>({
    logo: null,
    store_name: '',
    address: '',
    phone: '',
    email: '',
    timezone: timezones[0],
    currency: currencies[0],
});

const submit = () => {
    authError.value = '';

    form.post('/register-store', {
        preserveScroll: true,
        onError: () => {
            if (!form.errors.logo && !form.errors.store_name && !form.errors.address && !form.errors.phone && !form.errors.email && !form.errors.timezone && !form.errors.currency) {
                authError.value = 'Pendaftaran toko gagal. Silakan periksa kembali data Anda.';
            }
        },
    });
};
```

##### Mengapa
- Halaman ini memisahkan proses pembuatan akun user dari data toko sehingga alur lebih terstruktur.
- Preview logo membantu pengguna memverifikasi gambar sebelum submit.

## Catatan Tambahan
- `resources/js/components/welcome/WelcomeActions.vue` kini mendukung slot kustom, sehingga komponen wrapper lebih fleksibel untuk welcome page.
- `routes/web.php` sekarang memiliki route backend untuk mendukung form `/register`.
- Dokumentasi ini disusun untuk memudahkan tim frontend memahami perubahan UI dan alur auth tanpa perlu membaca semua file satu per satu.
