<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BusinessContext $businessContext,
    ) {
    }

    public function paginate(
        ?string $search = null,
        ?string $status = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        return $this->baseQuery()
            ->when(
                $search,
                function (
                    Builder $query,
                    string $search
                ): void {
                    $query->where(function (
                        Builder $query
                    ) use ($search): void {
                        $query
                            ->where(
                                'name',
                                'ILIKE',
                                '%' . $search . '%'
                            )
                            ->orWhere(
                                'code',
                                'ILIKE',
                                '%' . $search . '%'
                            )
                            ->orWhere(
                                'phone',
                                'ILIKE',
                                '%' . $search . '%'
                            )
                            ->orWhere(
                                'email',
                                'ILIKE',
                                '%' . $search . '%'
                            );
                    });
                }
            )
            ->when(
                $status,
                fn (
                    Builder $query,
                    string $status
                ) => $query->where(
                    'status',
                    $status
                )
            )
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findOrFail(
        string $customerId
    ): Customer {
        return $this->baseQuery()
            ->where('id', $customerId)
            ->firstOrFail();
    }

    public function create(
        array $data
    ): Customer {
        return DB::transaction(
            function () use ($data): Customer {
                $customer = Customer::query()->create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' =>
                        $this->tenantContext->tenantId(),
                    'business_id' =>
                        $this->businessContext->businessId(),
                    'type' =>
                        $data['type'] ?? 'COMPANY',
                    'code' =>
                        $data['code'] ?? null,
                    'name' => $data['name'],
                    'phone' =>
                        $data['phone'] ?? null,
                    'email' =>
                        $data['email'] ?? null,
                    'tax_id' =>
                        $data['tax_id'] ?? null,
                    'payment_terms_days' =>
                        $data['payment_terms_days'] ?? 0,
                    'notes' =>
                        $data['notes'] ?? null,
                    'status' => 'ACTIVE',
                ]);

                $this->syncPrimaryAddress(
                    $customer,
                    $data
                );

                return $customer
                    ->load('primaryBillingAddress');
            }
        );
    }

    public function update(
        Customer $customer,
        array $data
    ): Customer {
        return DB::transaction(
            function () use (
                $customer,
                $data
            ): Customer {
                $customerData = $data;

                unset(
                    $customerData['address'],
                    $customerData['city'],
                    $customerData['province']
                );

                $customer->fill($customerData);
                $customer->save();

                $this->syncPrimaryAddress(
                    $customer,
                    $data
                );

                return $customer
                    ->refresh()
                    ->load('primaryBillingAddress');
            }
        );
    }

    private function syncPrimaryAddress(
        Customer $customer,
        array $data
    ): void {
        $hasAddressInput =
            array_key_exists('address', $data)
            || array_key_exists('city', $data)
            || array_key_exists('province', $data);

        if (!$hasAddressInput) {
            return;
        }

        $address = isset($data['address'])
            ? trim((string) $data['address'])
            : null;

        $existing = CustomerAddress::query()
            ->where(
                'tenant_id',
                $customer->tenant_id
            )
            ->where(
                'business_id',
                $customer->business_id
            )
            ->where(
                'customer_id',
                $customer->id
            )
            ->where('type', 'BILLING')
            ->where('is_primary', true)
            ->first();

        $hasCityOrProvince =
            filled($data['city'] ?? null)
            || filled($data['province'] ?? null);

        if (
            !$existing
            && !$address
            && $hasCityOrProvince
        ) {
            throw ValidationException::withMessages([
                'address' => [
                    'Alamat wajib diisi ketika Kota '
                    . 'atau Provinsi diberikan.',
                ],
            ]);
        }

        if (
            array_key_exists('address', $data)
            && !$address
        ) {
            $existing?->delete();

            return;
        }

        if ($existing) {
            $existing->fill([
                'address_line_1' =>
                    $address
                    ?: $existing->address_line_1,
                'city' =>
                    array_key_exists('city', $data)
                        ? $data['city']
                        : $existing->city,
                'province' =>
                    array_key_exists(
                        'province',
                        $data
                    )
                        ? $data['province']
                        : $existing->province,
            ]);

            $existing->save();

            return;
        }

        if (!$address) {
            return;
        }

        CustomerAddress::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $customer->tenant_id,
            'business_id' =>
                $customer->business_id,
            'customer_id' =>
                $customer->id,
            'type' => 'BILLING',
            'label' => 'Alamat Utama',
            'address_line_1' => $address,
            'address_line_2' => null,
            'city' => $data['city'] ?? null,
            'province' =>
                $data['province'] ?? null,
            'postal_code' => null,
            'country_code' => 'ID',
            'is_primary' => true,
            'notes' => null,
            'status' => 'ACTIVE',
        ]);
    }

    private function baseQuery(): Builder
    {
        return Customer::query()
            ->with('primaryBillingAddress')
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            )
            ->where(
                'business_id',
                $this->businessContext->businessId()
            );
    }
}
