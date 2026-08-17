<?php

declare(strict_types=1);

namespace Emh\Watchword\Controller;

use Emh\Watchword\Domain\Model\Watchword;
use Emh\Watchword\Domain\Repository\WatchwordRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class WatchwordController extends ActionController
{
    private const DATE_FORMAT = 'd.m.Y';

    public function __construct(
        private readonly WatchwordRepository $watchwordRepository,
    ) {}

    /**
     * Renders the single watchword matching the current calendar day.
     * Registered as a non-cacheable (USER_INT) action, since the result
     * changes every day regardless of page cache lifetime.
     */
    public function listAction(): ResponseInterface
    {
        $today = new \DateTimeImmutable('today');
        $lookupDate = new \DateTimeImmutable($today->format('Y-m-d'), new \DateTimeZone('UTC'));

        $this->view->assign('watchword', $this->watchwordRepository->findByDate($lookupDate));

        return $this->htmlResponse();
    }

    /**
     * JSON endpoint for previous/next day navigation.
     * Query: type={ajaxTypeNum}&date=d.m.Y|Y-m-d&direction=previous|next
     * Without direction the watchword for the given date is returned.
     */
    public function ajaxAction(): ResponseInterface
    {
        $queryParams = $this->request->getQueryParams();
        $dateString = trim((string)($queryParams['date'] ?? ''));
        $direction = $this->resolveDirection($queryParams);

        $baseDate = $this->parseDate($dateString);
        if ($baseDate === false) {
            return $this->jsonError('Invalid date', 400);
        }

        $targetDate = $baseDate;
        if ($direction !== null) {
            $offset = $direction === 'next' ? '+1 day' : '-1 day';
            $targetDate = $baseDate->modify($offset);
        }
        $watchword = $this->watchwordRepository->findByDate($targetDate);

        if ($watchword === null) {
            return $this->jsonError('Watchword not found', 404);
        }

        return $this->jsonResponse(
            json_encode($this->serializeWatchword($watchword), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function resolveDirection(array $queryParams): ?string
    {
        $direction = strtolower((string)($queryParams['direction'] ?? ''));
        if (in_array($direction, ['previous', 'next'], true)) {
            return $direction;
        }

        if (array_key_exists('previous', $queryParams)) {
            return 'previous';
        }

        if (array_key_exists('next', $queryParams)) {
            return 'next';
        }

        return null;
    }

    private function parseDate(string $dateString): \DateTimeImmutable|false
    {
        $utc = new \DateTimeZone('UTC');
        foreach ([self::DATE_FORMAT, 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $dateString, $utc);
            if ($date !== false) {
                return $date;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     date: string,
     *     dateTime: string,
     *     weekday: string,
     *     sundayName: string,
     *     content: array<int, array{quote: string, reference: string, url: string}>
     * }
     */
    private function serializeWatchword(Watchword $watchword): array
    {
        $date = $watchword->getDate();
        $pageUrl = $this->uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(true)
            ->setTargetPageType(0)
            ->build();

        return [
            'date' => $date?->format(self::DATE_FORMAT) ?? '',
            'dateTime' => $date?->format('Y-m-d') ?? '',
            'weekday' => $watchword->getWeekday(),
            'sundayName' => $watchword->getSundayName(),
            'content' => [
                [
                    'quote' => $watchword->getWatchwordText(),
                    'reference' => $watchword->getWatchwordVerse(),
                    'url' => $pageUrl,
                ],
                [
                    'quote' => $watchword->getTeachingText(),
                    'reference' => $watchword->getTeachingVerse(),
                    'url' => $pageUrl,
                ],
            ],
        ];
    }

    private function jsonError(string $message, int $status): ResponseInterface
    {
        return $this->jsonResponse(
            json_encode(['error' => $message], JSON_THROW_ON_ERROR)
        )->withStatus($status);
    }
}
