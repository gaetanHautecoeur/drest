<?php
namespace Drest\Service\Action;

use Doctrine\ORM;
use DrestCommon\Response\Response;

class GetElement extends AbstractAction
{

    public function execute()
    {
        $classMetaData = $this->getMatchedRoute()->getClassMetaData();
        $elementName = $classMetaData->getEntityAlias();

        $em = $this->getEntityManager();

        $qb = $this->registerExpose(
            $this->getMatchedRoute()->getExpose(),
            $em->createQueryBuilder()->from($classMetaData->getClassName(), $elementName),
            $em->getClassMetadata($classMetaData->getClassName())
        );

        foreach ($this->getMatchedRoute()->getRouteParams() as $key => $value) {
            $qb->andWhere($elementName . '.' . $key . ' = :' . $key);
            $qb->setParameter($key, $value);
        }

        try {
            $objetArray = $qb->getQuery()->getSingleResult(ORM\Query::HYDRATE_ARRAY);
            if (($location = $this->getMatchedRoute()->getOriginLocation(
                    $objetArray,
                    $this->getRequest()->getUrl(),
                    $this->getEntityManager()
                )) !== false
            ) {
                $objetArray['_location'] = $location;
            }
            $resultSet = $this->createResultSet($objetArray);
        } catch (\Exception $e) {
            return $this->handleError($e, Response::STATUS_CODE_404);
        }

        return $resultSet;
    }
}
