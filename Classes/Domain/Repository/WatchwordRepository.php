<?php

declare(strict_types=1);

namespace Emh\Watchword\Domain\Repository;

use Emh\Watchword\Domain\Model\Watchword;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class WatchwordRepository extends Repository
{
    public const TABLE = 'tx_watchword_domain_model_watchword';

    protected $defaultOrderings = [
        'date' => QueryInterface::ORDER_ASCENDING,
    ];

    /**
     * @throws InvalidConfigurationTypeException
     */
    public function initializeObject(): void
    {
        /** @var Typo3QuerySettings $querySettings */
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Returns the set of unix timestamps from $dates that already exist in the table.
     *
     * @param int[] $dates
     * @return int[]
     */
    public function findExistingDates(array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        $result = $queryBuilder
            ->select('date')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->in('date', $queryBuilder->createNamedParameter($dates, Connection::PARAM_INT_ARRAY))
            )
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map('intval', $result);
    }

    /**
     * @return int[]
     */
    public function findDistinctYears(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        $result = $queryBuilder
            ->selectLiteral('DISTINCT ' . $queryBuilder->quoteIdentifier('year'))
            ->from(self::TABLE)
            ->orderBy('year', 'DESC')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map('intval', $result);
    }

    public function findAllByYear(?int $year = null): QueryResultInterface
    {
        $query = $this->createQuery();

        if ($year !== null && $year > 0) {
            $query->matching($query->equals('year', $year));
        }

        return $query->execute();
    }

    public function findByDate(\DateTimeInterface $date): ?Watchword
    {
        $query = $this->createQuery();
        $query->matching($query->equals('date', $date));

        /** @var Watchword|null $watchword */
        $watchword = $query->execute()->getFirst();

        return $watchword;
    }
}
