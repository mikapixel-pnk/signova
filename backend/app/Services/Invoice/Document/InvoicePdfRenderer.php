<?php

namespace App\Services\Invoice\Document;

use Barryvdh\DomPDF\Facade\Pdf;
use InvalidArgumentException;

class InvoicePdfRenderer
{
    public function renderHtml(
        ?string $view,
        array $viewModel,
        ?string $viewPath = null
    ): string {
        if ($viewPath !== null) {
            return view()
                ->file(
                    $viewPath,
                    $viewModel
                )
                ->render();
        }

        if ($view === null) {
            throw new InvalidArgumentException(
                'Invoice template view is missing.'
            );
        }

        return view(
            $view,
            $viewModel
        )->render();
    }

    public function render(
        ?string $view,
        array $viewModel,
        ?string $viewPath = null
    ): string {
        if ($viewPath !== null) {
            return Pdf::loadHTML(
                $this->renderHtml(
                    null,
                    $viewModel,
                    $viewPath
                )
            )
                ->setPaper(
                    'a4',
                    'portrait'
                )
                ->output();
        }

        if ($view === null) {
            throw new InvalidArgumentException(
                'Invoice template view is missing.'
            );
        }

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
