<?php

declare(strict_types=1);

namespace WebnetFr\DatabaseAnonymizer\Tests\System;

use Faker\Factory as FakerFactory;
use PHPUnit\Framework\TestCase;
use WebnetFr\DatabaseAnonymizer\Anonymizer;
use WebnetFr\DatabaseAnonymizer\Generator\FakerGenerator;
use WebnetFr\DatabaseAnonymizer\TargetField;
use WebnetFr\DatabaseAnonymizer\TargetTable;

/**
 * @author Vlad Riabchenko <vriabchenko@webnet.fr>
 */
class AnonymizerTest extends TestCase
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
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function testAnonymizeUserTable(): void
    {
        $faker          = FakerFactory::create();
        $targetFields[] = new TargetField('firstname', new FakerGenerator($faker, 'firstName'));
        $targetFields[] = new TargetField('lastname', new FakerGenerator($faker, 'lastName'));
        $targetFields[] = new TargetField('birthdate', new FakerGenerator($faker, 'dateTime', [], ['date_format' => 'Y-m-d H:i:s']));
        $targetFields[] = new TargetField('phone', new FakerGenerator($faker, 'e164PhoneNumber'));
        $targets[]      = new TargetTable('users', ['id'], $targetFields, false);

        $connection = $this->getConnection();
        $anonymizer = new Anonymizer();
        $anonymizer->anonymize($connection, $targets);

        $selectSQL = $connection->createQueryBuilder()
                                ->select('u.firstname, u.lastname, u.birthdate, u.phone')
                                ->from('users', 'u')
                                ->getSQL();

        $selectStmt = $connection->prepare($selectSQL);
        $result     = $selectStmt->executeQuery();

        while ($row = $result->fetchAssociative()) {
            self::assertTrue(is_string($row['firstname']));
            self::assertTrue(is_string($row['lastname']));
            self::assertTrue(is_string($row['birthdate']));
            self::assertTrue(is_string($row['phone']));
        }
    }

    public function testTruncate(): void
    {
        $targets = [
            new TargetTable('users', [], [], true),
            new TargetTable('orders', [], [], true),
            new TargetTable('productivity', [], [], true),
        ];

        $connection = $this->getConnection();
        $anonymizer = new Anonymizer();
        $anonymizer->anonymize($connection, $targets);

        $selectSQL  = $connection->createQueryBuilder()
                                 ->select('COUNT(*) AS count')
                                 ->from('users', 'u')
                                 ->getSQL();
        $selectStmt = $connection->prepare($selectSQL);
        $result     = $selectStmt->executeQuery();
        self::assertEquals(0, $result->fetchAssociative()['count']);

        $selectSQL  = $connection->createQueryBuilder()
                                 ->select('COUNT(*) AS count')
                                 ->from('orders', 'o')
                                 ->getSQL();
        $selectStmt = $connection->prepare($selectSQL);
        $result     = $selectStmt->executeQuery();
        self::assertEquals(0, $result->fetchAssociative()['count']);

        $selectSQL  = $connection->createQueryBuilder()
                                 ->select('COUNT(*) AS count')
                                 ->from('productivity', 'p')
                                 ->getSQL();
        $selectStmt = $connection->prepare($selectSQL);
        $result     = $selectStmt->executeQuery();
        self::assertEquals(0, $result->fetchAssociative()['count']);
    }
}
