<?php
namespace Drest\Service\Action;

use DrestCommon\Response\Response;
use DrestCommon\ResultSet;

class PostElement extends AbstractAction
{

    public function execute()
    {
        $classMetaData = $this->getMatchedRoute()->getClassMetaData();
        $em = $this->getEntityManager();

        $entityClass = $classMetaData->getClassName();
        $object = new $entityClass;
        
        $this->runHandle($object);
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

            $this->getResponse()->setStatusCode(Response::STATUS_CODE_201);
            if (($location = $this->getMatchedRoute()->getOriginLocation(
                    $object,
                    $this->getRequest()->getUrl(),
                    $this->getEntityManager()
                )) !== false
            ) {
                $this->getResponse()->setHttpHeader('Location', $location);
            }

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
