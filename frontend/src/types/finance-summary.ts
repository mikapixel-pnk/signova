export type FinanceSummary = {
  cash_bank: {
    total_balance: string;
    cash_balance: string;
    bank_balance: string;
    account_count: number;
  };

  cashflow: {
    total_in: string;
    total_out: string;
    net: string;

    breakdown: {
      customer_payment_net: string;
      manual_income_net: string;
      expense_net: string;
    };
  };

  receivable: {
    outstanding_total: string;
    overdue_total: string;
    invoice_count: number;
    overdue_count: number;
  };

  period: {
    from: string;
    to: string;
  };
};

export type FinanceSummaryResponse = {
  data: FinanceSummary;
};
