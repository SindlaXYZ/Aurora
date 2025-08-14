<?php

namespace Sindla\Bundle\AuroraBundle\Tests\Trait;

use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

trait DatabaseManagementTrait
{
    /**
     * @throws Exception
     */
    public function truncateTable(string $table, bool $cascade = false): void
    {
        // Disable DAMA (DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension) for this entire test
        StaticDriver::setKeepStaticConnections(false);

        if (str_starts_with($table, 'App\Entity')) {
            $table = strtolower(substr($table, strrpos($table, '\\') + 1));
        }

        $connection = $this->em->getConnection();
        $platform   = $connection->getDatabasePlatform();
        $connection->executeStatement($platform->getTruncateTableSQL($table, $cascade));

        // Re-enable DAMA (DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension)
        StaticDriver::setKeepStaticConnections(true);
    }

    /**
     * @throws \Exception
     */
    public function dbResetWithFixtures(): void
    {
        // Disable DAMA (DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension) for this entire test
        StaticDriver::setKeepStaticConnections(false);

        $this->dbReset();
        $this->loadFixtures();

        // Re-enable DAMA (DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension)
        StaticDriver::setKeepStaticConnections(true);
    }

    /**
     * @throws \Exception
     */
    public function dbResetWithoutFixtures(): void
    {
        // Disable DAMA (DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension) for this entire test
        StaticDriver::setKeepStaticConnections(false);

        $this->dbReset();

        // Re-enable DAMA (DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension)
        StaticDriver::setKeepStaticConnections(true);
    }

    /**
     * @throws \Exception
     */
    private function dbReset(): void
    {
        $kernel      = self::bootKernel();
        $application = new Application($kernel);
        $application->setAutoExit(false);

        // trigger_deprecation('doctrine/dbal', '3.8.3', 'Running command `doctrine:database:drop` will trigger deprecation: Subscribing to onSchemaCreateTable events is deprecated. (AbstractPlatform.php:2191 called by AbstractPlatform.php:2089, https://github.com/doctrine/dbal/issues/5784, package doctrine/dbal)');

        $application->run(
            new ArrayInput([
                'command'     => 'doctrine:database:drop',
                '--if-exists' => true,
                '--force'     => true,
            ]),
            new NullOutput()
        );

        $application->run(
            new ArrayInput([
                'command' => 'doctrine:database:create',
                '-q'      => true,
            ]),
            new NullOutput()
        );

        $application->run(
            new ArrayInput([
                'command'          => 'doctrine:migrations:migrate',
                '--no-interaction' => true,
            ]),
            new NullOutput()
        );
    }

    /**
     * @throws \Exception
     */
    private function loadFixtures(bool $append = true, bool $verbose = false, array $groups = []): string
    {
        $kernel      = self::bootKernel();
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = [
            'command'          => 'doctrine:fixtures:load',
            '--no-interaction' => true,
        ];

        if ($append) {
            $input['--append'] = true;
        }

        if ($verbose) {
            $input['--verbose'] = true;
        }

        if (!empty($groups)) {
            $input['--group'] = $groups;
        }

        // Use BufferedOutput if we want to capture output, otherwise NullOutput
        $output = $verbose ? new BufferedOutput() : new NullOutput();

        $application->run(new ArrayInput($input), $output);

        return $output instanceof BufferedOutput ? $output->fetch() : '';
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function save(object $entity): void
    {
        $this->em->persist($entity);
        $this->em->flush();
    }

    /**
     * @throws ORMException
     */
    public function refresh(object $entity): void
    {
        $this->em->refresh($entity);
    }
}
