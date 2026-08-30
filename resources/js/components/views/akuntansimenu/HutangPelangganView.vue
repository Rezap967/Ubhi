<template>
    <div
        class="flex min-h-screen flex-col justify-between bg-[#F4F6F9] font-sans text-sm text-gray-800"
    >
        <!-- Main Area -->
        <div class="space-y-4 p-4 md:p-6">
            <!-- Header Utama -->
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-800">
                    Piutang Pelanggan
                </h1>
                <button
                    @click="openTambahPiutangModal"
                    class="text-2xl font-light text-gray-600 hover:text-gray-900 focus:outline-none"
                >
                    +
                </button>
            </div>

            <!-- Card Summary (Total Piutang & Jatuh Tempo) -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Card Total Piutang -->
                <div
                    class="flex flex-col justify-center rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm"
                >
                    <div
                        class="flex items-center space-x-2 text-xs font-medium text-gray-500"
                    >
                        <svg
                            class="h-4 w-4 text-emerald-500"
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
                        <span>Total Piutang</span>
                    </div>
                    <div class="mt-1 text-base font-bold text-emerald-600">
                        Rp {{ formatRupiah(totalPiutang) }}
                    </div>
                </div>

                <!-- Card Jatuh Tempo -->
                <div
                    class="flex flex-col justify-center rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm"
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

            <!-- Filter Section (Kata Kunci & Status) -->
            <div class="flex flex-wrap items-center gap-4 text-xs">
                <div class="flex flex-col gap-1">
                    <label class="text-gray-600">Kata Kunci :</label>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari nama pelanggan..."
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

                    <!-- Dropdown Opsi Status -->
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

            <!-- Toolbar Tombol Aksi & Jumlah Data -->
            <div class="flex items-center justify-between pt-2">
                <div class="flex items-center space-x-1.5">
                    <button
                        @click="openTambahPiutangModal"
                        class="flex h-7 w-7 items-center justify-center rounded bg-emerald-600 text-white transition-colors hover:bg-emerald-700"
                        title="Tambah Piutang"
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

            <!-- Tabel Data Hutang Pelanggan -->
            <div
                class="overflow-x-auto rounded-xl border border-gray-200/80 bg-white shadow-sm"
            >
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50/50 text-gray-600"
                        >
                            <th class="px-4 py-3 font-semibold">
                                Pelanggan
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
                            v-for="item in filteredPiutangList"
                            :key="item.id"
                            class="cursor-pointer transition-colors hover:bg-gray-50/80"
                            @click="openDetailModal(item)"
                        >
                            <td class="px-4 py-3 font-medium text-gray-800">
                                {{ item.pelanggan }}
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
                                {{ item.jatuhTempo }}
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

        <!-- Bottom Status Bar -->
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
                <span>Waktu: <strong class="text-gray-800">15:22</strong></span>
            </div>
            <span class="text-gray-300">|</span>
            <div class="flex items-center space-x-1">
                <span
                    >Versi: <strong class="text-gray-800">v1.2.37</strong></span
                >
            </div>
        </footer>

        <!-- ========================================== -->
        <!-- MODAL 1: DETAIL PIUTANG PELANGGAN -->
        <!-- ========================================== -->
        <div
            v-if="activeModal === 'detail'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div
                class="animate-fade-in relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
            >
                <!-- Profil Pelanggan Singkat -->
                <div class="mb-6 flex items-center space-x-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-100 text-lg font-bold text-sky-500"
                    >
                        👤
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-800">
                            {{ selectedItem?.pelanggan }}
                        </h3>
                        <span
                            class="text-xs font-semibold"
                            :class="
                                selectedItem?.status_label === 'Lunas'
                                    ? 'text-gray-400'
                                    : 'text-sky-500'
                            "
                        >
                            {{ selectedItem?.status_label }}
                        </span>
                    </div>
                </div>

                <!-- Rincian Piutang -->
                <div class="mb-6 space-y-3 text-xs text-gray-700">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total Piutang</span>
                        <span class="font-medium"
                            >Rp
                            {{ formatRupiah(selectedItem?.totalPiutang) }}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Sudah Diterima</span>
                        <span class="font-medium"
                            >Rp
                            {{
                                formatRupiah(selectedItem?.sudahDiterima)
                            }}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Sisa Piutang</span>
                        <span class="font-medium"
                            >Rp {{ formatRupiah(selectedItem?.sisa) }}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Jatuh Tempo</span>
                        <span class="font-medium">{{
                            selectedItem?.jatuhTempoFull
                        }}</span>
                    </div>
                </div>

                <!-- Tombol Aksi Modal Detail -->
                <div class="space-y-2">
                    <button
                        v-if="selectedItem?.sisa > 0"
                        @click="openTerimaBayarModal"
                        class="flex w-full items-center justify-center space-x-2 rounded-xl bg-[#4C649B] py-2.5 text-xs font-medium text-white transition-colors hover:bg-[#3d517e]"
                    >
                        <span>💳</span>
                        <span>Terima Pembayaran</span>
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

        <!-- ========================================== -->
        <!-- MODAL 2: MENU AKSI (Tiga Titik) -->
        <!-- ========================================== -->
        <div
            v-if="activeModal === 'actionMenu'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div
                class="animate-fade-in w-full max-w-sm divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white shadow-2xl"
            >
                <button
                    v-if="selectedItem?.sisa > 0"
                    @click="openTerimaBayarModal"
                    class="flex w-full items-center space-x-3 px-6 py-3.5 text-left text-xs font-medium text-slate-800 hover:bg-gray-50"
                >
                    <span>💳</span>
                    <span>Terima Pembayaran</span>
                </button>
                <button
                    @click="openConfirmDelete"
                    class="flex w-full items-center space-x-3 px-6 py-3.5 text-left text-xs font-medium text-red-500 hover:bg-red-50"
                >
                    <span>🗑</span>
                    <span>Hapus Piutang</span>
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

        <!-- ========================================== -->
        <!-- MODAL 3: FORM TERIMA PEMBAYARAN -->
        <!-- ========================================== -->
        <div
            v-if="activeModal === 'terimaBayar'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div
                class="flex min-h-[420px] w-full max-w-lg flex-col justify-between overflow-hidden rounded-xl bg-white shadow-xl"
            >
                <!-- Header Modal Terima Pembayaran -->
                <div
                    class="flex items-center justify-between bg-[#DCE7FE] px-4 py-3"
                >
                    <div class="flex items-center space-x-2">
                        <span class="text-lg text-blue-700">💳</span>
                        <div>
                            <h3 class="text-xs font-bold text-slate-800">
                                Terima Pembayaran
                            </h3>
                            <p class="text-[11px] text-gray-600">
                                {{ selectedItem?.pelanggan }}
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

                <!-- Body Form -->
                <div class="flex-1 space-y-4 p-6">
                    <!-- Input Jumlah Diterima -->
                    <div class="relative pt-2">
                        <label
                            class="absolute -top-1 left-3 bg-white px-1 text-[11px] text-gray-500"
                            >Jumlah Diterima (Rp) *</label
                        >
                        <input
                            v-model="formBayar.jumlah"
                            type="text"
                            class="w-full rounded-lg border border-gray-800 px-3 py-2.5 text-xs font-medium focus:outline-none"
                        />
                    </div>

                    <!-- Input Dropdown Metode Pembayaran -->
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

                        <!-- Menu Dropdown Pilih Metode Pembayaran -->
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

                    <!-- Input Catatan Opsional -->
                    <div class="relative pt-2">
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

                <!-- Footer Tombol Simpan -->
                <div class="border-t border-gray-100 bg-gray-50 p-4">
                    <button
                        @click="submitBayar"
                        class="w-full rounded-lg bg-[#4C649B] py-3 text-xs font-semibold text-white transition-colors hover:bg-[#3d517e]"
                    >
                        Terima
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL 4: KONFIRMASI HAPUS PIUTANG -->
        <!-- ========================================== -->
        <div
            v-if="activeModal === 'confirmDelete'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div
                class="animate-fade-in w-full max-w-md rounded-2xl bg-[#EAEFF8] p-6 shadow-xl"
            >
                <h3 class="mb-2 text-base font-bold text-slate-800">
                    Hapus Piutang
                </h3>
                <p class="mb-6 text-xs text-slate-600">
                    Apakah Anda yakin ingin menghapus piutang dari "{{
                        selectedItem?.pelanggan
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
                        @click="deletePiutang"
                        class="rounded-xl bg-red-600 px-5 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-red-700"
                    >
                        Hapus
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL 5: FORM TAMBAH PIUTANG BARU -->
        <!-- ========================================== -->
        <div
            v-if="activeModal === 'tambahPiutang'"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div
                class="animate-fade-in flex w-full max-w-md flex-col justify-between overflow-hidden rounded-xl bg-white shadow-2xl"
            >
                <!-- Header Modal Tambah Piutang -->
                <div
                    class="flex items-center justify-between bg-[#DCE7FE] px-4 py-3"
                >
                    <div class="flex items-center space-x-2">
                        <span class="text-lg text-blue-700">➕</span>
                        <h3 class="text-xs font-bold text-slate-800">
                            Tambah Piutang Pelanggan
                        </h3>
                    </div>
                    <button
                        @click="closeModal"
                        class="text-lg text-gray-500 hover:text-gray-700"
                    >
                        ✕
                    </button>
                </div>

                <!-- Body Form Tambah -->
                <div class="space-y-4 p-6 text-xs">
                    <!-- Input Nama Pelanggan -->
                    <div class="relative pt-1">
                        <label
                            class="absolute -top-1.5 left-3 z-10 bg-white px-1 text-[11px] text-gray-500"
                            >Nama Pelanggan *</label
                        >
                        <input
                            v-model="formTambah.pelanggan"
                            type="text"
                            placeholder="Masukkan nama pelanggan"
                            class="w-full rounded-lg border border-gray-400 px-3 py-2.5 text-xs focus:border-blue-600 focus:outline-none"
                        />
                    </div>

                    <!-- Input Nominal Piutang -->
                    <div class="relative pt-1">
                        <label
                            class="absolute -top-1.5 left-3 z-10 bg-white px-1 text-[11px] text-gray-500"
                            >Jumlah Piutang (Rp) *</label
                        >
                        <input
                            v-model="formTambah.totalPiutang"
                            type="text"
                            placeholder="0"
                            class="w-full rounded-lg border border-gray-400 px-3 py-2.5 text-xs font-semibold focus:border-blue-600 focus:outline-none"
                        />
                    </div>

                    <!-- Input Tanggal Jatuh Tempo -->
                    <div class="relative pt-1">
                        <label
                            class="absolute -top-1.5 left-3 z-10 bg-white px-1 text-[11px] text-gray-500"
                            >Jatuh Tempo *</label
                        >
                        <input
                            v-model="formTambah.jatuhTempo"
                            type="date"
                            class="w-full rounded-lg border border-gray-400 px-3 py-2.5 text-xs focus:border-blue-600 focus:outline-none"
                        />
                    </div>

                    <!-- Input Catatan Opsional -->
                    <div class="relative pt-1">
                        <label
                            class="absolute -top-1.5 left-3 z-10 bg-white px-1 text-[11px] text-gray-500"
                            >Catatan (Opsional)</label
                        >
                        <textarea
                            v-model="formTambah.catatan"
                            rows="2"
                            placeholder="Tambahkan keterangan piutang..."
                            class="w-full rounded-lg border border-gray-400 px-3 py-2 text-xs focus:border-blue-600 focus:outline-none"
                        ></textarea>
                    </div>
                </div>

                <!-- Footer Tombol Simpan -->
                <div
                    class="flex justify-end space-x-2 border-t border-gray-100 bg-gray-50 p-4"
                >
                    <button
                        @click="closeModal"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-100"
                    >
                        Batal
                    </button>
                    <button
                        @click="submitTambahPiutang"
                        class="rounded-lg bg-[#4C649B] px-5 py-2 text-xs font-semibold text-white transition-colors hover:bg-[#3d517e]"
                    >
                        Simpan
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
    name: 'PiutangPelanggan',
    mounted() {
        if (!page.props.receivables) {
            router.get(
                '/akuntansi/piutang-pelanggan',
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }
    },
    data() {
        return {
            searchQuery: '',
            selectedStatus: 'Semua Status',
            isStatusDropdownOpen: false,
            isMetodeDropdownOpen: false,
            activeModal: null as string | null,
            selectedItem: null as any,
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
            formBayar: { jumlah: '', metode: 'Cash', catatan: '' },
            formTambah: {
                pelanggan: '',
                totalPiutang: '',
                jatuhTempo: '',
                catatan: '',
            },
        };
    },

    computed: {
        totalPiutang() {
            return page.props.summary?.total_receivable ?? 0;
        },
        totalJatuhTempo() {
            return page.props.summary?.overdue_amount ?? 0;
        },
        filteredPiutangList() {
            return page.props.receivables?.data ?? [];
        },
        totalData() {
            return page.props.total ?? page.props.receivables?.meta?.total ?? 0;
        },
    },

    watch: {
        searchQuery() {
            this.loadData();
        },
    },

    methods: {
        formatRupiah(val: unknown) {
            if (val === undefined || val === null) return '0';
            return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
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
                    return 'bg-emerald-100 text-emerald-600';
                case 'Lunas':
                    return 'bg-gray-100 text-gray-500';
                case 'Cicilan':
                    return 'bg-sky-100 text-sky-500';
                case 'Jatuh Tempo':
                    return 'bg-amber-100 text-amber-600';
                default:
                    return 'bg-gray-100 text-gray-600';
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
                '/akuntansi/piutang-pelanggan',
                {
                    search: this.searchQuery,
                    status: statusMap[this.selectedStatus] || 'all',
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        },

        refreshData() {
            router.reload({ only: ['receivables', 'summary', 'total'] });
        },

        openTambahPiutangModal() {
            this.formTambah = {
                pelanggan: '',
                totalPiutang: '',
                jatuhTempo: new Date().toISOString().slice(0, 10),
                catatan: '',
            };
            this.activeModal = 'tambahPiutang';
        },

        submitTambahPiutang() {
            const rawAmount =
                parseInt(
                    this.formTambah.totalPiutang.toString().replace(/\D/g, ''),
                    10,
                ) || 0;
            if (!this.formTambah.pelanggan.trim() || rawAmount <= 0) {
                alert(
                    'Nama pelanggan dan jumlah piutang wajib diisi dengan benar!',
                );
                return;
            }
            const accounts = page.props.accountOptions?.data || [];
            const receivableAccount = accounts.find(
                (account: any) => account.type === 'asset',
            );
            const revenueAccount = accounts.find(
                (account: any) => account.type === 'revenue',
            );
            router.post(
                '/akuntansi/piutang-pelanggan',
                {
                    customer_name: this.formTambah.pelanggan.trim(),
                    receivable_date: new Date().toISOString().slice(0, 10),
                    due_date: this.formTambah.jatuhTempo,
                    description: this.formTambah.catatan,
                    original_amount: rawAmount,
                    receivable_account_id: receivableAccount?.id,
                    revenue_account_id: revenueAccount?.id,
                },
                { onSuccess: () => this.closeModal() },
            );
        },

        openDetailModal(item: any) {
            this.selectedItem = item;
            this.activeModal = 'detail';
        },

        openActionMenu(item: any) {
            this.selectedItem = item;
            this.activeModal = 'actionMenu';
        },

        openTerimaBayarModal() {
            if (this.selectedItem)
                this.formBayar.jumlah = this.formatRupiah(
                    this.selectedItem.sisa,
                );
            this.activeModal = 'terimaBayar';
        },

        openConfirmDelete() {
            this.activeModal = 'confirmDelete';
        },

        closeModal() {
            this.activeModal = null;
            this.isMetodeDropdownOpen = false;
        },

        submitBayar() {
            if (!this.selectedItem) return;
            const amount =
                parseInt(this.formBayar.jumlah.replace(/\./g, ''), 10) || 0;
            const accounts = page.props.accountOptions?.data || [];
            const cashAccount = accounts.find(
                (account: any) => account.type === 'asset',
            );
            const methodMap: Record<string, string> = {
                Cash: 'cash',
                Transfer: 'transfer',
                'E-Wallet': 'ewallet',
                Debit: 'other',
                'Credit Card': 'other',
            };
            router.post(
                `/akuntansi/piutang-pelanggan/${this.selectedItem.id}/payments`,
                {
                    payment_date: new Date().toISOString().slice(0, 10),
                    amount,
                    cash_or_bank_account_id: cashAccount?.id,
                    payment_method: methodMap[this.formBayar.metode] || 'other',
                    notes: this.formBayar.catatan,
                },
                { onSuccess: () => this.closeModal() },
            );
        },

        deletePiutang() {
            if (this.selectedItem) {
                router.delete(
                    `/akuntansi/piutang-pelanggan/${this.selectedItem.id}`,
                    { onSuccess: () => this.closeModal() },
                );
            }
        },
    },
};
</script>

<style scoped>
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
