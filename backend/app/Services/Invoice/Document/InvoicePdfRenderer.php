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
            $html =
                view()
                    ->file(
                        $viewPath,
                        $viewModel
                    )
                    ->render();

            return $this->applyPaidWatermark(
                $html,
                $viewModel
            );
        }

        if ($view === null) {
            throw new InvalidArgumentException(
                'Invoice template view is missing.'
            );
        }

        $html =
            view(
                $view,
                $viewModel
            )->render();

        return $this->applyPaidWatermark(
            $html,
            $viewModel
        );
    }

    public function render(
        ?string $view,
        array $viewModel,
        ?string $viewPath = null
    ): string {
        /*
         * Semua template harus melewati renderHtml()
         * supaya transform dokumen, termasuk watermark
         * LUNAS, konsisten untuk named view maupun
         * filesystem view.
         */
        $html =
            $this->renderHtml(
                $view,
                $viewModel,
                $viewPath
            );

        return Pdf::loadHTML(
            $html
        )
            ->setPaper(
                'a4',
                'portrait'
            )
            ->output();
    }

    private function applyPaidWatermark(
        string $html,
        array $viewModel
    ): string {
        $status =
            strtoupper(
                (string) (
                    $viewModel[
                        'document'
                    ]['status']
                    ?? ''
                )
            );

        if ($status !== 'PAID') {
            return $html;
        }

        $watermark = <<<'HTML'
<style>
.signova-paid-watermark {
    position: fixed;
    top: 42%;
    left: 8%;
    width: 84%;
    padding: 10px 0;
    border: 6px solid #15803d;
    color: #15803d;
    opacity: 0.12;
    text-align: center;
    font-family: "DejaVu Sans", sans-serif;
    font-size: 82px;
    font-weight: 700;
    letter-spacing: 12px;
    transform: rotate(-24deg);
    z-index: 9999;
}
</style>
<div class="signova-paid-watermark">
    LUNAS
</div>
HTML;

        $matched =
            preg_match(
                '/<body\b[^>]*>/i',
                $html,
                $matches,
                PREG_OFFSET_CAPTURE
            );

        if (
            $matched !== 1
            || ! isset(
                $matches[0][0],
                $matches[0][1]
            )
        ) {
            return $watermark
                . $html;
        }

        $bodyEnd =
            $matches[0][1]
            + strlen(
                $matches[0][0]
            );

        return substr(
            $html,
            0,
            $bodyEnd
        )
            . $watermark
            . substr(
                $html,
                $bodyEnd
            );
    }

}
