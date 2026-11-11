/** A valid activation link: whose account it activates and until when it works (PD-34). */
export interface ActivationLink {
  username: string;
  expiresAt: Date;
}

/** A TOTP device to enroll: the secret to type by hand and the URI the QR code encodes. */
export interface TotpEnrollment {
  secret: string;
  provisioningUri: string;
}
