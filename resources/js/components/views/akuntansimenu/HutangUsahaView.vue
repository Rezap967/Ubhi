<template>
    <div
        class="flex min-h-screen flex-col justify-between bg-[#F4F6F9] font-sans text-sm text-gray-800"
    >
        <!-- Main Content -->
        <div class="space-y-4 p-4 md:p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-800">Hutang Usaha</h1>
                <button
                    @click="openTambahHutangModal"
                    class="text-2xl font-light text-gray-600 hover:text-gray-900 focus:outline-none"
                >
                    +
                </button>
            </div>

            <!-- Info Summary Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Total Hutang Card -->
                <div
                    class="flex flex-col justify-center rounded-xl border border-gray-200/70 bg-white p-3.5 shadow-sm"
                >
                    <div
                        class="flex items-center space-x-2 text-xs font-medium text-gray-500"
                    >
                        <svg
                            class="h-4 w-4 text-red-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                        <span>Total Hutang</span>
                    </div>
                    <div class="mt-1 text-base font-bold text-red-500">
                        Rp {{ formatRupiah(totalHutang) }}
                    </div>
                </div>

                <!-- Jatuh Tempo Card -->
                <div
                    class="flex flex-col justify-center rounded-xl border border-gray-200/70 bg-white p-3.5 shadow-sm"
                >
                    <div
                        class="flex items-center space-x-2 text-xs font-medium text-gray-500"
                    >
                        <svg
                            class="h-4 w-4 text-amber-500"
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
                        <span>Jatuh Tempo</span>
                    </div>
                    <div class="mt-1 text-base font-bold text-amber-500">
                        Rp {{ formatRupiah(totalJatuhTempo) }}
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="flex flex-wrap items-center gap-4 text-xs">
                <div class="flex flex-col gap-1">
                    <label class="text-gray-600">Kata Kunci :</label>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari nama pemasok..."
                        class="w-48 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
                    />
                </div>

                <div class="relative flex flex-col gap-1">
                    <label class="text-gray-600">Status :</label>
                    <button
                        @click="isStatusDropdownOpen = !isStatusDropdownOpen"
                        class="flex w-36 items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs focus:outline-none"
                    >
                        <span>{{ selectedStatus }}</span>
                        <svg
                            class="ml-1 h-3.5 w-3.5 text-gray-500"
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

                    <!-- Dropdown Option Status -->
                    <div
                        v-if="isStatusDropdownOpen"
                        class="absolute top-full left-0 z-20 mt-1 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white py-1 text-xs shadow-lg"
                    >
                        <div
                            v-for="status in statusOptions"
                            :key="status"
                            @click="selectStatus(status)"
                            class="cursor-pointer px-3 py-2 transition-colors hover:bg-gray-100"
                            :class="{
                                'bg-gray-200/70 font-semibold':
                                    selectedStatus === status,
                            }"
                        >
                            {{ status }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toolbar Buttons & Counter -->
            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center space-x-1.5">
                    <button
                        @click="openTambahHutangModal"
                        class="flex h-7 w-7 items-center justify-center rounded bg-emerald-600 text-white hover:bg-emerald-700"
                        title="Tambah Hutang"
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
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                    </button>
                    <button
                        class="flex h-7 w-7 items-center justify-center rounded bg-amber-400 text-white hover:bg-amber-500"
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
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                    </button>
                    <button
                        @click="refreshData"
                        class="flex h-7 w-7 items-center justify-center rounded bg-gray-400 text-white hover:bg-gray-500"
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
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                    </button>
                </div>
                <div class="text-xs text-gray-500">
                    Total data yang ditemukan :
                    <span class="font-medium text-gray-700">{{
                        totalData
                    }}</span
                    >.
                </div>
            </div>

            <!-- Data Table -->
            <div
                class="overflow-x-auto rounded-xl border border-gray-200/80 bg-white shadow-sm"
            >
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50/50 text-gray-600"
                        >
                            <th class="w-10 px-4 py-3 text-center">
                                <input
                                    type="checkbox"
                                    @change="toggleSelectAll"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-0"
                                />
                            </th>
                            <th class="px-4 py-3 font-semibold">
                                Pemasok
                                <span class="font-normal text-gray-400">↑</span>
                            </th>
                            <th class="px-4 py-3 text-center font-semibold">
                                Status
                                <span class="font-normal text-gray-400">↑</span>
                            </th>
                            <th class="px-4 py-3 text-center font-semibold">
                                Jatuh Tempo
                                <span class="font-normal text-gray-400">↑</span>
                            </th>
                            <th class="px-4 py-3 text-right font-semibold">
                                Sisa
                                <span class="font-normal text-gray-400">↑</span>
                            </th>
                            <th class="px-4 py-3 text-center font-semibold">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="item in filteredHutangList"
                            :key="item.id"
                            class="cursor-pointer transition-colors hover:bg-gray-50/80"
                            @click="openDetailModal(item)"
                        >
                            <td class="px-4 py-3 text-center" @click.stop>
                                <input
                                    type="checkbox"
                                    v-model="selectedIds"
                                    :value="item.id"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-0"
                                />
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800">
                                {{ item.pemasok }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-block rounded-md px-2.5 py-1 text-[11px] font-semibold"
                                    :class="
                                        getStatusBadgeClass(item.status_label)
                                    "
                                >
                                    {{ item.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600">
                                {{ item.due_date_label }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-medium text-gray-800"
                            >
                                Rp {{ formatRupiah(item.sisa) }}
                            </td>
                            <td class="px-4 py-3 text-center" @click.stop>
                                <button
                                    @click="openActionMenu(item)"
                                    class="p-1 font-bold text-blue-600 hover:text-blue-800"
                                >
                                    •••
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bottom Footer Status Bar -->
        <footer
            class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 border-t border-gray-200 bg-white px-4 py-2 text-[11px] text-gray-600"
        >
            <div class="flex items-center space-x-1">
                <span
                    >Penjualan:
                    <strong class="text-gray-800">0 transaksi</strong></span
                >
            </div>
            <span class="text-gray-300">|</span>
            <div class="flex items-center space-x-1">
                <span
                    >Pendapatan: Kotor:
                    <strong class="text-gray-800">Rp 0</strong> · Bersih:
                    <strong class="text-gray-800">Rp 0</strong></span
                >
            </div>
            <span class="text-gray-300">|</span>
            <div class="flex items-center space-x-1">
                <span>Waktu: <strong class="text-gray-800">14:34</strong></span>
            </div>
            <span class="text-gray-300">|</span>
            <div class="flex items-center space-x-1">
                <span
                    >Versi: <strong class="text-gray-800">v1.2.37</strong></span
                >
            </div>
        </footer>

        <!-- ========================================== -->
        <!-- MODAL TAMBAH HUTANG -->
        <!-- ========================================== -->
        <div
            v-if="activeModal === 'tambahHutang'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-2 sm:p-4"
        >
            <div
                class="animate-fade-in flex min-h-[520px] w-full max-w-xl flex-col justify-between overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl"
            >
                <!-- Modal Top Bar Header -->
                <div>
                    <div
                        class="flex items-center justify-between bg-[#DCE6FF] px-4 py-3"
                    >
                        <div class="flex items-center space-x-2 text-[#32487A]">
                            <div
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-[#32487A] text-xs font-bold text-white"
                            >
                                +
                            </div>
                            <h2 class="text-sm font-bold">Tambah Hutang</h2>
                        </div>
                        <button
                            @click="closeModal"
                            class="text-lg text-gray-500 hover:text-gray-700"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Mode Selector Tabs (Pilih Supplier vs Input Manual) -->
                    <div class="p-6 pb-2">
                        <div class="mb-6 flex justify-center space-x-4">
                            <!-- Tab 1: Pilih Supplier -->
                            <button
                                @click="formTambah.mode = 'pilih'"
                                class="flex items-center space-x-1.5 rounded-xl px-5 py-2 text-xs font-semibold transition-all"
                                :class="
                                    formTambah.mode === 'pilih'
                                        ? 'bg-[#DCE6FF] text-[#2C4170] shadow-sm'
                                        : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                "
                            >
                                <span v-if="formTambah.mode === 'pilih'"
                                    >✓</span
                                >
                                <span>Pilih Supplier</span>
                            </button>

                            <!-- Tab 2: Input Manual -->
                            <button
                                @click="formTambah.mode = 'manual'"
                                class="flex items-center space-x-1.5 rounded-xl px-5 py-2 text-xs font-semibold transition-all"
                                :class="
                                    formTambah.mode === 'manual'
                                        ? 'bg-[#DCE6FF] text-[#2C4170] shadow-sm'
                                        : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                "
                            >
                                <span v-if="formTambah.mode === 'manual'"
                                    >✓</span
                                >
                                <span>Input Manual</span>
                            </button>
                        </div>

                        <!-- Form Content based on Mode -->
                        <div class="space-y-4">
                            <!-- MODE 1: PILIH SUPPLIER -->
                            <template v-if="formTambah.mode === 'pilih'">
                                <div class="relative">
                                    <label
                                        class="absolute -top-2.5 left-3 z-10 bg-white px-1 text-[11px] text-gray-500"
                                        >Supplier</label
                                    >
                                    <button
                                        @click="
                                            isSupplierSelectModalOpen = true
                                        "
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-lg border border-gray-800 bg-white px-3 py-3 text-left text-xs"
                                    >
                                        <span
                                            :class="
                                                formTambah.selectedSupplier
                                                    ? 'font-medium text-gray-800'
                                                    : 'text-gray-400'
                                            "
                                        >
                                            {{
                                                formTambah.selectedSupplier ||
                                                'Pilih atau cari supplier'
                                            }}
                                        </span>
                                        <svg
                                            class="h-4 w-4 text-gray-600"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                    <div class="mt-1">
                                        <button
                                            type="button"
                                            @click="formTambah.mode = 'manual'"
                                            class="text-[11px] font-medium text-[#3B5284] underline hover:text-blue-800"
                                        >
                                            Supplier tidak ada di daftar?
                                            Masukkan manual
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- MODE 2: INPUT MANUAL SUPPLIER -->
                            <template v-if="formTambah.mode === 'manual'">
                                <div class="relative">
                                    <div
                                        class="flex items-center rounded-lg border border-gray-800 px-3 py-3"
                                    >
                                        <span class="mr-2 text-gray-500"
                                            >🏢</span
                                        >
                                        <input
                                            v-model="formTambah.manualNama"
                                            type="text"
                                            placeholder="Nama Supplier *"
                                            class="w-full text-xs focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <div class="relative">
                                    <div
                                        class="flex items-center rounded-lg border border-gray-800 px-3 py-3"
                                    >
                                        <span class="mr-2 text-gray-500"
                                            >📞</span
                                        >
                                        <input
                                            v-model="formTambah.manualTelepon"
                                            type="text"
                                            placeholder="No. Telepon Supplier"
                                            class="w-full text-xs focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </template>

                            <!-- 1. Jumlah Hutang (Muncul "Rp" saat diklik/fokus atau saat terisi) -->
                            <div class="relative">
                                <div
                                    class="flex items-center rounded-lg border border-gray-800 bg-white px-3 py-3 transition-colors"
                                    :class="{
                                        'ring-1 ring-gray-800': isJumlahFocused,
                                    }"
                                >
                                    <span
                                        v-if="
                                            isJumlahFocused || formTambah.jumlah
                                        "
                                        class="mr-1.5 text-xs font-semibold text-gray-700 select-none"
                                    >
                                        Rp
                                    </span>
                                    <input
                                        v-model="formTambah.jumlah"
                                        type="text"
                                        placeholder="Jumlah Hutang (Rp) *"
                                        @focus="isJumlahFocused = true"
                                        @blur="isJumlahFocused = false"
                                        @input="formatJumlahInput"
                                        class="w-full bg-transparent text-xs focus:outline-none"
                                    />
                                </div>
                            </div>

                            <!-- 2. Jatuh Tempo (Ikon kalender terletak di sebelah kiri teks tanggal) -->
                            <div class="relative">
                                <label
                                    class="absolute -top-2.5 left-3 z-10 bg-white px-1 text-[11px] text-gray-500"
                                    >Jatuh Tempo</label
                                >
                                <div
                                    class="relative flex items-center rounded-lg border border-gray-800 bg-white px-3 py-2.5"
                                >
                                    <input
                                        v-model="formTambah.jatuhTempo"
                                        type="date"
                                        class="custom-date-input w-full cursor-pointer bg-transparent text-xs text-gray-700 focus:outline-none"
                                    />
                                </div>
                            </div>

                            <!-- 3. Catatan -->
                            <div class="relative">
                                <div
                                    class="flex items-center rounded-lg border border-gray-800 px-3 py-3"
                                >
                                    <span class="mr-2 text-gray-600">📄</span>
                                    <input
                                        v-model="formTambah.catatan"
                                        type="text"
                                        placeholder="Catatan (opsional)"
                                        class="w-full text-xs focus:outline-none"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button Footer -->
                <div class="mt-6 border-t border-gray-100 bg-gray-50 p-4">
                    <button
                        @click="simpanTambahHutang"
                        class="w-full rounded-lg bg-[#42598B] py-3 text-xs font-semibold text-white transition-colors hover:bg-[#344872]"
                    >
                        Simpan
                    </button>
                </div>
            </div>
        </div>

        <!-- SUB-MODAL: PILIH SUPPLIER SEARCH DIALOG -->
        <div
            v-if="isSupplierSelectModalOpen"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
        >
            <div
                class="animate-fade-in relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800">
                        Pilih Supplier
                    </h3>
                    <button
                        @click="isSupplierSelectModalOpen = false"
                        class="text-base text-gray-400 hover:text-gray-600"
                    >
                        ✕
                    </button>
                </div>

                <!-- Search Supplier Input -->
                <div class="relative mb-4">
                    <div
                        class="flex items-center rounded-xl border border-gray-400 px-3 py-2.5"
                    >
                        <svg
                            class="mr-2 h-4 w-4 text-gray-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                        <input
                            v-model="supplierSearchQuery"
                            type="text"
                            placeholder="Cari supplier..."
                            class="w-full text-xs focus:outline-none"
                        />
                    </div>
                </div>

                <!-- Supplier List -->
                <div class="max-h-60 space-y-1 overflow-y-auto pr-1">
                    <div
                        v-for="sup in filteredSupplierList"
                        :key="sup.id"
                        @click="chooseSupplier(sup.nama)"
                        class="flex cursor-pointer items-center space-x-3 rounded-xl p-2.5 transition-colors hover:bg-gray-50"
                    >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-800"
                        >
                            P
                        </div>
                        <span class="text-xs font-medium text-gray-800">{{
                            sup.nama
                        }}</span>
                    </div>

                    <div
                        v-if="filteredSupplierList.length === 0"
                        class="py-6 text-center text-xs text-gray-400"
                    >
                        Supplier tidak ditemukan.
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Detail Hutang -->
        <div
            v-if="activeModal === 'detail'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div
                class="animate-fade-in relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
            >
                <div class="mb-6 flex items-center space-x-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-lg font-bold text-red-500"
                    >
                        🏪
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-800">
                            {{ selectedHutang?.supplier_name }}
                        </h3>
                        <span
                            class="text-xs font-semibold"
                            :class="
                                selectedHutang?.status_label === 'Lunas'
                                    ? 'text-emerald-600'
                                    : 'text-red-500'
                            "
                        >
                            {{ selectedHutang?.status_label }}
                        </span>
                    </div>
                </div>

                <div class="mb-6 space-y-3 text-xs text-gray-700">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total Hutang</span>
                        <span class="font-medium"
                            >Rp
                            {{
                                formatRupiah(selectedHutang?.original_amount)
                            }}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Sudah Dibayar</span>
                        <span class="font-medium"
                            >Rp
                            {{
                                formatRupiah(selectedHutang?.paid_amount)
                            }}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Sisa Hutang</span>
                        <span class="font-medium"
                            >Rp
                            {{
                                formatRupiah(selectedHutang?.outstanding_amount)
                            }}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Jatuh Tempo</span>
                        <span class="font-medium">{{
                            selectedHutang?.due_date_label
                        }}</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <button
                        v-if="selectedHutang?.outstanding_amount > 0"
                        @click="openBayarModal"
                        class="flex w-full items-center justify-center space-x-2 rounded-xl bg-[#4C649B] py-2.5 text-xs font-medium text-white transition-colors hover:bg-[#3d517e]"
                    >
                        <span>💳</span>
                        <span>Bayar Hutang</span>
                    </button>
                    <button
                        @click="closeModal"
                        class="w-full rounded-xl border border-gray-300 py-2.5 text-xs font-medium text-slate-600 transition-colors hover:bg-gray-50"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Menu Aksi -->
        <div
            v-if="activeModal === 'actionMenu'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div
                class="animate-fade-in w-full max-w-sm divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white shadow-2xl"
            >
                <button
                    v-if="selectedHutang?.outstanding_amount > 0"
                    @click="openBayarModal"
                    class="flex w-full items-center space-x-3 px-6 py-3.5 text-left text-xs font-medium text-slate-800 hover:bg-gray-50"
                >
                    <span>💳</span>
                    <span>Bayar Hutang</span>
                </button>
                <button
                    @click="openConfirmDelete"
                    class="flex w-full items-center space-x-3 px-6 py-3.5 text-left text-xs font-medium text-red-500 hover:bg-red-50"
                >
                    <span>🗑</span>
                    <span>Hapus Hutang</span>
                </button>
                <button
                    @click="closeModal"
                    class="flex w-full items-center space-x-3 px-6 py-3.5 text-left text-xs font-medium text-slate-800 hover:bg-gray-50"
                >
                    <span>✕</span>
                    <span>Batal</span>
                </button>
            </div>
        </div>

        <!-- Modal Form Bayar Hutang -->
        <div
            v-if="activeModal === 'bayar'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div
                class="flex min-h-[400px] w-full max-w-lg flex-col justify-between overflow-hidden rounded-xl bg-white shadow-xl"
            >
                <div
                    class="flex items-center justify-between bg-[#DCE7FE] px-4 py-3"
                >
                    <div class="flex items-center space-x-2">
                        <span class="text-lg text-blue-700">💳</span>
                        <div>
                            <h3 class="text-xs font-bold text-slate-800">
                                Bayar Hutang
                            </h3>
                            <p class="text-[11px] text-gray-600">
                                {{ selectedHutang?.supplier_name }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="closeModal"
                        class="text-lg text-gray-500 hover:text-gray-700"
                    >
                        ✕
                    </button>
                </div>

                <div class="flex-1 space-y-4 p-6">
                    <div class="relative pt-2">
                        <label
                            class="absolute -top-1 left-3 bg-white px-1 text-[11px] text-gray-500"
                            >Jumlah Bayar (Rp) *</label
                        >
                        <input
                            v-model="formBayar.jumlah"
                            type="text"
                            class="w-full rounded-lg border border-gray-800 px-3 py-2.5 text-xs font-medium focus:outline-none"
                        />
                    </div>

                    <div class="relative pt-2">
                        <label
                            class="absolute -top-1 left-3 z-10 bg-white px-1 text-[11px] text-gray-500"
                            >Metode Pembayaran *</label
                        >
                        <button
                            @click="
                                isMetodeDropdownOpen = !isMetodeDropdownOpen
                            "
                            class="flex w-full items-center justify-between rounded-lg border border-gray-800 bg-white px-3 py-2.5 text-left text-xs font-semibold"
                        >
                            <span>{{ formBayar.metode }}</span>
                            <svg
                                class="h-4 w-4 text-gray-600"
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

                        <div
                            v-if="isMetodeDropdownOpen"
                            class="absolute top-full right-0 left-0 z-30 mt-1 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl"
                        >
                            <div
                                v-for="metode in metodeOptions"
                                :key="metode"
                                @click="selectMetode(metode)"
                                class="cursor-pointer px-4 py-2.5 text-xs font-medium hover:bg-gray-100"
                                :class="{
                                    'bg-gray-200/80 font-bold':
                                        formBayar.metode === metode,
                                }"
                            >
                                {{ metode }}
                            </div>
                        </div>
                    </div>

                    <div class="relative pt-2">
                        <label
                            class="absolute -top-1 left-3 bg-white px-1 text-[11px] text-gray-500"
                            >Catatan (opsional)</label
                        >
                        <div
                            class="flex items-center rounded-lg border border-gray-800 px-3 py-2.5"
                        >
                            <span class="mr-2 text-gray-400">📄</span>
                            <input
                                v-model="formBayar.catatan"
                                type="text"
                                placeholder="Catatan (opsional)"
                                class="w-full text-xs focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-100 bg-gray-50 p-4">
                    <button
                        @click="submitBayar"
                        class="w-full rounded-lg bg-[#4C649B] py-3 text-xs font-semibold text-white transition-colors hover:bg-[#3d517e]"
                    >
                        Bayar
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Hapus -->
        <div
            v-if="activeModal === 'confirmDelete'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div
                class="animate-fade-in w-full max-w-md rounded-2xl bg-[#EAEFF8] p-6 shadow-xl"
            >
                <h3 class="mb-2 text-base font-bold text-slate-800">
                    Hapus Hutang
                </h3>
                <p class="mb-6 text-xs text-slate-600">
                    Apakah Anda yakin ingin menghapus hutang ke "{{
                        selectedHutang?.supplier_name
                    }}"?
                </p>

                <div class="flex justify-end space-x-3">
                    <button
                        @click="closeModal"
                        class="rounded-lg px-4 py-2 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-50"
                    >
                        Batal
                    </button>
                    <button
                        @click="deleteHutang"
                        class="rounded-xl bg-red-600 px-5 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-red-700"
                    >
                        Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import { router, usePage } from '@inertiajs/vue3';

const page = usePage<any>();

export default {
    name: 'HutangUsaha',
    data() {
        return {
            searchQuery: '',
            selectedStatus: 'Semua Status',
            isStatusDropdownOpen: false,
            isMetodeDropdownOpen: false,
            isSupplierSelectModalOpen: false,
            isJumlahFocused: false,
            supplierSearchQuery: '',

            activeModal: null as string | null,
            selectedHutang: null as any,
            selectedIds: [] as string[],

            statusOptions: [
                'Semua Status',
                'Belum Lunas',
                'Cicilan',
                'Lunas',
                'Jatuh Tempo',
                'Dibatalkan',
            ],

            metodeOptions: [
                'Cash',
                'Transfer',
                'Debit',
                'Credit Card',
                'E-Wallet',
            ],

            formTambah: {
                mode: 'pilih',
                selectedSupplier: '',
                manualNama: '',
                manualTelepon: '',
                jumlah: '',
                jatuhTempo: '',
                catatan: '',
            },

            formBayar: {
                jumlah: '',
                metode: 'Cash',
                catatan: '',
            },
        };
    },

    computed: {
        totalHutang() {
            return page.props.summary?.total_debt || 0;
        },

        totalJatuhTempo() {
            return page.props.summary?.overdue_amount || 0;
        },

        filteredHutangList() {
            return page.props.payables?.data || [];
        },

        filteredSupplierList() {
            const suppliers =
                page.props.suppliers?.data || page.props.payables?.data || [];
            const search = this.supplierSearchQuery.toLowerCase();

            return suppliers
                .filter((supplier: any, index: number, items: any[]) => {
                    const name = supplier.nama || supplier.supplier_name || '';

                    return (
                        name.toLowerCase().includes(search) &&
                        items.findIndex(
                            (item: any) =>
                                (item.nama || item.supplier_name) === name,
                        ) === index
                    );
                })
                .map((supplier: any) => ({
                    id: supplier.id,
                    nama: supplier.nama || supplier.supplier_name,
                }));
        },

        totalData() {
            return page.props.total ?? page.props.payables?.meta?.total ?? 0;
        },
    },

    watch: {
        searchQuery() {
            this.loadData();
        },
    },

    methods: {
        formatRupiah(val: unknown) {
            if (val === undefined || val === null) {
                return '0';
            }

            return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        },

        formatJumlahInput(e: Event) {
            const value = (e.target as HTMLInputElement).value.replace(
                /\D/g,
                '',
            );

            if (value) {
                this.formTambah.jumlah = parseInt(value, 10).toLocaleString(
                    'id-ID',
                );
            } else {
                this.formTambah.jumlah = '';
            }
        },

        selectStatus(status: string) {
            this.selectedStatus = status;
            this.isStatusDropdownOpen = false;
            this.loadData();
        },

        selectMetode(metode: string) {
            this.formBayar.metode = metode;
            this.isMetodeDropdownOpen = false;
        },

        getStatusBadgeClass(status: string) {
            switch (status) {
                case 'Belum Lunas':
                    return 'bg-red-100 text-red-600';
                case 'Lunas':
                    return 'bg-emerald-100 text-emerald-600';
                case 'Cicilan':
                case 'Sebagian':
                    return 'bg-amber-100 text-amber-600';
                case 'Jatuh Tempo':
                    return 'bg-orange-100 text-orange-600';
                default:
                    return 'bg-gray-100 text-gray-600';
            }
        },

        toggleSelectAll(e: Event) {
            const target = e.target as HTMLInputElement;
            if (target.checked) {
                this.selectedIds = this.filteredHutangList.map(
                    (item: any) => item.id,
                );
            } else {
                this.selectedIds = [];
            }
        },

        loadData() {
            const statusMap: Record<string, string> = {
                'Semua Status': 'all',
                'Belum Lunas': 'open',
                Cicilan: 'partial',
                Lunas: 'paid',
                'Jatuh Tempo': 'overdue',
                Dibatalkan: 'void',
            };
            router.get(
                '/akuntansi/hutang-pemasok',
                {
                    search: this.searchQuery,
                    status: statusMap[this.selectedStatus] || 'all',
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        },

        refreshData() {
            router.reload({ only: ['payables', 'summary', 'total'] });
        },

        openTambahHutangModal() {
            this.formTambah = {
                mode: 'pilih',
                selectedSupplier: '',
                manualNama: '',
                manualTelepon: '',
                jumlah: '',
                jatuhTempo: '',
                catatan: '',
            };
            this.isJumlahFocused = false;
            this.activeModal = 'tambahHutang';
        },

        chooseSupplier(nama: string) {
            this.formTambah.selectedSupplier = nama;
            this.isSupplierSelectModalOpen = false;
        },

        simpanTambahHutang() {
            const namaSupplier =
                this.formTambah.mode === 'pilih'
                    ? this.formTambah.selectedSupplier
                    : this.formTambah.manualNama;

            if (!namaSupplier) {
                alert('Silakan pilih atau isi nama supplier!');

                return;
            }

            const jumlahNum =
                parseInt(
                    this.formTambah.jumlah.toString().replace(/\D/g, ''),
                ) || 0;

            if (!jumlahNum) {
                alert('Silakan masukkan jumlah hutang yang valid!');

                return;
            }

            let formattedDate = new Date().toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });

            if (this.formTambah.jatuhTempo) {
                const d = new Date(this.formTambah.jatuhTempo);
                formattedDate = d.toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                });
            }

            const accounts = page.props.accountOptions?.data || [];
            const expenseAccount = accounts.find((account: any) =>
                ['expense', 'asset'].includes(account.type),
            );
            const liabilityAccount = accounts.find(
                (account: any) => account.type === 'liability',
            );
            router.post(
                '/akuntansi/hutang-pemasok',
                {
                    supplier_name: namaSupplier,
                    payable_date: new Date().toISOString().slice(0, 10),
                    due_date:
                        this.formTambah.jatuhTempo ||
                        new Date().toISOString().slice(0, 10),
                    description: this.formTambah.catatan,
                    original_amount: jumlahNum,
                    expense_or_inventory_account_id: expenseAccount?.id,
                    liability_account_id: liabilityAccount?.id,
                },
                { onSuccess: () => this.closeModal() },
            );
        },

        openDetailModal(item: any) {
            this.selectedHutang = item;
            this.activeModal = 'detail';
        },

        openActionMenu(item: any) {
            this.selectedHutang = item;
            this.activeModal = 'actionMenu';
        },

        openBayarModal() {
            if (this.selectedHutang) {
                this.formBayar.jumlah = this.formatRupiah(
                    this.selectedHutang.outstanding_amount,
                );
            }

            this.activeModal = 'bayar';
        },

        openConfirmDelete() {
            this.activeModal = 'confirmDelete';
        },

        closeModal() {
            this.activeModal = null;
            this.isMetodeDropdownOpen = false;
            this.isSupplierSelectModalOpen = false;
            this.isJumlahFocused = false;
        },

        submitBayar() {
            if (!this.selectedHutang) {
                return;
            }

            const cleanJumlah =
                parseInt(this.formBayar.jumlah.replace(/\./g, '')) || 0;
            const accounts = page.props.accountOptions?.data || [];
            const cashAccount = accounts.find(
                (account: any) => account.type === 'asset',
            );
            router.post(
                `/akuntansi/hutang-pemasok/${this.selectedHutang.id}/payments`,
                {
                    payment_date: new Date().toISOString().slice(0, 10),
                    amount: cleanJumlah,
                    cash_or_bank_account_id: cashAccount?.id,
                    payment_method:
                        this.formBayar.metode.toLowerCase() === 'cash'
                            ? 'cash'
                            : 'other',
                    notes: this.formBayar.catatan,
                },
                { onSuccess: () => this.closeModal() },
            );
        },

        deleteHutang() {
            if (this.selectedHutang) {
                router.delete(
                    `/akuntansi/hutang-pemasok/${this.selectedHutang.id}`,
                    { onSuccess: () => this.closeModal() },
                );
            }

            this.closeModal();
        },
    },
};
</script>

<style scoped>
/* Memindahkan ikon kalender native HTML5 ke sebelah kiri teks tanggal */
.custom-date-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    align-items: center;
}

/* Mengatur posisi & margin indikator kalender di sebelah kiri */
.custom-date-input::-webkit-calendar-picker-indicator {
    cursor: pointer;
    margin-right: 8px;
    margin-left: 0;
    padding: 0;
}

/* Memastikan teks tanggal rata kiri */
.custom-date-input::-webkit-datetime-edit {
    flex: 1;
    text-align: left;
    padding: 0;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.97);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
.animate-fade-in {
    animation: fadeIn 0.15s ease-out forwards;
}
</style>
