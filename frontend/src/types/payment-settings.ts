export type PaymentQrMetadata = {
  id: string;
  mime_type: string;
  size_bytes: number;
  original_name: string;
};

export type PaymentSettings = {
  bank_transfer_enabled: boolean;
  static_qr_enabled: boolean;
  has_static_qr: boolean;
  static_qr: PaymentQrMetadata | null;
  midtrans_enabled: boolean;
  partial_payment_enabled: boolean;
};

export type PaymentSettingsResponse = {
  success: boolean;
  data: PaymentSettings;
  message?: string | null;
  request_id?: string | null;
};

export type PaymentSettingsPayload = {
  bank_transfer_enabled?: boolean;
  static_qr_enabled?: boolean;
  partial_payment_enabled?: boolean;
};
