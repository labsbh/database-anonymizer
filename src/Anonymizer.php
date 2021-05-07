<?php

declare(strict_types=1);

namespace WebnetFr\DatabaseAnonymizer;

use WebnetFr\DatabaseAnonymizer\Event\AnonymizerEvent;
use Doctrine\DBAL\Connection;
use WebnetFr\DatabaseAnonymizer\Exception\InvalidAnonymousValueException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Database anonymizer.
 *
 * @author Vlad Riabchenko <vriabchenko@webnet.fr>
 */
class Anonymizer
{
    private ?EventDispatcherInterface $dispatcher;

    public function __construct(?EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Anonymize entire database based on target tables.
     *
     * @param Connection    $connection
     * @param TargetTable[] $targets
     *
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \Exception
     */
    public function anonymize(Connection $connection, array $targets): void
    {
        foreach ($targets as $targetTable) {
            if ($targetTable->isTruncate()) {
                $dbPlatform = $connection->getDatabasePlatform();
                if (null === $dbPlatform) {
                    throw new \Exception('Db platform should not be null');
                }
                $connection->executeQuery('SET FOREIGN_KEY_CHECKS=0');
                $truncateQuery = $dbPlatform->getTruncateTableSql($targetTable->getName());
                $connection->executeStatement($truncateQuery);
                $connection->executeQuery('SET FOREIGN_KEY_CHECKS=1');
            } else {
                $allFieldNames = $targetTable->getAllFieldNames();
                $pk            = $targetTable->getPrimaryKey();

                $fetchRowsSQL  = $connection
                    ->createQueryBuilder()
                    ->select(implode(',', $allFieldNames))
                    ->from($targetTable->getName())
                    ->getSQL();
                $fetchRowsStmt = $connection->prepare($fetchRowsSQL);
                $result        = $fetchRowsStmt->executeQuery();

                // Anonymize all rows in current target table.
                while ($row = $result->fetchAssociative()) {
                    $values = [];
                    // Anonymize all target fields in current row.
                    foreach ($targetTable->getTargetFields() as $targetField) {
                        $anonValue = $targetField->generate();

                        if (null !== $anonValue && !\is_string($anonValue)) {
                            throw new InvalidAnonymousValueException('Generated value must be null or string');
                        }

                        // Set anonymized value.
                        $values[$targetField->getName()] = $anonValue;
                    }

                    $pkValues = [];
                    foreach ($pk as $pkField) {
                        $pkValues[$pkField] = $row[$pkField];
                    }

                    $connection->update($targetTable->getName(), $values, $pkValues);
                }

                if (null !== $this->dispatcher) {
                    $this->dispatcher->dispatch(new AnonymizerEvent($targetTable->getName(), $values));
                }
            }
        }
    }
}
