<?php

namespace Espo\Modules\QuoteManagement\Hooks\QuoteItem;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Config;
use Espo\Modules\QuoteManagement\Services\Calculator;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class CalculateAmount implements BeforeSave
{
    public function __construct(private EntityManager $entityManager, private Config $config) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $quote = $this->entityManager->getEntityById('Quote', $entity->get('quoteId') ?? '');
        if (!$quote) { throw new BadRequest('请先选择有效的报价单。'); }
        // 产品关联用于检索；名称与规格保存为快照，产品后续改名不会改变历史报价。
        $catalogProductId = $entity->get('catalogProductId');
        if ($catalogProductId && ($entity->isNew() || $entity->isAttributeChanged('catalogProductId'))) {
            $product = $this->entityManager->getEntityById('CProduct', $catalogProductId);
            if (!$product) { throw new BadRequest('所选产品不存在。'); }
            $entity->set('productName', $product->get('name'));
            $entity->set('specification', $product->get('spec'));
        }
        $currency = $quote->get('totalCurrency') ?? $this->config->get('defaultCurrency') ?? 'CNY';
        $entity->set('unitPriceCurrency', $currency);
        $entity->set('amountCurrency', $currency);
        try {
            $entity->set('amount', Calculator::line($entity->get('quantity'), $entity->get('unitPrice'), $entity->get('discountPercent') ?? 0));
        } catch (\InvalidArgumentException $e) { throw new BadRequest($e->getMessage()); }

        if (!$entity->get('name') && $entity->get('productName')) {
            $entity->set('name', $entity->get('productName'));
        }
    }
}
