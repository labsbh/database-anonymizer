<?php

declare(strict_types=1);

namespace WebnetFr\DatabaseAnonymizer\Tests\System\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use WebnetFr\DatabaseAnonymizer\Command\AnonymizeCommand;
use WebnetFr\DatabaseAnonymizer\GeneratorFactory\ChainGeneratorFactory;
use WebnetFr\DatabaseAnonymizer\GeneratorFactory\ConstantGeneratorFactory;
use WebnetFr\DatabaseAnonymizer\GeneratorFactory\FakerGeneratorFactory;
use WebnetFr\DatabaseAnonymizer\GeneratorFactory\GeneratorFactoryInterface;
use WebnetFr\DatabaseAnonymizer\Tests\System\SystemTestTrait;

/**
 * @author Vlad Riabchenko <vriabchenko@webnet.fr>
 */
class AnonymizeCommandTest extends TestCase
{
    use SystemTestTrait;

    /**
     * @inheritdoc
     * @throws \Doctrine\DBAL\Exception
     */
    protected function setUp(): void
    {
        $this->regenerateUsersOrders();
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function testExecute(): void
    {
        $generator = new ChainGeneratorFactory();
        $generator->addFactory(new ConstantGeneratorFactory())
                  ->addFactory(new FakerGeneratorFactory());

        $command = (new Application('Database anonymizer', '0.0.1'))
            ->add(new AnonymizeCommand($generator));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['y']);
        $commandTester->execute(
            [
                'command'    => $command->getName(),
                'config'     => realpath(__DIR__.'/../../../../config/config.yaml'),
                '--type'     => $GLOBALS['db_type'],
                '--host'     => $GLOBALS['db_host'],
                '--port'     => $GLOBALS['db_port'],
                '--database' => $GLOBALS['db_name'],
                '--user'     => $GLOBALS['db_username'],
                '--password' => $GLOBALS['db_password'],
            ]);

        $connection = $this->getConnection();

        $selectSQL  = $connection->createQueryBuilder()
                                 ->select('email, firstname, lastname, birthdate, phone, password')
                                 ->from('users')
                                 ->getSQL();
        $selectStmt = $connection->prepare($selectSQL);
        $result     = $selectStmt->executeQuery();

        while ($row = $result->fetchAssociative()) {
            self::assertTrue(is_string($row['email']));
            self::assertTrue(is_string($row['firstname']));
            self::assertTrue(is_string($row['lastname']));
            self::assertTrue(is_string($row['birthdate']));
            self::assertTrue(is_string($row['phone']) || is_null($row['phone']));
            self::assertTrue(is_string($row['password']));
        }

        $selectSQL  = $connection->createQueryBuilder()
                                 ->select('address, street_address, zip_code, city, country, comment, comment, created_at')
                                 ->from('orders')
                                 ->getSQL();
        $selectStmt = $connection->prepare($selectSQL);
        $result     = $selectStmt->executeQuery();

        while ($row = $result->fetchAssociative()) {
            self::assertTrue(is_string($row['address']));
            self::assertTrue(is_string($row['street_address']));
            self::assertTrue(is_string($row['zip_code']));
            self::assertTrue(is_string($row['city']));
            self::assertTrue(is_string($row['country']));
            self::assertTrue(is_string($row['comment']));
            self::assertTrue(is_string($row['created_at']));
        }
    }

    public function testTruncate(): void
    {
        $generator = $this->createMock(GeneratorFactoryInterface::class);
        $command   = (new Application('Database anonymizer', '0.0.1'))
            ->add(new AnonymizeCommand($generator));

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['y']);
        $commandTester->execute(
            [
                'command'    => $command->getName(),
                'config'     => realpath(__DIR__.'/../../../../config/config_truncate.yaml'),
                '--type'     => $GLOBALS['db_type'],
                '--host'     => $GLOBALS['db_host'],
                '--port'     => $GLOBALS['db_port'],
                '--database' => $GLOBALS['db_name'],
                '--user'     => $GLOBALS['db_username'],
                '--password' => $GLOBALS['db_password'],
            ]);

        $connection = $this->getConnection();

        $selectSQL  = $connection->createQueryBuilder()
                                 ->select('COUNT(*) as count')
                                 ->from('users', 'u')
                                 ->getSQL();
        $selectStmt = $connection->prepare($selectSQL);
        $result     = $selectStmt->executeQuery();
        self::assertEquals(0, $result->fetchAssociative()['count']);

        $selectSQL  = $connection->createQueryBuilder()
                                 ->select('COUNT(*) as count')
                                 ->from('orders', 'o')
                                 ->getSQL();
        $selectStmt = $connection->prepare($selectSQL);
        $result     = $selectStmt->executeQuery();
        self::assertEquals(0, $result->fetchAssociative()['count']);

        $selectSQL  = $connection->createQueryBuilder()
                                 ->select('COUNT(*) as count')
                                 ->from('productivity', 'p')
                                 ->getSQL();
        $selectStmt = $connection->prepare($selectSQL);
        $result     = $selectStmt->executeQuery();
        self::assertEquals(0, $result->fetchAssociative()['count']);
    }
}
