<?php

namespace Espo\Modules\QuoteManagement\Hooks\QuoteItem;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\QuoteManagement\Services\QuoteService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;

class RecalculateQuote implements AfterSave, AfterRemove
{
    public function __construct(private QuoteService $quoteService) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $quoteId = $entity->get('quoteId');

        if ($quoteId) {
            $this->quoteService->recalculateTotals($quoteId);
        }
    }

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $quoteId = $entity->get('quoteId');

        if ($quoteId) {
            $this->quoteService->recalculateTotals($quoteId);
        }
    }
}
