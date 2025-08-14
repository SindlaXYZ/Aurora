<?php

namespace Sindla\Bundle\AuroraBundle\Tests\Trait;

use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;

/**
 * Trait that provides methods to control DAMA transaction rollback behavior.
 *
 * This trait adds functionality to selectively disable DAMA's automatic transaction
 * rollback for tests that need real persistence. Can be used with any test class
 * that has access to EntityManager and Symfony container.
 *
 * Requirements:
 * - Test class must have $this->em property (EntityManager)
 * - Test class must have getContainer() method (Symfony test case)
 *
 * @example
 * ```php
 * class MyEntityTest extends WebTestCase
 * {
 *     use PersistenceTestTrait;
 *
 *     // Your tests here...
 * }
 * ```
 */
trait PersistenceTrait
{
    /**
     * Execute a callback without a DAMA transaction rollback
     *
     * This method temporarily disables DAMA's automatic transaction rollback,
     * executes the provided callback, and then restores the original DAMA state.
     * Use this when you need to test actual database persistence.
     *
     * @param callable $callback The code to execute without DAMA rollback
     * @return mixed The return value of the callback
     *
     * @example
     * ```php
     * public function testPersistentData(): void
     * {
     *     $result = $this->withoutDAMA(function () {
     *         $entity = new MyEntity();
     *         $this->em->persist($entity);
     *         $this->em->flush();
     *         return $entity;
     *     });
     *
     *     // Data will actually be persisted in the database
     *     $this->assertNotNull($result->getId());
     * }
     * ```
     */
    protected function withoutDAMA(callable $callback): mixed
    {
        $originalState = StaticDriver::isKeepStaticConnections();

        try {
            StaticDriver::setKeepStaticConnections(false);
            return $callback();
        } finally {
            StaticDriver::setKeepStaticConnections($originalState);
        }
    }

    /**
     * Execute a callback with a guaranteed DAMA transaction rollback.
     *
     * This method ensures DAMA's automatic transaction rollback is enabled,
     * executes the provided callback, and then restores the original DAMA state.
     * Use this to explicitly ensure test isolation when DAMA might be disabled.
     *
     * @param callable $callback The code to execute with DAMA rollback
     * @return mixed The return value of the callback
     *
     * @example
     * ```php
     * public function testIsolatedData(): void
     * {
     *     $this->withDAMA(function () {
     *         $entity = new MyEntity();
     *         $this->em->persist($entity);
     *         $this->em->flush();
     *         // Data will be rolled back after test
     *     });
     * }
     * ```
     */
    protected function withDAMA(callable $callback): mixed
    {
        $originalState = StaticDriver::isKeepStaticConnections();

        try {
            StaticDriver::setKeepStaticConnections(true);
            return $callback();
        } finally {
            StaticDriver::setKeepStaticConnections($originalState);
        }
    }

    /**
     * Check if DAMA transaction rollback is currently enabled.
     *
     * @return bool True if DAMA is keeping static connections (rollback enabled)
     */
    protected function isDAMAEnabled(): bool
    {
        return StaticDriver::isKeepStaticConnections();
    }

    /**
     * Verify that data persists in the database by fetching it with a fresh EntityManager.
     *
     * This method creates a new EntityManager instance to ensure we're fetching
     * data from the actual database, not from any in-memory cache or transaction.
     *
     * @param string $entityClass The entity class name
     * @param array  $criteria    The search criteria
     * @return object|null The found entity or null
     *
     * @example
     * ```php
     * public function testDataPersistence(): void
     * {
     *     $this->withoutDAMA(function () {
     *         $country = new Country();
     *         $country->setName('Romania');
     *         $this->em->persist($country);
     *         $this->em->flush();
     *     });
     *
     *     $persistedCountry = $this->findPersistedEntity(Country::class, ['name' => 'Romania']);
     *     $this->assertNotNull($persistedCountry);
     * }
     * ```
     */
    protected function findPersistedEntity(string $entityClass, array $criteria): ?object
    {
        // Get a fresh EntityManager to bypass any transaction state
        $freshEM = $this->getContainer()->get('doctrine')->getManager();
        return $freshEM->getRepository($entityClass)->findOneBy($criteria);
    }

