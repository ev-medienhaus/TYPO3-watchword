<?php

declare(strict_types=1);

namespace Emh\Watchword\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Watchword extends AbstractEntity
{
    protected ?\DateTime $date = null;

    protected string $weekday = '';

    protected string $sundayName = '';

    protected string $watchwordVerse = '';

    protected string $watchwordText = '';

    protected string $teachingVerse = '';

    protected string $teachingText = '';

    protected int $year = 0;

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(?\DateTime $date): void
    {
        $this->date = $date;
    }

    public function getWeekday(): string
    {
        return $this->weekday;
    }

    public function setWeekday(string $weekday): void
    {
        $this->weekday = $weekday;
    }

    public function getSundayName(): string
    {
        return $this->sundayName;
    }

    public function setSundayName(string $sundayName): void
    {
        $this->sundayName = $sundayName;
    }

    public function getWatchwordVerse(): string
    {
        return $this->watchwordVerse;
    }

    public function setWatchwordVerse(string $watchwordVerse): void
    {
        $this->watchwordVerse = $watchwordVerse;
    }

    public function getWatchwordText(): string
    {
        return $this->watchwordText;
    }

    public function setWatchwordText(string $watchwordText): void
    {
        $this->watchwordText = $watchwordText;
    }

    public function getTeachingVerse(): string
    {
        return $this->teachingVerse;
    }

    public function setTeachingVerse(string $teachingVerse): void
    {
        $this->teachingVerse = $teachingVerse;
    }

    public function getTeachingText(): string
    {
        return $this->teachingText;
    }

    public function setTeachingText(string $teachingText): void
    {
        $this->teachingText = $teachingText;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): void
    {
        $this->year = $year;
    }
}
