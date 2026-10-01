<?php

namespace Espo\Modules\QuoteManagement\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

class QuoteService
{
    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
        private InjectableFactory $injectableFactory,
        private Acl $acl,
    ) {}

    /**
     * Generate a quote number like QT-2026-0001.
     */
    public function generateNumber(): string
    {
        $year = date('Y');
        $key = 'quoteNumberSequence';

        $seq = ((int) ($this->config->get($key) ?? 0)) + 1;

        $configWriter = $this->injectableFactory->create(ConfigWriter::class);
        $configWriter->set($key, $seq);
        $configWriter->save();

        return sprintf('QT-%s-%04d', $year, $seq);
    }

    /**
     * Calculate totals from line items and set them on the given (in-memory) quote entity.
     * Does not save.
     */
    public function applyTotals(Entity $quote): void
    {
        $items = $this->entityManager
            ->getRDBRepository('QuoteItem')
            ->where(['quoteId' => $quote->getId()])
            ->find();

        $amounts = [];
        foreach ($items as $item) {
            $amounts[] = $item->get('amount') ?? 0;
        }
        try {
            $quote->set(Calculator::totals($amounts, $quote->get('discountAmount') ?? 0, $quote->get('taxRate') ?? 0));
        } catch (\InvalidArgumentException $e) { throw new BadRequest($e->getMessage()); }
        $currency = $quote->get('totalCurrency') ?? $this->config->get('defaultCurrency') ?? 'CNY';
        foreach (['subtotalCurrency', 'discountAmountCurrency', 'taxAmountCurrency', 'totalCurrency'] as $field) {
            $quote->set($field, $currency);
        }
    }

    /**
     * Recalculate and persist totals of a quote. Skips hooks to avoid recursion.
     */
    public function recalculateTotals(string $quoteId): void
    {
        $quote = $this->entityManager->getEntityById('Quote', $quoteId);

        if (!$quote) {
            return;
        }

        $this->applyTotals($quote);

        $this->entityManager->saveEntity($quote, [SaveOption::SKIP_ALL => true]);
    }

    /**
     * Deep-copy a quote with all its line items as a new version.
     */
    public function duplicateAsNewVersion(string $quoteId): Entity
    {
        // 任一明细复制失败，整单回滚，避免留下半张报价。
        return $this->entityManager->getTransactionManager()->run(
            fn () => $this->copyVersion($quoteId)
        );
    }

    private function copyVersion(string $quoteId): Entity
    {
        $quote = $this->entityManager->getEntityById('Quote', $quoteId);

        if (!$quote) {
            throw new NotFound();
        }

        if (!$this->acl->check($quote, Table::ACTION_READ) ||
            !$this->acl->checkScope('Quote', Table::ACTION_CREATE) ||
            !$this->acl->checkScope('QuoteItem', Table::ACTION_CREATE)) {
            throw new Forbidden('没有复制报价的权限。');
        }

        $new = $this->entityManager->getNewEntity('Quote');

        $new->set([
            'status' => 'Draft',
            'quoteDate' => date('Y-m-d'),
            'validUntil' => $quote->get('validUntil'),
            'accountId' => $quote->get('accountId'),
            'opportunityId' => $quote->get('opportunityId'),
            'parentQuoteId' => $quote->getId(),
            'version' => ((int) $quote->get('version')) + 1,
            'versionNote' => '基于 ' . $quote->get('name') . ' 创建',
            'discountAmount' => $quote->get('discountAmount'),
            'taxRate' => $quote->get('taxRate'),
            'totalCurrency' => $quote->get('totalCurrency'),
            'description' => $quote->get('description'),
            'assignedUserId' => $quote->get('assignedUserId'),
            'teamsIds' => $quote->get('teamsIds'),
        ]);

        $this->entityManager->saveEntity($new);

        $items = $this->entityManager
            ->getRDBRepository('QuoteItem')
            ->where(['quoteId' => $quoteId])
            ->order('createdAt', 'ASC')
            ->find();

        foreach ($items as $item) {
            if (!$this->acl->check($item, Table::ACTION_READ)) {
                throw new Forbidden('没有读取全部报价明细的权限，已取消复制。');
            }
            $newItem = $this->entityManager->getNewEntity('QuoteItem');

            $newItem->set([
                'quoteId' => $new->getId(),
                'name' => $item->get('name'),
                'catalogProductId' => $item->get('catalogProductId'),
                'productName' => $item->get('productName'),
                'specification' => $item->get('specification'),
                'quantity' => $item->get('quantity'),
                'unitPrice' => $item->get('unitPrice'),
                'discountPercent' => $item->get('discountPercent'),
                'description' => $item->get('description'),
                'amount' => $item->get('amount'),
                'unitPriceCurrency' => $item->get('unitPriceCurrency'),
                'amountCurrency' => $item->get('amountCurrency'),
            ]);

            // 直接复制已经保存的产品快照，不因产品主数据变动重写历史名称和规格。
            $this->entityManager->saveEntity($newItem, [SaveOption::SKIP_ALL => true]);
        }

        $this->recalculateTotals($new->getId());

        $result = $this->entityManager->getEntityById('Quote', $new->getId());

        if (!$result) {
            throw new NotFound();
        }

        return $result;
    }
}
