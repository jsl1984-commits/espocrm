<?php
// path: custom/Espo/Modules/SRNCashFlow/Hooks/SRNContract/BeforeSave.php
namespace Espo\Modules\SRNCashFlow\Hooks\SRNContract;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\ORM\Repository\Option\SaveOptions;

class BeforeSave implements BeforeSaveHook
{
    public function __construct(private EntityManager $entityManager) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $em = $this->entityManager;

        $company1 = $entity->get('company1Id') ? $em->getEntity('Account', $entity->get('company1Id')) : null;
        $company2 = $entity->get('company2Id') ? $em->getEntity('Account', $entity->get('company2Id')) : null;

        $isInternal1 = $company1 ? (bool) $company1->get('isInternal') : false;
        $isInternal2 = $company2 ? (bool) $company2->get('isInternal') : false;

        // company1 provee, company2 recibe.
        if ($isInternal1 && !$isInternal2) {
            // Proveedor holding -> ingreso
            $entity->set('contractType', 'Venta');
        } elseif (!$isInternal1 && $isInternal2) {
            // Receptor holding -> egreso
            $entity->set('contractType', 'Compra');
        } elseif ($isInternal1 && $isInternal2) {
            $entity->set('contractType', 'Interno');
        } else {
            $entity->set('contractType', 'Indefinido');
        }

        // Validate contacts belong to corresponding companies when possible.
        $contact1Id = $entity->get('contact1Id');
        $contact2Id = $entity->get('contact2Id');

        if ($contact1Id && $entity->get('company1Id')) {
            $contact1 = $em->getEntity('Contact', $contact1Id);
            if ($contact1 && $contact1->has('accountId')) {
                $primaryAccountId = $contact1->get('accountId');
                if ($primaryAccountId && $primaryAccountId !== $entity->get('company1Id')) {
                    $entity->set('contact1Id', null);
                    $entity->set('contact1Name', null);
                }
            }
        }

        if ($contact2Id && $entity->get('company2Id')) {
            $contact2 = $em->getEntity('Contact', $contact2Id);
            if ($contact2 && $contact2->has('accountId')) {
                $primaryAccountId = $contact2->get('accountId');
                if ($primaryAccountId && $primaryAccountId !== $entity->get('company2Id')) {
                    $entity->set('contact2Id', null);
                    $entity->set('contact2Name', null);
                }
            }
        }
    }
}