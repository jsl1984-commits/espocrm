<?php
// path: custom/Espo/Modules/SRNCashFlow/Hooks/SRNContract/BeforeSave.php
namespace Espo\Modules\SRNCashFlow\Hooks\SRNContract;

use Espo\ORM\Entity;
use Espo\Core\Hook\Base;

class BeforeSave extends Base
{
    public function process(Entity $entity)
    {
        $em = $this->getEntityManager();

        $company1 = $entity->get('company1Id') ? $em->getEntity('Account', $entity->get('company1Id')) : null;
        $company2 = $entity->get('company2Id') ? $em->getEntity('Account', $entity->get('company2Id')) : null;

        $isInternal1 = $company1 ? (bool)$company1->get('isInternal') : false;
        $isInternal2 = $company2 ? (bool)$company2->get('isInternal') : false;

        if ($isInternal1 && !$isInternal2) {
            $entity->set('contractType', 'Venta');
        } elseif (!$isInternal1 && $isInternal2) {
            $entity->set('contractType', 'Compra');
        } elseif ($isInternal1 && $isInternal2) {
            $entity->set('contractType', 'Interno');
        } else {
            $entity->set('contractType', 'Indefinido');
        }
    }
}