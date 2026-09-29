<?php

namespace Espo\Modules\QuoteManagement\Services;

use Espo\Core\Exceptions\NotFound;
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

        $subtotal = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) $item->get('amount');
        }

        $subtotal = round($subtotal, 2);
        $discount = max(0.0, (float) ($quote->get('discountAmount') ?? 0));
        $taxRate = max(0.0, (float) ($quote->get('taxRate') ?? 0));

        $base = max(0.0, $subtotal - $discount);
        $taxAmount = round($base * $taxRate / 100, 2);
        $total = round($base + $taxAmount, 2);

        $quote->set([
            'subtotal' => $subtotal,
            'taxAmount' => $taxAmount,
            'total' => $total,
        ]);
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
        $quote = $this->entityManager->getEntityById('Quote', $quoteId);

        if (!$quote) {
            throw new NotFound();
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
            $newItem = $this->entityManager->getNewEntity('QuoteItem');

            $newItem->set([
                'quoteId' => $new->getId(),
                'productName' => $item->get('productName'),
                'specification' => $item->get('specification'),
                'quantity' => $item->get('quantity'),
                'unitPrice' => $item->get('unitPrice'),
                'discountPercent' => $item->get('discountPercent'),
                'description' => $item->get('description'),
            ]);

            $this->entityManager->saveEntity($newItem);
        }

        $result = $this->entityManager->getEntityById('Quote', $new->getId());

        if (!$result) {
            throw new NotFound();
        }

        return $result;
    }
}
