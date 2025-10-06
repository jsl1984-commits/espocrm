<?php
namespace Espo\Modules\JslCashFlow\Controllers;

use Espo\Core\Api\Controller;
use Espo\Core\Api\Response;
use Espo\ORM\EntityManager;
use Espo\Core\Api\Request;

class JslConsolidatedTax extends Controller
{
    public function __construct(private EntityManager $em) {}

    public function postActionGenerateVat(Request $request): Response
    {
        // TODO: implement generation for N months forward (default 12)
        return $this->respond(200, ['status' => 'ok']);
    }
}
