<template>
    <div
        class="relative flex min-h-screen flex-col justify-between bg-[#F8FAFC] font-sans text-xs text-slate-800 select-none sm:text-sm"
    >
        <!-- ================================================================= -->
        <!-- 1. HALAMAN UTAMA: CATATAN PAJAK                                   -->
        <!-- ================================================================= -->
        <div
            v-if="currentView === 'catatan'"
            class="mx-auto w-full max-w-7xl space-y-4 p-4 md:p-6"
        >
            <!-- Top Bar / Header -->
            <div class="flex items-center justify-between pb-2">
                <h1 class="text-base font-bold text-slate-900 sm:text-lg">
                    Catatan Pajak
                </h1>
                <div class="flex items-center space-x-2">
                    <!-- Tombol Pengaturan (Gear Icon) -->
                    <button
                        @click="currentView = 'pengaturan'"
                        class="rounded-lg p-1.5 text-slate-600 transition hover:bg-slate-200/60 hover:text-slate-900"
                        title="Pengaturan Pajak"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                    </button>

                    <!-- Tombol Estimasi Pajak Preview (Kalkulator Icon) -->
                    <button
                        @click="openEstimasiModal"
                        class="rounded-lg p-1.5 text-slate-600 transition hover:bg-slate-200/60 hover:text-slate-900"
                        title="Preview Estimasi Pajak"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M9 7h6m-6 4h6m-6 4h6M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                    </button>
                </div>
            </div>
            <!-- Subtitle Pajak -->
            <div class="text-xs font-semibold text-slate-500">Pajak</div>
            <!-- Card Summary (PPN & PPh Final) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- Card PPN -->
                <div
                    class="flex h-20 flex-col justify-between rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm"
                >
                    <div
                        class="flex items-center space-x-2 text-xs font-medium text-slate-700"
                    >
                        <svg
                            class="h-4 w-4 text-slate-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                        <span>PPN</span>
                    </div>
                    <div class="text-lg font-bold text-slate-700">
                        {{ summary.ppn_amount_label || '—' }}
                    </div>
                </div>
                <!-- Card PPh Final -->
                <div
                    class="flex h-20 flex-col justify-between rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm"
                >
                    <div
                        class="flex items-center space-x-2 text-xs font-medium text-slate-700"
                    >
                        <svg
                            class="h-4 w-4 text-slate-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"
                            />
                        </svg>
                        <span>PPh Final</span>
                    </div>
                    <div class="text-lg font-bold text-slate-700">
                        {{ summary.pph_final_amount_label || '—' }}
                    </div>
                </div>
            </div>
            <!-- Filter Dropdown Status -->
            <div class="relative space-y-1">
                <label class="block text-xs text-slate-600">Status :</label>
                <div class="relative w-44">
                    <button
                        @click="isStatusDropdownOpen = !isStatusDropdownOpen"
                        class="flex w-full items-center justify-between rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                    >
                        <span>{{ selectedStatus }}</span>
                        <svg
                            class="h-3.5 w-3.5 text-slate-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>

                    <!-- Status Dropdown Menu -->
                    <div
                        v-if="isStatusDropdownOpen"
                        class="absolute top-full left-0 z-30 mt-1 w-48 overflow-hidden rounded-md border border-slate-200 bg-white text-xs shadow-xl"
                    >
                        <div
                            v-for="status in statusOptions"
                            :key="status"
                            @click="selectStatus(status)"
                            class="cursor-pointer px-4 py-2.5 transition-colors"
                            :class="
                                selectedStatus === status
                                    ? 'bg-slate-200/70 font-semibold text-slate-900'
                                    : 'text-slate-700 hover:bg-slate-100'
                            "
                        >
                            {{ status }}
                        </div>
                    </div>
                </div>
            </div>
            <!-- Toolbar Aksi (+ Nonaktif, Centang, Refresh, Total Data) -->
            <div
                class="flex items-center justify-between border-t border-slate-200/60 pt-2"
            >
                <div class="flex items-center space-x-1.5">
                    <!-- FITUR TAMBAH DIMATIKAN -->
                    <button
                        disabled
                        class="flex h-7 w-7 cursor-not-allowed items-center justify-center rounded bg-slate-300 text-slate-400 shadow-sm"
                        title="Fitur Tambah Dinonaktifkan"
                    >
                        <svg
                            class="h-4 w-4 text-slate-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                    </button>
                    <button
                        @click="toggleSelectAll"
                        class="flex h-7 w-7 items-center justify-center rounded bg-[#FDE08A] text-amber-900 shadow-sm transition hover:bg-[#fcd34d]"
                        title="Pilih Semua"
                    >
                        <svg
                            class="h-4 w-4 text-amber-900"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                    </button>
                    <button
                        @click="reloadCatatan"
                        class="flex h-7 w-7 items-center justify-center rounded bg-[#475569] text-white shadow-sm transition hover:bg-[#334155]"
                        title="Reload"
                    >
                        <svg
                            class="h-3.5 w-3.5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                    </button>
                </div>
                <div class="text-xs font-medium text-slate-600">
                    Total data yang ditemukan : {{ totalData }}.
                </div>
            </div>
            <!-- ================================================================= -->
            <!-- TABEL CATATAN PAJAK (Tampil Jika Ada Data)                       -->
            <!-- ================================================================= -->
            <div
                v-if="filteredCatatan.length > 0"
                class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm"
            >
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="border-b border-slate-200 bg-slate-50/70 text-slate-700"
                        >
                            <th class="w-10 px-3 py-2.5 text-center">
                                <input
                                    type="checkbox"
                                    :checked="isAllSelected"
                                    @change="toggleSelectAll"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                            </th>
                            <th class="px-3 py-2.5 font-semibold">
                                No. Transaksi
                            </th>
                            <th class="px-3 py-2.5 font-semibold">Tanggal</th>
                            <th class="px-3 py-2.5 font-semibold">
                                Jenis Pajak
                            </th>
                            <th class="px-3 py-2.5 font-semibold">
                                DPP / Omzet
                            </th>
                            <th class="px-3 py-2.5 font-semibold">
                                Nominal Pajak
                            </th>
                            <th class="px-3 py-2.5 text-center font-semibold">
                                Status
                            </th>
                            <th class="px-3 py-2.5 text-center font-semibold">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <tr
                            v-for="item in filteredCatatan"
                            :key="item.id"
                            class="transition hover:bg-slate-50/50"
                        >
                            <td class="px-3 py-2.5 text-center">
                                <input
                                    type="checkbox"
                                    v-model="selectedIds"
                                    :value="item.id"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                            </td>
                            <td class="px-3 py-2.5 font-medium text-slate-900">
                                {{ item.noTransaksi }}
                            </td>
                            <td class="px-3 py-2.5 text-slate-600">
                                {{ item.tanggal }}
                            </td>
                            <td class="px-3 py-2.5 font-medium">
                                {{ item.jenisPajak }}
                            </td>
                            <td class="px-3 py-2.5">
                                Rp {{ item.dpp.toLocaleString('id-ID') }}
                            </td>
                            <td
                                class="px-3 py-2.5 font-semibold text-slate-900"
                            >
                                Rp
                                {{ item.nominalPajak.toLocaleString('id-ID') }}
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                    :class="{
                                        'bg-amber-100 text-amber-700':
                                            item.status_label === 'Pending',
                                        'bg-emerald-100 text-emerald-700':
                                            item.status_label === 'Dibayar',
                                        'bg-rose-100 text-rose-700':
                                            item.status_label === 'Dibatalkan',
                                    }"
                                >
                                    {{ item.status_label }}
                                </span>
                            </td>
                            <td class="space-x-1 px-3 py-2.5 text-center">
                                <button
                                    @click="bukaModalHapus(item)"
                                    class="text-[11px] font-medium text-rose-600 hover:text-rose-800 hover:underline"
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- ================================================================= -->
            <!-- EMPTY STATE (Tampil Jika Data Kosong)                             -->
            <!-- ================================================================= -->
            <div
                v-else
                class="flex flex-col items-center justify-center space-y-3 py-20"
            >
                <!-- SVG Receipt Graphic -->
                <div class="h-16 w-16 text-slate-300">
                    <svg
                        viewBox="0 0 64 64"
                        fill="none"
                        stroke="currentColor"
                        class="h-full w-full"
                    >
                        <path
                            d="M16 8H48V56L42 51L36 56L30 51L24 56L18 51L16 56V8Z"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <path
                            d="M24 20H40"
                            stroke-width="3"
                            stroke-linecap="round"
                        />
                        <path
                            d="M24 28H40"
                            stroke-width="3"
                            stroke-linecap="round"
                        />
                        <path
                            d="M24 36H32"
                            stroke-width="3"
                            stroke-linecap="round"
                        />
                    </svg>
                </div>
                <div class="text-center">
                    <h3 class="text-sm font-bold text-slate-700">
                        Belum ada catatan
                    </h3>
                    <p class="mt-1 text-xs text-slate-400">
                        Catatan pajak akan muncul di sini
                    </p>
                </div>
            </div>
        </div>
        <!-- ================================================================= -->
        <!-- 2. HALAMAN PENGATURAN PAJAK                                       -->
        <!-- ================================================================= -->
        <div
            v-else-if="currentView === 'pengaturan'"
            class="flex flex-1 flex-col justify-between"
        >
            <div>
                <!-- Header Pengaturan Warna Biru Soft -->
                <div
                    class="flex items-center justify-between bg-[#DCE7F9] px-6 py-3"
                >
                    <h1 class="text-base font-bold text-slate-800">
                        Pengaturan Pajak
                    </h1>
                    <button
                        @click="currentView = 'catatan'"
                        class="rounded border border-slate-300/60 bg-white/50 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900"
                    >
                        Kembali
                    </button>
                </div>
                <div class="mx-auto w-full max-w-7xl space-y-5 p-4 md:p-6">
                    <div class="text-xs font-semibold text-slate-500">
                        Pajak
                    </div>
                    <!-- Aktifkan PPN -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-800"
                                >Aktifkan PPN</span
                            >
                            <button
                                @click="
                                    config.isPPNActive = !config.isPPNActive
                                "
                                class="h-6 w-12 rounded-full p-0.5 transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="
                                    config.isPPNActive
                                        ? 'bg-[#435B8C]'
                                        : 'bg-slate-300'
                                "
                            >
                                <div
                                    class="h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200 ease-in-out"
                                    :class="
                                        config.isPPNActive
                                            ? 'translate-x-6'
                                            : 'translate-x-0'
                                    "
                                ></div>
                            </button>
                        </div>
                        <div class="relative pt-1">
                            <fieldset
                                class="relative rounded-xl border border-slate-400 p-2 px-3"
                            >
                                <legend
                                    class="px-1 text-[11px] leading-none font-medium text-slate-500"
                                >
                                    Tarif PPN (%)
                                </legend>
                                <input
                                    v-model="config.tarifPPN"
                                    type="text"
                                    :disabled="!config.isPPNActive"
                                    class="w-full bg-transparent text-xs font-semibold text-slate-800 focus:outline-none disabled:text-slate-400"
                                />
                            </fieldset>
                        </div>
                    </div>
                    <!-- Aktifkan PPh Final UMKM -->
                    <div class="space-y-3 pt-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-800"
                                >Aktifkan PPh Final UMKM</span
                            >
                            <button
                                @click="
                                    config.isPPhActive = !config.isPPhActive
                                "
                                class="h-6 w-12 rounded-full p-0.5 transition-colors duration-200 ease-in-out focus:outline-none"
                                :class="
                                    config.isPPhActive
                                        ? 'bg-[#435B8C]'
                                        : 'bg-slate-300'
                                "
                            >
                                <div
                                    class="h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200 ease-in-out"
                                    :class="
                                        config.isPPhActive
                                            ? 'translate-x-6'
                                            : 'translate-x-0'
                                    "
                                ></div>
                            </button>
                        </div>
                        <!-- Input Tarif PPh -->
                        <div class="relative">
                            <fieldset
                                class="relative rounded-xl border border-slate-400 p-2 px-3"
                            >
                                <legend
                                    class="px-1 text-[11px] leading-none font-medium text-slate-500"
                                >
                                    Tarif PPh (%)
                                </legend>
                                <input
                                    v-model="config.tarifPPh"
                                    type="text"
                                    :disabled="!config.isPPhActive"
                                    class="w-full bg-transparent text-xs font-semibold text-slate-800 focus:outline-none disabled:text-slate-400"
                                />
                            </fieldset>
                        </div>
                        <!-- Input Batas Omzet PPh -->
                        <div class="relative">
                            <fieldset
                                class="relative rounded-xl border border-slate-400 p-2 px-3"
                            >
                                <legend
                                    class="px-1 text-[11px] leading-none font-medium text-slate-500"
                                >
                                    Batas Omzet PPh (Rp/tahun)
                                </legend>
                                <div
                                    class="flex items-center text-xs font-semibold text-slate-800"
                                >
                                    <span class="mr-1">Rp</span>
                                    <input
                                        v-model="config.batasOmzetPPh"
                                        type="text"
                                        :disabled="!config.isPPhActive"
                                        class="w-full bg-transparent focus:outline-none disabled:text-slate-400"
                                    />
                                </div>
                            </fieldset>
                        </div>
                    </div>
                    <!-- Aktifkan PPnBM -->
                    <div class="flex items-center justify-between pt-2">
                        <span class="text-xs font-semibold text-slate-800"
                            >Aktifkan PPnBM (Barang Mewah)</span
                        >
                        <button
                            @click="
                                config.isPPnBMActive = !config.isPPnBMActive
                            "
                            class="h-6 w-12 rounded-full p-0.5 transition-colors duration-200 ease-in-out focus:outline-none"
                            :class="
                                config.isPPnBMActive
                                    ? 'bg-[#435B8C]'
                                    : 'bg-slate-300'
                            "
                        >
                            <div
                                class="h-5 w-5 transform rounded-full bg-white shadow transition-transform duration-200 ease-in-out"
                                :class="
                                    config.isPPnBMActive
                                        ? 'translate-x-6'
                                        : 'translate-x-0'
                                "
                            ></div>
                        </button>
                    </div>
                    <!-- Tombol Simpan -->
                    <div class="pt-4">
                        <button
                            @click="simpanPengaturan"
                            class="w-full rounded-lg bg-[#435B8C] py-2.5 text-xs font-semibold text-white shadow transition hover:bg-[#344973]"
                        >
                            Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- ================================================================= -->
        <!-- 3. MODAL ESTIMASI PAJAK (PREVIEW)                                 -->
        <!-- ================================================================= -->
        <div
            v-if="isEstimasiModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 p-2 sm:p-4"
        >
            <div
                class="my-auto flex w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white text-xs shadow-2xl"
            >
                <!-- Modal Header -->
                <div
                    class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 bg-white p-4 sm:p-5"
                >
                    <div>
                        <h2
                            class="text-sm font-bold text-slate-900 sm:text-base"
                        >
                            Estimasi Pajak (Preview)
                        </h2>
                        <p class="mt-0.5 text-[11px] text-slate-500">
                            Ringkasan perhitungan estimasi pajak berdasarkan
                            filter yang dipilih.
                        </p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button
                            @click="reloadEstimasi"
                            class="p-1 text-slate-500 hover:text-slate-800"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                        </button>
                        <button
                            @click="closeEstimasiModal"
                            class="flex items-center space-x-1 rounded border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            <span>‹ Kembali ke Catatan Pajak</span>
                        </button>
                    </div>
                </div>
                <!-- Modal Body Content -->
                <div class="space-y-4 bg-slate-50/50 p-4 sm:p-5">
                    <!-- Filter Inputs & Action -->
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-semibold text-slate-600"
                                    >Periode</label
                                >
                                <div
                                    @click="cycleEstimatePeriod"
                                    class="flex items-center space-x-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs"
                                >
                                    <svg
                                        class="h-3.5 w-3.5 text-slate-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        />
                                    </svg>
                                    <span>{{ estimatePeriodLabel }}</span>
                                    <svg
                                        class="ml-2 h-3 w-3 text-slate-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 9l-7 7-7-7"
                                        />
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-[11px] font-semibold text-slate-600"
                                    >Status</label
                                >
                                <div
                                    @click="cycleEstimateStatus"
                                    class="flex w-40 items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs"
                                >
                                    <span>{{ estimateStatusLabel }}</span>
                                    <svg
                                        class="h-3 w-3 text-slate-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 9l-7 7-7-7"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <button
                            @click="previewEstimasi"
                            class="flex items-center space-x-1.5 rounded-lg bg-[#1D4ED8] px-4 py-2 font-semibold text-white shadow-sm hover:bg-blue-800"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                />
                            </svg>
                            <span>Preview Estimasi</span>
                        </button>
                    </div>
                    <!-- Metric Cards Grid (4 Cards) -->
                    <div
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div
                            class="flex items-center space-x-3 rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-sm"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <div
                                    class="text-[11px] font-medium text-slate-500"
                                >
                                    Estimasi PPN
                                </div>
                                <div class="text-sm font-bold text-slate-900">
                                    {{ estimateSummary.ppn_amount_label }}
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    {{ estimateSummary.ppn_subtitle }}
                                </div>
                            </div>
                        </div>
                        <div
                            class="flex items-center space-x-3 rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-sm"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <div
                                    class="text-[11px] font-medium text-slate-500"
                                >
                                    Estimasi PPh Final
                                </div>
                                <div class="text-sm font-bold text-slate-900">
                                    {{ estimateSummary.pph_final_amount_label }}
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    {{ estimateSummary.pph_final_subtitle }}
                                </div>
                            </div>
                        </div>
                        <div
                            class="flex items-center space-x-3 rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-sm"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-600"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <div
                                    class="text-[11px] font-medium text-slate-500"
                                >
                                    Total Estimasi Pajak
                                </div>
                                <div class="text-sm font-bold text-slate-900">
                                    {{ estimateSummary.total_tax_amount_label }}
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    {{ estimateSummary.total_subtitle }}
                                </div>
                            </div>
                        </div>
                        <div
                            class="flex items-center space-x-3 rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-sm"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <div
                                    class="text-[11px] font-medium text-slate-500"
                                >
                                    Periode
                                </div>
                                <div class="text-sm font-bold text-slate-900">
                                    {{ estimatePeriodLabel }}
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    {{ estimate.period.label }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Data Table Estimasi -->
                    <div
                        class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white"
                    >
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b border-slate-200 bg-slate-50/70 text-slate-700"
                                >
                                    <th class="px-3 py-2.5 font-semibold">
                                        Jenis Pajak
                                    </th>
                                    <th class="px-3 py-2.5 font-semibold">
                                        Dasar Pengenaan Pajak
                                    </th>
                                    <th class="px-3 py-2.5 font-semibold">
                                        Tarif (%)
                                    </th>
                                    <th class="px-3 py-2.5 font-semibold">
                                        Estimasi Pajak
                                    </th>
                                    <th class="px-3 py-2.5 font-semibold">
                                        Jumlah Transaksi
                                    </th>
                                    <th class="px-3 py-2.5 font-semibold">
                                        Keterangan
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700"
                            >
                                <tr
                                    v-for="row in estimateRows"
                                    :key="row.tax_type"
                                >
                                    <td
                                        class="px-3 py-2.5 font-medium text-slate-800"
                                    >
                                        {{ row.tax_type_label }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        {{ row.total_tax_base_label
                                        }}<br /><span
                                            class="text-[10px] text-slate-400"
                                            >(DPP)</span
                                        >
                                    </td>
                                    <td class="px-3 py-2.5">
                                        {{ row.tarif_label }}
                                    </td>
                                    <td class="px-3 py-2.5 font-medium">
                                        {{ row.total_tax_amount_label }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        {{ row.transaction_count }}
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-500">
                                        {{ row.keterangan }}
                                    </td>
                                </tr>
                                <tr v-if="estimateRows.length === 0">
                                    <td
                                        colspan="6"
                                        class="px-3 py-4 text-center text-slate-400"
                                    >
                                        Belum ada data estimasi.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr
                                    class="border-t border-slate-200 bg-blue-50/50 font-bold text-blue-900"
                                >
                                    <td class="px-3 py-3 uppercase">
                                        TOTAL ESTIMASI
                                    </td>
                                    <td class="px-3 py-3">
                                        {{
                                            estimateSummary.total_tax_base_label
                                        }}
                                    </td>
                                    <td class="px-3 py-3">-</td>
                                    <td class="px-3 py-3">
                                        {{
                                            estimateSummary.total_tax_amount_label
                                        }}
                                    </td>
                                    <td class="px-3 py-3">
                                        {{
                                            estimateSummary.total_transaction_count
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-3 font-normal text-slate-600"
                                    >
                                        Total estimasi semua pajak
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <!-- Bottom Actions & Pagination -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-3 pt-2"
                    >
                        <div class="flex items-center space-x-2">
                            <button
                                @click="exportEstimasi"
                                class="flex items-center space-x-1.5 rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                            >
                                <svg
                                    class="h-3.5 w-3.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                                <span>Export Excel</span>
                            </button>
                            <button
                                @click="printEstimasi"
                                class="flex items-center space-x-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                            >
                                <svg
                                    class="h-3.5 w-3.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                                    />
                                </svg>
                                <span>Cetak</span>
                            </button>
                            <button
                                @click="reloadEstimasi"
                                class="flex items-center space-x-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                            >
                                <svg
                                    class="h-3.5 w-3.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                    />
                                </svg>
                                <span>Muat Ulang</span>
                            </button>
                        </div>

                        <div class="flex items-center space-x-3 text-slate-500">
                            <span
                                >Menampilkan {{ pageStart }} -
                                {{ pageEnd }} dari {{ totalData }} data</span
                            >
                            <div class="flex items-center space-x-1">
                                <button
                                    @click="previousEstimatePage"
                                    class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-400 hover:bg-slate-100"
                                >
                                    ‹‹
                                </button>
                                <button
                                    @click="previousEstimatePage"
                                    class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-400 hover:bg-slate-100"
                                >
                                    ‹
                                </button>
                                <button
                                    @click="estimatePage = estimatePage"
                                    class="flex h-7 w-7 items-center justify-center rounded bg-[#1D4ED8] font-bold text-white"
                                >
                                    {{ estimatePage }}
                                </button>
                                <button
                                    @click="nextEstimatePage"
                                    class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-400 hover:bg-slate-100"
                                >
                                    ›
                                </button>
                                <button
                                    @click="nextEstimatePage"
                                    class="flex h-7 w-7 items-center justify-center rounded border border-slate-200 text-slate-400 hover:bg-slate-100"
                                >
                                    ››
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- 4. MODAL KONFIRMASI HAPUS (SESUAI GAMBAR ACUAN)                  -->
        <!-- ================================================================= -->
        <div
            v-if="isDeleteModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4 backdrop-blur-[2px] transition-opacity"
        >
            <div
                class="w-full max-w-md space-y-4 rounded-3xl border border-white/60 bg-[#EEF2F6] p-6 shadow-2xl sm:p-7"
            >
                <h3
                    class="text-base font-bold tracking-tight text-slate-800 sm:text-lg"
                >
                    Hapus Catatan Pajak
                </h3>
                <p class="text-xs leading-relaxed text-slate-600 sm:text-sm">
                    Apakah Anda yakin ingin menghapus catatan transaksi
                    <span class="font-semibold text-slate-800"
                        >"{{ itemToDelete?.noTransaksi }}"</span
                    >?
                </p>
                <div class="flex items-center justify-end space-x-6 pt-3">
                    <button
                        @click="isDeleteModalOpen = false"
                        class="text-xs font-semibold text-blue-600 transition hover:text-blue-700 sm:text-sm"
                    >
                        Batal
                    </button>
                    <button
                        @click="eksekusiHapus"
                        class="rounded-2xl bg-[#E50000] px-6 py-2 text-xs font-medium text-white shadow-md transition hover:bg-red-700 sm:text-sm"
                    >
                        Hapus
                    </button>
                </div>
            </div>
        </div>

        <!-- Floating Status Bar -->
        <footer
            class="z-20 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 border-t border-slate-200 bg-white px-4 py-2 text-[11px] text-slate-600"
        >
            <div class="flex items-center space-x-1">
                <svg
                    class="h-3.5 w-3.5 text-slate-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                    />
                </svg>
                <span
                    >Penjualan:
                    <strong class="text-slate-800"
                        >{{ totalData }} transaksi</strong
                    ></span
                >
            </div>
            <span class="text-slate-300">|</span>
            <div class="flex items-center space-x-1">
                <svg
                    class="h-3.5 w-3.5 text-slate-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                    />
                </svg>
                <span
                    >Pendapatan: Kotor:
                    <strong class="text-slate-800"
                        >Rp {{ totalDPP.toLocaleString('id-ID') }}</strong
                    >
                    · Bersih:
                    <strong class="text-slate-800"
                        >Rp
                        {{
                            (totalDPP - totalPPN - totalPPh).toLocaleString(
                                'id-ID',
                            )
                        }}</strong
                    ></span
                >
            </div>
            <span class="text-slate-300">|</span>
            <div class="flex items-center space-x-1">
                <svg
                    class="h-3.5 w-3.5 text-slate-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <span
                    >Waktu: <strong class="text-slate-800">11:22</strong></span
                >
            </div>
            <span class="text-slate-300">|</span>
            <div class="flex items-center space-x-1">
                <svg
                    class="h-3.5 w-3.5 text-slate-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <span
                    >Versi:
                    <strong class="text-slate-800">v1.2.37</strong></span
                >
            </div>
        </footer>
    </div>
</template>

<script lang="ts">
import { router, usePage } from '@inertiajs/vue3';

interface EstimationRow {
    tax_type: string;
    tax_type_label: string;
    total_tax_base_label: string;
    tarif_label: string;
    total_tax_amount_label: string;
    transaction_count: number;
    keterangan: string;
}

const page = usePage<any>();

export default {
    name: 'CatatanPajakUI',
    mounted() {
        const status = page.props.filters?.status || 'all';
        this.selectedStatus = this.statusLabel(status);

        if (!page.props.taxRecords) {
            router.get(
                '/akuntansi/pajak',
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }
    },
    data() {
        return {
            currentView: 'catatan',
            isEstimasiModalOpen: false,
            estimateLoading: false,
            estimateError: null as string | null,
            estimatePage: 1,
            estimateFilters: {
                status: 'all',
                period: null as string | null,
                month: null as string | null,
                date_start: null as string | null,
                date_end: null as string | null,
                page: 1,
                per_page: 10,
            },
            estimate: {
                rows: { data: [], meta: { current_page: 1, last_page: 1 } },
                summary: {
                    ppn_amount_label: 'Rp 0',
                    ppn_subtitle: 'Dari 0 transaksi',
                    pph_final_amount_label: 'Rp 0',
                    pph_final_subtitle: 'Dari 0 transaksi',
                    total_tax_amount_label: 'Rp 0',
                    total_subtitle: 'Total dari semua pajak',
                    total_tax_base_label: 'Rp 0',
                    total_transaction_count: 0,
                },
                filters: {},
                period: {
                    label: 'Belum ada periode',
                    date_start: null,
                    date_end: null,
                    month: null,
                },
            },

            // State Modal Hapus Custom
            isDeleteModalOpen: false,
            itemToDelete: null as any,

            selectedStatus: 'Semua',
            isStatusDropdownOpen: false,
            selectedIds: [],
            // Config State
            config: {
                isPPNActive: true,
                tarifPPN: '11.0',
                isPPhActive: true,
                tarifPPh: '0.5',
                batasOmzetPPh: '4800000000',
                isPPnBMActive: true,
            },
        };
    },
    computed: {
        filteredCatatan() {
            return page.props.taxRecords?.data || [];
        },
        totalData() {
            return page.props.total ?? page.props.taxRecords?.meta?.total ?? 0;
        },
        summary() {
            return page.props.summary || {};
        },
        isAllSelected() {
            return (
                this.filteredCatatan.length > 0 &&
                this.selectedIds.length === this.filteredCatatan.length
            );
        },
        totalDPP() {
            return this.filteredCatatan.reduce(
                (sum: number, item: any) => sum + Number(item.dpp || 0),
                0,
            );
        },
        totalPPN() {
            return Number(this.summary.ppn_amount || 0);
        },
        totalPPh() {
            return Number(this.summary.pph_final_amount || 0);
        },
        statusOptions() {
            return (page.props.statuses || []).map(
                (status: any) => status.label,
            );
        },
        estimateSummary() {
            return this.estimate.summary;
        },
        estimateRows(): EstimationRow[] {
            return (this.estimate.rows?.data || []) as EstimationRow[];
        },
        estimatePeriodLabel() {
            return this.estimate.period?.label || 'Belum ada periode';
        },
        estimateDateRange() {
            const period = this.estimate.period || {};

            if (!period.date_start && !period.date_end) {
                return 'Belum ada periode';
            }

            return `${period.date_start || 'Awal'} - ${period.date_end || 'Sekarang'}`;
        },
        estimateStatusLabel() {
            return this.statusLabel(this.estimateFilters.status);
        },
        estimatePeriodOptions(): string[] {
            return Array.from(
                new Set(
                    this.filteredCatatan
                        .map((item: any) => item.tax_date?.slice(0, 7))
                        .filter(
                            (period: string | undefined): period is string =>
                                Boolean(period),
                        ),
                ),
            ) as string[];
        },
        pageStart() {
            return (
                page.props.taxRecords?.meta?.from ??
                (this.totalData > 0 ? 1 : 0)
            );
        },
        pageEnd() {
            return (
                page.props.taxRecords?.meta?.to ?? this.filteredCatatan.length
            );
        },
    },
    methods: {
        statusLabel(status: string) {
            return (
                (
                    {
                        all: 'Semua',
                        pending: 'Pending',
                        paid: 'Dibayar',
                        cancelled: 'Dibatalkan',
                    } as Record<string, string>
                )[status] || 'Semua'
            );
        },
        openEstimasiModal() {
            this.isEstimasiModalOpen = true;
            this.previewEstimasi();
        },
        closeEstimasiModal() {
            this.isEstimasiModalOpen = false;
        },
        async previewEstimasi() {
            this.estimateLoading = true;
            this.estimateError = null;

            const query = new URLSearchParams();
            Object.entries(this.estimateFilters).forEach(([key, value]) => {
                if (value !== null && value !== '') {
                    query.set(key, String(value));
                }
            });

            try {
                const response = await fetch(
                    `/api/akuntansi/tax-estimation?${query.toString()}`,
                    { headers: { Accept: 'application/json' } },
                );

                if (!response.ok) {
                    throw new Error('Data estimasi pajak gagal dimuat.');
                }

                const payload = await response.json();
                this.estimate = payload.data;
                this.estimatePage = payload.data.filters.page;
            } catch (error) {
                this.estimateError =
                    error instanceof Error
                        ? error.message
                        : 'Data estimasi pajak gagal dimuat.';
            } finally {
                this.estimateLoading = false;
            }
        },
        cycleEstimateStatus() {
            const statuses = ['all', 'pending', 'paid', 'cancelled'];
            const currentIndex = statuses.indexOf(this.estimateFilters.status);
            this.estimateFilters.status =
                statuses[(currentIndex + 1) % statuses.length];
            this.estimateFilters.page = 1;
            this.estimatePage = 1;
            this.previewEstimasi();
        },
        cycleEstimatePeriod() {
            const periods: (string | null)[] = [
                null,
                ...this.estimatePeriodOptions,
            ];
            const current = this.estimateFilters.month;
            const currentIndex = periods.indexOf(current);
            const next = periods[(currentIndex + 1) % periods.length];
            this.estimateFilters.month = next;
            this.estimateFilters.period = next;
            this.estimateFilters.page = 1;
            this.estimatePage = 1;
            this.previewEstimasi();
        },
        reloadEstimasi() {
            this.previewEstimasi();
        },
        previousEstimatePage() {
            if (this.estimatePage <= 1) {
                return;
            }

            this.estimatePage -= 1;
            this.estimateFilters.page = this.estimatePage;
            this.previewEstimasi();
        },
        nextEstimatePage() {
            const lastPage = this.estimate.rows?.meta?.last_page || 1;

            if (this.estimatePage >= lastPage) {
                return;
            }

            this.estimatePage += 1;
            this.estimateFilters.page = this.estimatePage;
            this.previewEstimasi();
        },
        exportEstimasi() {
            window.location.href = `/akuntansi/pajak/estimasi/export?${this.estimationQuery()}`;
        },
        printEstimasi() {
            window.open(
                `/akuntansi/pajak/estimasi/print?${this.estimationQuery()}`,
                '_blank',
            );
        },
        estimationQuery() {
            const query = new URLSearchParams();

            Object.entries(this.estimateFilters).forEach(([key, value]) => {
                if (value !== null && value !== '') {
                    query.set(key, String(value));
                }
            });

            return query.toString();
        },
        selectStatus(status: string) {
            this.selectedStatus = status;
            this.isStatusDropdownOpen = false;
            const statusMap: Record<string, string> = {
                Semua: 'all',
                Pending: 'pending',
                Dibayar: 'paid',
                Dibatalkan: 'cancelled',
            };
            router.get(
                '/akuntansi/pajak',
                {
                    ...(page.props.filters || {}),
                    status: statusMap[status] || 'all',
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        },
        simpanPengaturan() {
            alert('Pengaturan Pajak Berhasil Disimpan!');
            this.currentView = 'catatan';
        },
        toggleSelectAll() {
            if (this.isAllSelected) {
                this.selectedIds = [];
            } else {
                this.selectedIds = this.filteredCatatan.map(
                    (item: any) => item.id,
                );
            }
        },
        reloadCatatan() {
            router.reload({
                only: ['taxRecords', 'summary', 'total', 'filters'],
            });
        },
        // LOGIK UNTUK MEMBUKA MODAL HAPUS CUSTOM
        bukaModalHapus(item: any) {
            this.itemToDelete = item;
            this.isDeleteModalOpen = true;
        },
        // EKSEKUSI HAPUS
        eksekusiHapus() {
            if (this.itemToDelete) {
                router.delete(`/akuntansi/pajak/${this.itemToDelete.id}`, {
                    onSuccess: () => {
                        this.selectedIds = this.selectedIds.filter(
                            (id: string) => id !== this.itemToDelete.id,
                        );
                        this.isDeleteModalOpen = false;
                        this.itemToDelete = null;
                    },
                });

                return;
            }

            this.isDeleteModalOpen = false;
            this.itemToDelete = null;
        },
    },
};
</script>
