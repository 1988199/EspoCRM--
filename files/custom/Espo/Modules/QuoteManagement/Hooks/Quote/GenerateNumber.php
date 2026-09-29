<?php

namespace Espo\Modules\QuoteManagement\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\QuoteManagement\Services\QuoteService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class GenerateNumber implements BeforeSave
{
    public function __construct(private QuoteService $quoteService) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->isNew() && !$entity->get('name')) {
            $entity->set('name', $this->quoteService->generateNumber());
        }

        if (!$entity->get('quoteDate')) {
            $entity->set('quoteDate', date('Y-m-d'));
        }

        // Keep totals in sync when discount/tax rate are edited directly.
        $this->quoteService->applyTotals($entity);
    }
}