    /**
     * Find multiple persisted entities in the database using a fresh EntityManager.
     *
     * @param string     $entityClass The entity class name
     * @param array      $criteria    Optional search criteria
     * @param array|null $orderBy     Optional ordering
     * @param int|null   $limit       Optional limit
     * @param int|null   $offset      Optional offset
     * @return array Array of found entities
     */
    protected function findPersistedEntities(
        string $entityClass,
        array  $criteria = [],
        ?array $orderBy = null,
        ?int   $limit = null,
        ?int   $offset = null
    ): array
    {
        $freshEM = $this->getContainer()->get('doctrine')->getManager();
        return $freshEM->getRepository($entityClass)->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Count entities in the database using a fresh EntityManager.
     *
     * @param string $entityClass The entity class name
     * @param array  $criteria    Optional search criteria
     * @return int The number of entities found
     */
    protected function countPersistedEntities(string $entityClass, array $criteria = []): int
    {
        $freshEM    = $this->getContainer()->get('doctrine')->getManager();
        $repository = $freshEM->getRepository($entityClass);

        if (empty($criteria)) {
            return $repository->count([]);
        }

        return $repository->count($criteria);
    }

    /**
     * Clear all data from specified entity tables.
     *
     * WARNING: This method actually deletes data from the database!
     * Use only in tests that disable DAMA and when you need to clean up persistent data.
     *
     * @param array $entityClasses Array of entity class names to clear
     *
     * @example
     * ```php
     * protected function tearDown(): void
     * {
     *     // Clean up any persistent test data
     *     $this->clearPersistedEntities([Country::class, User::class]);
     *     parent::tearDown();
     * }
     * ```
     */
    protected function clearPersistedEntities(array $entityClasses): void
    {
        $this->withoutDAMA(function () use ($entityClasses) {
            foreach ($entityClasses as $entityClass) {
                $this->em->createQuery("DELETE FROM {$entityClass}")->execute();
            }
            $this->em->flush();
        });
    }

    /**
     * Clear specific entities based on criteria.
     *
     * More precise than clearPersistedEntities - allows you to delete only
     * specific test data based on criteria.
     *
     * @param string $entityClass The entity class name
     * @param array  $criteria    The criteria for entities to delete
     *
     * @example
     * ```php
     * // Clean up only test countries
     * $this->clearPersistedEntitiesByCriteria(Country::class, ['alpha2Code' => 'TEST']);
     * ```
     */
    protected function clearPersistedEntitiesByCriteria(string $entityClass, array $criteria): void
    {
        $this->withoutDAMA(function () use ($entityClass, $criteria) {
            $freshEM    = $this->getContainer()->get('doctrine')->getManager();
            $repository = $freshEM->getRepository($entityClass);

            $entities = $repository->findBy($criteria);
            foreach ($entities as $entity) {
                $freshEM->remove($entity);
            }
            $freshEM->flush();
        });
    }

    /**
     * Execute a raw SQL query with a fresh connection (useful for complex cleanup or verification).
     *
     * @param string $sql    The SQL query to execute
     * @param array  $params Optional parameters for the query
     * @return mixed Query result
     *
     * @example
     * ```php
     * // Complex cleanup
     * $this->executePersistedQuery('DELETE FROM country WHERE name LIKE ?', ['Test%']);
     *
     * // Verification query
     * $count = $this->executePersistedQuery('SELECT COUNT(*) as count FROM country')[0]['count'];
     * ```
     */
    protected function executePersistedQuery(string $sql, array $params = []): mixed
    {
        return $this->withoutDAMA(function () use ($sql, $params) {
            $freshEM    = $this->getContainer()->get('doctrine')->getManager();
            $connection = $freshEM->getConnection();

            return $connection->executeQuery($sql, $params)->fetchAllAssociative();
        });
    }
}
