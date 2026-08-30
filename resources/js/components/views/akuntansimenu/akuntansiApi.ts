import { useApi } from '../../../composables/useApi';

/**
 * Facade endpoint akuntansi untuk dipakai oleh view di folder ini.
 * Detail URL dan payload tetap didefinisikan di useApi.
 */
export function useAccountingApi() {
    const api = useApi();

    return {
        expenses: {
            list: api.getExpenses,
            create: api.createExpense,
            detail: api.getExpense,
            update: api.updateExpense,
            remove: api.deleteExpense,
            void: api.voidExpense,
            categories: api.getExpenseCategories,
            export: api.exportExpenses,
        },
        recurringExpenses: {
            list: api.getRecurringExpenses,
            create: api.createRecurringExpense,
            update: api.updateRecurringExpense,
            generate: api.generateRecurringExpense,
            updateStatus: api.updateRecurringExpenseStatus,
        },
        supplierPayables: {
            list: api.getSupplierPayables,
            detail: api.getSupplierPayable,
            create: api.createSupplierPayable,
            createPayment: api.createSupplierPayablePayment,
            export: api.exportSupplierPayables,
        },
        customerReceivables: {
            list: api.getCustomerReceivables,
            detail: api.getCustomerReceivable,
            create: api.createCustomerReceivable,
            createPayment: api.createCustomerReceivablePayment,
            export: api.exportCustomerReceivables,
        },
        taxes: {
            transactions: api.getTaxTransactions,
            rates: api.getTaxRates,
            createRate: api.createTaxRate,
            updateRate: api.updateTaxRate,
            export: api.exportTaxTransactions,
        },
        accounts: {
            list: api.getAccounts,
            create: api.createAccount,
            update: api.updateAccount,
            remove: api.deleteAccount,
            search: api.searchAccountingAccounts,
        },
        journals: {
            list: api.getJournalEntries,
            create: api.createJournalEntry,
            detail: api.getJournalEntry,
            post: api.postJournalEntry,
            reverse: api.reverseJournalEntry,
            preview: api.previewAccountingJournal,
        },
        reports: {
            trialBalance: api.getTrialBalance,
            exportTrialBalance: api.exportTrialBalance,
            profitAndLoss: api.getProfitAndLoss,
            exportProfitAndLoss: api.exportProfitAndLoss,
            balanceSheet: api.getBalanceSheet,
            exportBalanceSheet: api.exportBalanceSheet,
        },
        fixedAssets: {
            list: api.getFixedAssets,
            create: api.createFixedAsset,
            detail: api.getFixedAsset,
            depreciate: api.depreciateFixedAsset,
            dispose: api.disposeFixedAsset,
        },
        fixedAssetCategories: {
            list: api.getFixedAssetCategories,
            create: api.createFixedAssetCategory,
            update: api.updateFixedAssetCategory,
            remove: api.deleteFixedAssetCategory,
        },
        settings: {
            get: api.getAccountingSettings,
            update: api.updateAccountingSettings,
            previewJournal: api.previewAccountingSettingsJournal,
        },
        shared: {
            referenceNumber: api.getAccountingReferenceNumber,
            checkPeriod: api.checkAccountingPeriod,
            activeTaxRates: api.getActiveAccountingTaxRates,
        },
    };
}
