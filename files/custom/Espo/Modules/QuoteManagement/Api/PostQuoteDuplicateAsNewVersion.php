<?php

namespace Espo\Modules\QuoteManagement\Api;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\QuoteManagement\Services\QuoteService;
use Espo\ORM\EntityManager;

class PostQuoteDuplicateAsNewVersion implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private QuoteService $quoteService,
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if (!$id) {
            throw new BadRequest('No ID.');
        }

        $quote = $this->entityManager->getEntityById('Quote', $id);

        if (!$quote) {
            throw new NotFound();
        }

        if (!$this->acl->check($quote, Table::ACTION_READ) ||
            !$this->acl->checkScope('Quote', Table::ACTION_CREATE) ||
            !$this->acl->checkScope('QuoteItem', Table::ACTION_CREATE) ||
            !$this->acl->checkScope('QuoteItem', Table::ACTION_READ)) {
            throw new Forbidden('没有读取报价或创建新报价与明细的权限。');
        }

        $newQuote = $this->quoteService->duplicateAsNewVersion($id);

        return ResponseComposer::json([
            'id' => $newQuote->getId(),
            'name' => $newQuote->get('name'),
        ]);
    }
}
