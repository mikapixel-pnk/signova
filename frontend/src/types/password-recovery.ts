export type ForgotPasswordPayload = {
  identifier: string;
};

export type ForgotPasswordResponse = {
  message: string;

  data: {
    request_id: string;
  };
};

export type VerifyPasswordResetPayload = {
  challenge_id: string;
  code: string;
};

export type VerifyPasswordResetResponse = {
  message: string;

  data: {
    reset_proof: string;
  };
};

export type ResetPasswordPayload = {
  reset_proof: string;

  password: string;
  password_confirmation: string;
};

export type ResetPasswordResponse = {
  message: string;
};
