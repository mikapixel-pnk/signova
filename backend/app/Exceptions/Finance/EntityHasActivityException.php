<?php

namespace App\Exceptions\Finance;

use RuntimeException;

class EntityHasActivityException extends RuntimeException
{
    public function __construct(
        string $entityName
    ) {
        parent::__construct(
            "{$entityName} tidak dapat dihapus karena sudah memiliki transaksi atau aktivitas terkait."
        );
    }
}
