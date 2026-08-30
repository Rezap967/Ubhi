// POS Session & Cart types — FT-KASIR-01 & FT-KASIR-03

export interface PosProduct {
    product_id: number;
    name: string;
    sku?: string | null;
    barcode?: string | null;
    category?: { id: number | null; name: string } | null;
    brand?: { id: number; name: string } | null;
    unit?: string | null;
    price: number;
    stock_available: number;
    stock_status: 'available' | 'low_stock' | 'out_of_stock';
    can_be_sold: boolean;
}

export interface PosCategory {
    id: number | null;
    name: string;
    product_count?: number;
}

export interface PosCartItem {
    item_id: number;
    product_id: number | null;
    product_name: string;
    sku?: string | null;
    barcode?: string | null;
    unit_name?: string | null;
    unit_price: number;
    quantity: number;
    discount_value?: number;
    subtotal: number;
}

export interface PosAppliedDiscount {
    id: number;
    cart_id: number;
    coupon_id: number | null;
    coupon_name: string | null;
    promotion_id: number | null;
    promotion_name: string | null;
    scope: string;
    discount_type: 'diskon_persen' | 'potongan_tetap';
    discount_amount: number;
    source: 'manual' | 'auto';
    status: string;
}

export interface PosCustomer {
    id: number;
    code: string;
    name: string;
    phone: string | null;
    email: string | null;
    customer_group: { id: number; name: string } | null;
}

export interface PosCart {
    cart_id: number;
    session_id: number;
    status: 'active' | 'held' | 'checked_out' | 'cleared';
    items_count: number;
    subtotal: number;
    subtotal_amount?: number;
    discount_total: number;
    tax_total: number;
    grand_total: number;
    items: PosCartItem[];
    customer: PosCustomer | null;
    applied_discounts?: PosAppliedDiscount[];
    formatted?: {
        subtotal?: string;
        discount_total?: string;
        tax_total?: string;
        grand_total?: string;
    };
}

export interface PosSession {
    id: number;
    store_id: number;
    user_id: number;
    device_id: string;
    status: 'open' | 'closed' | 'force_closed';
    opening_cash: number;
    closing_cash: number | null;
    expected_cash: number;
    cash_difference: number | null;
    opened_at: string | null;
    closed_at: string | null;
    cart?: PosCart | null;
}

export interface PosSummary {
    transaction_count: number;
    gross_revenue: number;
    net_revenue: number;
}

export interface OperationalSummarySession {
    session_id: number;
    status: string;
    opened_at: string | null;
    duration_seconds: number;
    opening_cash: number;
    expected_cash: number;
}

export interface OperationalSummaryTransactions {
    transaction_count: number;
    gross_revenue: number;
    discount_total: number;
    tax_total: number;
    net_revenue: number;
    voided_count: number;
    voided_amount: number;
}

export interface OperationalSummaryPayments {
    cash_total: number;
    non_cash_total: number;
    qris_total: number;
    debit_total: number;
    credit_total: number;
    transfer_total: number;
    ewallet_total: number;
}

export interface OperationalSummaryFormatted {
    gross_revenue: string;
    net_revenue: string;
    cash_total: string;
    non_cash_total: string;
    opening_cash: string;
    expected_cash: string;
}

export interface OperationalSummaryMeta {
    server_time: string;
    app_version: string;
}

export interface OperationalSummary {
    session: OperationalSummarySession;
    transactions: OperationalSummaryTransactions;
    payments: OperationalSummaryPayments;
    formatted: OperationalSummaryFormatted;
    meta: OperationalSummaryMeta;
}

// ==========================================================
// FT-KASIR-05 — Checkout & Payment
// ==========================================================

export interface PaymentMethod {
    code: 'cash' | 'qris' | 'debit' | 'credit' | 'transfer' | 'ewallet';
    name: string;
    requires_reference: boolean;
}

export interface CheckoutPayment {
    payment_method: string;
    amount: number;
    reference_number?: string | null;
}

export interface CheckoutPayload {
    device_id: string;
    cart_id: number;
    customer_id?: number | null;
    idempotency_key: string;
    payments: CheckoutPayment[];
    notes?: string | null;
}

export interface CheckoutTransaction {
    transaction_id: number;
    transaction_number: string;
    status: string;
    subtotal: number;
    discount_total: number;
    tax_total: number;
    grand_total: number;
    paid_amount: number;
    change_amount: number;
    receipt_number?: string | null;
}
