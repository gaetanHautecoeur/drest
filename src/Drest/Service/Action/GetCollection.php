<?php
namespace Drest\Service\Action;

use Doctrine\ORM;
use DrestCommon\Response\Response;
use Doctrine\ORM\Tools\Pagination\Paginator;

class GetCollection extends AbstractAction
{

    public function execute()
    {
        $classMetaData = $this->getMatchedRoute()->getClassMetaData();
        $em = $this->getEntityManager();

        $qb = $this->registerExpose(
            $this->getMatchedRoute()->getExpose(),
            $em->createQueryBuilder()->from($classMetaData->getClassName(), $classMetaData->getEntityAlias()),
            $em->getClassMetadata($classMetaData->getClassName())
        );

        foreach ($this->getMatchedRoute()->getRouteParams() as $key => $value) {
            $qb->andWhere($classMetaData->getEntityAlias() . '.' . $key . ' = :' . $key);
            $qb->setParameter($key, $value);
        }
        
        //gestion de la collection
        $limit = $this->getRequest()->getQuery('_max');
        $nb_page = ((int) $limit)?$limit:25;
        $page = $this->getRequest()->getQuery('_page'); 
        $page = ((int) $page)?$page:1;
        $qb->setMaxResults($nb_page);
        $qb->setFirstResult(($page-1)*$nb_page);
        try {
            $objetsArray = $qb->getQuery()->getResult(ORM\Query::HYDRATE_ARRAY);
            foreach($objetsArray as $cle => $objetArray){
                if (($location = $this->getMatchedRoute()->getOriginLocation(
                        $objetArray,
                        $this->getRequest()->getUrl(),
                        $this->getEntityManager()
                    )) !== false
                ) {
                    $objetsArray[$cle]['_location'] = $location;
                }
            }
            $resultSet = $this->createResultSet($objetsArray);
            
            $paginator = new Paginator($qb->getQuery(), false);
            $resultSet['_paginate'] = array(
                '_page' => $page,
                '_nb_page' => ceil($paginator->count() / $nb_page),
                '_result_per_page' => $nb_page,
                '_total' => $paginator->count()
            );
        } catch (Exception $e) {
            return $this->handleError($e, Response::STATUS_CODE_404);
        }

        return $resultSet;
    }
}
