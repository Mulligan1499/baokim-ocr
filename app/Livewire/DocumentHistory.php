<?php

namespace App\Livewire;

use App\Repositories\OcrDocumentRepository;
use Livewire\Component;
use Livewire\WithPagination;

class DocumentHistory extends Component
{
    use WithPagination;

    public string $status = '';
    public int $perPage = 20;

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render(OcrDocumentRepository $documents)
    {
        $filters = [];
        if ($this->status !== '') {
            $filters['status'] = $this->status;
        }

        $page = $documents->paginateWithFilters($filters, $this->perPage);

        // Eager load extraction để tránh N+1 (BKM04)
        $page->getCollection()->load('extraction');

        return view('livewire.document-history', [
            'page' => $page,
        ]);
    }
}
