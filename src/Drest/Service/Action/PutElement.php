<?php
namespace Drest\Service\Action;

use Doctrine\ORM;
use DrestCommon\Response\Response;
use DrestCommon\ResultSet;

class PutElement extends AbstractAction
{

    public function execute()
    {
        $matchedRoute = $this->getMatchedRoute();
        $classMetaData = $matchedRoute->getClassMetaData();
        $elementName = $classMetaData->getEntityAlias();

        $em = $this->getEntityManager();

        $qb = $em->createQueryBuilder()->select($elementName)->from($classMetaData->getClassName(), $elementName);
        foreach ($matchedRoute->getRouteParams() as $key => $value) {
            $qb->andWhere($elementName . '.' . $key . ' = :' . $key);
            $qb->setParameter($key, $value);
        }

        try {
            $object = $qb->getQuery()->getSingleResult(ORM\Query::HYDRATE_OBJECT);
        } catch (\Exception $e) {
            return $this->handleError($e, Response::STATUS_CODE_404);
        }

        $this->runHandle($object);

        // Attempt to save the modified resource
        try {
            $em->persist($object);
            $em->flush($object);

            //TODO permet de s'affranchir des problemeatiques utf8
            $matchedRoute = $this->getMatchedRoute();
            // Run any attached handle function
            if ($matchedRoute->hasHandleCall()) {
                $handleMethod = $matchedRoute->getHandleCall();
                $object->$handleMethod($this->getRepresentation()->toArray(false), $this->getRequest(), $this->getEntityManager(), false);
            }

            $location = $matchedRoute->getOriginLocation(
                $object,
                $this->getRequest()->getUrl(),
                $this->getEntityManager()
            );
            $this->getResponse()->setStatusCode(Response::STATUS_CODE_200);
            
            //TODO : retourne l'element a partir de son URL
            $this->getService()->resetAction();
            $request = \Symfony\Component\HttpFoundation\Request::create($location, 'GET');
            $manager = $this->getService()->getDrestManager();
            $manager->dispatch($request);
            return $manager->getService()->getRepresentation()->toArray(false);
        } catch (\Exception $e) {
            return $this->handleError($e, Response::STATUS_CODE_500);
        }

        return $resultSet;
    }
}
