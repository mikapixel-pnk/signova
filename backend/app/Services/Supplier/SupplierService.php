<?php

namespace App\Services\Supplier;

use App\Models\Supplier;
use App\Tenancy\BusinessContext;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SupplierService
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
                                'contact_name',
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
        string $supplierId
    ): Supplier {
        return $this->baseQuery()
            ->where('id', $supplierId)
            ->firstOrFail();
    }

    public function create(
        array $data
    ): Supplier {
        return Supplier::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' =>
                $this->tenantContext->tenantId(),
            'business_id' =>
                $this->businessContext->businessId(),
            'code' =>
                $data['code'] ?? null,
            'name' =>
                $data['name'],
            'contact_name' =>
                $data['contact_name'] ?? null,
            'phone' =>
                $data['phone'] ?? null,
            'email' =>
                $data['email'] ?? null,
            'tax_id' =>
                $data['tax_id'] ?? null,
            'address' =>
                $data['address'] ?? null,
            'city' =>
                $data['city'] ?? null,
            'province' =>
                $data['province'] ?? null,
            'payment_terms_days' =>
                $data['payment_terms_days'] ?? 0,
            'notes' =>
                $data['notes'] ?? null,
            'status' => 'ACTIVE',
        ]);
    }

    public function update(
        Supplier $supplier,
        array $data
    ): Supplier {
        $supplier->fill($data);
        $supplier->save();

        return $supplier->refresh();
    }

    private function baseQuery(): Builder
    {
        return Supplier::query()
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
