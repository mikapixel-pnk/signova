<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CustomerService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
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
        return Customer::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $this->tenantContext->tenantId(),
            'type' => $data['type'] ?? 'COMPANY',
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'payment_terms_days' =>
                $data['payment_terms_days'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'status' => 'ACTIVE',
        ]);
    }

    public function update(
        Customer $customer,
        array $data
    ): Customer {
        $customer->fill($data);
        $customer->save();

        return $customer->refresh();
    }

    private function baseQuery(): Builder
    {
        return Customer::query()
            ->where(
                'tenant_id',
                $this->tenantContext->tenantId()
            );
    }
}
