# SIGNOVA Quotation Pricing Convention

Status: LOCKED BASELINE
Scope: Quotation pricing foundation
Canonical API/internal codes remain English.

## 1. Authority

Backend is the source of truth for quotation monetary calculations.

Frontend MUST NOT submit `amount`, `subtotal`, `tax_total`,
`discount_total`, or `total` as authoritative calculated values.

Catalog `base_price` provides the default unit price.
A quotation version stores immutable pricing snapshots.

## 2. Pricing Methods

### STANDARD

Gross:

quantity * unit_price

### AREA

Required calculation inputs:

- width
- height

Gross:

quantity * width * height * unit_price

### LENGTH

Required calculation input:

- length

Gross:

quantity * length * unit_price

### VOLUME

Required calculation inputs:

- width
- height
- depth

Gross:

quantity * width * height * depth * unit_price

### TIME

Required calculation input:

- duration

Gross:

quantity * duration * unit_price

### PACKAGE

Gross:

quantity * unit_price

### MANUAL

Gross:

quantity * unit_price

MANUAL permits an explicitly supplied unit price but the final
amount is still calculated by the backend.

## 3. Pricing Config

Quotation line `pricing_config` is an immutable calculation snapshot.

Supported calculation snapshot keys:

- width
- height
- depth
- length
- duration
- tax_rate

`tax_rate` is stored in `pricing_config` as part of the
immutable quotation line calculation snapshot because the
baseline schema persists `tax_amount` separately but does not
have a dedicated `tax_rate` column.

All measurement values MUST be numeric and greater than zero
when required by the selected pricing method.

Unknown calculation keys MUST NOT affect monetary calculation.

Catalog `pricing_config` may provide defaults in the future.
Quotation calculation inputs override catalog defaults only after
server validation.

## 4. Discount

Baseline quotation line discount is a fixed monetary amount.

Rules:

- discount_amount >= 0
- discount_amount <= gross

No automatic percentage discount is defined in this baseline.

## 5. Tax

Baseline line tax input is `tax_rate`.

Rules:

- tax_rate >= 0
- tax_rate <= 100

Calculation:

taxable_base = gross - discount_amount

tax_amount = taxable_base * tax_rate / 100

No default tax rate is assumed.

## 6. Final Line Amount

amount = gross - discount_amount + tax_amount

Amount cannot be negative.

## 7. Version Totals

subtotal = sum(line gross)

discount_total = sum(line discount_amount)

tax_total = sum(line tax_amount)

total = subtotal - discount_total + tax_total

## 8. Precision

Money is persisted at decimal(18,2).

Measurement/quantity calculations use decimal arithmetic.
Money values are rounded to two decimal places at monetary
boundaries.

Floating point arithmetic MUST NOT be treated as authoritative
for persisted monetary values.

## 9. Snapshot Rule

A quotation version never recalculates historically because the
catalog item later changes.

Each line stores the pricing method, calculation inputs,
unit price, discount, tax and final amount used at creation time.

## 10. Future Changes

Percentage discounts, tier pricing, minimum charge, wastage,
finishing surcharge, customer-specific price lists and tax policy
are future extensions and MUST be introduced explicitly rather
than changing this baseline silently.
