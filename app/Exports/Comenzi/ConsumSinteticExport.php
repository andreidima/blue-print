<?php

namespace App\Exports\Comenzi;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ConsumSinteticExport implements FromView, ShouldAutoSize
{
    public function __construct(
        private readonly array $report,
        private readonly string $viewName
    ) {
    }

    public function view(): View
    {
        return view($this->viewName, [
            'report' => $this->report,
        ]);
    }
}
