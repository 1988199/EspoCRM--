<?php

namespace Espo\Modules\QuoteManagement\Hooks\QuoteItem;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class CalculateAmount implements BeforeSave
{
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $quantity = max(0.0, (float) $entity->get('quantity'));
        $unitPrice = max(0.0, (float) $entity->get('unitPrice'));
        $discountPercent = min(100.0, max(0.0, (float) $entity->get('discountPercent')));

        $entity->set('amount', round($quantity * $unitPrice * (1 - $discountPercent / 100), 2));

        if (!$entity->get('name') && $entity->get('productName')) {
            $entity->set('name', $entity->get('productName'));
        }
    }
}
