<?php

declare(strict_types=1);

namespace Doctrine\ORM\Cache\Persister\Entity;

use Doctrine\ORM\Cache\EntityCacheKey;

/**
 * Specific non-strict read/write cached entity persister
 */
class NonStrictReadWriteCachedEntityPersister extends AbstractEntityPersister
{
    /**
     * {@inheritdoc}
     */
    public function afterTransactionComplete()
    {
        $isChanged = false;

        if (isset($this->queuedCache['insert'])) {
            foreach ($this->queuedCache['insert'] as $entity) {
                $isChanged = $this->updateCache($entity, $isChanged);
            }
        }

        if (isset($this->queuedCache['update'])) {
            foreach ($this->queuedCache['update'] as $entity) {
                $isChanged = $this->updateCache($entity, $isChanged);
            }
        }

        if (isset($this->queuedCache['delete'])) {
            foreach ($this->queuedCache['delete'] as $key) {
                $this->region->evict($key);

                $isChanged = true;
            }
        }

        if ($isChanged) {
            $this->timestampRegion->update($this->timestampKey);
        }

        $this->queuedCache = [];
    }

    /**
     * {@inheritdoc}
     */
    public function afterTransactionRolledBack()
    {
        $this->queuedCache = [];
    }

    /**
     * {@inheritdoc}
     */
    public function delete($entity)
    {
        $uow     = $this->em->getUnitOfWork();
        $key     = new EntityCacheKey($this->class->getRootClassName(), $uow->getEntityIdentifier($entity));
        $deleted = $this->persister->delete($entity);

        if ($deleted) {
            $this->region->evict($key);
        }

        $this->queuedCache['delete'][] = $key;

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function update($entity)
    {
        $this->persister->update($entity);

        $this->queuedCache['update'][] = $entity;
    }

    private function updateCache($entity, $isChanged)
    {
        $uow       = $this->em->getUnitOfWork();
        $class     = $this->metadataFactory->getMetadataFor(get_class($entity));
        $key       = new EntityCacheKey($class->getRootClassName(), $uow->getEntityIdentifier($entity));
        $entry     = $this->hydrator->buildCacheEntry($class, $key, $entity);
        $cached    = $this->region->put($key, $entry);
        $isChanged = $isChanged ?: $cached;

        if ($this->cacheLogger && $cached) {
            $this->cacheLogger->entityCachePut($this->regionName, $key);
        }

        return $isChanged;
    }
}
