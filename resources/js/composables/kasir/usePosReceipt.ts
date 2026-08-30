import axios from 'axios';
import { ref } from 'vue';

export interface PosReceiptPayload {
    transaction: {
        id: number;
        transaction_number: string;
        transaction_at: string;
        subtotal: string;
        discount_total: string;
        tax_total: string;
        grand_total: string;
        paid_amount: string;
        change_amount: string;
        status: string;
        notes: string | null;
    };
    store: {
        id: number;
        name: string;
        address: string | null;
        phone: string | null;
    };
    cashier: {
        id: number;
        name: string;
    };
    items: Array<{
        id: number;
        product_id: number;
        product_name: string;
        sku: string | null;
        quantity: number;
        unit_price: string;
        subtotal: string;
        discount_amount: string;
        tax_amount: string;
        total_price: string;
    }>;
    payments: Array<{
        id: number;
        payment_method: string;
        amount: string;
        reference_number: string | null;
        payment_at: string | null;
    }>;
}

export interface PosReceipt {
    id: number;
    transaction_id: number;
    receipt_number: string;
    receipt_payload: PosReceiptPayload;
    print_count: number;
    printed_at: string | null;
    created_at: string;
    updated_at: string;
}

function formatRupiah(value: number | string): string {
    const numericValue = typeof value === 'string' ? parseFloat(value) : value;

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(numericValue || 0);
}

function formatDate(dateString?: string | null): string {
    if (!dateString) {
        return '-';
    }

    return new Date(dateString).toLocaleString('id-ID', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function generateReceiptHtml(receipt: PosReceipt): string {
    const p = receipt.receipt_payload;
    const itemsHtml = p.items
        .map(
            (item) => `
        <div class="row">
            <div>${item.product_name}<br/>${item.quantity} x ${formatRupiah(item.unit_price)}</div>
            <div style="text-align: right;">${formatRupiah(item.total_price)}</div>
        </div>
    `,
        )
        .join('');

    const paymentsHtml = p.payments
        .map(
            (pay) => `
        <div class="row">
            <div>Bayar (${pay.payment_method})</div>
            <div>${formatRupiah(pay.amount)}</div>
        </div>
    `,
        )
        .join('');

    return `
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Receipt ${receipt.receipt_number}</title>
<style>
  @page {
    size: 58mm auto;
    margin: 0;
  }
  body {
    margin: 0;
    padding: 8px;
    font-family: 'Courier New', Courier, monospace, Arial, sans-serif;
    font-size: 12px;
    color: #000;
    background: #fff;
    width: 58mm;
  }
  .center { text-align: center; }
  .bold { font-weight: bold; }
  .row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 2px;
  }
  .divider {
    border-top: 1px dashed #000;
    margin: 6px 0;
  }
  p { margin: 2px 0; }
  .mt-2 { margin-top: 8px; }
  .text-right { text-align: right; }
</style>
</head>
<body>
    <div class="center">
        <h2 style="margin: 0 0 4px 0; font-size: 14px;">${p.store.name}</h2>
        ${p.store.address ? `<p>${p.store.address}</p>` : ''}
        ${p.store.phone ? `<p>${p.store.phone}</p>` : ''}
    </div>
    
    <div class="divider"></div>
    
    <div>
        <p>No: ${receipt.receipt_number}</p>
        <p>Trx: ${p.transaction.transaction_number}</p>
        <p>Tgl: ${formatDate(p.transaction.transaction_at)}</p>
        <p>Kasir: ${p.cashier.name}</p>
    </div>
    
    <div class="divider"></div>
    
    <div>${itemsHtml}</div>
    
    <div class="divider"></div>
    
    <div>
        <div class="row">
            <div>Subtotal</div>
            <div>${formatRupiah(p.transaction.subtotal)}</div>
        </div>
        ${
            parseFloat(p.transaction.discount_total) > 0
                ? `
        <div class="row">
            <div>Diskon</div>
            <div>-${formatRupiah(p.transaction.discount_total)}</div>
        </div>`
                : ''
        }
        ${
            parseFloat(p.transaction.tax_total) > 0
                ? `
        <div class="row">
            <div>Pajak</div>
            <div>${formatRupiah(p.transaction.tax_total)}</div>
        </div>`
                : ''
        }
        <div class="row bold mt-2" style="font-size: 14px;">
            <div>TOTAL</div>
            <div>${formatRupiah(p.transaction.grand_total)}</div>
        </div>
    </div>
    
    <div class="divider"></div>
    
    <div>
        ${paymentsHtml}
        <div class="row">
            <div>Kembali</div>
            <div>${formatRupiah(p.transaction.change_amount)}</div>
        </div>
    </div>
    
    <div class="divider"></div>
    
    <div class="center mt-2">
        <p>Terima Kasih</p>
        <p style="font-size: 10px;">Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.</p>
    </div>
</body>
</html>`;
}

export function usePosReceipt() {
    const isFetchingReceipt = ref(false);
    const isPrintingReceipt = ref(false);
    const receipt = ref<PosReceipt | null>(null);
    const error = ref<string | null>(null);

    const fetchReceipt = async (transactionId: number) => {
        isFetchingReceipt.value = true;
        error.value = null;

        try {
            const response = await axios.get<{
                success: boolean;
                data: PosReceipt;
                message: string;
            }>(`/api/v1/pos/transactions/${transactionId}/receipt`);
            receipt.value = response.data.data;

            return response.data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Gagal memuat struk.';

            throw err;
        } finally {
            isFetchingReceipt.value = false;
        }
    };

    const printReceipt = async (transactionId: number) => {
        isPrintingReceipt.value = true;
        error.value = null;

        try {
            const response = await axios.post<{
                success: boolean;
                data: PosReceipt;
                message: string;
            }>(`/api/v1/pos/transactions/${transactionId}/print`);
            receipt.value = response.data.data;

            return response.data.data;
        } catch (err: any) {
            error.value =
                err.response?.data?.message || 'Gagal mencetak struk.';

            throw err;
        } finally {
            isPrintingReceipt.value = false;
        }
    };

    const clearReceipt = () => {
        receipt.value = null;
        error.value = null;
    };

    const executeIframePrint = async (
        receiptData: PosReceipt,
    ): Promise<void> => {
        return new Promise((resolve, reject) => {
            try {
                const iframe = document.createElement('iframe');
                iframe.style.position = 'fixed';
                iframe.style.right = '0';
                iframe.style.bottom = '0';
                iframe.style.width = '0';
                iframe.style.height = '0';
                iframe.style.border = '0';
                document.body.appendChild(iframe);

                const doc = iframe.contentWindow?.document;

                if (!doc) {
                    throw new Error('Tidak dapat membuat print frame.');
                }

                doc.open();
                doc.write(generateReceiptHtml(receiptData));
                doc.close();

                setTimeout(() => {
                    iframe.contentWindow?.focus();
                    iframe.contentWindow?.print();

                    setTimeout(() => {
                        document.body.removeChild(iframe);
                        resolve();
                    }, 1000);
                }, 300);
            } catch (err) {
                reject(err);
            }
        });
    };

    return {
        isFetchingReceipt,
        isPrintingReceipt,
        receipt,
        error,
        fetchReceipt,
        printReceipt,
        clearReceipt,
        executeIframePrint,
    };
}
