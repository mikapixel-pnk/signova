<?php

namespace App\Services\Invoice\Document;

use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfRenderer
{
    public function render(
        string $view,
        array $viewModel
    ): string {
        return Pdf::loadView(
            $view,
            $viewModel
        )
            ->setPaper(
                'a4',
                'portrait'
            )
            ->output();
    }
}
